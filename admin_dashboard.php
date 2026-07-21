<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

include_once 'includes_session.php';
include_once 'Database.php';
include_once 'User.php';
include_once 'Student.php';
include_once 'Fee.php';
include_once 'Attendance.php';
include_once 'Result.php';
include_once 'Encryption.php';

requireAdmin();

$user = new User();
$student = new Student();
$fee = new Fee();
$attendance = new Attendance();
$result = new Result();

$currentUser = $user->getCurrentUser();
$db = Database::getInstance()->getConnection();

$searchQuery = '';
$searchResults = [];
$searchType = '';
$totalSearchResults = 0;

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    $searchTerm = '%' . $searchQuery . '%';
    $searchType = $_GET['type'] ?? 'all';
    
    if ($searchType === 'all' || $searchType === 'parents') {
        $stmt = $db->prepare("
            SELECT u.*, 'parent' as result_type, NULL as student_name, NULL as class_name, NULL as term, NULL as amount
            FROM users u
            WHERE u.role = 'parent' AND u.status = 'active'
            AND (u.fullname LIKE ? OR u.username LIKE ? OR u.phone LIKE ?)
            LIMIT 20
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $parentResults = $stmt->fetchAll();
        
        foreach ($parentResults as &$parent) {
            $parent['fullname'] = $parent['fullname'];
            $parent['phone'] = $parent['phone'];
            $parent['email'] = !empty($parent['email']) ? $parent['email'] : null;
        }
        
        $searchResults = array_merge($searchResults, $parentResults);
    }
    
    if ($searchType === 'all' || $searchType === 'children') {
        $stmt = $db->prepare("
            SELECT c.*, u.fullname as parent_name, cl.name as class_name, 'child' as result_type
            FROM children c
            LEFT JOIN parents p ON c.parent_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN classes cl ON c.class_id = cl.id
            WHERE c.status = 'active'
            AND (c.name LIKE ? OR u.fullname LIKE ?)
            LIMIT 20
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        $childResults = $stmt->fetchAll();
        
        foreach ($childResults as &$child) {
            $child['name'] = $child['name'];
            $child['parent_name'] = !empty($child['parent_name']) ? $child['parent_name'] : null;
        }
        
        $searchResults = array_merge($searchResults, $childResults);
    }
    
    if ($searchType === 'all' || $searchType === 'fees') {
        $stmt = $db->prepare("
            SELECT f.*, c.name as student_name, u.fullname as parent_name, 'fee' as result_type
            FROM fees f
            JOIN children c ON f.student_id = c.id
            LEFT JOIN parents p ON c.parent_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE c.status = 'active'
            AND (c.name LIKE ? OR f.term LIKE ? OR f.status LIKE ?)
            LIMIT 20
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $feeResults = $stmt->fetchAll();
        
        foreach ($feeResults as &$feeRow) {
            $feeRow['student_name'] = $feeRow['student_name'];
            $feeRow['parent_name'] = !empty($feeRow['parent_name']) ? $feeRow['parent_name'] : null;
        }
        
        $searchResults = array_merge($searchResults, $feeResults);
    }
    
    if ($searchType === 'all' || $searchType === 'attendance') {
        $stmt = $db->prepare("
            SELECT a.*, c.name as student_name, 'attendance' as result_type
            FROM attendance a
            JOIN children c ON a.student_id = c.id
            WHERE c.status = 'active'
            AND (c.name LIKE ? OR a.status LIKE ?)
            ORDER BY a.date DESC
            LIMIT 20
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        $attendanceResults = $stmt->fetchAll();
        
        foreach ($attendanceResults as &$attRow) {
            $attRow['student_name'] = $attRow['student_name'];
        }
        
        $searchResults = array_merge($searchResults, $attendanceResults);
    }
    
    if ($searchType === 'all' || $searchType === 'results') {
        $stmt = $db->prepare("
            SELECT r.*, c.name as student_name, 'result' as result_type
            FROM results r
            JOIN children c ON r.student_id = c.id
            WHERE c.status = 'active'
            AND (c.name LIKE ? OR r.subject LIKE ? OR r.grade LIKE ?)
            LIMIT 20
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $resultResults = $stmt->fetchAll();
        
        foreach ($resultResults as &$resRow) {
            $resRow['student_name'] = $resRow['student_name'];
        }
        
        $searchResults = array_merge($searchResults, $resultResults);
    }
    
    $totalSearchResults = count($searchResults);
}

if (isset($_POST['add_parent'])) {
    try {
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (fullname, username, email, phone, password, role, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'parent', 'active', NOW())
        ");
        $stmt->execute([
            $_POST['fullname'],
            $_POST['username'],
            $_POST['email'],
            $_POST['phone'],
            $hashedPassword
        ]);
        $userId = $db->lastInsertId();
        
        $stmt = $db->prepare("INSERT INTO parents (user_id, created_at) VALUES (?, NOW())");
        $stmt->execute([$userId]);
        
        header('Location: admin_dashboard.php?success=parent_added');
        exit();
    } catch(Exception $e) {
        $error = 'Error adding parent: ' . $e->getMessage();
    }
}

if (isset($_POST['update_parent'])) {
    try {
        $query = "UPDATE users SET fullname = ?, username = ?, email = ?, phone = ? WHERE id = ?";
        $params = [$_POST['fullname'], $_POST['username'], $_POST['email'], $_POST['phone'], $_POST['user_id']];
        
        if (!empty($_POST['password'])) {
            $query = "UPDATE users SET fullname = ?, username = ?, email = ?, phone = ?, password = ? WHERE id = ?";
            $params = [$_POST['fullname'], $_POST['username'], $_POST['email'], $_POST['phone'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['user_id']];
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        header('Location: admin_dashboard.php?success=parent_updated');
        exit();
    } catch(Exception $e) {
        $error = 'Error updating parent: ' . $e->getMessage();
    }
}

if (isset($_GET['deactivate_parent'])) {
    try {
        $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'parent'");
        $stmt->execute([$_GET['deactivate_parent']]);
        header('Location: admin_dashboard.php?success=parent_deactivated');
        exit();
    } catch(Exception $e) {
        $error = 'Error deactivating parent: ' . $e->getMessage();
    }
}

if (isset($_POST['add_child'])) {
    try {
        $stmt = $db->prepare("
            INSERT INTO children (parent_id, name, dob, gender, admission_date, class_id, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $_POST['parent_id'],
            $_POST['name'],
            $_POST['dob'],
            $_POST['gender'],
            $_POST['admission_date'] ?? date('Y-m-d'),
            $_POST['class_id'] ?? null
        ]);
        header('Location: admin_dashboard.php?success=child_added');
        exit();
    } catch(Exception $e) {
        $error = 'Error adding child: ' . $e->getMessage();
    }
}

if (isset($_POST['update_child'])) {
    try {
        $stmt = $db->prepare("
            UPDATE children 
            SET name = ?, dob = ?, gender = ?, class_id = ?, admission_date = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['dob'],
            $_POST['gender'],
            $_POST['class_id'],
            $_POST['admission_date'],
            $_POST['child_id']
        ]);
        header('Location: admin_dashboard.php?success=child_updated');
        exit();
    } catch(Exception $e) {
        $error = 'Error updating child: ' . $e->getMessage();
    }
}

if (isset($_GET['delete_child'])) {
    try {
        $stmt = $db->prepare("UPDATE children SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$_GET['delete_child']]);
        header('Location: admin_dashboard.php?success=child_deleted');
        exit();
    } catch(Exception $e) {
        $error = 'Error deleting child: ' . $e->getMessage();
    }
}

if (isset($_POST['link_parent'])) {
    try {
        $checkParent = $db->prepare("
            SELECT p.id FROM parents p 
            JOIN users u ON p.user_id = u.id 
            WHERE u.id = ? AND u.role = 'parent' AND u.status = 'active'
        ");
        $checkParent->execute([$_POST['parent_user_id']]);
        $parent = $checkParent->fetch();
        
        if (!$parent) {
            $error = 'Invalid or inactive parent selected!';
        } else {
            $stmt = $db->prepare("UPDATE children SET parent_id = ? WHERE id = ? AND status = 'active'");
            $stmt->execute([$parent['id'], $_POST['child_id']]);
            header('Location: admin_dashboard.php?success=linked');
            exit();
        }
    } catch(Exception $e) {
        $error = 'Error linking parent: ' . $e->getMessage();
    }
}

if (isset($_POST['unlink_parent'])) {
    try {
        $stmt = $db->prepare("UPDATE children SET parent_id = NULL WHERE id = ? AND status = 'active'");
        $stmt->execute([$_POST['child_id']]);
        header('Location: admin_dashboard.php?success=unlinked');
        exit();
    } catch(Exception $e) {
        $error = 'Error unlinking parent: ' . $e->getMessage();
    }
}

if (isset($_POST['add_class'])) {
    try {
        $stmt = $db->prepare("
            INSERT INTO classes (name, description, capacity, academic_year, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['capacity'],
            $_POST['academic_year']
        ]);
        header('Location: admin_dashboard.php?success=class_added');
        exit();
    } catch(Exception $e) {
        $error = 'Error adding class: ' . $e->getMessage();
    }
}

if (isset($_POST['update_class'])) {
    try {
        $stmt = $db->prepare("
            UPDATE classes 
            SET name = ?, description = ?, capacity = ?, academic_year = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['capacity'],
            $_POST['academic_year'],
            $_POST['class_id']
        ]);
        header('Location: admin_dashboard.php?success=class_updated');
        exit();
    } catch(Exception $e) {
        $error = 'Error updating class: ' . $e->getMessage();
    }
}

if (isset($_POST['add_fee'])) {
    try {
        $stmt = $db->prepare("
            INSERT INTO fees (student_id, term, academic_year, amount, amount_paid, due_date, status, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['student_id'],
            $_POST['term'],
            $_POST['academic_year'],
            $_POST['amount'],
            $_POST['amount_paid'] ?? 0,
            $_POST['due_date'],
            $_POST['status'] ?? 'pending',
            $_POST['notes'] ?? ''
        ]);
        header('Location: admin_dashboard.php?success=fee_added#feesSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error adding fee: ' . $e->getMessage();
    }
}

if (isset($_POST['update_fee'])) {
    try {
        $stmt = $db->prepare("
            UPDATE fees 
            SET amount = ?, amount_paid = ?, due_date = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['amount'],
            $_POST['amount_paid'],
            $_POST['due_date'],
            $_POST['status'],
            $_POST['notes'],
            $_POST['fee_id']
        ]);
        header('Location: admin_dashboard.php?success=fee_updated#feesSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error updating fee: ' . $e->getMessage();
    }
}

if (isset($_GET['delete_fee'])) {
    try {
        $stmt = $db->prepare("DELETE FROM fees WHERE id = ?");
        $stmt->execute([$_GET['delete_fee']]);
        header('Location: admin_dashboard.php?success=fee_deleted#feesSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error deleting fee: ' . $e->getMessage();
    }
}

if (isset($_POST['record_payment'])) {
    try {
        $feeId = $_POST['fee_id'];
        $paymentAmount = $_POST['payment_amount'];
        
        $stmt = $db->prepare("SELECT amount, amount_paid FROM fees WHERE id = ?");
        $stmt->execute([$feeId]);
        $feeData = $stmt->fetch();
        
        $newAmountPaid = $feeData['amount_paid'] + $paymentAmount;
        $newStatus = 'partial';
        
        if ($newAmountPaid >= $feeData['amount']) {
            $newStatus = 'paid';
            $newAmountPaid = $feeData['amount'];
        }
        
        $stmt = $db->prepare("
            UPDATE fees 
            SET amount_paid = ?, status = ?, payment_date = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newAmountPaid, $newStatus, $feeId]);
        
        header('Location: admin_dashboard.php?success=payment_recorded#feesSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error recording payment: ' . $e->getMessage();
    }
}

if (isset($_POST['add_attendance'])) {
    try {
        $students = $_POST['students'] ?? [];
        $date = $_POST['date'];
        
        foreach ($students as $studentId => $status) {
            if ($status !== '') {
                $stmt = $db->prepare("
                    INSERT INTO attendance (student_id, date, status, marked_by, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$studentId, $date, $status, $currentUser['id']]);
            }
        }
        header('Location: admin_dashboard.php?success=attendance_added#attendanceSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error adding attendance: ' . $e->getMessage();
    }
}

if (isset($_POST['update_attendance'])) {
    try {
        $stmt = $db->prepare("UPDATE attendance SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['attendance_id']]);
        header('Location: admin_dashboard.php?success=attendance_updated#attendanceSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error updating attendance: ' . $e->getMessage();
    }
}

if (isset($_GET['delete_attendance'])) {
    try {
        $stmt = $db->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$_GET['delete_attendance']]);
        header('Location: admin_dashboard.php?success=attendance_deleted#attendanceSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error deleting attendance: ' . $e->getMessage();
    }
}

if (isset($_POST['add_result'])) {
    try {
        $score = $_POST['score'];
        $grade = '';
        if ($score >= 80) $grade = 'A';
        elseif ($score >= 70) $grade = 'B';
        elseif ($score >= 60) $grade = 'C';
        elseif ($score >= 50) $grade = 'D';
        else $grade = 'F';
        
        $stmt = $db->prepare("
            INSERT INTO results (student_id, subject, term, academic_year, score, grade, remarks, teacher_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['student_id'],
            $_POST['subject'],
            $_POST['term'],
            $_POST['academic_year'],
            $_POST['score'],
            $grade,
            $_POST['remarks'] ?? '',
            $currentUser['id']
        ]);
        header('Location: admin_dashboard.php?success=result_added#resultsSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error adding result: ' . $e->getMessage();
    }
}

if (isset($_POST['update_result'])) {
    try {
        $score = $_POST['score'];
        $grade = '';
        if ($score >= 80) $grade = 'A';
        elseif ($score >= 70) $grade = 'B';
        elseif ($score >= 60) $grade = 'C';
        elseif ($score >= 50) $grade = 'D';
        else $grade = 'F';
        
        $stmt = $db->prepare("
            UPDATE results 
            SET subject = ?, score = ?, grade = ?, remarks = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['subject'],
            $_POST['score'],
            $grade,
            $_POST['remarks'],
            $_POST['result_id']
        ]);
        header('Location: admin_dashboard.php?success=result_updated#resultsSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error updating result: ' . $e->getMessage();
    }
}

if (isset($_GET['delete_result'])) {
    try {
        $stmt = $db->prepare("DELETE FROM results WHERE id = ?");
        $stmt->execute([$_GET['delete_result']]);
        header('Location: admin_dashboard.php?success=result_deleted#resultsSection');
        exit();
    } catch(Exception $e) {
        $error = 'Error deleting result: ' . $e->getMessage();
    }
}

$totalParents = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'parent' AND status = 'active'")->fetch()['total'];
$totalChildren = $db->query("SELECT COUNT(*) as total FROM children WHERE status = 'active'")->fetch()['total'];
$totalClasses = $db->query("SELECT COUNT(*) as total FROM classes WHERE status = 'active'")->fetch()['total'];

$parents = $db->query("
    SELECT u.*, p.id as parent_id, 
           COUNT(c.id) as children_count
    FROM users u
    JOIN parents p ON u.id = p.user_id
    LEFT JOIN children c ON p.id = c.parent_id AND c.status = 'active'
    WHERE u.role = 'parent' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY u.fullname
")->fetchAll();

$children = $db->query("
    SELECT c.*, u.fullname as parent_name, cl.name as class_name
    FROM children c
    LEFT JOIN parents p ON c.parent_id = p.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN classes cl ON c.class_id = cl.id
    WHERE c.status = 'active'
    ORDER BY c.name
")->fetchAll();

$classes = $db->query("SELECT * FROM classes WHERE status = 'active' ORDER BY name")->fetchAll();

$fees = $db->query("
    SELECT f.*, c.name as student_name, u.fullname as parent_name
    FROM fees f
    LEFT JOIN children c ON f.student_id = c.id
    LEFT JOIN parents p ON c.parent_id = p.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE c.status = 'active' OR c.status IS NULL
    ORDER BY f.created_at DESC
")->fetchAll();

$students = $db->query("
    SELECT c.id, c.name, u.fullname as parent_name 
    FROM children c
    LEFT JOIN parents p ON c.parent_id = p.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE c.status = 'active'
    ORDER BY c.name
")->fetchAll();

$attendanceRecords = $db->query("
    SELECT a.*, c.name as student_name, c.id as student_id
    FROM attendance a
    JOIN children c ON a.student_id = c.id
    WHERE c.status = 'active'
    ORDER BY a.date DESC
    LIMIT 50
")->fetchAll();

$resultsRecords = $db->query("
    SELECT r.*, c.name as student_name
    FROM results r
    JOIN children c ON r.student_id = c.id
    WHERE c.status = 'active'
    ORDER BY r.created_at DESC
    LIMIT 50
")->fetchAll();

$editParent = null;
if (isset($_GET['edit_parent'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'parent'");
    $stmt->execute([$_GET['edit_parent']]);
    $editParent = $stmt->fetch();
}

$editChild = null;
if (isset($_GET['edit_child'])) {
    $stmt = $db->prepare("SELECT * FROM children WHERE id = ?");
    $stmt->execute([$_GET['edit_child']]);
    $editChild = $stmt->fetch();
}

$editClass = null;
if (isset($_GET['edit_class'])) {
    $stmt = $db->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$_GET['edit_class']]);
    $editClass = $stmt->fetch();
}

$editFee = null;
if (isset($_GET['edit_fee'])) {
    $stmt = $db->prepare("SELECT * FROM fees WHERE id = ?");
    $stmt->execute([$_GET['edit_fee']]);
    $editFee = $stmt->fetch();
}

$editAttendance = null;
if (isset($_GET['edit_attendance'])) {
    $stmt = $db->prepare("SELECT * FROM attendance WHERE id = ?");
    $stmt->execute([$_GET['edit_attendance']]);
    $editAttendance = $stmt->fetch();
}

$editResult = null;
if (isset($_GET['edit_result'])) {
    $stmt = $db->prepare("SELECT * FROM results WHERE id = ?");
    $stmt->execute([$_GET['edit_result']]);
    $editResult = $stmt->fetch();
}

$successMessage = '';
if (isset($_GET['success'])) {
    switch($_GET['success']) {
        case 'parent_added': $successMessage = 'Parent added successfully!'; break;
        case 'parent_updated': $successMessage = 'Parent updated successfully!'; break;
        case 'parent_deactivated': $successMessage = 'Parent deactivated successfully!'; break;
        case 'child_added': $successMessage = 'Child added successfully!'; break;
        case 'child_updated': $successMessage = 'Child updated successfully!'; break;
        case 'child_deleted': $successMessage = 'Child removed successfully!'; break;
        case 'linked': $successMessage = 'Parent linked to child successfully!'; break;
        case 'unlinked': $successMessage = 'Parent unlinked from child successfully!'; break;
        case 'class_added': $successMessage = 'Class added successfully!'; break;
        case 'class_updated': $successMessage = 'Class updated successfully!'; break;
        case 'fee_added': $successMessage = 'Fee added successfully!'; break;
        case 'fee_updated': $successMessage = 'Fee updated successfully!'; break;
        case 'fee_deleted': $successMessage = 'Fee deleted successfully!'; break;
        case 'payment_recorded': $successMessage = 'Payment recorded successfully!'; break;
        case 'attendance_added': $successMessage = 'Attendance recorded successfully!'; break;
        case 'attendance_updated': $successMessage = 'Attendance updated successfully!'; break;
        case 'attendance_deleted': $successMessage = 'Attendance deleted successfully!'; break;
        case 'result_added': $successMessage = 'Result added successfully!'; break;
        case 'result_updated': $successMessage = 'Result updated successfully!'; break;
        case 'result_deleted': $successMessage = 'Result deleted successfully!'; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard - Kibwana Nursery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #D4AF37;
            --gold-dark: #B8960F;
            --dark-blue: #1E3A5F;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f2f5; }
        
        .sidebar {
            background: var(--dark-blue);
            min-height: 100vh;
            padding: 0;
            position: fixed;
            left: 0;
            top: 0;
            width: 220px;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar-brand {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-brand .logo-icon {
            width: 48px; height: 48px;
            background: var(--gold);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
            margin-bottom: 6px;
        }
        .sidebar-brand h4 { color: #fff; font-weight: 700; margin: 0; font-size: 16px; }
        .sidebar-brand small { color: rgba(255,255,255,0.5); font-size: 10px; }
        
        .sidebar-menu { list-style: none; padding: 10px 0; margin: 0; }
        .sidebar-menu li { padding: 0 10px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
        }
        .sidebar-menu li a i { margin-right: 10px; width: 18px; text-align: center; font-size: 14px; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active {
            background: rgba(212, 175, 55, 0.12);
            color: var(--gold);
        }
        .sidebar-menu li a.active { background: var(--gold); color: #fff; }
        
        .hamburger {
            display: none;
            background: var(--dark-blue);
            color: #fff;
            border: none;
            font-size: 24px;
            padding: 10px 15px;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1001;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .main-content { margin-left: 220px; padding: 15px 20px; }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0 15px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .top-bar h3 { font-weight: 700; color: var(--dark-blue); font-size: 18px; margin: 0; }
        .top-bar h3 i { color: var(--gold); margin-right: 6px; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar {
            width: 36px; height: 36px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--dark-blue), #2A4A7A);
            border-radius: 14px;
            padding: 18px 20px;
            color: #fff;
            margin-bottom: 20px;
        }
        .welcome-banner h5 { font-weight: 700; margin: 0; font-size: 16px; }
        .welcome-banner p { margin: 0; opacity: 0.8; font-size: 13px; }
        
        .search-box {
            background: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            margin-bottom: 20px;
        }
        .search-box .input-group { width: 100%; }
        .search-box .input-group-text {
            background: var(--gold);
            color: #fff;
            border: none;
            border-radius: 10px 0 0 10px;
            padding: 8px 12px;
        }
        .search-box .form-control {
            border-color: var(--gold);
            border-radius: 0;
            font-size: 13px;
            padding: 8px 12px;
        }
        .search-box .form-control:focus {
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }
        .search-box .btn-gold {
            border-radius: 0 10px 10px 0;
            padding: 8px 14px;
            font-size: 13px;
        }
        .search-box .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 8px;
        }
        .search-box .filter-buttons .btn {
            font-size: 10px;
            padding: 3px 10px;
            border-radius: 50px;
        }
        .search-box .filter-buttons .btn.active {
            background: var(--gold);
            color: #fff;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            margin-bottom: 12px;
        }
        .stat-card .stat-number { font-size: 22px; font-weight: 800; color: var(--dark-blue); }
        .stat-card .stat-label { color: #6B7280; font-size: 12px; font-weight: 500; }
        .stat-card .stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }
        .stat-card .stat-icon.gold { background: rgba(212, 175, 55, 0.12); color: var(--gold); }
        .stat-card .stat-icon.blue { background: rgba(30, 58, 95, 0.08); color: var(--dark-blue); }
        .stat-card .stat-icon.green { background: rgba(34, 197, 94, 0.12); color: #22C55E; }
        .stat-card .stat-icon.purple { background: rgba(139, 92, 246, 0.12); color: #8B5CF6; }
        
        .section-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            margin-bottom: 18px;
            overflow: hidden;
        }
        .section-card .card-header {
            padding: 12px 16px;
            background: #fafbfc;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .section-card .card-header h5 {
            font-weight: 700;
            color: var(--dark-blue);
            margin: 0;
            font-size: 14px;
        }
        .section-card .card-header h5 i { color: var(--gold); margin-right: 6px; }
        .section-card .card-body { padding: 12px 14px; }
        
        .table-responsive { margin: 0 -4px; }
        .table { margin: 0; font-size: 12px; }
        .table th { 
            font-weight: 600; 
            color: #6B7280; 
            font-size: 10px; 
            text-transform: uppercase; 
            letter-spacing: 0.3px; 
            border-bottom: 2px solid #eef2f7; 
            padding: 6px 8px;
        }
        .table td { 
            vertical-align: middle; 
            font-size: 11px; 
            padding: 6px 8px;
        }
        .table tbody tr:hover { background: #fafbfc; }
        
        .badge-gold { background: var(--gold); color: #fff; padding: 3px 10px; border-radius: 50px; font-weight: 600; font-size: 10px; }
        .badge-info-custom { background: #3B82F6; color: #fff; padding: 3px 10px; border-radius: 50px; font-weight: 600; font-size: 10px; }
        
        .btn-gold {
            background: linear-gradient(135deg, #D4AF37, #E8C84A);
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 11px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #B8960F, #D4AF37);
            color: #fff;
        }
        .btn-gold-sm { padding: 4px 10px; font-size: 10px; }
        
        .quick-card {
            background: #fff;
            border-radius: 14px;
            padding: 14px 12px;
            text-align: center;
            border: 1px solid #eef2f7;
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .quick-card:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .quick-card i { font-size: 22px; color: var(--gold); }
        .quick-card h6 { font-weight: 700; color: var(--dark-blue); margin-top: 6px; margin-bottom: 2px; font-size: 11px; }
        .quick-card small { color: #6B7280; font-size: 10px; }
        
        .modal-content { border-radius: 14px; border: none; }
        .modal-header { border-bottom: 1px solid #eef2f7; padding: 14px 18px; }
        .modal-header h5 { font-weight: 700; color: var(--dark-blue); font-size: 15px; }
        .modal-header h5 i { color: var(--gold); }
        .modal-body { padding: 18px; }
        .modal-body .form-label { font-size: 12px; }
        .modal-body .form-control { font-size: 13px; padding: 8px 12px; }
        .modal-footer { border-top: 1px solid #eef2f7; padding: 12px 18px; }
        
        .result-badge-parent { background: #22C55E; color: #fff; padding: 2px 8px; border-radius: 50px; font-size: 10px; }
        .result-badge-child { background: #3B82F6; color: #fff; padding: 2px 8px; border-radius: 50px; font-size: 10px; }
        .result-badge-fee { background: #F59E0B; color: #fff; padding: 2px 8px; border-radius: 50px; font-size: 10px; }
        .result-badge-attendance { background: #8B5CF6; color: #fff; padding: 2px 8px; border-radius: 50px; font-size: 10px; }
        .result-badge-result { background: #EF4444; color: #fff; padding: 2px 8px; border-radius: 50px; font-size: 10px; }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 999;
        }
        .sidebar-overlay.active { display: block; }
        
        @media (max-width: 768px) {
            .hamburger { display: block; }
            .sidebar { left: -220px; width: 220px; }
            .sidebar.open { left: 0; }
            .main-content { margin-left: 0; padding: 55px 10px 15px; }
            .top-bar h3 { font-size: 16px; }
            .user-info span { font-size: 12px; }
            .user-avatar { width: 32px; height: 32px; font-size: 12px; }
            .stat-card .stat-number { font-size: 18px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-icon { width: 34px; height: 34px; font-size: 14px; }
            .section-card .card-header { padding: 10px 12px; }
            .section-card .card-header h5 { font-size: 13px; }
            .section-card .card-body { padding: 10px 12px; }
            .table { font-size: 10px; }
            .table th, .table td { padding: 4px 6px; }
            .quick-card { padding: 12px 10px; }
            .quick-card i { font-size: 18px; }
            .quick-card h6 { font-size: 10px; }
            .welcome-banner { padding: 14px 16px; }
            .welcome-banner h5 { font-size: 14px; }
            .welcome-banner p { font-size: 11px; }
            .badge-gold, .badge-info-custom { font-size: 9px; padding: 2px 8px; }
            .search-box { padding: 12px 14px; }
            .search-box .form-control { font-size: 12px; }
            .search-box .filter-buttons .btn { font-size: 9px; padding: 2px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 50px 6px 10px; }
            .top-bar h3 { font-size: 14px; }
            .stat-card .stat-number { font-size: 16px; }
            .table { font-size: 9px; }
            .table th, .table td { padding: 3px 4px; }
            .section-card .card-header h5 { font-size: 12px; }
            .btn-gold { font-size: 10px; padding: 4px 10px; }
            .btn-gold-sm { font-size: 9px; padding: 3px 8px; }
            .modal-header h5 { font-size: 14px; }
            .modal-body .form-control { font-size: 12px; }
        }
    </style>
</head>
<body>

<button class="hamburger" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon"><i class="fas fa-child"></i></div>
        <h4>Kibwana</h4>
        <small>Nursery School</small>
    </div>
    <ul class="sidebar-menu">
        <li><a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="#parentsSection" onclick="scrollToSection('parentsSection')"><i class="fas fa-users"></i><span>Parents</span></a></li>
        <li><a href="#childrenSection" onclick="scrollToSection('childrenSection')"><i class="fas fa-child"></i><span>Children</span></a></li>
        <li><a href="#feesSection" onclick="scrollToSection('feesSection')"><i class="fas fa-money-bill-wave"></i><span>Fees</span></a></li>
        <li><a href="#attendanceSection" onclick="scrollToSection('attendanceSection')"><i class="fas fa-calendar-check"></i><span>Attendance</span></a></li>
        <li><a href="#resultsSection" onclick="scrollToSection('resultsSection')"><i class="fas fa-star"></i><span>Results</span></a></li>
        <li><a href="#classesSection" onclick="scrollToSection('classesSection')"><i class="fas fa-school"></i><span>Classes</span></a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">

    <div class="top-bar">
        <h3><i class="fas fa-home"></i> Admin</h3>
        <div class="user-info">
            <span style="font-weight: 500; color: var(--dark-blue); font-size: 12px;">
                <?php echo htmlspecialchars($currentUser['fullname']); ?>
            </span>
            <div class="user-avatar">
                <?php echo strtoupper(substr($currentUser['fullname'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <div class="welcome-banner">
        <div>
            <h5><i class="fas fa-user-shield me-2"></i> Welcome, <?php echo htmlspecialchars($currentUser['fullname']); ?>!</h5>
            <p>Manage parents, children, fees, attendance & results</p>
        </div>
    </div>

    <div class="search-box">
        <form method="GET" action="admin_dashboard.php">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control" 
                       placeholder="Search parents, children, fees..." 
                       value="<?php echo htmlspecialchars($searchQuery); ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($searchType); ?>">
                <button type="submit" class="btn-gold"><i class="fas fa-search"></i></button>
                <?php if (!empty($searchQuery)): ?>
                    <a href="admin_dashboard.php" class="btn btn-secondary btn-sm" style="border-radius:0 10px 10px 0;padding:4px 10px;font-size:12px;">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
        <div class="filter-buttons">
            <a href="?search=<?php echo urlencode($searchQuery); ?>&type=all" 
               class="btn btn-sm <?php echo $searchType === 'all' || empty($searchQuery) ? 'btn-gold' : 'btn-outline-secondary'; ?>">
                All
            </a>
            <a href="?search=<?php echo urlencode($searchQuery); ?>&type=parents" 
               class="btn btn-sm <?php echo $searchType === 'parents' ? 'btn-gold' : 'btn-outline-secondary'; ?>">
                <i class="fas fa-users"></i> Parents
            </a>
            <a href="?search=<?php echo urlencode($searchQuery); ?>&type=children" 
               class="btn btn-sm <?php echo $searchType === 'children' ? 'btn-gold' : 'btn-outline-secondary'; ?>">
                <i class="fas fa-child"></i> Children
            </a>
            <a href="?search=<?php echo urlencode($searchQuery); ?>&type=fees" 
               class="btn btn-sm <?php echo $searchType === 'fees' ? 'btn-gold' : 'btn-outline-secondary'; ?>">
                <i class="fas fa-money-bill-wave"></i> Fees
            </a>
            <a href="?search=<?php echo urlencode($searchQuery); ?>&type=attendance" 
               class="btn btn-sm <?php echo $searchType === 'attendance' ? 'btn-gold' : 'btn-outline-secondary'; ?>">
                <i class="fas fa-calendar-check"></i> Attendance
            </a>
            <a href="?search=<?php echo urlencode($searchQuery); ?>&type=results" 
               class="btn btn-sm <?php echo $searchType === 'results' ? 'btn-gold' : 'btn-outline-secondary'; ?>">
                <i class="fas fa-star"></i> Results
            </a>
        </div>
        <?php if (!empty($searchQuery)): ?>
            <div class="mt-1">
                <small class="text-muted" style="font-size: 11px;">
                    <i class="fas fa-info-circle me-1"></i>
                    Found <strong><?php echo $totalSearchResults; ?></strong> result(s) for "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
                </small>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($searchQuery) && !empty($searchResults)): ?>
        <div class="section-card mb-3">
            <div class="card-header">
                <h5><i class="fas fa-search"></i> Results</h5>
                <span class="badge-gold"><?php echo $totalSearchResults; ?></span>
            </div>
            <div class="card-body" style="padding: 6px 10px;">
                <div class="table-responsive">
                    <table class="table table-hover" style="font-size: 10px;">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name / Details</th>
                                <th>Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($searchResults as $result): ?>
                                <tr>
                                    <td>
                                        <?php if ($result['result_type'] === 'parent'): ?>
                                            <span class="result-badge-parent"><i class="fas fa-user"></i></span>
                                        <?php elseif ($result['result_type'] === 'child'): ?>
                                            <span class="result-badge-child"><i class="fas fa-child"></i></span>
                                        <?php elseif ($result['result_type'] === 'fee'): ?>
                                            <span class="result-badge-fee"><i class="fas fa-money-bill-wave"></i></span>
                                        <?php elseif ($result['result_type'] === 'attendance'): ?>
                                            <span class="result-badge-attendance"><i class="fas fa-calendar-check"></i></span>
                                        <?php elseif ($result['result_type'] === 'result'): ?>
                                            <span class="result-badge-result"><i class="fas fa-star"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php 
                                            if ($result['result_type'] === 'parent') {
                                                echo htmlspecialchars($result['fullname'] ?? 'N/A');
                                            } elseif ($result['result_type'] === 'child') {
                                                echo htmlspecialchars($result['name'] ?? 'N/A');
                                            } elseif ($result['result_type'] === 'fee') {
                                                echo htmlspecialchars($result['student_name'] ?? 'N/A');
                                            } elseif ($result['result_type'] === 'attendance') {
                                                echo htmlspecialchars($result['student_name'] ?? 'N/A');
                                            } elseif ($result['result_type'] === 'result') {
                                                echo htmlspecialchars($result['student_name'] ?? 'N/A');
                                            }
                                            ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php if ($result['result_type'] === 'parent'): ?>
                                                <?php echo htmlspecialchars($result['phone']); ?>
                                            <?php elseif ($result['result_type'] === 'child'): ?>
                                                <?php echo htmlspecialchars($result['class_name'] ?? 'N/A'); ?>
                                            <?php elseif ($result['result_type'] === 'fee'): ?>
                                                Tsh <?php echo number_format($result['amount'], 0); ?>
                                            <?php elseif ($result['result_type'] === 'attendance'): ?>
                                                <?php echo date('d M', strtotime($result['date'])); ?>
                                            <?php elseif ($result['result_type'] === 'result'): ?>
                                                <?php echo $result['subject']; ?>: <?php echo $result['score']; ?>%
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($result['result_type'] === 'parent'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($result['result_type'] === 'child'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($result['result_type'] === 'fee'): ?>
                                            <span class="badge <?php echo $result['status'] === 'paid' ? 'bg-success' : ($result['status'] === 'partial' ? 'bg-info' : 'bg-warning'); ?>">
                                                <?php echo ucfirst($result['status']); ?>
                                            </span>
                                        <?php elseif ($result['result_type'] === 'attendance'): ?>
                                            <span class="badge <?php echo $result['status'] === 'present' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo ucfirst($result['status']); ?>
                                            </span>
                                        <?php elseif ($result['result_type'] === 'result'): ?>
                                            <span class="badge <?php echo $result['grade'] >= 'A' ? 'bg-success' : ($result['grade'] >= 'C' ? 'bg-info' : 'bg-danger'); ?>">
                                                <?php echo $result['grade']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif (!empty($searchQuery) && empty($searchResults)): ?>
        <div class="alert alert-info border-0 mb-3" style="border-radius: 12px;font-size:12px;padding:10px 14px;">
            <i class="fas fa-info-circle me-2"></i>
            No results found for "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" style="border-radius: 12px;font-size:12px;padding:10px 14px;">
            <i class="fas fa-check-circle me-2"></i> <?php echo $successMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" style="border-radius: 12px;font-size:12px;padding:10px 14px;">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
        </div>
    <?php endif; ?>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo $totalParents; ?></div>
                        <div class="stat-label"><i class="fas fa-user-friends me-1"></i> Parents</div>
                    </div>
                    <div class="stat-icon gold"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo $totalChildren; ?></div>
                        <div class="stat-label"><i class="fas fa-child me-1"></i> Children</div>
                    </div>
                    <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo count($fees); ?></div>
                        <div class="stat-label"><i class="fas fa-money-bill-wave me-1"></i> Fees</div>
                    </div>
                    <div class="stat-icon green"><i class="fas fa-file-invoice"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo $totalClasses; ?></div>
                        <div class="stat-label"><i class="fas fa-school me-1"></i> Classes</div>
                    </div>
                    <div class="stat-icon purple"><i class="fas fa-building"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-4 col-md-2">
            <div class="quick-card" onclick="document.getElementById('addParentModalBtn').click()">
                <i class="fas fa-user-plus"></i>
                <h6>Parent</h6>
                <small>Add</small>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="quick-card" onclick="document.getElementById('addChildModalBtn').click()">
                <i class="fas fa-child"></i>
                <h6>Child</h6>
                <small>Add</small>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="quick-card" onclick="document.getElementById('addFeeModalBtn').click()">
                <i class="fas fa-money-bill-wave"></i>
                <h6>Fee</h6>
                <small>Add</small>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="quick-card" onclick="document.getElementById('addAttendanceModalBtn').click()">
                <i class="fas fa-calendar-check"></i>
                <h6>Attend</h6>
                <small>Record</small>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="quick-card" onclick="document.getElementById('addResultModalBtn').click()">
                <i class="fas fa-star"></i>
                <h6>Result</h6>
                <small>Add</small>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="quick-card" onclick="document.getElementById('addClassModalBtn').click()">
                <i class="fas fa-school"></i>
                <h6>Class</h6>
                <small>Add</small>
            </div>
        </div>
    </div>

    <div id="parentsSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-users"></i> Parents</h5>
            <div>
                <span class="badge-gold me-1"><?php echo $totalParents; ?></span>
                <button class="btn-gold btn-gold-sm" id="addParentModalBtn" data-bs-toggle="modal" data-bs-target="#addParentModal">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($parents) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Kids</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parents as $index => $parent): ?>
                                <tr>
                                    <td><strong style="font-size:11px;"><?php echo htmlspecialchars($parent['fullname']); ?></strong></td>
                                    <td style="font-size:10px;"><?php echo htmlspecialchars($parent['phone']); ?></td>
                                    <td><span class="badge-gold"><?php echo $parent['children_count']; ?></span></td>
                                    <td>
                                        <div class="btn-group" role="group" style="gap:2px;">
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#linkModal" onclick="setLinkData(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['fullname']); ?>')" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-link"></i>
                                            </button>
                                            <a href="admin_dashboard.php?edit_parent=<?php echo $parent['id']; ?>#parentsSection" class="btn btn-sm btn-warning" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin_dashboard.php?deactivate_parent=<?php echo $parent['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-user-slash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning border-0" style="border-radius: 10px;font-size:12px;padding:10px;">
                    <i class="fas fa-info-circle me-2"></i> No parents registered yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="childrenSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-child"></i> Children</h5>
            <div>
                <span class="badge-gold me-1"><?php echo $totalChildren; ?></span>
                <button class="btn-gold btn-gold-sm" id="addChildModalBtn" data-bs-toggle="modal" data-bs-target="#addChildModal">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($children) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Parent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($children as $index => $child): ?>
                                <tr>
                                    <td><strong style="font-size:11px;"><?php echo htmlspecialchars($child['name']); ?></strong></td>
                                    <td><span class="badge-info-custom" style="font-size:9px;"><?php echo htmlspecialchars($child['class_name'] ?? 'N/A'); ?></span></td>
                                    <td style="font-size:10px;">
                                        <?php if ($child['parent_name']): ?>
                                            <span class="badge bg-success" style="font-size:8px;"><?php echo htmlspecialchars($child['parent_name']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" style="font-size:8px;">Unlinked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group" style="gap:2px;">
                                            <?php if ($child['parent_name']): ?>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#unlinkModal" onclick="setUnlinkData(<?php echo $child['id']; ?>, '<?php echo htmlspecialchars($child['name']); ?>')" style="padding:2px 6px;font-size:9px;">
                                                    <i class="fas fa-unlink"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="admin_dashboard.php?edit_child=<?php echo $child['id']; ?>#childrenSection" class="btn btn-sm btn-warning" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin_dashboard.php?delete_child=<?php echo $child['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning border-0" style="border-radius: 10px;font-size:12px;padding:10px;">
                    <i class="fas fa-info-circle me-2"></i> No children registered yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="feesSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-money-bill-wave"></i> Fees</h5>
            <div>
                <span class="badge-gold me-1"><?php echo count($fees); ?></span>
                <button class="btn-gold btn-gold-sm" id="addFeeModalBtn" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($fees) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fees as $index => $fee): 
                                $balance = $fee['amount'] - $fee['amount_paid'];
                                $statusClass = '';
                                $statusText = '';
                                
                                if ($fee['status'] === 'paid') {
                                    $statusClass = 'badge bg-success';
                                    $statusText = 'Paid';
                                } elseif ($fee['status'] === 'partial') {
                                    $statusClass = 'badge bg-info';
                                    $statusText = 'Partial';
                                } elseif (strtotime($fee['due_date']) < time() && $fee['status'] !== 'paid') {
                                    $statusClass = 'badge bg-danger';
                                    $statusText = 'Overdue';
                                } else {
                                    $statusClass = 'badge bg-warning';
                                    $statusText = 'Pending';
                                }
                            ?>
                                <tr>
                                    <td><strong style="font-size:11px;"><?php echo htmlspecialchars($fee['student_name'] ?? 'N/A'); ?></strong></td>
                                    <td style="font-size:11px;">Tsh <?php echo number_format($fee['amount'], 0); ?></td>
                                    <td style="font-size:11px;">Tsh <?php echo number_format($fee['amount_paid'], 0); ?></td>
                                    <td><span class="<?php echo $statusClass; ?>" style="font-size:8px;padding:2px 6px;"><?php echo $statusText; ?></span></td>
                                    <td>
                                        <div class="btn-group" role="group" style="gap:2px;">
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal" onclick="setPaymentData(<?php echo $fee['id']; ?>, '<?php echo htmlspecialchars($fee['student_name']); ?>', <?php echo $balance; ?>)" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </button>
                                            <a href="admin_dashboard.php?edit_fee=<?php echo $fee['id']; ?>#feesSection" class="btn btn-sm btn-warning" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin_dashboard.php?delete_fee=<?php echo $fee['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning border-0" style="border-radius: 10px;font-size:12px;padding:10px;">
                    <i class="fas fa-info-circle me-2"></i> No fees recorded yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="attendanceSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-check"></i> Attendance</h5>
            <div>
                <span class="badge-gold me-1"><?php echo count($attendanceRecords); ?></span>
                <button class="btn-gold btn-gold-sm" id="addAttendanceModalBtn" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($attendanceRecords) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceRecords as $index => $att): ?>
                                <tr>
                                    <td><strong style="font-size:11px;"><?php echo htmlspecialchars($att['student_name']); ?></strong></td>
                                    <td style="font-size:10px;"><?php echo date('d M', strtotime($att['date'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $att['status'] === 'present' ? 'bg-success' : ($att['status'] === 'late' ? 'bg-warning' : 'bg-danger'); ?>" style="font-size:8px;padding:2px 6px;">
                                            <?php echo ucfirst($att['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group" style="gap:2px;">
                                            <a href="admin_dashboard.php?edit_attendance=<?php echo $att['id']; ?>#attendanceSection" class="btn btn-sm btn-warning" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin_dashboard.php?delete_attendance=<?php echo $att['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning border-0" style="border-radius: 10px;font-size:12px;padding:10px;">
                    <i class="fas fa-info-circle me-2"></i> No attendance records yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="resultsSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-star"></i> Results</h5>
            <div>
                <span class="badge-gold me-1"><?php echo count($resultsRecords); ?></span>
                <button class="btn-gold btn-gold-sm" id="addResultModalBtn" data-bs-toggle="modal" data-bs-target="#addResultModal">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($resultsRecords) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Score</th>
                                <th>Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultsRecords as $index => $res): ?>
                                <tr>
                                    <td><strong style="font-size:11px;"><?php echo htmlspecialchars($res['student_name']); ?></strong></td>
                                    <td style="font-size:10px;"><?php echo htmlspecialchars($res['subject']); ?></td>
                                    <td style="font-size:11px;"><?php echo $res['score']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $res['grade'] >= 'A' ? 'bg-success' : ($res['grade'] >= 'C' ? 'bg-info' : 'bg-danger'); ?>" style="font-size:9px;padding:2px 8px;">
                                            <?php echo $res['grade']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group" style="gap:2px;">
                                            <a href="admin_dashboard.php?edit_result=<?php echo $res['id']; ?>#resultsSection" class="btn btn-sm btn-warning" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin_dashboard.php?delete_result=<?php echo $res['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')" style="padding:2px 6px;font-size:9px;">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning border-0" style="border-radius: 10px;font-size:12px;padding:10px;">
                    <i class="fas fa-info-circle me-2"></i> No results recorded yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="classesSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-school"></i> Classes</h5>
            <div>
                <span class="badge-gold me-1"><?php echo $totalClasses; ?></span>
                <button class="btn-gold btn-gold-sm" id="addClassModalBtn" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($classes) > 0): ?>
                <div class="row g-2">
                    <?php foreach ($classes as $class): ?>
                        <div class="col-6 col-md-4">
                            <div class="p-2" style="background: #f8f9fa; border-radius: 10px; border: 1px solid #eef2f7;">
                                <h6 style="font-size:12px;font-weight:700;color:var(--dark-blue);">
                                    <i class="fas fa-school me-1" style="color: var(--gold);"></i> 
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </h6>
                                <small class="text-muted" style="font-size:9px;"><?php echo htmlspecialchars($class['description'] ?? 'No description'); ?></small><br>
                                <small style="font-size:9px;"><strong>Cap:</strong> <?php echo $class['capacity']; ?></small>
                                <div class="mt-1">
                                    <a href="admin_dashboard.php?edit_class=<?php echo $class['id']; ?>#classesSection" class="btn btn-sm btn-warning" style="padding:2px 8px;font-size:9px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning border-0" style="border-radius: 10px;font-size:12px;padding:10px;">
                    <i class="fas fa-info-circle me-2"></i> No classes added yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<div class="modal fade" id="addParentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-user-plus me-2"></i> Add Parent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label fw-bold">Full Name *</label><input type="text" name="fullname" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Username *</label><input type="text" name="username" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Email</label><input type="email" name="email" class="form-control"></div>
                    <div class="mb-2"><label class="form-label fw-bold">Phone *</label><input type="text" name="phone" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Password *</label><input type="password" name="password" class="form-control" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_parent" class="btn-gold btn-sm"><i class="fas fa-user-plus me-1"></i> Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editParent): ?>
<div class="modal fade" id="editParentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-user-edit me-2"></i> Edit Parent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" value="<?php echo $editParent['id']; ?>">
                    <div class="mb-2"><label class="form-label fw-bold">Full Name *</label><input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($editParent['fullname']); ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Username *</label><input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($editParent['username']); ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editParent['email']); ?>"></div>
                    <div class="mb-2"><label class="form-label fw-bold">Phone *</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($editParent['phone']); ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">New Password</label><input type="password" name="password" class="form-control" placeholder="Leave blank"><small class="text-muted" style="font-size:10px;">Leave blank to keep current</small></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_parent" class="btn-gold btn-sm"><i class="fas fa-save me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal(document.getElementById('editParentModal')).show();});</script>
<?php endif; ?>

<div class="modal fade" id="addChildModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-child me-2"></i> Add Child</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label fw-bold">Child Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">DOB *</label><input type="date" name="dob" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Gender *</label><select name="gender" class="form-control" required><option value="">Select</option><option value="male">Male</option><option value="female">Female</option></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Parent *</label><select name="parent_id" class="form-control" required><option value="">Select Parent</option><?php foreach ($parents as $parent): ?><option value="<?php echo $parent['parent_id']; ?>"><?php echo htmlspecialchars($parent['fullname']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Class</label><select name="class_id" class="form-control"><option value="">Select</option><?php foreach ($classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Admission Date</label><input type="date" name="admission_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_child" class="btn-gold btn-sm"><i class="fas fa-plus me-1"></i> Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editChild): ?>
<div class="modal fade" id="editChildModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-child me-2"></i> Edit Child</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="child_id" value="<?php echo $editChild['id']; ?>">
                    <div class="mb-2"><label class="form-label fw-bold">Child Name *</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editChild['name']); ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">DOB *</label><input type="date" name="dob" class="form-control" value="<?php echo $editChild['dob']; ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Gender *</label><select name="gender" class="form-control" required><option value="male" <?php echo $editChild['gender']==='male'?'selected':''; ?>>Male</option><option value="female" <?php echo $editChild['gender']==='female'?'selected':''; ?>>Female</option></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Class</label><select name="class_id" class="form-control"><option value="">Select</option><?php foreach ($classes as $class): ?><option value="<?php echo $class['id']; ?>" <?php echo $editChild['class_id']==$class['id']?'selected':''; ?>><?php echo htmlspecialchars($class['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Admission Date</label><input type="date" name="admission_date" class="form-control" value="<?php echo $editChild['admission_date'] ?? date('Y-m-d'); ?>"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_child" class="btn-gold btn-sm"><i class="fas fa-save me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal(document.getElementById('editChildModal')).show();});</script>
<?php endif; ?>

<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-plus me-2"></i> Add Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label fw-bold">Student *</label><select name="student_id" class="form-control" required><option value="">Select Student</option><?php foreach ($students as $student): ?><option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Term *</label><input type="text" name="term" class="form-control" placeholder="e.g., Term 1" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Academic Year *</label><input type="text" name="academic_year" class="form-control" value="<?php echo date('Y'); ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Amount (Tsh) *</label><input type="number" name="amount" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Amount Paid</label><input type="number" name="amount_paid" class="form-control" value="0"></div>
                    <div class="mb-2"><label class="form-label fw-bold">Due Date *</label><input type="date" name="due_date" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Status</label><select name="status" class="form-control"><option value="pending">Pending</option><option value="partial">Partial</option><option value="paid">Paid</option></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fee" class="btn-gold btn-sm"><i class="fas fa-plus me-1"></i> Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editFee): ?>
<div class="modal fade" id="editFeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-edit me-2"></i> Edit Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="fee_id" value="<?php echo $editFee['id']; ?>">
                    <div class="mb-2"><label class="form-label fw-bold">Amount (Tsh) *</label><input type="number" name="amount" class="form-control" value="<?php echo $editFee['amount']; ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Amount Paid</label><input type="number" name="amount_paid" class="form-control" value="<?php echo $editFee['amount_paid']; ?>"></div>
                    <div class="mb-2"><label class="form-label fw-bold">Due Date *</label><input type="date" name="due_date" class="form-control" value="<?php echo $editFee['due_date']; ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Status</label><select name="status" class="form-control"><option value="pending" <?php echo $editFee['status']==='pending'?'selected':''; ?>>Pending</option><option value="partial" <?php echo $editFee['status']==='partial'?'selected':''; ?>>Partial</option><option value="paid" <?php echo $editFee['status']==='paid'?'selected':''; ?>>Paid</option></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Notes</label><textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($editFee['notes']); ?></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_fee" class="btn-gold btn-sm"><i class="fas fa-save me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal(document.getElementById('editFeeModal')).show();});</script>
<?php endif; ?>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-money-bill-wave me-2"></i> Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="fee_id" id="paymentFeeId">
                    <div class="mb-2"><label class="form-label fw-bold">Student</label><input type="text" id="paymentStudentName" class="form-control" disabled></div>
                    <div class="mb-2"><label class="form-label fw-bold">Balance</label><input type="text" id="paymentBalance" class="form-control" disabled></div>
                    <div class="mb-2"><label class="form-label fw-bold">Payment Amount *</label><input type="number" name="payment_amount" class="form-control" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="record_payment" class="btn" style="background:#22C55E;color:#fff;font-size:12px;padding:6px 14px;"><i class="fas fa-check me-1"></i> Pay</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-calendar-check me-2"></i> Take Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label fw-bold">Date *</label><input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="table-responsive">
                        <table class="table table-bordered" style="font-size:10px;">
                            <thead>
                                <tr><th>#</th><th>Student</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $index => $student): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                        <td>
                                            <select name="students[<?php echo $student['id']; ?>]" class="form-control" style="font-size:10px;padding:4px 6px;">
                                                <option value="present">Present</option>
                                                <option value="absent">Absent</option>
                                                <option value="late">Late</option>
                                                <option value="excused">Excused</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_attendance" class="btn-gold btn-sm"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editAttendance): ?>
<div class="modal fade" id="editAttendanceModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-edit me-2"></i> Edit Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="attendance_id" value="<?php echo $editAttendance['id']; ?>">
                    <div class="mb-2"><label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-control">
                            <option value="present" <?php echo $editAttendance['status']==='present'?'selected':''; ?>>Present</option>
                            <option value="absent" <?php echo $editAttendance['status']==='absent'?'selected':''; ?>>Absent</option>
                            <option value="late" <?php echo $editAttendance['status']==='late'?'selected':''; ?>>Late</option>
                            <option value="excused" <?php echo $editAttendance['status']==='excused'?'selected':''; ?>>Excused</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_attendance" class="btn-gold btn-sm"><i class="fas fa-save me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal(document.getElementById('editAttendanceModal')).show();});</script>
<?php endif; ?>

<div class="modal fade" id="addResultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-star me-2"></i> Add Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label fw-bold">Student *</label><select name="student_id" class="form-control" required><option value="">Select</option><?php foreach ($students as $student): ?><option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Subject *</label><select name="subject" class="form-control" required><option value="">Select</option><option value="Kiswahili">Kiswahili</option><option value="English">English</option><option value="Reading & Writing">Reading & Writing</option><option value="Arithmetics">Arithmetics</option></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Term *</label><input type="text" name="term" class="form-control" placeholder="e.g., Term 1" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Academic Year *</label><input type="text" name="academic_year" class="form-control" value="<?php echo date('Y'); ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Score *</label><input type="number" name="score" class="form-control" min="0" max="100" placeholder="0-100" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Remarks</label><textarea name="remarks" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_result" class="btn-gold btn-sm"><i class="fas fa-plus me-1"></i> Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editResult): ?>
<div class="modal fade" id="editResultModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-edit me-2"></i> Edit Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="result_id" value="<?php echo $editResult['id']; ?>">
                    <div class="mb-2"><label class="form-label fw-bold">Subject *</label><select name="subject" class="form-control" required><option value="Kiswahili" <?php echo $editResult['subject']==='Kiswahili'?'selected':''; ?>>Kiswahili</option><option value="English" <?php echo $editResult['subject']==='English'?'selected':''; ?>>English</option><option value="Reading & Writing" <?php echo $editResult['subject']==='Reading & Writing'?'selected':''; ?>>Reading & Writing</option><option value="Arithmetics" <?php echo $editResult['subject']==='Arithmetics'?'selected':''; ?>>Arithmetics</option></select></div>
                    <div class="mb-2"><label class="form-label fw-bold">Score *</label><input type="number" name="score" class="form-control" min="0" max="100" value="<?php echo $editResult['score']; ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Remarks</label><textarea name="remarks" class="form-control" rows="2"><?php echo htmlspecialchars($editResult['remarks']); ?></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_result" class="btn-gold btn-sm"><i class="fas fa-save me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal(document.getElementById('editResultModal')).show();});</script>
<?php endif; ?>

<div class="modal fade" id="linkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-link me-2"></i> Link Parent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="parent_user_id" id="linkParentId">
                    <div class="mb-2"><label class="form-label fw-bold">Parent</label><input type="text" id="linkParentName" class="form-control" disabled></div>
                    <div class="mb-2"><label class="form-label fw-bold">Select Child *</label><select name="child_id" class="form-control" required><option value="">Select Child</option><?php foreach ($children as $child): ?><option value="<?php echo $child['id']; ?>"><?php echo htmlspecialchars($child['name']); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="link_parent" class="btn-gold btn-sm"><i class="fas fa-link me-1"></i> Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="unlinkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-unlink me-2" style="color:#EF4444;"></i> Unlink Parent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="child_id" id="unlinkChildId">
                    <div class="mb-2"><label class="form-label fw-bold">Child</label><input type="text" id="unlinkChildName" class="form-control" disabled></div>
                    <div class="alert alert-warning" style="font-size:11px;padding:8px 12px;"><i class="fas fa-exclamation-triangle me-2"></i>This will remove the relationship.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="unlink_parent" class="btn btn-danger btn-sm"><i class="fas fa-unlink me-1"></i> Unlink</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-school me-2"></i> Add Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label fw-bold">Class Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="mb-2"><label class="form-label fw-bold">Capacity</label><input type="number" name="capacity" class="form-control" value="25"></div>
                    <div class="mb-2"><label class="form-label fw-bold">Academic Year *</label><input type="text" name="academic_year" class="form-control" value="<?php echo date('Y'); ?>" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_class" class="btn-gold btn-sm"><i class="fas fa-plus me-1"></i> Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editClass): ?>
<div class="modal fade" id="editClassModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5><i class="fas fa-edit me-2"></i> Edit Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="class_id" value="<?php echo $editClass['id']; ?>">
                    <div class="mb-2"><label class="form-label fw-bold">Class Name *</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editClass['name']); ?>" required></div>
                    <div class="mb-2"><label class="form-label fw-bold">Description</label><textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($editClass['description']); ?></textarea></div>
                    <div class="mb-2"><label class="form-label fw-bold">Capacity</label><input type="number" name="capacity" class="form-control" value="<?php echo $editClass['capacity']; ?>"></div>
                    <div class="mb-2"><label class="form-label fw-bold">Academic Year *</label><input type="text" name="academic_year" class="form-control" value="<?php echo $editClass['academic_year']; ?>" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_class" class="btn-gold btn-sm"><i class="fas fa-save me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal(document.getElementById('editClassModal')).show();});</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }

    function scrollToSection(sectionId) {
        const element = document.getElementById(sectionId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        if (window.innerWidth <= 768) {
            toggleSidebar();
        }
    }

    function setLinkData(parentUserId, parentName) {
        document.getElementById('linkParentId').value = parentUserId;
        document.getElementById('linkParentName').value = parentName;
    }
    
    function setUnlinkData(childId, childName) {
        document.getElementById('unlinkChildId').value = childId;
        document.getElementById('unlinkChildName').value = childName;
    }
    
    function setPaymentData(feeId, studentName, balance) {
        document.getElementById('paymentFeeId').value = feeId;
        document.getElementById('paymentStudentName').value = studentName;
        document.getElementById('paymentBalance').value = 'Tsh ' + Number(balance).toLocaleString();
    }

    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const hamburger = document.querySelector('.hamburger');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !hamburger.contains(event.target)) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
                var closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            });
        }, 5000);
    });
</script>

</body>
</html>

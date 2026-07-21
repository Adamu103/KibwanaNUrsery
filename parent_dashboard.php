<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'includes_session.php';
include_once 'Database.php';
include_once 'User.php';
include_once 'Student.php';
include_once 'Fee.php';
include_once 'Attendance.php';
include_once 'Result.php';
include_once 'Encryption.php';

requireParent();

$db = Database::getInstance()->getConnection();
$user = new User();
$student = new Student();
$fee = new Fee();
$attendance = new Attendance();
$result = new Result();

$currentUser = $user->getCurrentUser();

$stmt = $db->prepare("SELECT id FROM parents WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$parent = $stmt->fetch();

$children = [];
$feeDetails = [];
$totalFees = 0;
$totalPaidFees = 0;
$totalBalanceFees = 0;
$attendanceSummary = ['rate' => 0];

if ($parent) {
    $stmt = $db->prepare("
        SELECT c.*, cl.name as class_name 
        FROM children c
        LEFT JOIN classes cl ON c.class_id = cl.id
        WHERE c.parent_id = ? AND c.status = 'active'
        ORDER BY c.name
    ");
    $stmt->execute([$parent['id']]);
    $children = $stmt->fetchAll();
    
    foreach ($children as &$child) {
        $child['name'] = $child['name'];
    }
    
    $childIds = array_column($children, 'id');
    
    if (count($childIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($childIds), '?'));
        
        $stmt = $db->prepare("
            SELECT f.*, c.name as student_name
            FROM fees f
            JOIN children c ON f.child_id = c.id
            WHERE f.child_id IN ($placeholders) AND c.status = 'active'
            ORDER BY f.created_at DESC
        ");
        $stmt->execute($childIds);
        $feeDetails = $stmt->fetchAll();
        
        foreach ($feeDetails as &$feeRow) {
            $feeRow['student_name'] = $feeRow['student_name'];
        }
        
        foreach ($feeDetails as $feeRow) {
            $totalFees += $feeRow['amount'];
            $totalPaidFees += $feeRow['amount_paid'];
            $totalBalanceFees += ($feeRow['amount'] - $feeRow['amount_paid']);
        }
        
        $attQuery = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE child_id IN ($placeholders)
        ");
        $attQuery->execute($childIds);
        $attData = $attQuery->fetch();
        
        if ($attData && $attData['total'] > 0) {
            $attendanceSummary['rate'] = ($attData['present'] / $attData['total']) * 100;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Parent Dashboard - Kibwana Nursery</title>
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
        .welcome-banner .banner-icon { display: none; }
        
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
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
        .stat-card .stat-icon.red { background: rgba(239, 68, 68, 0.12); color: #EF4444; }
        
        .section-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            margin-bottom: 18px;
            overflow: hidden;
        }
        .section-card .card-header {
            padding: 14px 18px;
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
        .section-card .card-body { padding: 14px 16px; }
        
        .table-responsive { margin: 0 -4px; }
        .table { margin: 0; font-size: 12px; }
        .table th { 
            font-weight: 600; 
            color: #6B7280; 
            font-size: 10px; 
            text-transform: uppercase; 
            letter-spacing: 0.3px; 
            border-bottom: 2px solid #eef2f7; 
            padding: 8px 10px;
        }
        .table td { 
            vertical-align: middle; 
            font-size: 12px; 
            padding: 8px 10px;
        }
        .table tbody tr:hover { background: #fafbfc; }
        
        .badge-gold { background: var(--gold); color: #fff; padding: 3px 10px; border-radius: 50px; font-weight: 600; font-size: 10px; }
        .badge-info-custom { background: #3B82F6; color: #fff; padding: 3px 10px; border-radius: 50px; font-weight: 600; font-size: 10px; }
        
        .quick-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 15px;
            text-align: center;
            border: 1px solid #eef2f7;
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 12px;
        }
        .quick-card:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .quick-card i { font-size: 26px; color: var(--gold); }
        .quick-card h6 { font-weight: 700; color: var(--dark-blue); margin-top: 8px; margin-bottom: 2px; font-size: 13px; }
        .quick-card small { color: #6B7280; font-size: 11px; }
        
        .fee-summary-item {
            padding: 12px 16px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 8px;
        }
        .fee-summary-item h6 { font-size: 11px; font-weight: 600; margin-bottom: 2px; }
        .fee-summary-item h4 { font-size: 18px; font-weight: 700; margin: 0; }
        
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
            .main-content { margin-left: 0; padding: 60px 12px 20px; }
            .top-bar h3 { font-size: 16px; }
            .user-info span { font-size: 13px; }
            .stat-card .stat-number { font-size: 20px; }
            .table { font-size: 11px; }
            .table th, .table td { padding: 6px 8px; }
            .section-card .card-header { padding: 12px 14px; }
            .section-card .card-header h5 { font-size: 13px; }
            .section-card .card-body { padding: 12px 14px; }
            .quick-card { padding: 15px 12px; }
            .quick-card i { font-size: 22px; }
            .quick-card h6 { font-size: 12px; }
            .welcome-banner { padding: 14px 16px; }
            .welcome-banner h5 { font-size: 14px; }
            .welcome-banner p { font-size: 12px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 55px 8px 15px; }
            .top-bar h3 { font-size: 14px; }
            .user-info span { font-size: 12px; }
            .user-avatar { width: 32px; height: 32px; font-size: 12px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 18px; }
            .stat-card .stat-icon { width: 34px; height: 34px; font-size: 14px; }
            .table { font-size: 10px; }
            .table th, .table td { padding: 4px 6px; }
            .section-card .card-header h5 { font-size: 12px; }
            .section-card .card-body { padding: 10px 12px; }
            .fee-summary-item h4 { font-size: 16px; }
            .quick-card { padding: 12px 10px; }
            .quick-card i { font-size: 20px; }
            .quick-card h6 { font-size: 11px; }
            .badge-gold, .badge-info-custom { font-size: 9px; padding: 2px 8px; }
            .welcome-banner h5 { font-size: 13px; }
            .welcome-banner p { font-size: 11px; }
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
        <li><a href="parent_dashboard.php" class="active"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="#childrenSection" onclick="scrollToSection('childrenSection')"><i class="fas fa-child"></i><span>Children</span></a></li>
        <li><a href="#feesSection" onclick="scrollToSection('feesSection')"><i class="fas fa-money-bill-wave"></i><span>Fees</span></a></li>
        <li><a href="#attendanceSection" onclick="scrollToSection('attendanceSection')"><i class="fas fa-calendar-check"></i><span>Attendance</span></a></li>
        <li><a href="#resultsSection" onclick="scrollToSection('resultsSection')"><i class="fas fa-star"></i><span>Results</span></a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">

    <div class="top-bar">
        <h3><i class="fas fa-home"></i> Dashboard</h3>
        <div class="user-info">
            <span style="font-weight: 500; color: var(--dark-blue); font-size: 13px;">
                <?php echo htmlspecialchars($currentUser['fullname']); ?>
            </span>
            <div class="user-avatar">
                <?php echo strtoupper(substr($currentUser['fullname'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <div class="welcome-banner">
        <div>
            <h5><i class="fas fa-user me-2"></i> Welcome, <?php echo htmlspecialchars($currentUser['fullname']); ?>!</h5>
            <p>Overview of your children's progress</p>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo count($children); ?></div>
                        <div class="stat-label"><i class="fas fa-user-graduate me-1"></i> Children</div>
                    </div>
                    <div class="stat-icon gold"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number">Tsh <?php echo number_format($totalPaidFees, 0); ?></div>
                        <div class="stat-label"><i class="fas fa-check-circle me-1"></i> Paid</div>
                    </div>
                    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number">Tsh <?php echo number_format($totalBalanceFees, 0); ?></div>
                        <div class="stat-label"><i class="fas fa-clock me-1"></i> Balance</div>
                    </div>
                    <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo number_format($attendanceSummary['rate'] ?? 0, 1); ?>%</div>
                        <div class="stat-label"><i class="fas fa-calendar-check me-1"></i> Attendance</div>
                    </div>
                    <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div id="childrenSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-user-graduate"></i> My Children</h5>
            <span class="badge-gold"><?php echo count($children); ?></span>
        </div>
        <div class="card-body">
            <?php if (count($children) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($children as $child): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($child['name']); ?></strong></td>
                                    <td><span class="badge-info-custom"><?php echo htmlspecialchars($child['class_name'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <span class="badge bg-<?php echo $child['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($child['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning border-0" style="border-radius: 10px; font-size: 13px; padding: 12px;">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>No children registered yet.</strong>
                    <p class="mb-0 mt-1">Contact the school admin to register your child.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="feesSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-money-bill-wave"></i> My Fees</h5>
            <span class="badge-gold"><?php echo count($feeDetails); ?></span>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-4">
                    <div class="fee-summary-item" style="background: #f8f9fa;">
                        <h6 class="text-muted">Total</h6>
                        <h4 class="text-dark">Tsh <?php echo number_format($totalFees, 0); ?></h4>
                    </div>
                </div>
                <div class="col-4">
                    <div class="fee-summary-item" style="background: #f0fdf4;">
                        <h6 class="text-muted">Paid</h6>
                        <h4 class="text-success">Tsh <?php echo number_format($totalPaidFees, 0); ?></h4>
                    </div>
                </div>
                <div class="col-4">
                    <div class="fee-summary-item" style="background: #fef2f2;">
                        <h6 class="text-muted">Balance</h6>
                        <h4 class="text-danger">Tsh <?php echo number_format($totalBalanceFees, 0); ?></h4>
                    </div>
                </div>
            </div>

            <?php if (count($feeDetails) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Child</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeDetails as $feeRow): 
                                $balance = $feeRow['amount'] - $feeRow['amount_paid'];
                                if ($feeRow['status'] === 'paid') {
                                    $statusClass = 'badge bg-success';
                                    $statusText = 'Paid';
                                } elseif ($feeRow['status'] === 'partial') {
                                    $statusClass = 'badge bg-info';
                                    $statusText = 'Partial';
                                } elseif (strtotime($feeRow['due_date']) < time() && $feeRow['status'] !== 'paid') {
                                    $statusClass = 'badge bg-danger';
                                    $statusText = 'Overdue';
                                } else {
                                    $statusClass = 'badge bg-warning';
                                    $statusText = 'Pending';
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($feeRow['student_name']); ?></strong></td>
                                    <td>Tsh <?php echo number_format($feeRow['amount'], 0); ?></td>
                                    <td>Tsh <?php echo number_format($feeRow['amount_paid'], 0); ?></td>
                                    <td><span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="fas fa-info-circle fa-2x mb-2 d-block" style="color: var(--gold);"></i>
                    <p class="mb-0" style="font-size: 13px;">No fees recorded yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="attendanceSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-check"></i> Attendance</h5>
            <span class="badge-gold"><?php echo count($children); ?></span>
        </div>
        <div class="card-body">
            <?php if (count($children) > 0): ?>
                <div class="row g-2">
                    <?php foreach ($children as $child): ?>
                        <div class="col-6 col-md-4">
                            <div class="p-3" style="background: #f8f9fa; border-radius: 10px; text-align: center;">
                                <h6 style="font-size: 13px;"><?php echo htmlspecialchars($child['name']); ?></h6>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo number_format($attendanceSummary['rate'] ?? 0, 0); ?>%"></div>
                                </div>
                                <small class="text-muted" style="font-size: 11px;"><?php echo number_format($attendanceSummary['rate'] ?? 0, 0); ?>%</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <span style="font-size: 13px;">No attendance data available.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="resultsSection" class="section-card">
        <div class="card-header">
            <h5><i class="fas fa-star"></i> Results</h5>
            <span class="badge-gold">Coming soon</span>
        </div>
        <div class="card-body">
            <div class="text-center text-muted py-3">
                <i class="fas fa-clock fa-2x mb-2 d-block" style="color: var(--gold);"></i>
                <p class="mb-0" style="font-size: 13px;">Results will be displayed here when available.</p>
            </div>
        </div>
    </div>

    <div class="row g-2 mt-2">
        <div class="col-6 col-md-3">
            <div class="quick-card" onclick="location.href='#childrenSection'">
                <i class="fas fa-child"></i>
                <h6>Children</h6>
                <small>View</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="quick-card" onclick="location.href='#feesSection'">
                <i class="fas fa-money-bill-wave"></i>
                <h6>Fees</h6>
                <small>Check</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="quick-card" onclick="location.href='#attendanceSection'">
                <i class="fas fa-calendar-check"></i>
                <h6>Attendance</h6>
                <small>Track</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="quick-card" onclick="location.href='#resultsSection'">
                <i class="fas fa-star"></i>
                <h6>Results</h6>
                <small>Check</small>
            </div>
        </div>
    </div>

</div>

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
</script>

</body>
</html>

<?php
// includes/functions.php

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Generate receipt number
function generateReceiptNumber() {
    return 'RCP-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

// Format currency
function formatCurrency($amount) {
    return 'KES ' . number_format($amount, 2);
}

// Get current date
function currentDate() {
    return date('Y-m-d');
}

// Get current time
function currentTime() {
    return date('H:i:s');
}

// Get month name
function getMonthName($month) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March',
        4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September',
        10 => 'October', 11 => 'November', 12 => 'December'
    ];
    return $months[(int)$month] ?? '';
}

// Calculate age from DOB
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today);
    return $age->y;
}

// Get user role badge
function getRoleBadge($role) {
    if ($role === 'admin') {
        return '<span class="badge bg-danger">Admin</span>';
    }
    return '<span class="badge bg-primary">Parent</span>';
}

// Get status badge
function getStatusBadge($status) {
    $colors = [
        'active' => 'success',
        'inactive' => 'secondary',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'info',
        'overdue' => 'danger',
        'present' => 'success',
        'absent' => 'danger',
        'late' => 'warning',
        'excused' => 'info'
    ];
    
    $color = $colors[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

// Get gender badge
function getGenderBadge($gender) {
    if ($gender === 'male') {
        return '<span class="badge bg-info">Male</span>';
    }
    return '<span class="badge bg-danger">Female</span>';
}

// Truncate text
function truncateText($text, $length = 50) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

// Get class options for select dropdown
function getClassOptions($selected = null) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, name FROM classes ORDER BY name");
    $options = '<option value="">Select Class</option>';
    while ($class = $stmt->fetch()) {
        $sel = ($selected == $class['id']) ? 'selected' : '';
        $options .= '<option value="' . $class['id'] . '" ' . $sel . '>' . htmlspecialchars($class['name']) . '</option>';
    }
    return $options;
}

// Get parent options for select dropdown
function getParentOptions($selected = null) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT u.id, u.fullname, u.username
        FROM users u
        JOIN parents p ON u.id = p.user_id
        WHERE u.role = 'parent' AND u.status = 'active'
        ORDER BY u.fullname
    ");
    $options = '<option value="">Select Parent</option>';
    while ($parent = $stmt->fetch()) {
        $sel = ($selected == $parent['id']) ? 'selected' : '';
        $options .= '<option value="' . $parent['id'] . '" ' . $sel . '>' . 
                    htmlspecialchars($parent['fullname']) . ' (' . htmlspecialchars($parent['username']) . ')' . 
                    '</option>';
    }
    return $options;
}

// Child options for select dropdown
function getChildOptions($selected = null) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT c.id, c.name, u.fullname as parent_name
        FROM children c
        JOIN parents p ON c.parent_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE c.status = 'active'
        ORDER BY c.name
    ");
    $options = '<option value="">Select Child</option>';
    while ($child = $stmt->fetch()) {
        $sel = ($selected == $child['id']) ? 'selected' : '';
        $options .= '<option value="' . $child['id'] . '" ' . $sel . '>' . 
                    htmlspecialchars($child['name']) . ' (' . htmlspecialchars($child['parent_name']) . ')' . 
                    '</option>';
    }
    return $options;
}
?>
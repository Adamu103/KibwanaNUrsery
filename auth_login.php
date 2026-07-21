<?php
// auth/login.php

include 'includes_session.php';
include 'User.php';

// If already logged in, redirect
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: parent_dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = new User();
    $selectedRole = $_POST['role'] ?? 'parent'; // Get selected role
    
    $result = $user->login($_POST['username'], $_POST['password']);
    
    if ($result['success']) {
        // Check if selected role matches actual role
        if ($selectedRole === 'admin' && $result['role'] === 'admin') {
            header('Location: admin_dashboard.php');
        } elseif ($selectedRole === 'parent' && $result['role'] === 'parent') {
            header('Location: parent_dashboard.php');
        } else {
            $error = "Invalid role selected. Please select your correct role.";
        }
        exit();
    }
    $error = $result['message'] ?? 'Login failed!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kibwana Nursery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #D4AF37;
            --gold-dark: #B8960F;
            --dark-blue: #1E3A5F;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #FFF8E7, #FDE68A);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
        }
        .login-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-card .logo i {
            font-size: 48px;
            color: var(--gold);
        }
        .login-card .logo h3 {
            color: var(--dark-blue);
            font-weight: 800;
            margin-top: 10px;
        }
        .login-card .logo p {
            color: #6B7280;
            font-size: 14px;
        }
        
        /* Role Selector */
        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: #F3F4F6;
            padding: 5px;
            border-radius: 12px;
        }
        .role-selector .role-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #6B7280;
            font-weight: 600;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s ease;
        }
        .role-selector .role-btn i {
            margin-right: 8px;
        }
        .role-selector .role-btn.active {
            background: var(--gold);
            color: #fff;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }
        .role-selector .role-btn:hover:not(.active) {
            background: rgba(212, 175, 55, 0.1);
        }
        
        .btn-gold {
            background: var(--gold);
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 700;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-gold:hover {
            background: var(--gold-dark);
            color: #fff;
            transform: translateY(-2px);
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 16px;
        }
        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }
        .back-link {
            color: #6B7280;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            color: var(--gold);
        }
        
        .alert-custom {
            border-radius: 10px;
            padding: 12px 16px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <i class="fas fa-child"></i>
            <h3>Kibwana Nursery</h3>
            <p>Nursery Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-custom">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Role Selector -->
            <div class="role-selector" id="roleSelector">
                <button type="button" class="role-btn active" data-role="parent" onclick="setRole('parent')">
                    <i class="fas fa-user-friends"></i> Parent
                </button>
                <button type="button" class="role-btn" data-role="admin" onclick="setRole('admin')">
                    <i class="fas fa-user-shield"></i> Admin
                </button>
            </div>
            <input type="hidden" name="role" id="loginRole" value="parent">
            
            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="fas fa-user me-1"></i> Username or Email
                </label>
                <input type="text" name="username" class="form-control" placeholder="Enter username or email" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="fas fa-lock me-1"></i> Password
                </label>
                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>
            
            <button type="submit" class="btn-gold">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
        </form>
        
        <div class="text-center mt-4">
            <a href="../index.php" class="back-link">
                <i class="fas fa-arrow-left me-1"></i> Back to Home
            </a>
            <span class="mx-2 text-muted">|</span>
            <a href="../index.php?tab=register" class="back-link">
                <i class="fas fa-user-plus me-1"></i> Register
            </a>
        </div>
    </div>
    
    <script>
        function setRole(role) {
            // Update buttons
            var buttons = document.querySelectorAll('.role-selector .role-btn');
            for (var i = 0; i < buttons.length; i++) {
                var btn = buttons[i];
                if (btn.dataset.role === role) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            }
            // Update hidden input
            document.getElementById('loginRole').value = role;
        }
    </script>
</body>
</html>

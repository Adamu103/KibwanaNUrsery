<?php
// index.php - Rangi Kama Zile za Zamani (Gold & Dark Blue)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include database and classes
include_once 'User.php';
include_once 'Database.php';

$error = '';
$regError = '';
$regSuccess = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'login';

// Check if user is already logged in
if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit();
    } elseif ($_SESSION['user']['role'] === 'parent') {
        header('Location: parent_dashboard.php');
        exit();
    }
}

// Handle Login
if (isset($_POST['login'])) {
    $user = new User();
    $selectedRole = $_POST['role'] ?? 'parent';
    
    $result = $user->login($_POST['username'], $_POST['password']);
    
    if ($result['success']) {
        if ($selectedRole === 'admin' && $result['role'] === 'admin') {
            header('Location: admin_dashboard.php');
            exit();
        } elseif ($selectedRole === 'parent' && $result['role'] === 'parent') {
            header('Location: parent_dashboard.php');
            exit();
        } else {
            $error = "Invalid role selected. You are logged in as {$result['role']} but selected {$selectedRole}.";
        }
    } else {
        $error = $result['message'] ?? 'Login failed!';
    }
}

// Handle Registration - Parent only
if (isset($_POST['register'])) {
    $user = new User();
    $result = $user->register($_POST);
    
    if ($result['success']) {
        $regSuccess = 'Registration successful! Please login with your credentials.';
        $active_tab = 'login';
        $_POST = array();
    } else {
        $regError = $result['message'] ?? 'Registration failed!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kibwana Nursery School</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ===== CUSTOM CSS - RANGI ZA ZAMANI ===== */
        :root {
            --gold: #D4AF37;
            --gold-light: #E8C84A;
            --gold-dark: #B8960F;
            --gold-gradient: linear-gradient(135deg, #D4AF37, #E8C84A);
            --gold-hover: linear-gradient(135deg, #B8960F, #D4AF37);
            
            --dark-blue: #1E3A5F;
            --dark-blue-light: #2A4A7A;
            --dark-blue-dark: #0F2440;
            
            --bg-white: #FFFFFF;
            --card-bg: #F8F9FA;
            --text-dark: #1E1B4B;
            --text-muted: #6B7280;
            --border-light: #E5E7EB;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1E3A5F 0%, #2A4A7A 50%, #0F2440 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        /* ===== BUBBLES - KWA RANGI ZA ZAMANI ===== */
        body::before {
            content: '';
            position: absolute;
            width: 250px;
            height: 250px;
            background: rgba(212, 175, 55, 0.06);
            border-radius: 50%;
            top: -80px;
            left: -80px;
            animation: float 20s infinite;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 350px;
            height: 350px;
            background: rgba(212, 175, 55, 0.05);
            border-radius: 50%;
            bottom: -100px;
            right: -100px;
            animation: float 15s infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, 20px); }
        }
        
        /* ===== MAIN CARD - RANGI ZA ZAMANI ===== */
        .glass-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 24px;
            padding: 30px 28px;
            width: 100%;
            max-width: 420px;
            margin: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border: 1px solid rgba(212, 175, 55, 0.15);
            animation: slideUp 0.5s ease;
            position: relative;
            z-index: 1;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ===== LOGO - ZA ZAMANI ===== */
        .logo-section {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo-icon {
            width: 70px;
            height: 70px;
            background: var(--gold-gradient);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 8px 30px rgba(212, 175, 55, 0.25);
        }
        
        .logo-icon i {
            font-size: 40px;
            color: white;
        }
        
        .logo-section h2 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .logo-section h2 span {
            color: var(--gold);
        }
        
        .logo-section p {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }
        
        /* ===== TABS - ZA ZAMANI ===== */
        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: rgba(255,255,255,0.05);
            border-radius: 50px;
            padding: 5px;
            border: 1px solid rgba(212, 175, 55, 0.1);
        }
        
        .tab-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.6);
            font-weight: 600;
            cursor: pointer;
            border-radius: 50px;
            transition: all 0.3s;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .tab-btn i {
            margin-right: 6px;
        }
        
        .tab-btn.active {
            background: var(--gold-gradient);
            color: white;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.25);
        }
        
        .tab-btn:hover:not(.active) {
            color: white;
            background: rgba(212, 175, 55, 0.1);
        }
        
        /* ===== ROLE SELECTOR - ZA ZAMANI ===== */
        .role-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 50px;
            padding: 5px;
            border: 1px solid rgba(212, 175, 55, 0.1);
        }
        
        .role-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.6);
            font-weight: 600;
            cursor: pointer;
            border-radius: 50px;
            transition: all 0.3s;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .role-btn i {
            margin-right: 5px;
        }
        
        .role-btn.active {
            background: var(--gold-gradient);
            color: white;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.2);
        }
        
        .role-btn:hover:not(.active) {
            color: white;
            background: rgba(212, 175, 55, 0.1);
        }
        
        /* ===== FORM - ZA ZAMANI ===== */
        .form-group {
            position: relative;
            margin-bottom: 18px;
        }
        
        .form-group i:not(.toggle-password) {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.4);
            font-size: 14px;
            z-index: 1;
        }
        
        .form-control {
            width: 100%;
            padding: 11px 15px 11px 40px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(212, 175, 55, 0.15);
            border-radius: 12px;
            color: white;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            background: rgba(255,255,255,0.1);
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
        }
        
        .form-control::placeholder {
            color: rgba(255,255,255,0.4);
            font-size: 13px;
        }
        
        .form-control:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 30px rgba(30, 58, 95, 0.8) inset !important;
            -webkit-text-fill-color: white !important;
        }
        
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.4);
            cursor: pointer;
            font-size: 14px;
            z-index: 2;
            transition: all 0.3s;
        }
        
        .toggle-password:hover {
            color: var(--gold);
        }
        
        /* ===== BUTTON - ZA ZAMANI ===== */
        .btn-submit {
            width: 100%;
            padding: 11px;
            background: var(--gold-gradient);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 6px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.2);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(212, 175, 55, 0.35);
            background: var(--gold-hover);
        }
        
        .btn-submit i {
            margin-right: 8px;
        }
        
        /* ===== ALERTS - ZA ZAMANI ===== */
        .alert {
            border-radius: 12px;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 175, 55, 0.15);
            color: white;
            margin-bottom: 18px;
            padding: 10px 15px;
            font-size: 13px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .alert i {
            margin-right: 8px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            border-color: rgba(40, 167, 69, 0.2);
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            border-color: rgba(220, 53, 69, 0.2);
        }
        
        /* ===== SWITCH LINK - ZA ZAMANI ===== */
        .switch-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid rgba(212, 175, 55, 0.1);
        }
        
        .switch-link a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s;
        }
        
        .switch-link a:hover {
            color: var(--gold);
            text-decoration: underline;
        }
        
        .switch-link a i {
            margin-right: 5px;
        }
        
        /* ===== STUDENT BADGE - ZA ZAMANI ===== */
        .student-badge {
            background: rgba(212, 175, 55, 0.15);
            color: var(--gold);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Plus Jakarta Sans', sans-serif;
            border: 1px solid rgba(212, 175, 55, 0.1);
        }
        
        .student-badge i {
            margin-right: 5px;
            color: var(--gold);
        }
        
        /* ===== INFO TEXT - ZA ZAMANI ===== */
        .info-text {
            text-align: center;
            color: rgba(255,255,255,0.3);
            font-size: 11px;
            margin-top: 16px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .info-text i {
            margin-right: 4px;
        }
        
        .info-text strong {
            color: rgba(212, 175, 55, 0.6);
        }
        
        /* ===== SECTION DIVIDER - ZA ZAMANI ===== */
        .section-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 16px 0 12px;
        }
        
        .section-divider hr {
            flex: 1;
            border-color: rgba(212, 175, 55, 0.1);
            margin: 0;
        }
        
        .section-divider .label {
            color: rgba(255,255,255,0.4);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            white-space: nowrap;
        }
        
        .section-divider .label i {
            margin-right: 4px;
            font-size: 10px;
            color: var(--gold);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .glass-card {
                padding: 24px 20px;
                max-width: 340px;
                margin: 15px;
                border-radius: 20px;
            }
            
            .logo-icon {
                width: 60px;
                height: 60px;
            }
            
            .logo-icon i {
                font-size: 32px;
            }
            
            .logo-section h2 {
                font-size: 20px;
            }
            
            .logo-section p {
                font-size: 12px;
            }
            
            .form-tabs .tab-btn {
                padding: 8px;
                font-size: 12px;
            }
            
            .role-selector .role-btn {
                padding: 8px;
                font-size: 12px;
            }
            
            .form-control {
                padding: 10px 12px 10px 36px;
                font-size: 13px;
            }
            
            .form-group i:not(.toggle-password) {
                left: 12px;
                font-size: 13px;
            }
            
            .btn-submit {
                padding: 10px;
                font-size: 13px;
            }
            
            body::before {
                width: 150px;
                height: 150px;
                top: -60px;
                left: -60px;
            }
            
            body::after {
                width: 200px;
                height: 200px;
                bottom: -60px;
                right: -60px;
            }
        }
        
        @media (max-width: 380px) {
            .glass-card {
                padding: 18px 14px;
                max-width: 300px;
                border-radius: 16px;
            }
            
            .logo-icon {
                width: 50px;
                height: 50px;
            }
            
            .logo-icon i {
                font-size: 26px;
            }
            
            .logo-section h2 {
                font-size: 18px;
            }
            
            .form-tabs .tab-btn {
                padding: 6px;
                font-size: 11px;
            }
            
            .role-selector .role-btn {
                padding: 6px;
                font-size: 11px;
            }
            
            .form-control {
                padding: 8px 10px 8px 32px;
                font-size: 12px;
            }
            
            .form-group i:not(.toggle-password) {
                left: 10px;
                font-size: 12px;
            }
            
            .btn-submit {
                padding: 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="glass-card">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-child"></i>
            </div>
            <h2>Kibwana <span>Nursery</span></h2>
            <p>Nursery Management System</p>
        </div>
        
        <!-- Tabs -->
        <div class="form-tabs">
            <button class="tab-btn <?php echo $active_tab == 'login' ? 'active' : ''; ?>" onclick="switchTab('login')">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            <button class="tab-btn <?php echo $active_tab == 'register' ? 'active' : ''; ?>" onclick="switchTab('register')">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </div>
        
        <!-- ===== LOGIN FORM ===== -->
        <div id="loginForm" style="display: <?php echo $active_tab == 'login' ? 'block' : 'none'; ?>">
            <?php if($error && $active_tab == 'login'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- Role Selector -->
                <div class="role-selector mb-3">
                    <button type="button" class="role-btn active" data-role="parent" onclick="setLoginRole('parent')">
                        <i class="fas fa-user-friends"></i> Parent
                    </button>
                    <button type="button" class="role-btn" data-role="admin" onclick="setLoginRole('admin')">
                        <i class="fas fa-user-shield"></i> Admin
                    </button>
                </div>
                <input type="hidden" name="role" id="loginRole" value="parent">
                
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" class="form-control" placeholder="Username or Email" required>
                </div>
                
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('loginPassword', this)"></i>
                </div>
                
                <button type="submit" name="login" class="btn-submit">
                    <i class="fas fa-arrow-right"></i> Login
                </button>
            </form>
            
            <div class="switch-link">
                <a onclick="switchTab('register')">
                    <i class="fas fa-user-plus"></i> Don't have an account? Register
                </a>
            </div>
        </div>
        
        <!-- ===== REGISTER FORM ===== -->
        <div id="registerForm" style="display: <?php echo $active_tab == 'register' ? 'block' : 'none'; ?>">
            <div class="text-center mb-3">
                <span class="student-badge">
                    <i class="fas fa-user-friends"></i> Parent Registration Only
                </span>
            </div>
            
            <?php if($regError && $active_tab == 'register'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($regError); ?>
                </div>
            <?php endif; ?>
            
            <?php if($regSuccess && $active_tab == 'register'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($regSuccess); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- Parent Details -->
                <div class="section-divider">
                    <hr>
                    <span class="label"><i class="fas fa-user"></i> Personal Details</span>
                    <hr>
                </div>
                
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="fullname" class="form-control" placeholder="Full Name *" required>
                </div>
                
                <div class="form-group">
                    <i class="fas fa-user-tag"></i>
                    <input type="text" name="username" class="form-control" placeholder="Username *" required>
                </div>
                
                <div class="form-group">
                    <i class="fas fa-phone"></i>
                    <input type="text" name="phone" class="form-control" placeholder="Phone Number *" required>
                </div>
                
                <div class="form-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="Email Address (Optional)">
                </div>
                
                <div class="section-divider">
                    <hr>
                    <span class="label"><i class="fas fa-lock"></i> Security</span>
                    <hr>
                </div>
                
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="regPassword" class="form-control" placeholder="Password (min 6) *" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('regPassword', this)"></i>
                </div>
                
                <div class="form-group">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm Password *" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmPassword', this)"></i>
                </div>
                
                <button type="submit" name="register" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Register as Parent
                </button>
            </form>
            
            <div class="switch-link">
                <a onclick="switchTab('login')">
                    <i class="fas fa-sign-in-alt"></i> Already registered? Login here
                </a>
            </div>
        </div>
        
      
    </div>
    
    <!-- ===== JAVASCRIPT ===== -->
    <script>
        function switchTab(tab) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const tabs = document.querySelectorAll('.tab-btn');
            
            if(tab === 'login') {
                loginForm.style.display = 'block';
                registerForm.style.display = 'none';
                tabs[0].classList.add('active');
                tabs[1].classList.remove('active');
            } else {
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
                tabs[0].classList.remove('active');
                tabs[1].classList.add('active');
            }
        }
        
        function setLoginRole(role) {
            const roleBtns = document.querySelectorAll('#loginForm .role-btn');
            const loginRole = document.getElementById('loginRole');
            
            roleBtns.forEach(btn => {
                btn.classList.remove('active');
                if(btn.getAttribute('data-role') === role) {
                    btn.classList.add('active');
                }
            });
            loginRole.value = role;
        }
        
        function togglePassword(inputId, iconElement) {
            const passwordInput = document.getElementById(inputId);
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'text') {
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }
        
        // Set default role
        document.addEventListener('DOMContentLoaded', function() {
            setLoginRole('parent');
            
            <?php if (isset($_GET['tab']) && $_GET['tab'] === 'register'): ?>
                switchTab('register');
            <?php endif; ?>
            
            <?php if (isset($regSuccess)): ?>
                switchTab('login');
            <?php endif; ?>
        });
    </script>
</body>
</html>

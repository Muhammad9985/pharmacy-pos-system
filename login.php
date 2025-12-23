<?php
require_once 'config/centralized_db.php';
require_once 'includes/centralized_auth.php';
require_once 'includes/centralized_functions.php';

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pharma POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #1e293b;
            --white: #ffffff;
            --gradient: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .login-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: flex;
            min-height: 600px;
        }
        
        .login-left {
            background: var(--gradient);
            color: var(--white);
            padding: 3rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="%23ffffff" opacity="0.1"/></svg>') repeat;
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-50px) translateY(-50px); }
        }
        
        .pharmacy-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .login-right {
            padding: 3rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .input-group {
            display: flex;
            width: 100%;
        }
        
        .input-group-text {
            background: var(--light);
            border: 2px solid #e2e8f0;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: var(--secondary);
            padding: 0.875rem 1rem;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--white);
            flex: 1;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .form-control.with-icon {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: var(--primary);
        }
        
        .btn-login {
            background: var(--gradient);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--white);
            width: 100%;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }
        
        .quick-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .quick-btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-super { border-color: var(--danger); color: var(--danger); }
        .btn-super:hover { background: var(--danger); color: var(--white); }
        
        .btn-shop { border-color: var(--success); color: var(--success); }
        .btn-shop:hover { background: var(--success); color: var(--white); }
        
        .btn-cashier { border-color: var(--primary); color: var(--primary); }
        .btn-cashier:hover { background: var(--primary); color: var(--white); }
        
        .btn-north { border-color: var(--warning); color: var(--warning); }
        .btn-north:hover { background: var(--warning); color: var(--white); }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: #991b1b;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .text-muted { color: var(--secondary) !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .mb-3 { margin-bottom: 1rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .mt-4 { margin-top: 1.5rem !important; }
        .text-center { text-align: center !important; }
        .fw-bold { font-weight: 700 !important; }
        .lead { font-size: 1.125rem; font-weight: 400; }
        
        .row { display: flex; flex-wrap: wrap; margin: 0 -0.5rem; }
        .col-6 { flex: 0 0 50%; max-width: 50%; padding: 0 0.5rem; }
        .g-2 > * { margin-bottom: 0.5rem; }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                margin: 1rem;
                min-height: auto;
            }
            
            .login-left, .login-right {
                padding: 2rem;
            }
            
            .pharmacy-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side -->
        <div class="login-left">
            <div>
                <div class="pharmacy-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <h2 class="mb-4">Pharma POS System</h2>
                <p class="lead mb-4">Centralized Multi-Shop Management</p>
                <p class="mb-0">Professional pharmacy management system with role-based access control for multiple branches.</p>
            </div>
        </div>
        
        <!-- Right Side -->
        <div class="login-right">
            <div>
                <h3 class="text-center mb-3">Welcome Back</h3>
                <p class="text-center text-muted mb-4">Sign in to access your dashboard</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div style="display: flex;">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" name="username" class="form-control with-icon" 
                                   placeholder="Enter username" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div style="display: flex;">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" name="password" class="form-control with-icon" 
                                   placeholder="Enter password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                        Sign In
                    </button>
                </form>
                
                <div class="mt-4">
                    <h6 class="text-center mb-3">Quick Demo Login:</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <button class="quick-btn btn-super" onclick="quickLogin('superadmin', 'admin123')">
                                <i class="fas fa-crown" style="margin-right: 0.25rem;"></i> Super Admin
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="quick-btn btn-shop" onclick="quickLogin('admin1', 'admin123')">
                                <i class="fas fa-store" style="margin-right: 0.25rem;"></i> Shop Admin
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="quick-btn btn-cashier" onclick="quickLogin('cashier1', 'admin123')">
                                <i class="fas fa-cash-register" style="margin-right: 0.25rem;"></i> Cashier
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="quick-btn btn-north" onclick="quickLogin('admin2', 'admin123')">
                                <i class="fas fa-building" style="margin-right: 0.25rem;"></i> North Branch
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt" style="margin-right: 0.25rem;"></i>
                        Secure authentication with role-based access
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function quickLogin(username, password) {
            document.querySelector('input[name="username"]').value = username;
            document.querySelector('input[name="password"]').value = password;
            document.querySelector('form').submit();
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    </script>
</body>
</html>
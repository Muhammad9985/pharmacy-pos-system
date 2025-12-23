<?php
// Modern Admin Layout Component
function renderAdminLayout($pageTitle, $content, $activePage = '', $additionalCSS = '', $additionalJS = '') {
    global $auth;
    
    $user = $auth->getUser();
    $isSuperAdmin = $auth->isSuperAdmin();
    $dashboardPage = $isSuperAdmin ? 'super_dashboard.php' : 'shop_dashboard.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="robots" content="noindex, nofollow">
        <title><?= htmlspecialchars($pageTitle) ?> - Pharma POS</title>
        
        <!-- Preconnect for faster loading -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://cdnjs.cloudflare.com">
        
        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Custom CSS -->
        <link rel="stylesheet" href="../assets/css/admin-modern.css">
        
        <?= $additionalCSS ?>
        
        <style>
            /* Inline critical CSS for faster rendering */
            body { margin: 0; padding: 0; }
            .admin-container { display: flex; min-height: 100vh; }
        </style>
    </head>
    <body>
        <div class="admin-container">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h1 class="sidebar-title">
                        <i class="fas <?= $isSuperAdmin ? 'fa-crown' : 'fa-store' ?>"></i>
                        <?= $isSuperAdmin ? 'Super Admin' : 'Shop Admin' ?>
                    </h1>
                    <?php if (!$isSuperAdmin && isset($user['shop_name'])): ?>
                        <p class="sidebar-subtitle"><?= htmlspecialchars($user['shop_name']) ?></p>
                    <?php endif; ?>
                    <p class="sidebar-subtitle">Welcome, <?= htmlspecialchars($user['full_name']) ?></p>
                </div>
                
                <nav class="sidebar-nav">
                    <div class="nav-item">
                        <a href="<?= $dashboardPage ?>" class="nav-link <?= $activePage === 'super_dashboard' || $activePage === 'shop_dashboard' ? 'active' : '' ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </div>
                    
                    <?php if ($isSuperAdmin): ?>
                        <div class="nav-item">
                            <a href="shops.php" class="nav-link <?= $activePage === 'shops' ? 'active' : '' ?>">
                                <i class="fas fa-store"></i>
                                <span>Manage Shops</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="users.php" class="nav-link <?= $activePage === 'users' ? 'active' : '' ?>">
                                <i class="fas fa-users"></i>
                                <span>Manage Users</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="nav-item">
                        <a href="medicines.php" class="nav-link <?= $activePage === 'medicines' ? 'active' : '' ?>">
                            <i class="fas fa-pills"></i>
                            <span>Medicines</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="stock.php" class="nav-link <?= $activePage === 'stock' ? 'active' : '' ?>">
                            <i class="fas fa-boxes"></i>
                            <span>Stock Management</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="reports.php" class="nav-link <?= $activePage === 'reports' ? 'active' : '' ?>">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="../pos/index.php" class="nav-link">
                            <i class="fas fa-cash-register"></i>
                            <span>POS System</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </nav>
            </aside>
            
            <!-- Main Content -->
            <main class="main-content">
                <header class="page-header">
                    <h1 class="page-title">
                        <i class="fas <?= getPageIcon($activePage) ?>"></i>
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                </header>
                
                <div class="content-wrapper">
                    <div id="alertContainer"></div>
                    <?= $content ?>
                </div>
            </main>
        </div>
        
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        
        <!-- Common JS -->
        <script>
            // Alert System
            function showAlert(message, type = 'info') {
                const alertHTML = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        <i class="fas fa-${getAlertIcon(type)} me-2"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                document.getElementById('alertContainer').innerHTML = alertHTML;
                setTimeout(() => {
                    const alert = document.querySelector('.alert');
                    if (alert) alert.remove();
                }, 5000);
            }
            
            function getAlertIcon(type) {
                const icons = {
                    'success': 'check-circle',
                    'danger': 'exclamation-circle',
                    'warning': 'exclamation-triangle',
                    'info': 'info-circle'
                };
                return icons[type] || 'info-circle';
            }
            
            // CSRF Protection
            const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
            
            // Form Validation
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
            
            // Auto-hide alerts
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    if (!alert.classList.contains('alert-dismissible')) {
                        alert.style.transition = 'opacity 0.5s';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }
                });
            }, 5000);
        </script>
        
        <?= $additionalJS ?>
    </body>
    </html>
    <?php
}

function getPageIcon($page) {
    $icons = [
        'super_dashboard' => 'fa-crown',
        'shop_dashboard' => 'fa-tachometer-alt',
        'shops' => 'fa-store',
        'users' => 'fa-users',
        'medicines' => 'fa-pills',
        'stock' => 'fa-boxes',
        'reports' => 'fa-chart-bar'
    ];
    return $icons[$page] ?? 'fa-file';
}
?>
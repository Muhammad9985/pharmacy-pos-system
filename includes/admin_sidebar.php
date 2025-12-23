<?php
// Admin Sidebar Component
function renderAdminSidebar($currentPage = '') {
    global $auth;
    
    $sidebarClass = $auth->isSuperAdmin() ? 'super-admin-sidebar' : 'shop-admin-sidebar';
    $sidebarTitle = $auth->isSuperAdmin() ? 'Super Admin' : 'Shop Admin';
    $sidebarIcon = $auth->isSuperAdmin() ? 'fa-crown' : 'fa-store';
    $dashboardPage = $auth->isSuperAdmin() ? 'super_dashboard.php' : 'shop_dashboard.php';
    
    echo '<div class="col-md-2 sidebar ' . $sidebarClass . ' p-0">';
    echo '<div class="p-3 text-white">';
    echo '<h5><i class="fas ' . $sidebarIcon . '"></i> ' . $sidebarTitle . '</h5>';
    
    if (!$auth->isSuperAdmin() && isset($_SESSION['shop_name'])) {
        echo '<small>' . $_SESSION['shop_name'] . '</small><br>';
    }
    
    echo '<small>Welcome, ' . ($_SESSION['full_name'] ?? 'User') . '</small>';
    echo '</div>';
    
    echo '<nav class="nav flex-column">';
    
    // Dashboard
    $activeClass = ($currentPage === 'dashboard') ? 'active' : '';
    echo '<a class="nav-link ' . $activeClass . '" href="' . $dashboardPage . '">';
    echo '<i class="fas fa-tachometer-alt"></i> Dashboard</a>';
    
    // Super Admin specific links
    if ($auth->isSuperAdmin()) {
        $activeClass = ($currentPage === 'shops') ? 'active' : '';
        echo '<a class="nav-link ' . $activeClass . '" href="shops.php">';
        echo '<i class="fas fa-store"></i> Manage Shops</a>';
        
        $activeClass = ($currentPage === 'users') ? 'active' : '';
        echo '<a class="nav-link ' . $activeClass . '" href="users.php">';
        echo '<i class="fas fa-users"></i> Manage Users</a>';
    }
    
    // Common links for both roles
    $activeClass = ($currentPage === 'medicines') ? 'active' : '';
    echo '<a class="nav-link ' . $activeClass . '" href="medicines.php">';
    echo '<i class="fas fa-pills"></i> Medicines</a>';
    
    $activeClass = ($currentPage === 'stock') ? 'active' : '';
    echo '<a class="nav-link ' . $activeClass . '" href="stock.php">';
    echo '<i class="fas fa-boxes"></i> Stock Management</a>';
    
    $activeClass = ($currentPage === 'reports') ? 'active' : '';
    echo '<a class="nav-link ' . $activeClass . '" href="reports.php">';
    echo '<i class="fas fa-chart-bar"></i> Reports</a>';
    
    // POS System
    echo '<a class="nav-link" href="../pos/index.php">';
    echo '<i class="fas fa-cash-register"></i> POS System</a>';
    
    // Logout
    echo '<a class="nav-link" href="logout.php">';
    echo '<i class="fas fa-sign-out-alt"></i> Logout</a>';
    
    echo '</nav>';
    echo '</div>';
}
?>
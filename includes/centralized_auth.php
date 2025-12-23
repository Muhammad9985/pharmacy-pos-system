<?php
session_start();
require_once __DIR__ . '/../config/centralized_db.php';

class CentralizedAuth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function login($username, $password) {
        $user = $this->db->fetch("
            SELECT u.*, r.name as role_name, s.name as shop_name, s.code as shop_code
            FROM users u 
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN shops s ON u.shop_id = s.id
            WHERE u.username = ? AND u.is_active = 1
        ", [$username]);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['shop_id'] = $user['shop_id'];
            $_SESSION['shop_name'] = $user['shop_name'];
            $_SESSION['shop_code'] = $user['shop_code'];
            
            // Update last login
            $this->db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            
            $this->logActivity($user['id'], 'login', 'User logged in', $user['shop_id']);
            return true;
        }
        return false;
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out', $_SESSION['shop_id']);
        }
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function hasRole($role) {
        return isset($_SESSION['role_name']) && $_SESSION['role_name'] === $role;
    }
    
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) return false;
        
        $result = $this->db->fetch("
            SELECT 1 FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.name = ?
        ", [$_SESSION['role_id'], $permission]);
        
        return $result !== false;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    }
    
    public function requirePermission($permission) {
        $this->requireLogin();
        if (!$this->hasPermission($permission)) {
            header('Location: ../unauthorized.php');
            exit;
        }
    }
    
    public function requireRole($roles) {
        $this->requireLogin();
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        $hasRole = false;
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                $hasRole = true;
                break;
            }
        }
        
        if (!$hasRole) {
            header('Location: ../unauthorized.php');
            exit;
        }
    }
    
    public function getUser() {
        if (!$this->isLoggedIn()) return null;
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role_id' => $_SESSION['role_id'],
            'role_name' => $_SESSION['role_name'],
            'shop_id' => $_SESSION['shop_id'],
            'shop_name' => $_SESSION['shop_name'],
            'shop_code' => $_SESSION['shop_code']
        ];
    }
    
    public function requireSuperAdmin() {
        $this->requireLogin();
        if (!$this->hasRole('super_admin')) {
            header('Location: ../unauthorized.php');
            exit;
        }
    }
    
    public function getUserShopId() {
        return $_SESSION['shop_id'] ?? null;
    }
    
    public function isSuperAdmin() {
        return $this->hasRole('super_admin');
    }
    
    public function isShopAdmin() {
        return $this->hasRole('shop_admin');
    }
    
    public function isCashier() {
        return $this->hasRole('cashier');
    }
    
    private function logActivity($userId, $action, $details, $shopId = null) {
        $this->db->query("
            INSERT INTO activity_logs (user_id, shop_id, action, module, ip_address, user_agent) 
            VALUES (?, ?, ?, 'auth', ?, ?)
        ", [
            $userId, 
            $shopId, 
            $action, 
            $_SERVER['REMOTE_ADDR'] ?? '', 
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

$auth = new CentralizedAuth($db);
?>
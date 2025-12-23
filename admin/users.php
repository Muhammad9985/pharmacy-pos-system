<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';
require_once '../includes/centralized_functions.php';
require_once '../includes/admin_layout.php';

$auth->requireRole(['super_admin']);

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'create_user':
            try {
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $full_name = sanitize($_POST['full_name']);
                $role_id = (int)$_POST['role_id'];
                $shop_id = $_POST['shop_id'] ? (int)$_POST['shop_id'] : null;
                $phone = sanitize($_POST['phone']);
                
                $db->query("INSERT INTO users (username, email, password, full_name, role_id, shop_id, phone) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                    [$username, $email, $password, $full_name, $role_id, $shop_id, $phone]);
                
                echo json_encode(['success' => true, 'message' => 'User created successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_user':
            try {
                $id = (int)$_POST['id'];
                $email = sanitize($_POST['email']);
                $full_name = sanitize($_POST['full_name']);
                $role_id = (int)$_POST['role_id'];
                $shop_id = $_POST['shop_id'] ? (int)$_POST['shop_id'] : null;
                $phone = sanitize($_POST['phone']);
                
                $sql = "UPDATE users SET email=?, full_name=?, role_id=?, shop_id=?, phone=?";
                $params = [$email, $full_name, $role_id, $shop_id, $phone];
                
                if (!empty($_POST['password'])) {
                    $sql .= ", password=?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                
                $sql .= " WHERE id=?";
                $params[] = $id;
                
                $db->query($sql, $params);
                
                echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_user':
            try {
                $id = (int)$_POST['id'];
                $db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$id]);
                echo json_encode(['success' => true, 'message' => 'User deactivated successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_user':
            try {
                $id = (int)$_POST['id'];
                $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
                if ($user) {
                    echo json_encode(['success' => true, 'data' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

$users = $db->query("
    SELECT u.*, r.display_name as role_name, s.name as shop_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN shops s ON u.shop_id = s.id 
    WHERE u.is_active = 1 
    ORDER BY u.full_name
")->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY name")->fetchAll();
$shops = $db->query("SELECT * FROM shops WHERE is_active = 1 ORDER BY name")->fetchAll();

// Content for the page
ob_start();
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>
            User Management
        </h5>
        <button class="btn btn-light btn-sm" onclick="openUserModal()">
            <i class="fas fa-plus me-1"></i> Add New User
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>User Details</th>
                        <th>Role & Shop</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                            <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?= htmlspecialchars($user['role_name']) ?></span><br>
                            <small class="text-muted"><?= htmlspecialchars($user['shop_name'] ?: 'All Shops') ?></small>
                        </td>
                        <td>
                            <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($user['email']) ?><br>
                            <i class="fas fa-phone me-1"></i> <?= htmlspecialchars($user['phone']) ?>
                        </td>
                        <td>
                            <span class="badge bg-success">Active</span><br>
                            <small class="text-muted">Last: <?= $user['last_login'] ? date('M j', strtotime($user['last_login'])) : 'Never' ?></small>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editUser(<?= $user['id'] ?>)" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['username'] !== 'superadmin'): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>)" title="Deactivate User">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade modal-modern" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i>
                    <span id="modalTitle">Add New User</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" id="userId" name="id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control form-control-modern" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control form-control-modern" id="fullName" name="full_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control form-control-modern" id="email" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control form-control-modern" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select form-control-modern" id="roleId" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shop</label>
                            <select class="form-select form-control-modern" id="shopId" name="shop_id">
                                <option value="">All Shops (Super Admin)</option>
                                <?php foreach ($shops as $shop): ?>
                                <option value="<?= $shop['id'] ?>"><?= htmlspecialchars($shop['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span id="passwordNote">*</span></label>
                        <input type="password" class="form-control form-control-modern" id="password" name="password">
                        <small class="text-muted">Leave blank to keep current password when editing</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-gradient" onclick="saveUser()">
                    <i class="fas fa-save me-1"></i> Save User
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$additionalJS = '
<script>
let isEditing = false;
const userCsrfToken = "' . $_SESSION['csrf_token'] . '";

function openUserModal() {
    isEditing = false;
    document.getElementById("modalTitle").textContent = "Add New User";
    document.getElementById("userForm").reset();
    document.getElementById("userId").value = "";
    document.getElementById("username").disabled = false;
    document.getElementById("password").required = true;
    document.getElementById("passwordNote").textContent = "*";
    new bootstrap.Modal(document.getElementById("userModal")).show();
}

function editUser(id) {
    isEditing = true;
    document.getElementById("modalTitle").textContent = "Edit User";
    
    fetch("", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_user&id=" + id + "&csrf_token=" + userCsrfToken
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const user = data.data;
            document.getElementById("userId").value = user.id;
            document.getElementById("username").value = user.username;
            document.getElementById("username").disabled = true;
            document.getElementById("fullName").value = user.full_name;
            document.getElementById("email").value = user.email;
            document.getElementById("phone").value = user.phone || "";
            document.getElementById("roleId").value = user.role_id;
            document.getElementById("shopId").value = user.shop_id || "";
            document.getElementById("password").required = false;
            document.getElementById("password").value = "";
            document.getElementById("passwordNote").textContent = "(optional)";
            
            new bootstrap.Modal(document.getElementById("userModal")).show();
        } else {
            alert("Error: " + (data.message || "Error loading user data"));
        }
    })
    .catch(error => {
        alert("Error loading user data");
    });
}

function saveUser() {
    const form = document.getElementById("userForm");
    const formData = new FormData(form);
    formData.append("action", isEditing ? "update_user" : "create_user");
    
    if (!form.checkValidity()) {
        form.classList.add("was-validated");
        return;
    }
    
    fetch("", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Success: " + data.message);
            bootstrap.Modal.getInstance(document.getElementById("userModal")).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Error saving user");
    });
}

function deleteUser(id) {
    if (confirm("Are you sure you want to deactivate this user?")) {
        fetch("", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=delete_user&id=" + id + "&csrf_token=" + userCsrfToken
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Success: " + data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(error => {
            alert("Error deleting user");
        });
    }
}
</script>';

renderAdminLayout('Manage Users', $content, 'users', '', $additionalJS);
?>
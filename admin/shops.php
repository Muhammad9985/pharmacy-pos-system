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
        case 'create_shop':
            try {
                $name = sanitize($_POST['name']);
                $code = sanitize($_POST['code']);
                $address = sanitize($_POST['address']);
                $phone = sanitize($_POST['phone']);
                $email = sanitize($_POST['email']);
                $license = sanitize($_POST['license_number']);
                $tax = sanitize($_POST['tax_number']);
                
                // Check if code already exists
                $existing = $db->query("SELECT id FROM shops WHERE code = ?", [$code])->fetch();
                if ($existing) {
                    echo json_encode(['success' => false, 'message' => 'Shop code already exists']);
                    exit;
                }
                
                $db->query("INSERT INTO shops (name, code, address, phone, email, license_number, tax_number) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                    [$name, $code, $address, $phone, $email, $license, $tax]);
                
                echo json_encode(['success' => true, 'message' => 'Shop created successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_shop':
            try {
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                $address = sanitize($_POST['address']);
                $phone = sanitize($_POST['phone']);
                $email = sanitize($_POST['email']);
                $license = sanitize($_POST['license_number']);
                $tax = sanitize($_POST['tax_number']);
                
                $db->query("UPDATE shops SET name=?, address=?, phone=?, email=?, license_number=?, tax_number=? WHERE id=?", 
                    [$name, $address, $phone, $email, $license, $tax, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Shop updated successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'toggle_shop':
            try {
                $id = (int)$_POST['id'];
                $status = (int)$_POST['status'];
                $db->query("UPDATE shops SET is_active = ? WHERE id = ?", [$status, $id]);
                $message = $status ? 'Shop activated successfully!' : 'Shop deactivated successfully!';
                echo json_encode(['success' => true, 'message' => $message]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_shop':
            try {
                $id = (int)$_POST['id'];
                $shop = $db->query("SELECT * FROM shops WHERE id = ?", [$id])->fetch();
                echo json_encode(['success' => true, 'data' => $shop]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get shops data
$shops = $db->query("
    SELECT s.*, 
           COUNT(u.id) as user_count,
           COALESCE(SUM(CASE WHEN DATE(sales.sale_date) = CURDATE() THEN sales.total_amount ELSE 0 END), 0) as today_sales
    FROM shops s 
    LEFT JOIN users u ON s.id = u.shop_id AND u.is_active = 1
    LEFT JOIN sales ON s.id = sales.shop_id
    GROUP BY s.id 
    ORDER BY s.name
")->fetchAll();

$stats = [
    'total_shops' => count($shops),
    'active_shops' => count(array_filter($shops, fn($s) => $s['is_active'])),
    'total_users' => array_sum(array_column($shops, 'user_count')),
    'total_sales' => array_sum(array_column($shops, 'today_sales'))
];

// Content for the page
ob_start();
?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card primary">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-store"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $stats['total_shops'] ?></h3>
                    <small class="text-muted">Total Shops</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $stats['active_shops'] ?></h3>
                    <small class="text-muted">Active Shops</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $stats['total_users'] ?></h3>
                    <small class="text-muted">Total Users</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h3 class="mb-0">Rs. <?= number_format($stats['total_sales'], 2) ?></h3>
                    <small class="text-muted">Today's Sales</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shops Table -->
<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-store me-2"></i>
            Shops Management
        </h5>
        <button class="btn btn-gradient" onclick="openShopModal()">
            <i class="fas fa-plus me-1"></i> Add New Shop
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>Shop Details</th>
                        <th>Contact Info</th>
                        <th>Users</th>
                        <th>Today's Sales</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shops as $shop): ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($shop['name']) ?></strong>
                                <span class="badge bg-primary ms-2"><?= htmlspecialchars($shop['code']) ?></span>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($shop['address'] ?: 'No address') ?></small>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($shop['phone'] ?: 'No phone') ?></div>
                            <small class="text-muted"><?= htmlspecialchars($shop['email'] ?: 'No email') ?></small>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= $shop['user_count'] ?> users</span>
                        </td>
                        <td>
                            <strong class="text-success">Rs. <?= number_format($shop['today_sales'], 2) ?></strong>
                        </td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       <?= $shop['is_active'] ? 'checked' : '' ?>
                                       onchange="toggleShop(<?= $shop['id'] ?>, this.checked)">
                                <label class="form-check-label">
                                    <?= $shop['is_active'] ? 'Active' : 'Inactive' ?>
                                </label>
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editShop(<?= $shop['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="users.php?shop_id=<?= $shop['id'] ?>" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-users"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Shop Modal -->
<div class="modal fade modal-modern" id="shopModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-store me-2"></i>
                    <span id="modalTitle">Add New Shop</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="shopForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" id="shopId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shop Name *</label>
                            <input type="text" class="form-control form-control-modern" id="shopName" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shop Code *</label>
                            <input type="text" class="form-control form-control-modern" id="shopCode" name="code" required>
                            <small class="text-muted">Unique identifier for the shop</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control form-control-modern" id="shopAddress" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control form-control-modern" id="shopPhone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control form-control-modern" id="shopEmail" name="email">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control form-control-modern" id="shopLicense" name="license_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tax Number</label>
                            <input type="text" class="form-control form-control-modern" id="shopTax" name="tax_number">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-gradient" onclick="saveShop()">
                    <i class="fas fa-save me-1"></i> Save Shop
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

    function openShopModal() {
        isEditing = false;
        document.getElementById("modalTitle").textContent = "Add New Shop";
        document.getElementById("shopForm").reset();
        document.getElementById("shopId").value = "";
        document.getElementById("shopCode").disabled = false;
        new bootstrap.Modal(document.getElementById("shopModal")).show();
    }

    function editShop(id) {
        isEditing = true;
        document.getElementById("modalTitle").textContent = "Edit Shop";
        
        const formData = new FormData();
        formData.append("action", "get_shop");
        formData.append("id", id);
        formData.append("csrf_token", "' . $_SESSION['csrf_token'] . '");
        
        fetch("", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const shop = data.data;
                document.getElementById("shopId").value = shop.id;
                document.getElementById("shopName").value = shop.name;
                document.getElementById("shopCode").value = shop.code;
                document.getElementById("shopCode").disabled = true;
                document.getElementById("shopAddress").value = shop.address || "";
                document.getElementById("shopPhone").value = shop.phone || "";
                document.getElementById("shopEmail").value = shop.email || "";
                document.getElementById("shopLicense").value = shop.license_number || "";
                document.getElementById("shopTax").value = shop.tax_number || "";
                
                new bootstrap.Modal(document.getElementById("shopModal")).show();
            } else {
                showAlert(data.message, "danger");
            }
        })
        .catch(error => {
            showAlert("Error loading shop data", "danger");
        });
    }

    function saveShop() {
        const form = document.getElementById("shopForm");
        if (!form.checkValidity()) {
            form.classList.add("was-validated");
            return;
        }
        
        const formData = new FormData(form);
        formData.append("action", isEditing ? "update_shop" : "create_shop");
        
        fetch("", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, "success");
                bootstrap.Modal.getInstance(document.getElementById("shopModal")).hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, "danger");
            }
        })
        .catch(error => {
            showAlert("Error saving shop", "danger");
        });
    }

    function toggleShop(id, status) {
        const formData = new FormData();
        formData.append("action", "toggle_shop");
        formData.append("id", id);
        formData.append("status", status ? 1 : 0);
        formData.append("csrf_token", "' . $_SESSION['csrf_token'] . '");
        
        fetch("", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, "success");
            } else {
                showAlert(data.message, "danger");
                // Revert checkbox state
                event.target.checked = !status;
            }
        })
        .catch(error => {
            showAlert("Error updating shop status", "danger");
            event.target.checked = !status;
        });
    }
</script>';

renderAdminLayout('Shops Management', $content, 'shops', '', $additionalJS);
?>
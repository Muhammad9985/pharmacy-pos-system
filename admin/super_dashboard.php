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
            
        case 'delete_shop':
            try {
                $id = (int)$_POST['id'];
                $db->query("UPDATE shops SET is_active = 0 WHERE id = ?", [$id]);
                echo json_encode(['success' => true, 'message' => 'Shop deactivated successfully!']);
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

// Get dashboard data
$stats = [
    'total_shops' => $db->query("SELECT COUNT(*) FROM shops WHERE is_active = 1")->fetchColumn(),
    'total_users' => $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_medicines' => $db->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1")->fetchColumn(),
    'today_sales' => $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn()
];

$recent_sales = $db->query("
    SELECT s.*, sh.name as shop_name, u.full_name as user_name 
    FROM sales s 
    JOIN shops sh ON s.shop_id = sh.id 
    JOIN users u ON s.user_id = u.id 
    ORDER BY s.sale_date DESC LIMIT 5
")->fetchAll();

$shops = $db->query("SELECT * FROM shops WHERE is_active = 1 ORDER BY name")->fetchAll();

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
                    <small class="text-muted">Active Shops</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
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
                    <i class="fas fa-pills"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $stats['total_medicines'] ?></h3>
                    <small class="text-muted">Medicines</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h3 class="mb-0">Rs. <?= number_format($stats['today_sales'], 2) ?></h3>
                    <small class="text-muted">Today's Sales</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Shops Management -->
    <div class="col-md-8">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-store me-2"></i>
                    Shops Management
                </h5>
                <button class="btn btn-light btn-sm" onclick="openShopModal()">
                    <i class="fas fa-plus me-1"></i> Add Shop
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>Shop Name</th>
                                <th>Code</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shops as $shop): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($shop['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($shop['address']) ?></small>
                                </td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($shop['code']) ?></span></td>
                                <td><?= htmlspecialchars($shop['phone']) ?></td>
                                <td>
                                    <span class="badge bg-success">Active</span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editShop(<?= $shop['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteShop(<?= $shop['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="col-md-4">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Recent Sales
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($recent_sales as $sale): ?>
                <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background: var(--light);">
                    <div>
                        <strong><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($sale['shop_name']) ?></small>
                    </div>
                    <div class="text-end">
                        <strong class="text-success">Rs. <?= number_format($sale['total_amount'], 2) ?></strong><br>
                        <small class="text-muted"><?= date('M j, H:i', strtotime($sale['sale_date'])) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Shop Modal -->
<div class="modal fade modal-modern" id="shopModal" tabindex="-1">
    <div class="modal-dialog">
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
                    <input type="hidden" id="shopId" name="id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shop Name *</label>
                            <input type="text" class="form-control form-control-modern" id="shopName" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shop Code *</label>
                            <input type="text" class="form-control form-control-modern" id="shopCode" name="code" required>
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
        
        fetch("", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `action=get_shop&id=${id}`
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
            }
        });
    }

    function saveShop() {
        const form = document.getElementById("shopForm");
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
        });
    }

    function deleteShop(id) {
        if (confirm("Are you sure you want to deactivate this shop?")) {
            fetch("", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: `action=delete_shop&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, "success");
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, "danger");
                }
            });
        }
    }
</script>';

renderAdminLayout('Super Dashboard', $content, 'super_dashboard', '', $additionalJS);
?>
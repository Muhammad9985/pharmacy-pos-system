<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';
require_once '../includes/centralized_functions.php';
require_once '../includes/admin_layout.php';

$auth->requireRole(['shop_admin']);
$user = $auth->getUser();
$shop_id = $user['shop_id'];

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_stock':
            try {
                $medicine_id = (int)$_POST['medicine_id'];
                $batch_number = sanitize($_POST['batch_number']);
                $supplier_id = (int)$_POST['supplier_id'];
                $manufacture_date = $_POST['manufacture_date'];
                $expiry_date = $_POST['expiry_date'];
                $purchase_price = (float)$_POST['purchase_price'];
                $quantity = (int)$_POST['quantity'];
                
                $db->query("INSERT INTO stock_batches (shop_id, medicine_id, batch_number, supplier_id, manufacture_date, expiry_date, purchase_price, base_unit_quantity, current_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                    [$shop_id, $medicine_id, $batch_number, $supplier_id, $manufacture_date, $expiry_date, $purchase_price, $quantity, $quantity]);
                
                echo json_encode(['success' => true, 'message' => 'Stock added successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_stock':
            try {
                $id = (int)$_POST['id'];
                $quantity = (int)$_POST['quantity'];
                
                $db->query("UPDATE stock_batches SET current_quantity = ? WHERE id = ? AND shop_id = ?", 
                    [$quantity, $id, $shop_id]);
                
                echo json_encode(['success' => true, 'message' => 'Stock updated successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get dashboard data
$shop = $db->query("SELECT * FROM shops WHERE id = ?", [$shop_id])->fetch();

$stats = [
    'total_stock' => $db->query("SELECT COALESCE(SUM(current_quantity), 0) FROM stock_batches WHERE shop_id = ? AND is_active = 1", [$shop_id])->fetchColumn(),
    'low_stock' => $db->query("SELECT COUNT(*) FROM stock_batches WHERE shop_id = ? AND current_quantity < 50 AND is_active = 1", [$shop_id])->fetchColumn(),
    'expiring_soon' => $db->query("SELECT COUNT(*) FROM stock_batches WHERE shop_id = ? AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND is_active = 1", [$shop_id])->fetchColumn(),
    'today_sales' => $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE shop_id = ? AND DATE(sale_date) = CURDATE()", [$shop_id])->fetchColumn()
];

$recent_sales = $db->query("
    SELECT s.*, u.full_name as user_name 
    FROM sales s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.shop_id = ? 
    ORDER BY s.sale_date DESC LIMIT 5
", [$shop_id])->fetchAll();

$low_stock_items = $db->query("
    SELECT sb.*, m.name as medicine_name, m.strength 
    FROM stock_batches sb 
    JOIN medicines m ON sb.medicine_id = m.id 
    WHERE sb.shop_id = ? AND sb.current_quantity < 50 AND sb.is_active = 1 
    ORDER BY sb.current_quantity ASC LIMIT 10
", [$shop_id])->fetchAll();

$medicines = $db->query("SELECT id, name, strength FROM medicines WHERE is_active = 1 ORDER BY name")->fetchAll();
$suppliers = $db->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name")->fetchAll();

// Content for the page
ob_start();
?>

<!-- Special Alerts -->
<?php if ($stats['low_stock'] > 0): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Low Stock Alert!</strong> You have <?= $stats['low_stock'] ?> items with low stock levels.
    <a href="#lowStockSection" class="alert-link">View Details</a>
</div>
<?php endif; ?>

<?php if ($stats['expiring_soon'] > 0): ?>
<div class="alert alert-danger">
    <i class="fas fa-calendar-times me-2"></i>
    <strong>Expiry Alert!</strong> You have <?= $stats['expiring_soon'] ?> items expiring within 30 days.
    <a href="stock.php" class="alert-link">Check Expiry</a>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card primary">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= number_format($stats['total_stock']) ?></h3>
                    <small class="text-muted">Total Stock</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $stats['low_stock'] ?></h3>
                    <small class="text-muted">Low Stock Items</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card danger">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $stats['expiring_soon'] ?></h3>
                    <small class="text-muted">Expiring Soon</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
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
    <!-- Low Stock Items -->
    <div class="col-md-6">
        <div class="content-card" id="lowStockSection">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Low Stock Items
                </h5>
                <button class="btn btn-light btn-sm" onclick="openStockModal()">
                    <i class="fas fa-plus me-1"></i> Add Stock
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_items)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-success">All Good!</h5>
                        <p class="text-muted">No low stock items found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($low_stock_items as $item): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background: var(--light);">
                        <div>
                            <strong><?= htmlspecialchars($item['medicine_name']) ?></strong>
                            <span class="badge bg-info ms-2"><?= htmlspecialchars($item['strength']) ?></span><br>
                            <small class="text-muted">Batch: <?= htmlspecialchars($item['batch_number']) ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-warning"><?= $item['current_quantity'] ?> left</span><br>
                            <button class="btn btn-sm btn-outline-primary mt-1" onclick="updateStock(<?= $item['id'] ?>, <?= $item['current_quantity'] ?>)">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="col-md-6">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Recent Sales
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_sales)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-shopping-cart text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">No Sales Yet</h5>
                        <p class="text-muted">Start making sales to see them here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_sales as $sale): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background: var(--light);">
                        <div>
                            <strong><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer') ?></strong><br>
                            <small class="text-muted">By: <?= htmlspecialchars($sale['user_name']) ?></small>
                        </div>
                        <div class="text-end">
                            <strong class="text-success">Rs. <?= number_format($sale['total_amount'], 2) ?></strong><br>
                            <small class="text-muted"><?= date('M j, H:i', strtotime($sale['sale_date'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Stock Modal -->
<div class="modal fade modal-modern" id="stockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-boxes me-2"></i>
                    Add New Stock
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Medicine *</label>
                            <select class="form-control form-control-modern" name="medicine_id" required>
                                <option value="">Select Medicine</option>
                                <?php foreach ($medicines as $medicine): ?>
                                <option value="<?= $medicine['id'] ?>"><?= htmlspecialchars($medicine['name']) ?> - <?= htmlspecialchars($medicine['strength']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier *</label>
                            <select class="form-control form-control-modern" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Number *</label>
                            <input type="text" class="form-control form-control-modern" name="batch_number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity *</label>
                            <input type="number" class="form-control form-control-modern" name="quantity" min="1" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Purchase Price *</label>
                            <input type="number" class="form-control form-control-modern" name="purchase_price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Manufacture Date *</label>
                            <input type="date" class="form-control form-control-modern" name="manufacture_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expiry Date *</label>
                            <input type="date" class="form-control form-control-modern" name="expiry_date" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-gradient" onclick="saveStock()">
                    <i class="fas fa-save me-1"></i> Add Stock
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade modal-modern" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Update Stock Quantity
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateStockForm">
                    <input type="hidden" id="updateStockId" name="id">
                    <div class="mb-3">
                        <label class="form-label">Current Quantity</label>
                        <input type="number" class="form-control form-control-modern" id="currentQuantity" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Quantity *</label>
                        <input type="number" class="form-control form-control-modern" id="newQuantity" name="quantity" min="0" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-gradient" onclick="saveStockUpdate()">
                    <i class="fas fa-save me-1"></i> Update Stock
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$additionalJS = '
<script>
    function openStockModal() {
        document.getElementById("stockForm").reset();
        new bootstrap.Modal(document.getElementById("stockModal")).show();
    }

    function saveStock() {
        const form = document.getElementById("stockForm");
        const formData = new FormData(form);
        formData.append("action", "add_stock");
        
        fetch("", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, "success");
                bootstrap.Modal.getInstance(document.getElementById("stockModal")).hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, "danger");
            }
        });
    }

    function updateStock(id, currentQty) {
        document.getElementById("updateStockId").value = id;
        document.getElementById("currentQuantity").value = currentQty;
        document.getElementById("newQuantity").value = currentQty;
        new bootstrap.Modal(document.getElementById("updateStockModal")).show();
    }

    function saveStockUpdate() {
        const form = document.getElementById("updateStockForm");
        const formData = new FormData(form);
        formData.append("action", "update_stock");
        
        fetch("", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, "success");
                bootstrap.Modal.getInstance(document.getElementById("updateStockModal")).hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, "danger");
            }
        });
    }
</script>';

renderAdminLayout($shop['name'] . ' - Dashboard', $content, 'shop_dashboard', '', $additionalJS);
?>
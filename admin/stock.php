<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';
require_once '../includes/centralized_functions.php';
require_once '../includes/admin_layout.php';

$auth->requireRole(['super_admin', 'shop_admin']);
$user = $auth->getUser();
$shop_id = $auth->isSuperAdmin() ? null : $user['shop_id'];

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
        case 'add_stock':
            try {
                $medicine_id = (int)$_POST['medicine_id'];
                $batch_number = sanitize($_POST['batch_number']);
                $supplier_id = (int)$_POST['supplier_id'];
                $manufacture_date = $_POST['manufacture_date'];
                $expiry_date = $_POST['expiry_date'];
                $purchase_price = (float)$_POST['purchase_price'];
                $quantity = (int)$_POST['quantity'];
                $target_shop_id = $shop_id ?: (int)$_POST['shop_id'];
                
                $db->query("INSERT INTO stock_batches (shop_id, medicine_id, batch_number, supplier_id, manufacture_date, expiry_date, purchase_price, base_unit_quantity, current_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                    [$target_shop_id, $medicine_id, $batch_number, $supplier_id, $manufacture_date, $expiry_date, $purchase_price, $quantity, $quantity]);
                
                echo json_encode(['success' => true, 'message' => 'Stock added successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_stock':
            try {
                $id = (int)$_POST['id'];
                $quantity = (int)$_POST['quantity'];
                
                $whereClause = $shop_id ? "AND shop_id = ?" : "";
                $params = $shop_id ? [$quantity, $id, $shop_id] : [$quantity, $id];
                
                $db->query("UPDATE stock_batches SET current_quantity = ? WHERE id = ? $whereClause", $params);
                
                echo json_encode(['success' => true, 'message' => 'Stock updated successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_stock':
            try {
                $id = (int)$_POST['id'];
                
                $whereClause = $shop_id ? "AND shop_id = ?" : "";
                $params = $shop_id ? [$id, $shop_id] : [$id];
                
                $db->query("UPDATE stock_batches SET is_active = 0 WHERE id = ? $whereClause", $params);
                
                echo json_encode(['success' => true, 'message' => 'Stock batch deactivated successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Build query based on user role
$shopFilter = $shop_id ? "AND sb.shop_id = $shop_id" : "";

$stock_batches = $db->query("
    SELECT sb.*, m.name as medicine_name, m.strength, s.name as shop_name, sup.name as supplier_name,
           DATEDIFF(sb.expiry_date, CURDATE()) as days_to_expiry
    FROM stock_batches sb 
    JOIN medicines m ON sb.medicine_id = m.id 
    JOIN shops s ON sb.shop_id = s.id 
    LEFT JOIN suppliers sup ON sb.supplier_id = sup.id 
    WHERE sb.is_active = 1 $shopFilter
    ORDER BY sb.expiry_date ASC, sb.current_quantity ASC
")->fetchAll();

$medicines = $db->query("SELECT id, name, strength FROM medicines WHERE is_active = 1 ORDER BY name")->fetchAll();
$suppliers = $db->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name")->fetchAll();
$shops = $auth->isSuperAdmin() ? $db->query("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY name")->fetchAll() : [];

// Calculate stats
$stats = [
    'total_batches' => count($stock_batches),
    'total_quantity' => array_sum(array_column($stock_batches, 'current_quantity')),
    'low_stock' => count(array_filter($stock_batches, fn($b) => $b['current_quantity'] < 50)),
    'expiring_soon' => count(array_filter($stock_batches, fn($b) => $b['days_to_expiry'] <= 30 && $b['days_to_expiry'] >= 0))
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
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $stats['total_batches'] ?></h3>
                    <small class="text-muted">Stock Batches</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-cubes"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= number_format($stats['total_quantity']) ?></h3>
                    <small class="text-muted">Total Units</small>
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
                    <small class="text-muted">Low Stock</small>
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
</div>

<!-- Stock Table -->
<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-boxes me-2"></i>
            Stock Management
        </h5>
        <button class="btn btn-gradient" onclick="openStockModal()">
            <i class="fas fa-plus me-1"></i> Add Stock Batch
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>Medicine Details</th>
                        <?php if ($auth->isSuperAdmin()): ?>
                        <th>Shop</th>
                        <?php endif; ?>
                        <th>Batch Info</th>
                        <th>Stock Level</th>
                        <th>Expiry Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock_batches as $batch): ?>
                    <tr class="<?= $batch['current_quantity'] < 50 ? 'table-warning' : '' ?> <?= $batch['days_to_expiry'] <= 30 && $batch['days_to_expiry'] >= 0 ? 'table-danger' : '' ?>">
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($batch['medicine_name']) ?></strong>
                                <span class="badge bg-info ms-2"><?= htmlspecialchars($batch['strength']) ?></span>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-industry me-1"></i>
                                <?= htmlspecialchars($batch['supplier_name'] ?: 'Unknown Supplier') ?>
                            </small>
                        </td>
                        <?php if ($auth->isSuperAdmin()): ?>
                        <td>
                            <span class="badge bg-primary"><?= htmlspecialchars($batch['shop_name']) ?></span>
                        </td>
                        <?php endif; ?>
                        <td>
                            <div>
                                <strong>Batch: <?= htmlspecialchars($batch['batch_number']) ?></strong>
                            </div>
                            <small class="text-muted">
                                Purchase: Rs. <?= number_format($batch['purchase_price'], 2) ?>
                            </small><br>
                            <small class="text-muted">
                                Mfg: <?= date('M Y', strtotime($batch['manufacture_date'])) ?>
                            </small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <strong class="<?= $batch['current_quantity'] < 50 ? 'text-warning' : 'text-success' ?>">
                                    <?= $batch['current_quantity'] ?> units
                                </strong>
                                <?php if ($batch['current_quantity'] < 50): ?>
                                    <i class="fas fa-exclamation-triangle text-warning ms-2" title="Low Stock"></i>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                Original: <?= $batch['base_unit_quantity'] ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($batch['days_to_expiry'] < 0): ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-times me-1"></i>Expired
                                </span>
                            <?php elseif ($batch['days_to_expiry'] <= 30): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?= $batch['days_to_expiry'] ?> days
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i>
                                    <?= $batch['days_to_expiry'] ?> days
                                </span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted">
                                Exp: <?= date('M j, Y', strtotime($batch['expiry_date'])) ?>
                            </small>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="updateStock(<?= $batch['id'] ?>, <?= $batch['current_quantity'] ?>)" title="Update Quantity">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteStock(<?= $batch['id'] ?>)" title="Deactivate Batch">
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

<!-- Add Stock Modal -->
<div class="modal fade modal-modern" id="stockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-boxes me-2"></i>
                    Add New Stock Batch
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Medicine *</label>
                            <select class="form-control form-control-modern" name="medicine_id" required>
                                <option value="">Select Medicine</option>
                                <?php foreach ($medicines as $medicine): ?>
                                <option value="<?= $medicine['id'] ?>">
                                    <?= htmlspecialchars($medicine['name']) ?> - <?= htmlspecialchars($medicine['strength']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($auth->isSuperAdmin()): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shop *</label>
                            <select class="form-control form-control-modern" name="shop_id" required>
                                <option value="">Select Shop</option>
                                <?php foreach ($shops as $shop): ?>
                                <option value="<?= $shop['id'] ?>"><?= htmlspecialchars($shop['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
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
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
        if (!form.checkValidity()) {
            form.classList.add("was-validated");
            return;
        }
        
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
        })
        .catch(error => {
            showAlert("Error adding stock", "danger");
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
        if (!form.checkValidity()) {
            form.classList.add("was-validated");
            return;
        }
        
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
        })
        .catch(error => {
            showAlert("Error updating stock", "danger");
        });
    }

    function deleteStock(id) {
        if (confirm("Are you sure you want to deactivate this stock batch?")) {
            const formData = new FormData();
            formData.append("action", "delete_stock");
            formData.append("id", id);
            formData.append("csrf_token", "' . $_SESSION['csrf_token'] . '");
            
            fetch("", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, "success");
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, "danger");
                }
            })
            .catch(error => {
                showAlert("Error deleting stock", "danger");
            });
        }
    }
</script>';

renderAdminLayout('Stock Management', $content, 'stock', '', $additionalJS);
?>
<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';
require_once '../includes/centralized_functions.php';
require_once '../includes/admin_layout.php';

$auth->requireRole(['super_admin', 'shop_admin']);

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
        case 'create_medicine':
            try {
                $name = sanitize($_POST['name']);
                $generic_name = sanitize($_POST['generic_name']);
                $brand = sanitize($_POST['brand']);
                $category_id = (int)$_POST['category_id'];
                $manufacturer = sanitize($_POST['manufacturer']);
                $strength = sanitize($_POST['strength']);
                $base_unit_id = (int)$_POST['base_unit_id'];
                $barcode = sanitize($_POST['barcode']);
                $drap_registration = sanitize($_POST['drap_registration']);
                $is_prescription = isset($_POST['is_prescription']) ? 1 : 0;
                
                $db->query("INSERT INTO medicines (name, generic_name, brand, category_id, manufacturer, strength, base_unit_id, barcode, drap_registration, is_prescription) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                    [$name, $generic_name, $brand, $category_id, $manufacturer, $strength, $base_unit_id, $barcode, $drap_registration, $is_prescription]);
                
                echo json_encode(['success' => true, 'message' => 'Medicine created successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_medicine':
            try {
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                $generic_name = sanitize($_POST['generic_name']);
                $brand = sanitize($_POST['brand']);
                $category_id = (int)$_POST['category_id'];
                $manufacturer = sanitize($_POST['manufacturer']);
                $strength = sanitize($_POST['strength']);
                $barcode = sanitize($_POST['barcode']);
                $drap_registration = sanitize($_POST['drap_registration']);
                $is_prescription = isset($_POST['is_prescription']) ? 1 : 0;
                
                $db->query("UPDATE medicines SET name=?, generic_name=?, brand=?, category_id=?, manufacturer=?, strength=?, barcode=?, drap_registration=?, is_prescription=? WHERE id=?", 
                    [$name, $generic_name, $brand, $category_id, $manufacturer, $strength, $barcode, $drap_registration, $is_prescription, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Medicine updated successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_medicine':
            try {
                $id = (int)$_POST['id'];
                $db->query("UPDATE medicines SET is_active = 0 WHERE id = ?", [$id]);
                echo json_encode(['success' => true, 'message' => 'Medicine deactivated successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_medicine':
            try {
                $id = (int)$_POST['id'];
                $medicine = $db->query("SELECT * FROM medicines WHERE id = ?", [$id])->fetch();
                echo json_encode(['success' => true, 'data' => $medicine]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

$medicines = $db->query("
    SELECT m.*, c.name as category_name, u.display_name as unit_name 
    FROM medicines m 
    LEFT JOIN categories c ON m.category_id = c.id 
    LEFT JOIN medicine_units u ON m.base_unit_id = u.id 
    WHERE m.is_active = 1 
    ORDER BY m.name
")->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$units = $db->query("SELECT * FROM medicine_units ORDER BY display_name")->fetchAll();

// Content for the page
ob_start();
?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card primary">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-pills"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= count($medicines) ?></h3>
                    <small class="text-muted">Total Medicines</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-prescription-bottle-alt"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= count(array_filter($medicines, fn($m) => $m['is_prescription'])) ?></h3>
                    <small class="text-muted">Prescription</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= count(array_filter($medicines, fn($m) => !$m['is_prescription'])) ?></h3>
                    <small class="text-muted">OTC Medicines</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-industry"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= count(array_unique(array_column($medicines, 'manufacturer'))) ?></h3>
                    <small class="text-muted">Manufacturers</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Medicines Table -->
<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-pills me-2"></i>
            Medicines Management
        </h5>
        <button class="btn btn-gradient" onclick="openMedicineModal()">
            <i class="fas fa-plus me-1"></i> Add New Medicine
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>Medicine Details</th>
                        <th>Category & Brand</th>
                        <th>Strength & Unit</th>
                        <th>Type</th>
                        <th>DRAP</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicines as $medicine): ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($medicine['name']) ?></strong>
                                <?php if ($medicine['barcode']): ?>
                                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($medicine['barcode']) ?></span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                <?= htmlspecialchars($medicine['generic_name'] ?: 'No generic name') ?>
                            </small><br>
                            <small class="text-muted">
                                <i class="fas fa-industry me-1"></i>
                                <?= htmlspecialchars($medicine['manufacturer'] ?: 'Unknown') ?>
                            </small>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($medicine['category_name'] ?: 'Uncategorized') ?></div>
                            <small class="text-muted"><?= htmlspecialchars($medicine['brand'] ?: 'Generic') ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($medicine['strength'] ?: 'N/A') ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($medicine['unit_name'] ?: 'No unit') ?></small>
                        </td>
                        <td>
                            <?php if ($medicine['is_prescription']): ?>
                                <span class="badge bg-warning"><i class="fas fa-prescription-bottle-alt me-1"></i>Prescription</span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="fas fa-shopping-cart me-1"></i>OTC</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($medicine['drap_registration']): ?>
                                <span class="badge bg-info"><?= htmlspecialchars($medicine['drap_registration']) ?></span>
                            <?php else: ?>
                                <small class="text-muted">Not registered</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editMedicine(<?= $medicine['id'] ?>)" title="Edit Medicine">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteMedicine(<?= $medicine['id'] ?>)" title="Deactivate Medicine">
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

<!-- Medicine Modal -->
<div class="modal fade modal-modern" id="medicineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-pills me-2"></i>
                    <span id="modalTitle">Add New Medicine</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="medicineForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" id="medicineId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Medicine Name *</label>
                            <input type="text" class="form-control form-control-modern" id="medicineName" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Generic Name</label>
                            <input type="text" class="form-control form-control-modern" id="genericName" name="generic_name">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" class="form-control form-control-modern" id="brand" name="brand">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-control form-control-modern" id="categoryId" name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" class="form-control form-control-modern" id="manufacturer" name="manufacturer">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Strength</label>
                            <input type="text" class="form-control form-control-modern" id="strength" name="strength" placeholder="e.g., 500mg, 10ml">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Base Unit *</label>
                            <select class="form-control form-control-modern" id="baseUnitId" name="base_unit_id" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $unit): ?>
                                <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control form-control-modern" id="barcode" name="barcode">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DRAP Registration</label>
                            <input type="text" class="form-control form-control-modern" id="drapRegistration" name="drap_registration">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="isPrescription" name="is_prescription">
                                <label class="form-check-label" for="isPrescription">
                                    <i class="fas fa-prescription-bottle-alt me-1"></i>
                                    Prescription Medicine
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-gradient" onclick="saveMedicine()">
                    <i class="fas fa-save me-1"></i> Save Medicine
                </button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$additionalCSS = '';

$additionalJS = '<script>
    let isEditing = false;
    const CSRF_TOKEN = ' . json_encode($_SESSION['csrf_token']) . ';

    function openMedicineModal() {
        isEditing = false;
        document.getElementById("modalTitle").textContent = "Add New Medicine";
        document.getElementById("medicineForm").reset();
        document.getElementById("medicineId").value = "";
        new bootstrap.Modal(document.getElementById("medicineModal")).show();
    }

    function editMedicine(id) {
        isEditing = true;
        document.getElementById("modalTitle").textContent = "Edit Medicine";
        
        fetch("", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `action=get_medicine&id=${id}&csrf_token=${CSRF_TOKEN}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const medicine = data.data;
                document.getElementById("medicineId").value = medicine.id;
                document.getElementById("medicineName").value = medicine.name;
                document.getElementById("genericName").value = medicine.generic_name || "";
                document.getElementById("brand").value = medicine.brand || "";
                document.getElementById("categoryId").value = medicine.category_id || "";
                document.getElementById("manufacturer").value = medicine.manufacturer || "";
                document.getElementById("strength").value = medicine.strength || "";
                document.getElementById("baseUnitId").value = medicine.base_unit_id || "";
                document.getElementById("barcode").value = medicine.barcode || "";
                document.getElementById("drapRegistration").value = medicine.drap_registration || "";
                document.getElementById("isPrescription").checked = medicine.is_prescription == 1;
                
                new bootstrap.Modal(document.getElementById("medicineModal")).show();
            }
        });
    }

    function saveMedicine() {
        const form = document.getElementById("medicineForm");
        const formData = new FormData(form);
        formData.append("action", isEditing ? "update_medicine" : "create_medicine");
        
        fetch("", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, "success");
                bootstrap.Modal.getInstance(document.getElementById("medicineModal")).hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, "danger");
            }
        });
    }

    function deleteMedicine(id) {
        if (confirm("Are you sure you want to deactivate this medicine?")) {
            fetch("", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: `action=delete_medicine&id=${id}&csrf_token=${CSRF_TOKEN}`
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

renderAdminLayout('Medicines Management', $content, 'medicines', $additionalCSS, $additionalJS);
?>
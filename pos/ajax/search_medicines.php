<?php
require_once '../../config/centralized_db.php';
require_once '../../includes/centralized_auth.php';
require_once '../../includes/centralized_functions.php';

$auth->requirePermission('pos_operations');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = sanitize($_POST['query']);
    $shop_id = $_POST['shop_id'] ?? null;
    
    $sql = "
        SELECT sb.id as batch_id, sb.batch_number, sb.current_quantity, sb.expiry_date,
               m.id as medicine_id, m.name, m.generic_name, m.brand, m.strength,
               mu.display_name as base_unit,
               CASE 
                   WHEN sb.expiry_date < CURDATE() THEN 'expired'
                   WHEN sb.current_quantity <= 0 THEN 'out_of_stock'
                   ELSE 'available'
               END as status
        FROM stock_batches sb
        JOIN medicines m ON sb.medicine_id = m.id
        JOIN medicine_units mu ON m.base_unit_id = mu.id
        WHERE sb.is_active = 1 
        AND m.is_active = 1
        AND sb.current_quantity > 0
        AND sb.expiry_date > CURDATE()
        AND (
            m.name LIKE ? OR 
            m.generic_name LIKE ? OR
            m.brand LIKE ? OR 
            m.barcode LIKE ?
        )
    ";
    
    $params = ["%$query%", "%$query%", "%$query%", "%$query%"];
    
    if ($shop_id) {
        $sql .= " AND sb.shop_id = ?";
        $params[] = $shop_id;
    }
    
    $sql .= " ORDER BY m.name, sb.expiry_date ASC LIMIT 20";
    
    $medicines = $db->fetchAll($sql, $params);
    
    if (empty($medicines)) {
        echo '<div class="alert alert-warning">No medicines found matching your search.</div>';
        exit;
    }
    
    echo '<div class="row">';
    foreach ($medicines as $medicine) {
        $stockBadge = $medicine['current_quantity'] <= 10 ? 'bg-warning text-dark' : 'bg-success';
        
        echo '<div class="col-md-6 mb-3">';
        echo '<div class="medicine-item" onclick="selectMedicine(' . htmlspecialchars(json_encode($medicine)) . ')">';
        
        echo '<div class="d-flex justify-content-between align-items-start">';
        echo '<div class="flex-grow-1">';
        echo '<h6 class="mb-1">' . htmlspecialchars($medicine['name']) . '</h6>';
        
        if ($medicine['generic_name']) {
            echo '<small class="text-muted">Generic: ' . htmlspecialchars($medicine['generic_name']) . '</small><br>';
        }
        
        if ($medicine['brand']) {
            echo '<small class="text-info">Brand: ' . htmlspecialchars($medicine['brand']) . '</small><br>';
        }
        
        echo '<small class="text-muted">Strength: ' . htmlspecialchars($medicine['strength']) . '</small><br>';
        echo '<small class="text-muted">Unit: ' . htmlspecialchars($medicine['base_unit']) . '</small><br>';
        echo '<small class="text-muted">Batch: ' . htmlspecialchars($medicine['batch_number']) . '</small><br>';
        echo '<small class="text-muted">Expiry: ' . formatDate($medicine['expiry_date']) . '</small>';
        echo '</div>';
        
        echo '<div class="text-end">';
        echo '<span class="badge ' . $stockBadge . '">' . $medicine['current_quantity'] . ' available</span>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="alert alert-danger">Invalid request</div>';
}
?>
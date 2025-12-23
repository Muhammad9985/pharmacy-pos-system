<?php
require_once '../../config/centralized_db.php';
require_once '../../includes/centralized_auth.php';

$auth->requirePermission('pos_operations');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['medicine_id'])) {
    $medicine_id = (int)$_POST['medicine_id'];
    
    // Get base unit
    $base_unit = $db->fetch("
        SELECT mu.id, mu.name, mu.display_name, 50 as price
        FROM medicines m
        JOIN medicine_units mu ON m.base_unit_id = mu.id
        WHERE m.id = ?
    ", [$medicine_id]);
    
    $units = [];
    if ($base_unit) {
        $units[] = [
            'id' => $base_unit['id'],
            'name' => $base_unit['display_name'],
            'price' => $base_unit['price']
        ];
    }
    
    // Get unit conversions
    $conversions = $db->fetchAll("
        SELECT uc.to_unit_id as id, mu.display_name as name, uc.selling_price as price
        FROM unit_conversions uc
        JOIN medicine_units mu ON uc.to_unit_id = mu.id
        WHERE uc.medicine_id = ? AND uc.is_active = 1
    ", [$medicine_id]);
    
    foreach ($conversions as $conversion) {
        $units[] = [
            'id' => $conversion['id'],
            'name' => $conversion['name'],
            'price' => $conversion['price']
        ];
    }
    
    echo json_encode($units);
} else {
    echo json_encode([]);
}
?>
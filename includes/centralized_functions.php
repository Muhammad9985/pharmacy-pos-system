<?php
// Centralized Pharmacy Utility Functions

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount, $currency = 'PKR') {
    return $currency . ' ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d-m-Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d-m-Y H:i', strtotime($datetime));
}

function generateInvoiceNumber($shopCode = 'MAIN') {
    return $shopCode . date('Ymd') . rand(1000, 9999);
}

function isExpired($expiryDate) {
    return strtotime($expiryDate) < time();
}

function isExpiringSoon($expiryDate, $days = 30) {
    return strtotime($expiryDate) < strtotime("+$days days");
}

function getStockStatus($quantity, $threshold = 10) {
    if ($quantity <= 0) return 'out_of_stock';
    if ($quantity <= $threshold) return 'low_stock';
    return 'in_stock';
}

function logActivity($db, $userId, $action, $module, $recordId = null, $shopId = null, $oldValues = null, $newValues = null) {
    $db->query("
        INSERT INTO activity_logs (user_id, shop_id, action, module, record_id, old_values, new_values, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $userId, 
        $shopId, 
        $action, 
        $module, 
        $recordId,
        $oldValues ? json_encode($oldValues) : null,
        $newValues ? json_encode($newValues) : null,
        $_SERVER['REMOTE_ADDR'] ?? '', 
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

function getSetting($db, $key, $default = '', $shopId = null) {
    $result = $db->fetch("SELECT setting_value FROM settings WHERE setting_key = ? AND shop_id = ?", [$key, $shopId]);
    if (!$result && $shopId !== null) {
        // Fallback to global setting
        $result = $db->fetch("SELECT setting_value FROM settings WHERE setting_key = ? AND shop_id IS NULL", [$key]);
    }
    return $result ? $result['setting_value'] : $default;
}

function updateSetting($db, $key, $value, $shopId = null) {
    $db->query("
        INSERT INTO settings (shop_id, setting_key, setting_value) VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?
    ", [$shopId, $key, $value, $value]);
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function validateRequired($fields, $data) {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function convertUnits($quantity, $fromUnitId, $toUnitId, $db, $medicineId) {
    if ($fromUnitId === $toUnitId) return $quantity;
    
    // Get conversion factor
    $conversion = $db->fetch("
        SELECT conversion_factor FROM unit_conversions 
        WHERE medicine_id = ? AND from_unit_id = ? AND to_unit_id = ? AND is_active = 1
    ", [$medicineId, $fromUnitId, $toUnitId]);
    
    if ($conversion) {
        return $quantity * $conversion['conversion_factor'];
    }
    
    return $quantity; // No conversion found, return as is
}

function getUnitPrice($medicineId, $unitId, $db) {
    $price = $db->fetch("
        SELECT selling_price FROM unit_conversions 
        WHERE medicine_id = ? AND to_unit_id = ? AND is_active = 1
    ", [$medicineId, $unitId]);
    
    return $price ? $price['selling_price'] : 0;
}

function calculateTax($amount, $taxRate = 17) {
    return ($amount * $taxRate) / 100;
}

function generateBarcode($length = 13) {
    $barcode = '';
    for ($i = 0; $i < $length; $i++) {
        $barcode .= rand(0, 9);
    }
    return $barcode;
}

function formatPhoneNumber($phone) {
    // Format Pakistani phone numbers
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 11 && substr($phone, 0, 2) === '03') {
        return '+92-' . substr($phone, 1, 2) . '-' . substr($phone, 3);
    }
    return $phone;
}

function validateCNIC($cnic) {
    // Pakistani CNIC validation
    $cnic = preg_replace('/[^0-9]/', '', $cnic);
    return strlen($cnic) === 13;
}

function getShopStock($db, $shopId, $medicineId = null) {
    $sql = "
        SELECT sb.*, m.name as medicine_name, m.strength, mu.display_name as unit_name
        FROM stock_batches sb
        JOIN medicines m ON sb.medicine_id = m.id
        JOIN medicine_units mu ON m.base_unit_id = mu.id
        WHERE sb.shop_id = ? AND sb.is_active = 1 AND sb.current_quantity > 0
    ";
    $params = [$shopId];
    
    if ($medicineId) {
        $sql .= " AND sb.medicine_id = ?";
        $params[] = $medicineId;
    }
    
    $sql .= " ORDER BY sb.expiry_date ASC";
    
    return $db->fetchAll($sql, $params);
}

function checkStockAvailability($db, $shopId, $medicineId, $requiredQuantity) {
    $totalStock = $db->fetch("
        SELECT SUM(current_quantity) as total 
        FROM stock_batches 
        WHERE shop_id = ? AND medicine_id = ? AND is_active = 1 AND expiry_date > CURDATE()
    ", [$shopId, $medicineId]);
    
    return ($totalStock['total'] ?? 0) >= $requiredQuantity;
}

function getExpiredStock($db, $shopId = null) {
    $sql = "
        SELECT sb.*, m.name as medicine_name, s.name as shop_name
        FROM stock_batches sb
        JOIN medicines m ON sb.medicine_id = m.id
        JOIN shops s ON sb.shop_id = s.id
        WHERE sb.expiry_date < CURDATE() AND sb.current_quantity > 0 AND sb.is_active = 1
    ";
    $params = [];
    
    if ($shopId) {
        $sql .= " AND sb.shop_id = ?";
        $params[] = $shopId;
    }
    
    return $db->fetchAll($sql, $params);
}

function getLowStock($db, $shopId = null, $threshold = 10) {
    $sql = "
        SELECT sb.*, m.name as medicine_name, s.name as shop_name
        FROM stock_batches sb
        JOIN medicines m ON sb.medicine_id = m.id
        JOIN shops s ON sb.shop_id = s.id
        WHERE sb.current_quantity <= ? AND sb.current_quantity > 0 AND sb.is_active = 1
    ";
    $params = [$threshold];
    
    if ($shopId) {
        $sql .= " AND sb.shop_id = ?";
        $params[] = $shopId;
    }
    
    return $db->fetchAll($sql, $params);
}
?>
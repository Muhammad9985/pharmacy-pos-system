<?php
require_once '../../config/centralized_db.php';
require_once '../../includes/centralized_auth.php';
require_once '../../includes/centralized_functions.php';

$auth->requirePermission('pos_operations');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sale_data'])) {
    try {
        $saleData = json_decode($_POST['sale_data'], true);
        
        if (empty($saleData['items'])) {
            throw new Exception('No items in cart');
        }
        
        $shop_id = $saleData['shop_id'] ?? $_SESSION['shop_id'];
        if (!$shop_id) {
            throw new Exception('Shop not specified');
        }
        
        $db->beginTransaction();
        
        // Calculate totals
        $subtotal = 0;
        foreach ($saleData['items'] as $item) {
            $subtotal += $item['total'];
        }
        
        $taxRate = 0.17; // 17% tax
        $taxAmount = $subtotal * $taxRate;
        $discountAmount = $saleData['discount_amount'] ?? 0;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        
        // Generate invoice number
        $shop_code = $db->fetch("SELECT code FROM shops WHERE id = ?", [$shop_id])['code'] ?? 'SHOP';
        $invoiceNumber = generateInvoiceNumber($shop_code);
        
        // Insert sale record
        $db->query("
            INSERT INTO sales (invoice_number, shop_id, user_id, customer_name, customer_phone, customer_cnic, subtotal, tax_amount, discount_amount, total_amount, payment_method) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $invoiceNumber,
            $shop_id,
            $_SESSION['user_id'],
            $saleData['customer_name'] ?? null,
            $saleData['customer_phone'] ?? null,
            $saleData['customer_cnic'] ?? null,
            $subtotal,
            $taxAmount,
            $discountAmount,
            $totalAmount,
            $saleData['payment_method'] ?? 'cash'
        ]);
        
        $saleId = $db->lastInsertId();
        
        // Insert sale items and update stock
        foreach ($saleData['items'] as $item) {
            // Check stock availability
            $batch = $db->fetch("SELECT current_quantity FROM stock_batches WHERE id = ?", [$item['batchId']]);
            
            if (!$batch || $batch['current_quantity'] < $item['quantity']) {
                throw new Exception('Insufficient stock for ' . $item['medicineName']);
            }
            
            // Insert sale item
            $db->query("
                INSERT INTO sale_items (sale_id, batch_id, unit_id, quantity_in_unit, base_unit_quantity, unit_price, total_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $saleId, 
                $item['batchId'], 
                $item['unitId'], 
                $item['quantity'], 
                $item['quantity'], // Assuming 1:1 conversion for now
                $item['unitPrice'], 
                $item['total']
            ]);
            
            // Update stock
            $db->query("UPDATE stock_batches SET current_quantity = current_quantity - ? WHERE id = ?", 
                [$item['quantity'], $item['batchId']]);
        }
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'sale_completed', 'sales', $saleId, $shop_id);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'invoice_number' => $invoiceNumber,
            'sale_id' => $saleId,
            'total_amount' => $totalAmount
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>
<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';
require_once '../includes/centralized_functions.php';

$auth->requireRole(['super_admin', 'shop_admin']);
$user = $auth->getUser();
$shop_id = $auth->isSuperAdmin() ? null : $user['shop_id'];

// Get parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$selected_shop = $_GET['shop_id'] ?? '';
$export_type = $_GET['type'] ?? 'sales';

// Build shop filter
$shopFilter = '';
$shopParams = [];
if ($shop_id) {
    $shopFilter = "AND s.shop_id = ?";
    $shopParams[] = $shop_id;
} elseif ($selected_shop) {
    $shopFilter = "AND s.shop_id = ?";
    $shopParams[] = $selected_shop;
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $export_type . '_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

switch ($export_type) {
    case 'sales':
        // Export sales data
        fputcsv($output, ['Date', 'Invoice No', 'Customer', 'Shop', 'User', 'Items', 'Total Amount', 'Payment Method']);
        
        $sales = $db->query("
            SELECT 
                s.sale_date,
                s.invoice_number,
                s.customer_name,
                sh.name as shop_name,
                u.full_name as user_name,
                COUNT(si.id) as item_count,
                s.total_amount,
                s.payment_method
            FROM sales s
            JOIN shops sh ON s.shop_id = sh.id
            JOIN users u ON s.user_id = u.id
            LEFT JOIN sale_items si ON s.id = si.sale_id
            WHERE DATE(s.sale_date) BETWEEN ? AND ? $shopFilter
            GROUP BY s.id
            ORDER BY s.sale_date DESC
        ", array_merge([$start_date, $end_date], $shopParams))->fetchAll();
        
        foreach ($sales as $sale) {
            fputcsv($output, [
                $sale['sale_date'],
                $sale['invoice_number'],
                $sale['customer_name'] ?: 'Walk-in',
                $sale['shop_name'],
                $sale['user_name'],
                $sale['item_count'],
                $sale['total_amount'],
                $sale['payment_method']
            ]);
        }
        break;
        
    case 'medicines':
        // Export top medicines data
        fputcsv($output, ['Medicine Name', 'Strength', 'Quantity Sold', 'Revenue', 'Sales Count']);
        
        $medicines = $db->query("
            SELECT 
                m.name as medicine_name,
                m.strength,
                SUM(si.quantity) as total_quantity,
                SUM(si.subtotal) as total_revenue,
                COUNT(DISTINCT s.id) as sales_count
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            JOIN medicines m ON si.medicine_id = m.id
            WHERE DATE(s.sale_date) BETWEEN ? AND ? $shopFilter
            GROUP BY si.medicine_id
            ORDER BY total_revenue DESC
        ", array_merge([$start_date, $end_date], $shopParams))->fetchAll();
        
        foreach ($medicines as $medicine) {
            fputcsv($output, [
                $medicine['medicine_name'],
                $medicine['strength'],
                $medicine['total_quantity'],
                $medicine['total_revenue'],
                $medicine['sales_count']
            ]);
        }
        break;
        
    case 'stock':
        // Export stock data
        $stock_filter = $shop_id ? "AND sb.shop_id = $shop_id" : "";
        
        fputcsv($output, ['Medicine', 'Strength', 'Shop', 'Batch', 'Current Stock', 'Purchase Price', 'Expiry Date', 'Days to Expiry', 'Supplier']);
        
        $stock = $db->query("
            SELECT 
                m.name as medicine_name,
                m.strength,
                sh.name as shop_name,
                sb.batch_number,
                sb.current_quantity,
                sb.purchase_price,
                sb.expiry_date,
                DATEDIFF(sb.expiry_date, CURDATE()) as days_to_expiry,
                sup.name as supplier_name
            FROM stock_batches sb
            JOIN medicines m ON sb.medicine_id = m.id
            JOIN shops sh ON sb.shop_id = sh.id
            LEFT JOIN suppliers sup ON sb.supplier_id = sup.id
            WHERE sb.is_active = 1 $stock_filter
            ORDER BY sb.expiry_date ASC
        ")->fetchAll();
        
        foreach ($stock as $item) {
            fputcsv($output, [
                $item['medicine_name'],
                $item['strength'],
                $item['shop_name'],
                $item['batch_number'],
                $item['current_quantity'],
                $item['purchase_price'],
                $item['expiry_date'],
                $item['days_to_expiry'],
                $item['supplier_name']
            ]);
        }
        break;
}

fclose($output);
exit;
?>
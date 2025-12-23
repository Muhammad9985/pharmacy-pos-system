<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';
require_once '../includes/centralized_functions.php';

$auth->requirePermission('pos_operations');

if (!isset($_GET['invoice'])) {
    die('Invoice number required');
}

$invoiceNumber = sanitize($_GET['invoice']);

// Get sale details
$sale = $db->fetch("
    SELECT s.*, u.full_name as cashier_name, sh.name as shop_name, sh.address as shop_address, sh.phone as shop_phone
    FROM sales s
    JOIN users u ON s.user_id = u.id
    JOIN shops sh ON s.shop_id = sh.id
    WHERE s.invoice_number = ?
", [$invoiceNumber]);

if (!$sale) {
    die('Invoice not found');
}

// Get sale items
$items = $db->fetchAll("
    SELECT si.*, 
           m.name as medicine_name, m.brand, m.strength,
           mu.display_name as unit_name,
           b.batch_number
    FROM sale_items si
    JOIN stock_batches b ON si.batch_id = b.id
    JOIN medicines m ON b.medicine_id = m.id
    JOIN medicine_units mu ON si.unit_id = mu.id
    WHERE si.sale_id = ?
", [$sale['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= $invoiceNumber ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
        }
        .invoice-header { border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 20px; }
        .invoice-footer { border-top: 1px solid #dee2e6; padding-top: 20px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row no-print mb-3">
            <div class="col-12 text-end">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <button class="btn btn-secondary" onclick="window.close()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
        
        <div class="invoice-header">
            <div class="row">
                <div class="col-md-6">
                    <h2 class="text-primary"><?= $sale['shop_name'] ?></h2>
                    <p class="mb-1"><?= $sale['shop_address'] ?></p>
                    <p class="mb-1">Phone: <?= $sale['shop_phone'] ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <h4>INVOICE</h4>
                    <p class="mb-1"><strong>Invoice #:</strong> <?= $sale['invoice_number'] ?></p>
                    <p class="mb-1"><strong>Date:</strong> <?= formatDateTime($sale['sale_date']) ?></p>
                    <p class="mb-1"><strong>Cashier:</strong> <?= $sale['cashier_name'] ?></p>
                </div>
            </div>
        </div>
        
        <?php if ($sale['customer_name']): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <h6>Bill To:</h6>
                <p class="mb-1"><strong><?= $sale['customer_name'] ?></strong></p>
                <?php if ($sale['customer_phone']): ?>
                    <p class="mb-1">Phone: <?= $sale['customer_phone'] ?></p>
                <?php endif; ?>
                <?php if ($sale['customer_cnic']): ?>
                    <p class="mb-1">CNIC: <?= $sale['customer_cnic'] ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Medicine</th>
                        <th>Strength</th>
                        <th>Unit</th>
                        <th>Batch</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= $item['medicine_name'] ?></strong>
                                <?php if ($item['brand']): ?>
                                    <br><small class="text-muted"><?= $item['brand'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['strength'] ?></td>
                            <td><?= $item['unit_name'] ?></td>
                            <td><?= $item['batch_number'] ?></td>
                            <td class="text-center"><?= $item['quantity_in_unit'] ?></td>
                            <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                            <td class="text-end"><?= formatCurrency($item['total_price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <p><strong>Payment Method:</strong> <?= ucfirst($sale['payment_method']) ?></p>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <td class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end"><?= formatCurrency($sale['subtotal']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-end"><strong>Tax (17%):</strong></td>
                        <td class="text-end"><?= formatCurrency($sale['tax_amount']) ?></td>
                    </tr>
                    <?php if ($sale['discount_amount'] > 0): ?>
                    <tr>
                        <td class="text-end"><strong>Discount:</strong></td>
                        <td class="text-end">-<?= formatCurrency($sale['discount_amount']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-primary">
                        <td class="text-end"><strong>Total Amount:</strong></td>
                        <td class="text-end"><strong><?= formatCurrency($sale['total_amount']) ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="invoice-footer text-center">
            <p class="mb-1"><strong>Thank you for your business!</strong></p>
            <p class="text-muted">Please check your medicines before leaving the store.</p>
            <p class="text-muted">For any queries, please contact us at <?= $sale['shop_phone'] ?></p>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
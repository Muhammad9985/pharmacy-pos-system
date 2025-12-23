<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';
require_once '../includes/centralized_functions.php';

$auth->requirePermission('pos_operations');

$user_shop_id = $auth->getUserShopId();
$shop_info = null;

if ($user_shop_id) {
    $shop_info = $db->fetch("SELECT * FROM shops WHERE id = ?", [$user_shop_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .cart-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            min-height: 600px;
        }
        
        .medicine-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .medicine-item:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.15);
        }
        
        .cart-item {
            border-bottom: 1px solid #e9ecef;
            padding: 12px 0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .total-section {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
            border: 1px solid #e9ecef;
        }
        
        .btn-primary {
            background: #007bff;
            border-color: #007bff;
        }
        
        .btn-success {
            background: #28a745;
            border-color: #28a745;
        }
        
        .search-input {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 12px 15px;
            font-size: 16px;
        }
        
        .search-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .unit-selector {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
            border: 1px solid #e9ecef;
        }
        
        .unit-option {
            display: inline-block;
            margin: 3px;
            padding: 8px 12px;
            background: white;
            border: 1px solid #ced4da;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .unit-option:hover {
            border-color: #007bff;
        }
        
        .unit-option.selected {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .modal-header {
            background: #007bff;
            color: white;
        }
        
        .btn {
            border-radius: 6px;
        }
        
        .form-control {
            border-radius: 6px;
        }
        
        .badge {
            font-size: 11px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0"><i class="fas fa-cash-register me-2"></i>POS System</h5>
                    <?php if ($shop_info): ?>
                        <small><?= htmlspecialchars($shop_info['name']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-end">
                    <span class="me-3"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                    <?php if ($auth->hasPermission('manage_medicines')): ?>
                        <a href="../admin/<?= $auth->isSuperAdmin() ? 'super_dashboard.php' : 'shop_dashboard.php' ?>" class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                    <?php endif; ?>
                    <a href="../admin/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid mt-3">
        <div class="row">
            <!-- Search Section -->
            <div class="col-lg-8">
                <div class="search-section">
                    <div class="row">
                        <div class="col-md-9">
                            <input type="text" id="medicineSearch" class="form-control search-input" 
                                   placeholder="Search medicines by name, brand, or barcode..." autofocus>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" onclick="searchMedicines()" style="padding: 12px;">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="searchResults"></div>
            </div>
            
            <!-- Cart Section -->
            <div class="col-lg-4">
                <div class="cart-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Cart</h6>
                        <button class="btn btn-outline-danger btn-sm" onclick="clearCart()">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                    </div>
                    
                    <div id="cartItems" style="max-height: 300px; overflow-y: auto;">
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                            <p>Cart is empty</p>
                        </div>
                    </div>
                    
                    <div class="total-section">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="subtotal">Rs. 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (17%):</span>
                            <span id="taxAmount">Rs. 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Discount:</span>
                            <input type="number" id="discountAmount" class="form-control form-control-sm d-inline-block" 
                                   style="width: 80px;" value="0" onchange="updateTotals()">
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong id="totalAmount">Rs. 0.00</strong>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-6">
                                <select id="paymentMethod" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-success w-100" onclick="processSale()" id="checkoutBtn" disabled>
                                    <i class="fas fa-check"></i> Checkout
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Medicine Modal -->
    <div class="modal fade" id="medicineModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Add to Cart</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="medicineDetails"></div>
                    <div id="unitSelector" class="unit-selector">
                        <label class="form-label">Select Unit:</label>
                        <div id="unitOptions"></div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-4">
                            <label class="form-label">Quantity</label>
                            <input type="number" id="quantity" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Unit Price</label>
                            <input type="number" id="unitPrice" class="form-control" readonly>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Total</label>
                            <input type="number" id="itemTotal" class="form-control" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addToCart()">Add to Cart</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
        let cart = [];
        let selectedMedicine = null;
        let selectedUnit = null;
        
        function searchMedicines() {
            const query = $('#medicineSearch').val().trim();
            if (query.length < 2) {
                $('#searchResults').html('<div class="alert alert-info">Please enter at least 2 characters</div>');
                return;
            }
            
            $.ajax({
                url: 'ajax/search_medicines.php',
                method: 'POST',
                data: { query: query, shop_id: <?= $user_shop_id ?: 'null' ?> },
                success: function(response) {
                    $('#searchResults').html(response);
                },
                error: function() {
                    $('#searchResults').html('<div class="alert alert-danger">Error searching medicines</div>');
                }
            });
        }
        
        $('#medicineSearch').on('input', function() {
            clearTimeout(window.searchTimeout);
            window.searchTimeout = setTimeout(searchMedicines, 500);
        });
        
        function selectMedicine(medicineData) {
            selectedMedicine = medicineData;
            
            $('#medicineDetails').html(`
                <div class="alert alert-info">
                    <strong>${medicineData.name}</strong><br>
                    ${medicineData.generic_name ? 'Generic: ' + medicineData.generic_name + '<br>' : ''}
                    ${medicineData.brand ? 'Brand: ' + medicineData.brand + '<br>' : ''}
                    Strength: ${medicineData.strength}<br>
                    Available: ${medicineData.current_quantity}
                </div>
            `);
            
            loadUnits(medicineData.medicine_id);
            $('#medicineModal').modal('show');
        }
        
        function loadUnits(medicineId) {
            $.ajax({
                url: 'ajax/get_units.php',
                method: 'POST',
                data: { medicine_id: medicineId },
                success: function(response) {
                    const units = JSON.parse(response);
                    let html = '';
                    units.forEach(unit => {
                        html += `<span class="unit-option" onclick="selectUnit(${unit.id}, '${unit.name}', ${unit.price})">
                                    ${unit.name} - Rs. ${unit.price}
                                 </span>`;
                    });
                    $('#unitOptions').html(html);
                }
            });
        }
        
        function selectUnit(unitId, unitName, price) {
            selectedUnit = { id: unitId, name: unitName, price: price };
            $('.unit-option').removeClass('selected');
            event.target.classList.add('selected');
            $('#unitPrice').val(price);
            updateItemTotal();
        }
        
        function updateItemTotal() {
            const quantity = parseInt($('#quantity').val()) || 1;
            const price = parseFloat($('#unitPrice').val()) || 0;
            $('#itemTotal').val((quantity * price).toFixed(2));
        }
        
        $('#quantity').on('input', updateItemTotal);
        
        function addToCart() {
            if (!selectedMedicine || !selectedUnit) {
                alert('Please select medicine and unit');
                return;
            }
            
            const quantity = parseInt($('#quantity').val());
            const unitPrice = parseFloat($('#unitPrice').val());
            const total = quantity * unitPrice;
            
            if (quantity > selectedMedicine.current_quantity) {
                alert('Quantity exceeds available stock!');
                return;
            }
            
            cart.push({
                medicineId: selectedMedicine.medicine_id,
                medicineName: selectedMedicine.name,
                unitId: selectedUnit.id,
                unitName: selectedUnit.name,
                quantity: quantity,
                unitPrice: unitPrice,
                total: total,
                batchId: selectedMedicine.batch_id
            });
            
            updateCartDisplay();
            $('#medicineModal').modal('hide');
            resetModal();
        }
        
        function resetModal() {
            $('#quantity').val(1);
            $('#unitPrice').val('');
            $('#itemTotal').val('');
            $('.unit-option').removeClass('selected');
            selectedMedicine = null;
            selectedUnit = null;
        }
        
        function updateCartDisplay() {
            if (cart.length === 0) {
                $('#cartItems').html(`
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <p>Cart is empty</p>
                    </div>
                `);
                $('#checkoutBtn').prop('disabled', true);
            } else {
                let html = '';
                cart.forEach((item, index) => {
                    html += `
                        <div class="cart-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <strong>${item.medicineName}</strong><br>
                                    <small class="text-muted">${item.unitName} × ${item.quantity}</small><br>
                                    <small class="text-success">Rs. ${item.unitPrice} × ${item.quantity} = Rs. ${item.total.toFixed(2)}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" onclick="removeCartItem(${index})">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                $('#cartItems').html(html);
                $('#checkoutBtn').prop('disabled', false);
            }
            updateTotals();
        }
        
        function updateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
            const taxAmount = subtotal * 0.17;
            const discountAmount = parseFloat($('#discountAmount').val()) || 0;
            const total = subtotal + taxAmount - discountAmount;
            
            $('#subtotal').text('Rs. ' + subtotal.toFixed(2));
            $('#taxAmount').text('Rs. ' + taxAmount.toFixed(2));
            $('#totalAmount').text('Rs. ' + total.toFixed(2));
        }
        
        function removeCartItem(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }
        
        function clearCart() {
            if (cart.length > 0 && confirm('Clear cart?')) {
                cart = [];
                updateCartDisplay();
            }
        }
        
        function processSale() {
            if (cart.length === 0) {
                alert('Cart is empty');
                return;
            }
            
            const saleData = {
                items: cart,
                customer_name: '',
                customer_phone: '',
                customer_cnic: '',
                payment_method: $('#paymentMethod').val(),
                discount_amount: parseFloat($('#discountAmount').val()) || 0,
                shop_id: <?= $user_shop_id ?: 'null' ?>
            };
            
            $('#checkoutBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            $.ajax({
                url: 'ajax/process_sale.php',
                method: 'POST',
                data: { sale_data: JSON.stringify(saleData) },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        alert('Sale completed!\nInvoice: ' + result.invoice_number);
                        cart = [];
                        updateCartDisplay();
                        $('#discountAmount').val(0);
                        
                        if (confirm('Print invoice?')) {
                            window.open('print_invoice.php?invoice=' + result.invoice_number, '_blank');
                        }
                    } else {
                        alert('Error: ' + result.message);
                    }
                },
                error: function() {
                    alert('Error processing sale');
                },
                complete: function() {
                    $('#checkoutBtn').prop('disabled', false).html('<i class="fas fa-check"></i> Checkout');
                }
            });
        }
        
        $(document).ready(function() {
            updateCartDisplay();
        });
    </script>
</body>
</html>
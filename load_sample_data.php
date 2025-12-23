<?php
require_once 'config/centralized_db.php';

try {
    $db->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Truncate all tables completely
    $db->query("TRUNCATE TABLE sale_items");
    $db->query("TRUNCATE TABLE sales");
    $db->query("TRUNCATE TABLE stock_batches");
    $db->query("TRUNCATE TABLE unit_conversions");
    $db->query("TRUNCATE TABLE medicines");
    $db->query("TRUNCATE TABLE suppliers");
    $db->query("TRUNCATE TABLE categories");
    $db->query("TRUNCATE TABLE users");
    $db->query("TRUNCATE TABLE shops");
    $db->query("TRUNCATE TABLE activity_logs");
    
    $db->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Insert categories
    $categories = [
        ['Analgesics', 'Pain relief medicines'],
        ['Antibiotics', 'Bacterial infection treatment'],
        ['Antacids', 'Stomach acid relief'],
        ['Diabetes', 'Blood sugar management'],
        ['Respiratory', 'Breathing and lung medicines'],
        ['Gastrointestinal', 'Digestive system medicines'],
        ['Dermatology', 'Skin treatment medicines'],
        ['Vitamins', 'Nutritional supplements'],
        ['Cardiovascular', 'Heart and blood medicines'],
        ['Neurological', 'Brain and nerve medicines']
    ];
    
    foreach ($categories as $category) {
        $db->query("INSERT INTO categories (name, description) VALUES (?, ?)", $category);
    }
    
    // Insert shops and get their IDs
    $shop_ids = [];
    $shops = [
        ['Main Branch Pharmacy', 'MAIN001', 'Shop 1, Main Market, Karachi', '+92-21-1234567', 'main@pharmacy.com', 'LIC001', 'TAX001'],
        ['North Branch Pharmacy', 'NORTH001', 'Shop 5, North Nazimabad, Karachi', '+92-21-2345678', 'north@pharmacy.com', 'LIC002', 'TAX002'],
        ['Gulshan Branch Pharmacy', 'GULSHAN001', 'Plot 123, Gulshan-e-Iqbal, Karachi', '+92-21-3456789', 'gulshan@pharmacy.com', 'LIC003', 'TAX003']
    ];
    
    foreach ($shops as $i => $shop) {
        $db->query("INSERT INTO shops (name, code, address, phone, email, license_number, tax_number) VALUES (?, ?, ?, ?, ?, ?, ?)", $shop);
        $shop_ids[$i] = $db->lastInsertId();
    }
    
    // Insert users and get their IDs
    $user_ids = [];
    $users = [
        ['superadmin', 'superadmin@pharmacy.com', password_hash('admin123', PASSWORD_DEFAULT), 'Super Administrator', 1, NULL, '+92-300-0000000'],
        ['admin1', 'admin1@pharmacy.com', password_hash('admin123', PASSWORD_DEFAULT), 'Ahmed Ali', 2, $shop_ids[0], '+92-300-1234567'],
        ['admin2', 'admin2@pharmacy.com', password_hash('admin123', PASSWORD_DEFAULT), 'Fatima Khan', 2, $shop_ids[1], '+92-300-2345678'],
        ['cashier1', 'cashier1@pharmacy.com', password_hash('admin123', PASSWORD_DEFAULT), 'Muhammad Hassan', 3, $shop_ids[0], '+92-300-3456789'],
        ['cashier2', 'cashier2@pharmacy.com', password_hash('admin123', PASSWORD_DEFAULT), 'Ayesha Malik', 3, $shop_ids[1], '+92-300-4567890']
    ];
    
    foreach ($users as $i => $user) {
        $db->query("INSERT INTO users (username, email, password, full_name, role_id, shop_id, phone) VALUES (?, ?, ?, ?, ?, ?, ?)", $user);
        $user_ids[$i] = $db->lastInsertId();
    }
    
    // Insert suppliers
    $suppliers = [
        ['MediCorp Pakistan', 'Dr. Rashid Ahmed', '+92-21-5555001', 'medicorp@example.com', 'Karachi Industrial Area', 'TAX001'],
        ['PharmaTech Ltd', 'Ms. Sana Qureshi', '+92-21-5555002', 'pharmatech@example.com', 'Lahore Cantt', 'TAX002'],
        ['HealthCare Supplies', 'Mr. Tariq Mahmood', '+92-21-5555003', 'healthcare@example.com', 'Islamabad F-7', 'TAX003']
    ];
    
    foreach ($suppliers as $supplier) {
        $db->query("INSERT INTO suppliers (name, contact_person, phone, email, address, tax_number) VALUES (?, ?, ?, ?, ?, ?)", $supplier);
    }
    
    // Insert medicines
    $medicines = [
        ['Paracetamol 500mg', 'Acetaminophen', 'Panadol', 1, 'GSK Pakistan', 'Paracetamol 500mg', '500mg', 1, 'PAR500001', 'DRAP001', 0],
        ['Amoxicillin 250mg', 'Amoxicillin', 'Augmentin', 2, 'GSK Pakistan', 'Amoxicillin 250mg', '250mg', 2, 'AMX250001', 'DRAP002', 1],
        ['Omeprazole 20mg', 'Omeprazole', 'Losec', 6, 'Abbott Pakistan', 'Omeprazole 20mg', '20mg', 2, 'OME20001', 'DRAP003', 1],
        ['Metformin 500mg', 'Metformin HCl', 'Glucophage', 4, 'Merck Pakistan', 'Metformin 500mg', '500mg', 1, 'MET500001', 'DRAP004', 1],
        ['Diclofenac 50mg', 'Diclofenac Sodium', 'Voltaren', 1, 'Novartis Pakistan', 'Diclofenac 50mg', '50mg', 1, 'DIC50001', 'DRAP006', 0],
        ['Cetirizine 10mg', 'Cetirizine HCl', 'Zyrtec', 1, 'UCB Pakistan', 'Cetirizine 10mg', '10mg', 1, 'CET10001', 'DRAP007', 0],
        ['Centrum Multivitamin', 'Multivitamin', 'Centrum', 8, 'Pfizer Pakistan', 'Multivitamin Complex', 'Complex', 1, 'MUL001', 'DRAP010', 0],
        ['Ibuprofen 400mg', 'Ibuprofen', 'Brufen', 1, 'Abbott Pakistan', 'Ibuprofen 400mg', '400mg', 1, 'IBU400001', 'DRAP016', 0],
        ['Calcium Tablets', 'Calcium Carbonate', 'Caltrate', 8, 'Pfizer Pakistan', 'Calcium 600mg', '600mg', 1, 'CAL600001', 'DRAP020', 0]
    ];
    
    foreach ($medicines as $medicine) {
        $db->query("INSERT INTO medicines (name, generic_name, brand, category_id, manufacturer, salt_composition, strength, base_unit_id, barcode, drap_registration, is_prescription) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $medicine);
    }
    
    // Insert unit conversions
    $conversions = [
        [1, 1, 5, 10, 18.00, 20.00], // Paracetamol: tablet to strip
        [2, 2, 5, 10, 95.00, 120.00], // Amoxicillin: capsule to strip
        [3, 2, 5, 14, 245.00, 280.00], // Omeprazole: capsule to strip
        [4, 1, 5, 15, 45.00, 50.00], // Metformin: tablet to strip
        [5, 1, 5, 10, 38.00, 45.00], // Diclofenac: tablet to strip
        [6, 1, 5, 10, 32.00, 38.00], // Cetirizine: tablet to strip
        [7, 1, 5, 30, 210.00, 250.00], // Multivitamin: tablet to strip
        [8, 1, 5, 10, 65.00, 80.00], // Ibuprofen: tablet to strip
        [9, 1, 5, 10, 85.00, 100.00] // Calcium: tablet to strip
    ];
    
    foreach ($conversions as $conversion) {
        $db->query("INSERT INTO unit_conversions (medicine_id, from_unit_id, to_unit_id, conversion_factor, selling_price, mrp) VALUES (?, ?, ?, ?, ?, ?)", $conversion);
    }
    
    // Insert stock batches
    $stock_batches = [
        // Main Branch
        [$shop_ids[0], 1, 'PCM001', 1, '2024-01-15', '2026-01-15', 15.00, 500, 450],
        [$shop_ids[0], 2, 'AMX001', 1, '2024-01-10', '2025-11-10', 80.00, 200, 180],
        [$shop_ids[0], 3, 'OME001', 2, '2024-01-25', '2026-01-25', 180.00, 100, 85],
        [$shop_ids[0], 4, 'MET001', 2, '2024-02-01', '2026-12-01', 25.00, 300, 275],
        [$shop_ids[0], 5, 'DIC001', 1, '2024-02-10', '2025-08-10', 30.00, 200, 185],
        
        // North Branch
        [$shop_ids[1], 1, 'PCM002', 1, '2024-02-10', '2026-02-10', 15.00, 400, 380],
        [$shop_ids[1], 6, 'CET002', 3, '2024-01-12', '2026-06-12', 25.00, 200, 185],
        [$shop_ids[1], 8, 'IBU001', 1, '2024-02-12', '2026-04-12', 55.00, 120, 110],
        
        // Gulshan Branch
        [$shop_ids[2], 1, 'PCM003', 1, '2024-01-20', '2026-01-20', 15.00, 350, 320],
        [$shop_ids[2], 7, 'MUL002', 3, '2024-02-12', '2026-08-12', 60.00, 100, 85],
        [$shop_ids[2], 9, 'CAL002', 2, '2024-02-10', '2026-10-10', 70.00, 150, 135]
    ];
    
    foreach ($stock_batches as $batch) {
        $db->query("INSERT INTO stock_batches (shop_id, medicine_id, batch_number, supplier_id, manufacture_date, expiry_date, purchase_price, base_unit_quantity, current_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", $batch);
    }
    
    // Insert sales with correct user IDs
    $sales = [
        ['MAIN001-001', $shop_ids[0], $user_ids[1], 'Ali Ahmed', '+92-300-1111111', '42101-1234567-1', 120.00, 20.40, 0.00, 140.40, 'cash', '2024-12-01 10:30:00'],
        ['MAIN001-002', $shop_ids[0], $user_ids[3], 'Fatima Sheikh', '+92-300-2222222', NULL, 200.00, 34.00, 10.00, 224.00, 'card', '2024-12-01 14:15:00'],
        ['NORTH001-001', $shop_ids[1], $user_ids[2], 'Hassan Khan', '+92-300-3333333', '42101-2345678-2', 85.00, 14.45, 0.00, 99.45, 'cash', '2024-12-01 16:45:00'],
        ['GULSHAN001-001', $shop_ids[2], $user_ids[0], 'Walk-in Customer', NULL, NULL, 75.00, 12.75, 0.00, 87.75, 'cash', '2024-12-02 11:30:00']
    ];
    
    foreach ($sales as $sale) {
        $db->query("INSERT INTO sales (invoice_number, shop_id, user_id, customer_name, customer_phone, customer_cnic, subtotal, tax_amount, discount_amount, total_amount, payment_method, sale_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $sale);
    }
    
    // Insert sale items
    $sale_items = [
        [1, 1, 1, 6, 6, 20.00, 120.00],
        [2, 2, 1, 5, 5, 120.00, 600.00],
        [3, 6, 1, 2, 2, 32.00, 64.00],
        [4, 9, 1, 3, 3, 25.00, 75.00]
    ];
    
    foreach ($sale_items as $item) {
        $db->query("INSERT INTO sale_items (sale_id, batch_id, unit_id, quantity_in_unit, base_unit_quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)", $item);
    }
    
    echo "Sample data loaded successfully!<br>";
    echo "Categories: 10 medicine categories<br>";
    echo "Shops: 3 branches created<br>";
    echo "Users: 5 users added (1 super admin, 2 shop admins, 2 cashiers)<br>";
    echo "Suppliers: 3 suppliers<br>";
    echo "Medicines: 9 medicines with variants<br>";
    echo "Unit Conversions: Multiple unit options<br>";
    echo "Stock: Multi-shop inventory<br>";
    echo "Sales: 4 sample transactions<br>";
    echo "<br><strong>Login Credentials:</strong><br>";
    echo "Super Admin: superadmin / admin123<br>";
    echo "Shop Admin (Main): admin1 / admin123<br>";
    echo "Shop Admin (North): admin2 / admin123<br>";
    echo "Cashier (Main): cashier1 / admin123<br>";
    echo "Cashier (North): cashier2 / admin123<br>";
    echo "<br><a href='login.php'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
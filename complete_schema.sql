-- Complete the schema with missing data
USE centralized_pharmacy;

-- Complete roles insertion
INSERT INTO roles (name, display_name, description) VALUES 
('super_admin', 'Super Administrator', 'Full system access across all shops'),
('shop_admin', 'Shop Administrator', 'Full access to assigned shop'),
('cashier', 'Cashier/POS User', 'POS operations only');

-- Insert default permissions
INSERT INTO permissions (name, display_name, module) VALUES 
('manage_shops', 'Manage Shops', 'shops'),
('manage_users', 'Manage Users', 'users'),
('manage_medicines', 'Manage Medicines', 'medicines'),
('manage_stock', 'Manage Stock', 'stock'),
('view_reports', 'View Reports', 'reports'),
('pos_operations', 'POS Operations', 'pos'),
('manage_suppliers', 'Manage Suppliers', 'suppliers'),
('stock_transfers', 'Stock Transfers', 'transfers'),
('system_settings', 'System Settings', 'settings');

-- Assign permissions to roles
INSERT INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'super_admin';

INSERT INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'shop_admin' AND p.name IN ('manage_medicines', 'manage_stock', 'view_reports', 'pos_operations', 'manage_suppliers');

INSERT INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'cashier' AND p.name = 'pos_operations';

-- Insert default medicine units
INSERT INTO medicine_units (name, display_name, type) VALUES 
('tablet', 'Tablet', 'base'),
('capsule', 'Capsule', 'base'),
('ml', 'Milliliter', 'base'),
('gm', 'Gram', 'base'),
('strip', 'Strip', 'pack'),
('box', 'Box', 'box'),
('bottle', 'Bottle', 'bottle'),
('tube', 'Tube', 'tube'),
('vial', 'Vial', 'base'),
('ampoule', 'Ampoule', 'base'),
('sachet', 'Sachet', 'pack'),
('inhaler', 'Inhaler', 'base'),
('spray', 'Spray', 'base');

-- Insert default categories
INSERT INTO categories (name, description) VALUES 
('Antibiotics', 'Bacterial infection medicines'),
('Analgesics', 'Pain relief medicines'),
('Cardiovascular', 'Heart and blood pressure medicines'),
('Diabetes', 'Diabetes management medicines'),
('Respiratory', 'Breathing and lung medicines'),
('Gastrointestinal', 'Digestive system medicines'),
('Dermatology', 'Skin care medicines'),
('Vitamins', 'Vitamins and supplements');

-- Create super admin user
INSERT INTO users (username, email, password, full_name, role_id, shop_id) VALUES 
('superadmin', 'admin@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 1, NULL);

-- Insert default shop
INSERT INTO shops (name, code, address, phone, email) VALUES 
('Main Pharmacy', 'MAIN001', '123 Main Street, Karachi', '+92-21-1234567', 'main@pharmacy.com');

-- Insert global settings
INSERT INTO settings (shop_id, setting_key, setting_value, description) VALUES 
(NULL, 'system_name', 'Centralized Pharmacy POS', 'System name'),
(NULL, 'currency', 'PKR', 'Currency'),
(NULL, 'tax_rate', '17', 'Tax rate percentage'),
(NULL, 'low_stock_threshold', '10', 'Low stock alert threshold'),
(NULL, 'expiry_alert_days', '30', 'Days before expiry alert');

-- Create indexes for performance
CREATE INDEX idx_medicines_name ON medicines(name);
CREATE INDEX idx_medicines_barcode ON medicines(barcode);
CREATE INDEX idx_stock_expiry ON stock_batches(expiry_date);
CREATE INDEX idx_stock_quantity ON stock_batches(current_quantity);
CREATE INDEX idx_sales_date ON sales(sale_date);
CREATE INDEX idx_sales_shop ON sales(shop_id);
CREATE INDEX idx_activity_logs_date ON activity_logs(created_at);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
-- Centralized Multi-Shop Pharmacy POS System Database Schema
DROP DATABASE IF EXISTS centralized_pharmacy;
CREATE DATABASE centralized_pharmacy;
USE centralized_pharmacy;

-- Roles table
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Permissions table
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Role permissions mapping
CREATE TABLE role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
);

-- Shops/Branches table
CREATE TABLE shops (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    license_number VARCHAR(100),
    tax_number VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table with shop assignment
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role_id INT NOT NULL,
    shop_id INT NULL, -- NULL for super admin
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (shop_id) REFERENCES shops(id)
);

-- Categories
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    tax_number VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medicine units master
CREATE TABLE medicine_units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    type ENUM('base', 'pack', 'bottle', 'tube', 'box') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medicines master
CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200),
    brand VARCHAR(100),
    category_id INT,
    manufacturer VARCHAR(200),
    salt_composition TEXT,
    strength VARCHAR(100),
    base_unit_id INT NOT NULL, -- Base selling unit (tablet, ml, etc.)
    barcode VARCHAR(100),
    drap_registration VARCHAR(100), -- DRAP compliance
    is_prescription BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (base_unit_id) REFERENCES medicine_units(id)
);

-- Unit conversions for medicines
CREATE TABLE unit_conversions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    from_unit_id INT NOT NULL,
    to_unit_id INT NOT NULL,
    conversion_factor DECIMAL(10,4) NOT NULL, -- How many base units in this unit
    selling_price DECIMAL(10,2) NOT NULL,
    mrp DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (from_unit_id) REFERENCES medicine_units(id),
    FOREIGN KEY (to_unit_id) REFERENCES medicine_units(id),
    UNIQUE KEY unique_medicine_conversion (medicine_id, from_unit_id, to_unit_id)
);

-- Stock batches per shop
CREATE TABLE stock_batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NOT NULL,
    medicine_id INT NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    supplier_id INT,
    manufacture_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    base_unit_quantity INT NOT NULL, -- Always in base units
    current_quantity INT NOT NULL,
    damaged_quantity INT DEFAULT 0,
    purchase_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    UNIQUE KEY unique_shop_batch (shop_id, medicine_id, batch_number)
);

-- Sales transactions
CREATE TABLE sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    shop_id INT NOT NULL,
    user_id INT NOT NULL,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    customer_cnic VARCHAR(15),
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'upi', 'bank_transfer') DEFAULT 'cash',
    payment_status ENUM('paid', 'pending', 'partial') DEFAULT 'paid',
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (shop_id) REFERENCES shops(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Sale items with unit tracking
CREATE TABLE sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    batch_id INT NOT NULL,
    unit_id INT NOT NULL, -- Which unit was sold
    quantity_in_unit INT NOT NULL, -- Quantity in the sold unit
    base_unit_quantity INT NOT NULL, -- Equivalent base units
    unit_price DECIMAL(10,2) NOT NULL, -- Price per unit sold
    total_price DECIMAL(10,2) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    tax_percentage DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES stock_batches(id),
    FOREIGN KEY (unit_id) REFERENCES medicine_units(id)
);

-- Returns
CREATE TABLE returns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    batch_id INT NOT NULL,
    unit_id INT NOT NULL,
    quantity_returned INT NOT NULL,
    base_unit_quantity INT NOT NULL,
    reason TEXT,
    return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_by INT NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (batch_id) REFERENCES stock_batches(id),
    FOREIGN KEY (unit_id) REFERENCES medicine_units(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Stock transfers between shops
CREATE TABLE stock_transfers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    from_shop_id INT NOT NULL,
    to_shop_id INT NOT NULL,
    batch_id INT NOT NULL,
    quantity_transferred INT NOT NULL,
    transfer_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    initiated_by INT NOT NULL,
    approved_by INT,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (from_shop_id) REFERENCES shops(id),
    FOREIGN KEY (to_shop_id) REFERENCES shops(id),
    FOREIGN KEY (batch_id) REFERENCES stock_batches(id),
    FOREIGN KEY (initiated_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Activity logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    shop_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (shop_id) REFERENCES shops(id)
);

-- System settings
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NULL, -- NULL for global settings
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id),
    UNIQUE KEY unique_shop_setting (shop_id, setting_key)
);

-- Insert default roles
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
(NULL, 'currency', 'PKR', 'Currency symbol'),
(NULL, 'tax_rate', '17', 'Default tax rate percentage'),
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
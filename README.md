# 🏥 Centralized Multi-Shop Pharmacy POS System

A complete centralized pharmacy management system with Point of Sale (POS) functionality designed specifically for Pakistan, supporting multiple pharmacy branches with role-based access control.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

## 🌟 Features

### 🏗️ Multi-Shop Architecture
- **Centralized Database** - Single database for all pharmacy branches
- **Shop-Specific Operations** - Each shop manages its own stock and sales
- **Role-Based Access Control** - Super Admin → Shop Admin → Cashier hierarchy
- **Real-Time Synchronization** - Live stock and sales tracking across branches

### 👥 User Management
- **Super Administrator** - Complete system access across all shops
- **Shop Administrator** - Full access to assigned shop operations
- **Cashier/POS User** - Point of sale operations only

### 💊 Advanced Medicine Management
- **Multi-Unit System** - Tablet, Capsule, ML, Gram, Strip, Box, Bottle
- **DRAP Compliance** - Pakistani drug regulatory authority compliance
- **Batch Tracking** - FIFO inventory management with expiry monitoring
- **Flexible Selling** - Sell by individual units or bulk packages

### 🛒 Professional POS System
- **Clean Interface** - Easy-to-use, professional design
- **Real-Time Stock** - Live availability checking
- **Pakistani Tax System** - 17% GST calculation
- **Multiple Payment Methods** - Cash, Card, Bank Transfer
- **Invoice Generation** - Thermal printing and PDF support

### 📊 Comprehensive Reporting
- **Sales Analytics** - Daily/Monthly/Yearly reports with charts
- **Inventory Reports** - Stock valuation and expiry alerts
- **Shop Performance** - Multi-branch comparison
- **Low Stock Alerts** - Automated notifications

### 🔐 Security Features
- **bcrypt Password Hashing**
- **CSRF Token Protection**
- **SQL Injection Prevention**
- **Activity Audit Logs**
- **Pakistani Compliance** (CNIC validation, phone formatting)

## 🚀 Installation

### Prerequisites
- **XAMPP/WAMP/LAMP** server
- **PHP 7.4+** with PDO extension
- **MySQL 5.7+** with InnoDB support

### Setup Steps

1. **Clone Repository**
   ```bash
   git clone https://github.com/Muhammad9985/pharmacy-pos-system.git
   cd pharmacy-pos-system
   ```

2. **Move to Web Directory**
   ```bash
   # For XAMPP
   cp -r * C:\xampp\htdocs\pharma\
   
   # For Linux/Mac
   sudo cp -r * /var/www/html/pharma/
   ```

3. **Database Setup**
   ```sql
   -- Create database and import schema
   mysql -u root -p
   CREATE DATABASE centralized_pharmacy;
   USE centralized_pharmacy;
   SOURCE centralized_schema.sql;
   ```

4. **Load Sample Data**
   ```
   Visit: http://localhost/pharma/load_sample_data.php
   ```

5. **Access System**
   ```
   URL: http://localhost/pharma/
   ```

## 🔑 Default Login Credentials

| Role | Username | Password | Access Level |
|------|----------|----------|--------------|
| Super Admin | `superadmin` | `admin123` | All shops |
| Shop Admin (Main) | `admin1` | `admin123` | Main Branch |
| Shop Admin (North) | `admin2` | `admin123` | North Branch |
| Cashier (Main) | `cashier1` | `admin123` | POS Only |
| Cashier (North) | `cashier2` | `admin123` | POS Only |

## 📱 System Modules

### Admin Dashboard
- **Real-Time Analytics** - Sales and inventory statistics
- **Multi-Shop Overview** - Consolidated performance metrics
- **Alert System** - Low stock and expiry notifications
- **User Management** - Role-based user administration

### POS System
- **Medicine Search** - Quick product lookup by name/barcode
- **Unit Selection** - Flexible selling units (tablet, strip, box)
- **Cart Management** - Easy add/remove items
- **Payment Processing** - Multiple payment methods
- **Invoice Printing** - Thermal and PDF receipts

### Inventory Management
- **Stock Tracking** - Batch-wise inventory control
- **Expiry Monitoring** - 30-day advance alerts
- **Supplier Management** - Vendor tracking and performance
- **Stock Transfers** - Inter-branch inventory movement

### Reports & Analytics
- **Sales Reports** - Detailed transaction analysis
- **Inventory Reports** - Stock valuation and turnover
- **Performance Charts** - Visual data representation
- **Export Options** - PDF and Excel export

## 🛠️ Technical Stack

### Backend
- **PHP 7.4+** - Server-side logic
- **MySQL 5.7+** - Relational database
- **PDO** - Database abstraction layer
- **MVC Pattern** - Organized code structure

### Frontend
- **Bootstrap 5** - Responsive UI framework
- **jQuery 3.7** - JavaScript library
- **AJAX** - Seamless user experience
- **Chart.js** - Data visualization

### Security
- **Password Hashing** - bcrypt encryption
- **Prepared Statements** - SQL injection prevention
- **CSRF Protection** - Cross-site request forgery prevention
- **Input Validation** - Server-side sanitization

## 📋 Database Schema

The system uses a normalized database schema with 17 tables:

- `users` - Authentication and role management
- `shops` - Branch information
- `medicines` - Medicine master data
- `stock_batches` - Inventory tracking
- `sales` & `sale_items` - Transaction records
- `activity_logs` - Audit trail
- And more...

## 🔧 Configuration

### Environment Setup
```php
// config/centralized_db.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'centralized_pharmacy');
```

### System Settings
- Tax rate: 17% (Pakistani GST)
- Low stock threshold: 10 units
- Expiry alert: 30 days advance
- Currency: PKR (Pakistani Rupee)



## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and queries:
- **Email**: rafiqalbaloshi3@gmail.com
- **LinkedIn**: [Muhammad Rafique](https://www.linkedin.com/in/muhammad-rafique-944b05159)
- **Portfolio**: [mr-software.online](https://mr-software.online)

## 🙏 Acknowledgments

- Built for Pakistani pharmacy market compliance
- DRAP (Drug Regulatory Authority of Pakistan) compliant
- Designed for multi-branch pharmacy operations
- Supports Urdu language integration

---

**Developed by Muhammad Rafique**  
*Full Stack Developer | PHP | MySQL | JavaScript*

[![LinkedIn](https://img.shields.io/badge/LinkedIn-0077B5?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/muhammad-rafique-944b05159)
[![GitHub](https://img.shields.io/badge/GitHub-100000?style=for-the-badge&logo=github&logoColor=white)](https://github.com/Muhammad9985)
[![Portfolio](https://img.shields.io/badge/Portfolio-FF5722?style=for-the-badge&logo=google-chrome&logoColor=white)](https://mr-software.online)

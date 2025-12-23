<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';
require_once '../includes/centralized_functions.php';
require_once '../includes/admin_layout.php';

$auth->requireRole(['super_admin', 'shop_admin']);
$user = $auth->getUser();
$shop_id = $auth->isSuperAdmin() ? null : $user['shop_id'];

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$selected_shop = $_GET['shop_id'] ?? '';

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

// Sales Summary
$sales_summary = $db->query("
    SELECT 
        COUNT(s.id) as total_sales,
        COALESCE(SUM(s.total_amount), 0) as total_revenue,
        COALESCE(AVG(s.total_amount), 0) as avg_sale_amount,
        COUNT(DISTINCT s.customer_name) as unique_customers
    FROM sales s 
    WHERE DATE(s.sale_date) BETWEEN ? AND ? $shopFilter
", array_merge([$start_date, $end_date], $shopParams))->fetch();

// Daily Sales Chart Data
$daily_sales = $db->query("
    SELECT 
        DATE(s.sale_date) as sale_date,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.total_amount), 0) as daily_revenue
    FROM sales s 
    WHERE DATE(s.sale_date) BETWEEN ? AND ? $shopFilter
    GROUP BY DATE(s.sale_date)
    ORDER BY DATE(s.sale_date)
", array_merge([$start_date, $end_date], $shopParams))->fetchAll();

// Top Selling Medicines
$top_medicines = $db->query("
    SELECT 
        m.name as medicine_name,
        m.strength,
        SUM(si.quantity_in_unit) as total_quantity,
        COALESCE(SUM(si.total_price), 0) as total_revenue,
        COUNT(DISTINCT s.id) as sales_count
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN stock_batches sb ON si.batch_id = sb.id
    JOIN medicines m ON sb.medicine_id = m.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ? $shopFilter
    GROUP BY m.id
    ORDER BY total_revenue DESC
    LIMIT 10
", array_merge([$start_date, $end_date], $shopParams))->fetchAll();

// Shop Performance (for super admin)
$shop_performance = [];
if ($auth->isSuperAdmin()) {
    $shop_performance = $db->query("
        SELECT 
            sh.name as shop_name,
            COUNT(s.id) as total_sales,
            COALESCE(SUM(s.total_amount), 0) as total_revenue,
            COUNT(DISTINCT s.user_id) as active_users
        FROM shops sh
        LEFT JOIN sales s ON sh.id = s.shop_id AND DATE(s.sale_date) BETWEEN ? AND ?
        WHERE sh.is_active = 1
        GROUP BY sh.id
        ORDER BY total_revenue DESC
    ", [$start_date, $end_date])->fetchAll();
}

// Low Stock Alert
$low_stock_filter = $shop_id ? "AND sb.shop_id = $shop_id" : "";
$low_stock_items = $db->query("
    SELECT 
        m.name as medicine_name,
        m.strength,
        sh.name as shop_name,
        sb.current_quantity,
        sb.batch_number,
        DATEDIFF(sb.expiry_date, CURDATE()) as days_to_expiry
    FROM stock_batches sb
    JOIN medicines m ON sb.medicine_id = m.id
    JOIN shops sh ON sb.shop_id = sh.id
    WHERE sb.is_active = 1 AND sb.current_quantity < 50 $low_stock_filter
    ORDER BY sb.current_quantity ASC
    LIMIT 10
")->fetchAll();

// Get shops for filter (super admin only)
$shops = $auth->isSuperAdmin() ? $db->query("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY name")->fetchAll() : [];

// Content for the page
ob_start();
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: none;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
}

.stat-icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-icon.success { background: linear-gradient(135deg, #56ab2f, #a8e6cf); }
.stat-icon.info { background: linear-gradient(135deg, #3498db, #2980b9); }
.stat-icon.warning { background: linear-gradient(135deg, #f39c12, #e67e22); }

.chart-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: none;
    overflow: hidden;
}

.chart-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
}

.filter-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.btn-modern {
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.table-modern {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.table-modern thead {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.table-modern tbody tr:hover {
    background-color: #f8f9fa;
    transform: scale(1.01);
    transition: all 0.2s ease;
}

.alert-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: none;
    max-height: 400px;
    overflow-y: auto;
}

.medicine-item {
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.medicine-item:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.form-control-modern {
    border-radius: 10px;
    border: 2px solid #e9ecef;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}
</style>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h4 class="mb-1">📊 Sales Analytics & Reports</h4>
            <p class="mb-0 opacity-75">Comprehensive insights into your pharmacy performance</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="d-flex align-items-center justify-content-end">
                <i class="fas fa-calendar-alt me-2"></i>
                <span><?= date('M j, Y', strtotime($start_date)) ?> - <?= date('M j, Y', strtotime($end_date)) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-card">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-bold">📅 Start Date</label>
            <input type="date" class="form-control form-control-modern" name="start_date" value="<?= $start_date ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold">📅 End Date</label>
            <input type="date" class="form-control form-control-modern" name="end_date" value="<?= $end_date ?>">
        </div>
        <?php if ($auth->isSuperAdmin()): ?>
        <div class="col-md-3">
            <label class="form-label fw-bold">🏪 Shop</label>
            <select class="form-control form-control-modern" name="shop_id">
                <option value="">All Shops</option>
                <?php foreach ($shops as $shop): ?>
                <option value="<?= $shop['id'] ?>" <?= $selected_shop == $shop['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($shop['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary-modern btn-modern me-2">
                <i class="fas fa-filter me-1"></i> Apply Filter
            </button>
            <button type="button" class="btn btn-outline-secondary btn-modern" onclick="exportReport()">
                <i class="fas fa-download me-1"></i> Export
            </button>
        </div>
    </form>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon primary me-3">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-primary"><?= number_format($sales_summary['total_sales']) ?></h5>
                    <small class="text-muted fw-bold">Total Sales</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon success me-3">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-success">Rs. <?= number_format($sales_summary['total_revenue'], 2) ?></h5>
                    <small class="text-muted fw-bold">Total Revenue</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon info me-3">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-info">Rs. <?= number_format($sales_summary['avg_sale_amount'], 2) ?></h5>
                    <small class="text-muted fw-bold">Average Sale</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon warning me-3">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-warning"><?= number_format($sales_summary['unique_customers']) ?></h5>
                    <small class="text-muted fw-bold">Unique Customers</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Sales Chart -->
    <div class="col-md-8 mb-4">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-chart-area me-2 text-primary"></i>
                    📈 Daily Sales Trend
                </h5>
            </div>
            <div class="p-3">
                <canvas id="dailySalesChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="col-md-4 mb-4">
        <div class="alert-card">
            <div class="chart-header">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    ⚠️ Stock Alerts
                </h5>
            </div>
            <div class="p-3">
                <?php if (empty($low_stock_items)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-success">✅ All Good!</h6>
                        <p class="text-muted">No low stock items.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($low_stock_items as $item): ?>
                    <div class="medicine-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($item['medicine_name']) ?></strong>
                                <span class="badge bg-info ms-1"><?= htmlspecialchars($item['strength']) ?></span><br>
                                <?php if ($auth->isSuperAdmin()): ?>
                                <small class="text-muted">🏪 <?= htmlspecialchars($item['shop_name']) ?></small><br>
                                <?php endif; ?>
                                <small class="text-muted">📦 Batch: <?= htmlspecialchars($item['batch_number']) ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-warning"><?= $item['current_quantity'] ?> left</span>
                                <?php if ($item['days_to_expiry'] <= 30 && $item['days_to_expiry'] >= 0): ?>
                                <br><span class="badge bg-danger mt-1">⏰ <?= $item['days_to_expiry'] ?> days</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Medicines & Shop Performance -->
<div class="row">
    <div class="col-md-8 mb-4">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-trophy me-2 text-warning"></i>
                    🏆 Top Selling Medicines
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th>🥇 Rank</th>
                            <th>💊 Medicine</th>
                            <th>📊 Quantity</th>
                            <th>💰 Revenue</th>
                            <th>🛒 Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_medicines as $index => $medicine): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary fs-6"><?= $index + 1 ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($medicine['medicine_name']) ?></strong>
                                <span class="badge bg-info ms-2"><?= htmlspecialchars($medicine['strength']) ?></span>
                            </td>
                            <td><strong class="text-primary"><?= number_format($medicine['total_quantity']) ?></strong></td>
                            <td><strong class="text-success">Rs. <?= number_format($medicine['total_revenue'], 2) ?></strong></td>
                            <td><?= number_format($medicine['sales_count']) ?> sales</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Shop Performance -->
    <?php if ($auth->isSuperAdmin() && !empty($shop_performance)): ?>
    <div class="col-md-4 mb-4">
        <div class="alert-card">
            <div class="chart-header">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-store me-2 text-info"></i>
                    🏪 Shop Performance
                </h5>
            </div>
            <div class="p-3">
                <?php foreach ($shop_performance as $shop): ?>
                <div class="medicine-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>🏪 <?= htmlspecialchars($shop['shop_name']) ?></strong><br>
                            <small class="text-muted">👥 <?= $shop['active_users'] ?> active users</small>
                        </div>
                        <div class="text-end">
                            <strong class="text-success">Rs. <?= number_format($shop['total_revenue'], 2) ?></strong><br>
                            <small class="text-muted">🛒 <?= $shop['total_sales'] ?> sales</small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

// Prepare chart data
$chart_labels = json_encode(array_column($daily_sales, 'sale_date'));
$chart_data = json_encode(array_column($daily_sales, 'daily_revenue'));

$additionalJS = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Daily Sales Chart with modern styling
    const ctx = document.getElementById("dailySalesChart").getContext("2d");
    new Chart(ctx, {
        type: "line",
        data: {
            labels: ' . $chart_labels . ',
            datasets: [{
                label: "Daily Revenue (Rs.)",
                data: ' . $chart_data . ',
                borderColor: "rgb(102, 126, 234)",
                backgroundColor: "rgba(102, 126, 234, 0.1)",
                tension: 0.4,
                fill: true,
                pointBackgroundColor: "rgb(102, 126, 234)",
                pointBorderColor: "#fff",
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: "rgba(0,0,0,0.1)"
                    },
                    ticks: {
                        callback: function(value) {
                            return "Rs. " + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        color: "rgba(0,0,0,0.1)"
                    }
                }
            }
        }
    });

    function exportReport() {
        const params = new URLSearchParams(window.location.search);
        params.set("export", "1");
        window.open("export.php?" + params.toString(), "_blank");
    }
</script>';

renderAdminLayout('Reports & Analytics', $content, 'reports', '', $additionalJS);
?>
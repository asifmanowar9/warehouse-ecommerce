<?php
// Start the session if one hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';
require 'functions.php';

requireLogin();
requireAdminAccess();

// Check if user is logged in
if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Set default values
$reportType = $_GET['report'] ?? 'inventory';
$dateRange = $_GET['range'] ?? 'month';
$startDate = null;
$endDate = null;

// Calculate date range based on selection
$today = date('Y-m-d');
$endDate = $today;

switch ($dateRange) {
    case 'day':
        $startDate = $today;
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'quarter':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? $today;
        break;
}

// Report data variables
$reportData = [];
$chartLabels = [];
$chartValues = [];
$reportTitle = '';
$reportDescription = '';

// Generate report based on type
switch ($reportType) {
    case 'inventory':
        $reportTitle = 'Current Inventory Status';
        $reportDescription = 'Overview of current stock levels compared to reorder points';
        
        // Get inventory status
        $stmt = $pdo->prepare("
            SELECT 
                p.id, p.sku, p.name, p.description, p.reorder_level,
                v.on_hand,
                s.name AS supplier_name,
                CASE 
                    WHEN v.on_hand <= 0 THEN 'Out of Stock' 
                    WHEN v.on_hand <= p.reorder_level THEN 'Low Stock'
                    ELSE 'In Stock'
                END AS stock_status
            FROM products p
            JOIN v_product_stock v ON p.id = v.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            ORDER BY 
                CASE 
                    WHEN v.on_hand <= 0 THEN 1
                    WHEN v.on_hand <= p.reorder_level THEN 2
                    ELSE 3
                END,
                p.name ASC
        ");
        $stmt->execute();
        $reportData = $stmt->fetchAll();
        
        // Chart data - stock status distribution
        $chartQuery = $pdo->query("
            SELECT 
                CASE 
                    WHEN v.on_hand <= 0 THEN 'Out of Stock' 
                    WHEN v.on_hand <= p.reorder_level THEN 'Low Stock'
                    ELSE 'In Stock'
                END AS status,
                COUNT(*) as count
            FROM products p
            JOIN v_product_stock v ON p.id = v.id
            GROUP BY 
                CASE 
                    WHEN v.on_hand <= 0 THEN 'Out of Stock' 
                    WHEN v.on_hand <= p.reorder_level THEN 'Low Stock'
                    ELSE 'In Stock'
                END
            ORDER BY 
                CASE 
                    WHEN status = 'Out of Stock' THEN 1
                    WHEN status = 'Low Stock' THEN 2
                    ELSE 3
                END
        ");
        $chartData = $chartQuery->fetchAll();
        
        foreach ($chartData as $item) {
            $chartLabels[] = $item['status'];
            $chartValues[] = $item['count'];
        }
        break;
        
    case 'movements':
        $reportTitle = 'Stock Movement Report';
        $reportDescription = "Stock movements from $startDate to $endDate";
        
        // Get stock movements
        $stmt = $pdo->prepare("
            SELECT 
                sm.id, sm.moved_at, sm.qty, sm.movement_type, sm.reference,
                p.sku, p.name AS product_name,
                l.code AS location_code,
                u.username AS moved_by_user
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.id
            LEFT JOIN locations l ON sm.location_id = l.id
            LEFT JOIN users u ON sm.moved_by = u.id
            WHERE DATE(sm.moved_at) BETWEEN ? AND ?
            ORDER BY sm.moved_at DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll();
        
        // Chart data - movement types by day
        $chartQuery = $pdo->prepare("
            SELECT 
                DATE(moved_at) AS move_date,
                movement_type,
                COUNT(*) AS count
            FROM stock_movements
            WHERE DATE(moved_at) BETWEEN ? AND ?
            GROUP BY DATE(moved_at), movement_type
            ORDER BY move_date
        ");
        $chartQuery->execute([$startDate, $endDate]);
        $chartData = $chartQuery->fetchAll();
        
        // Process chart data for visualization
        $dates = [];
        $movementTypes = ['PURCHASE' => [], 'SALE' => [], 'ADJUST' => [], 'TRANSFER' => []];
        
        foreach ($chartData as $row) {
            if (!in_array($row['move_date'], $dates)) {
                $dates[] = $row['move_date'];
            }
        }
        
        foreach ($dates as $date) {
            foreach (array_keys($movementTypes) as $type) {
                $movementTypes[$type][$date] = 0;
            }
        }
        
        foreach ($chartData as $row) {
            $movementTypes[$row['movement_type']][$row['move_date']] = $row['count'];
        }
        
        // Format for chart.js
        $chartLabels = json_encode($dates);
        $chartValues = json_encode($movementTypes);
        break;
        
    case 'orders':
        $reportTitle = 'Purchase Orders Report';
        $reportDescription = "Purchase orders from $startDate to $endDate";
        
        // Get purchase orders
        $stmt = $pdo->prepare("
            SELECT 
                po.id, po.order_date, po.status, po.total_amount,
                s.name AS supplier_name,
                u.username AS ordered_by_user
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN users u ON po.ordered_by = u.id
            WHERE po.order_date BETWEEN ? AND ?
            ORDER BY po.order_date DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll();
        
        // Chart data - orders by status
        $chartQuery = $pdo->prepare("
            SELECT 
                status,
                COUNT(*) AS count,
                SUM(total_amount) AS total
            FROM purchase_orders
            WHERE order_date BETWEEN ? AND ?
            GROUP BY status
        ");
        $chartQuery->execute([$startDate, $endDate]);
        $statusData = $chartQuery->fetchAll();
        
        foreach ($statusData as $item) {
            $chartLabels[] = $item['status'];
            $chartValues[] = $item['total'];
        }
        break;
        
    case 'suppliers':
        $reportTitle = 'Supplier Performance Report';
        $reportDescription = "Supplier metrics from $startDate to $endDate";
        
        // Get supplier metrics
        $stmt = $pdo->prepare("
            SELECT 
                s.id, s.name, s.contact_name, s.email,
                COUNT(DISTINCT po.id) AS order_count,
                SUM(po.total_amount) AS total_spend,
                AVG(CASE WHEN po.status = 'RECEIVED' THEN 
                    DATEDIFF(sm.moved_at, po.order_date) 
                    ELSE NULL END) AS avg_delivery_days
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.id = po.supplier_id AND po.order_date BETWEEN ? AND ?
            LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
            LEFT JOIN stock_movements sm ON sm.reference = CONCAT('PO#', po.id) AND sm.movement_type = 'PURCHASE'
            GROUP BY s.id, s.name, s.contact_name, s.email
            ORDER BY total_spend DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll();
        
        // Chart data - top suppliers by spend
        $topSuppliers = array_slice($reportData, 0, 5);
        foreach ($topSuppliers as $supplier) {
            if ($supplier['total_spend']) {
                $chartLabels[] = $supplier['name'];
                $chartValues[] = $supplier['total_spend'];
            }
        }
        break;
}

// Function to determine status class for badges
function getStatusClass($status) {
    switch (strtoupper($status)) {
        case 'OUT OF STOCK':
            return 'bg-danger';
        case 'LOW STOCK':
            return 'bg-warning';
        case 'IN STOCK':
            return 'bg-success';
        case 'OPEN':
            return 'bg-primary';
        case 'RECEIVED':
            return 'bg-success';
        case 'CANCELLED':
            return 'bg-secondary';
        case 'PURCHASE':
            return 'bg-success';
        case 'SALE':
            return 'bg-primary';
        case 'ADJUST':
            return 'bg-warning';
        case 'TRANSFER':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
}

// Calculate summary data for report header
$summaryData = [];
switch ($reportType) {
    case 'inventory':
        $summaryQuery = $pdo->query("
            SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN v.on_hand <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN v.on_hand > 0 AND v.on_hand <= p.reorder_level THEN 1 ELSE 0 END) as low_stock,
                SUM(v.on_hand) as total_items,
                SUM(v.on_hand * p.unit_price) as inventory_value
            FROM products p
            JOIN v_product_stock v ON p.id = v.id
        ");
        $summaryData = $summaryQuery->fetch();
        break;
        
    case 'movements':
        $summaryQuery = $pdo->prepare("
            SELECT 
                COUNT(*) as total_movements,
                SUM(CASE WHEN movement_type = 'PURCHASE' THEN 1 ELSE 0 END) as purchases,
                SUM(CASE WHEN movement_type = 'SALE' THEN 1 ELSE 0 END) as sales,
                SUM(CASE WHEN qty > 0 THEN qty ELSE 0 END) as items_in,
                SUM(CASE WHEN qty < 0 THEN ABS(qty) ELSE 0 END) as items_out
            FROM stock_movements
            WHERE DATE(moved_at) BETWEEN ? AND ?
        ");
        $summaryQuery->execute([$startDate, $endDate]);
        $summaryData = $summaryQuery->fetch();
        break;
        
    case 'orders':
        $summaryQuery = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(DISTINCT supplier_id) as supplier_count,
                SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) as open_orders,
                SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END) as received_orders,
                SUM(total_amount) as total_spend
            FROM purchase_orders
            WHERE order_date BETWEEN ? AND ?
        ");
        $summaryQuery->execute([$startDate, $endDate]);
        $summaryData = $summaryQuery->fetch();
        break;
        
    case 'suppliers':
        $summaryQuery = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT s.id) as total_suppliers,
                COUNT(DISTINCT po.id) as total_orders,
                SUM(po.total_amount) as total_spend,
                AVG(CASE WHEN po.status = 'RECEIVED' THEN 
                    DATEDIFF(sm.moved_at, po.order_date) 
                    ELSE NULL END) AS avg_delivery_days
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.id = po.supplier_id AND po.order_date BETWEEN ? AND ?
            LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
            LEFT JOIN stock_movements sm ON sm.reference = CONCAT('PO#', po.id) AND sm.movement_type = 'PURCHASE'
        ");
        $summaryQuery->execute([$startDate, $endDate]);
        $summaryData = $summaryQuery->fetch();
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Include the navbar -->
    <?php include 'includes/navbar.php'; ?>

    <header>
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">WAREHOUSE REPORTS</h1>
            <p class="lead mb-0">Data insights and analytics | <?= date('Y-m-d') ?></p>
        </div>
    </header>

    <main class="container py-5">
        <!-- Report Selection and Filters -->
        <div class="card-glass p-4 mb-4">
            <form method="get" class="row g-3 align-items-end">
                <!-- Report Type Selection -->
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="report" class="form-select search-box" onchange="this.form.submit()">
                        <option value="inventory" <?= $reportType === 'inventory' ? 'selected' : '' ?>>Inventory Status</option>
                        <option value="movements" <?= $reportType === 'movements' ? 'selected' : '' ?>>Stock Movements</option>
                        <option value="orders" <?= $reportType === 'orders' ? 'selected' : '' ?>>Purchase Orders</option>
                        <option value="suppliers" <?= $reportType === 'suppliers' ? 'selected' : '' ?>>Supplier Performance</option>
                    </select>
                </div>
                
                <!-- Date Range Selection -->
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <select name="range" id="dateRange" class="form-select search-box" onchange="toggleCustomDateFields(); this.form.submit();">
                        <option value="day" <?= $dateRange === 'day' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $dateRange === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="month" <?= $dateRange === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="quarter" <?= $dateRange === 'quarter' ? 'selected' : '' ?>>Last 90 Days</option>
                        <option value="year" <?= $dateRange === 'year' ? 'selected' : '' ?>>Last Year</option>
                        <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <!-- Custom Date Fields (hidden by default) -->
                <div class="col-md-2 custom-date-fields" style="<?= $dateRange === 'custom' ? '' : 'display:none;' ?>">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control search-box" value="<?= $startDate ?>">
                </div>
                <div class="col-md-2 custom-date-fields" style="<?= $dateRange === 'custom' ? '' : 'display:none;' ?>">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control search-box" value="<?= $endDate ?>">
                </div>
                
                <!-- Export Options and Submit Button -->
                <div class="col-md-2 ms-auto">
                    <div class="d-grid">
                        <button type="button" onclick="exportReport()" class="btn btn-primary">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Summary Cards -->
        <div class="row mb-4 g-4">
            <?php if ($reportType === 'inventory'): ?>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2"><?= number_format($summaryData['total_products']) ?></h3>
                        <p class="mb-0 text-secondary">Total Products</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2 text-danger"><?= number_format($summaryData['out_of_stock']) ?></h3>
                        <p class="mb-0 text-secondary">Out of Stock</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2 text-warning"><?= number_format($summaryData['low_stock']) ?></h3>
                        <p class="mb-0 text-secondary">Low Stock</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2">$<?= number_format($summaryData['inventory_value'], 2) ?></h3>
                        <p class="mb-0 text-secondary">Total Value</p>
                    </div>
                </div>
            <?php elseif ($reportType === 'movements'): ?>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2"><?= number_format($summaryData['total_movements']) ?></h3>
                        <p class="mb-0 text-secondary">Total Movements</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2 text-success"><?= number_format($summaryData['purchases']) ?></h3>
                        <p class="mb-0 text-secondary">Purchases</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2 text-primary"><?= number_format($summaryData['sales']) ?></h3>
                        <p class="mb-0 text-secondary">Sales</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2"><?= number_format($summaryData['items_in'] - $summaryData['items_out']) ?></h3>
                        <p class="mb-0 text-secondary">Net Change</p>
                    </div>
                </div>
            <?php elseif ($reportType === 'orders'): ?>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2"><?= number_format($summaryData['total_orders']) ?></h3>
                        <p class="mb-0 text-secondary">Total Orders</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2 text-primary"><?= number_format($summaryData['open_orders']) ?></h3>
                        <p class="mb-0 text-secondary">Open Orders</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2"><?= number_format($summaryData['supplier_count']) ?></h3>
                        <p class="mb-0 text-secondary">Suppliers</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2">$<?= number_format($summaryData['total_spend'], 2) ?></h3>
                        <p class="mb-0 text-secondary">Total Spend</p>
                    </div>
                </div>
            <?php elseif ($reportType === 'suppliers'): ?>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2"><?= number_format($summaryData['total_suppliers']) ?></h3>
                        <p class="mb-0 text-secondary">Active Suppliers</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2"><?= number_format($summaryData['total_orders']) ?></h3>
                        <p class="mb-0 text-secondary">Total Orders</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2"><?= number_format($summaryData['avg_delivery_days'], 1) ?> days</h3>
                        <p class="mb-0 text-secondary">Avg Delivery Time</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-glass p-3 text-center">
                        <h3 class="fs-5 fw-bold mb-2">$<?= number_format($summaryData['total_spend'], 2) ?></h3>
                        <p class="mb-0 text-secondary">Total Spend</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Report Content -->
        <div class="row g-4">
            <!-- Chart Visualization -->
            <div class="col-md-5">
                <div class="card-glass p-4 h-100">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-pie-chart-fill me-2 text-primary"></i>
                        Data Visualization
                    </h5>
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="reportChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Report Data Table -->
            <div class="col-md-7">
                <div class="card-glass p-4 h-100">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-table me-2 text-secondary"></i>
                        <?= htmlspecialchars($reportTitle) ?>
                    </h5>
                    <p class="text-secondary mb-3"><?= htmlspecialchars($reportDescription) ?></p>
                    
                    <div class="table-responsive">
                        <?php if ($reportType === 'inventory'): ?>
                            <table class="table table-custom table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Product Name</th>
                                        <th>On Hand</th>
                                        <th>Reorder Level</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['sku']) ?></td>
                                            <td><?= htmlspecialchars($item['name']) ?></td>
                                            <td><?= number_format($item['on_hand']) ?></td>
                                            <td><?= number_format($item['reorder_level']) ?></td>
                                            <td>
                                                <span class="badge <?= getStatusClass($item['stock_status']) ?>">
                                                    <?= htmlspecialchars($item['stock_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($reportData)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3">No inventory data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php elseif ($reportType === 'movements'): ?>
                            <table class="table table-custom table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $item): ?>
                                        <tr>
                                            <td><?= date('Y-m-d', strtotime($item['moved_at'])) ?></td>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td>
                                                <span class="badge <?= getStatusClass($item['movement_type']) ?>">
                                                    <?= htmlspecialchars($item['movement_type']) ?>
                                                </span>
                                            </td>
                                            <td class="<?= $item['qty'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $item['qty'] > 0 ? '+' : '' ?><?= number_format($item['qty']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($item['reference'] ?? 'N/A') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($reportData)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3">No movement data available for the selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php elseif ($reportType === 'orders'): ?>
                            <table class="table table-custom table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Supplier</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['id']) ?></td>
                                            <td><?= htmlspecialchars($item['order_date']) ?></td>
                                            <td><?= htmlspecialchars($item['supplier_name']) ?></td>
                                            <td>
                                                <span class="badge <?= getStatusClass($item['status']) ?>">
                                                    <?= htmlspecialchars($item['status']) ?>
                                                </span>
                                            </td>
                                            <td>$<?= number_format($item['total_amount'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($reportData)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3">No orders available for the selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php elseif ($reportType === 'suppliers'): ?>
                            <table class="table table-custom table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Orders</th>
                                        <th>Total Spend</th>
                                        <th>Avg Delivery</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['name']) ?></td>
                                            <td><?= number_format($item['order_count']) ?></td>
                                            <td>$<?= number_format($item['total_spend'] ?? 0, 2) ?></td>
                                            <td>
                                                <?= $item['avg_delivery_days'] ? number_format($item['avg_delivery_days'], 1) . ' days' : 'N/A' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($reportData)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3">No supplier data available for the selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="mt-auto text-center py-3 text-light">
        <div class="container d-flex justify-content-between align-items-center">
            <span>© <?=date('Y')?> WAREHOUSE MANAGEMENT SYSTEM</span>
            <span>USER: <?= htmlspecialchars($_SESSION['username']) ?></span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize chart based on report type
        document.addEventListener('DOMContentLoaded', function() {
            let ctx = document.getElementById('reportChart').getContext('2d');
            let reportType = '<?= $reportType ?>';
            let chartLabels = <?= !empty($chartLabels) ? json_encode($chartLabels) : '[]' ?>;
            let chartValues = <?= !empty($chartValues) ? json_encode($chartValues) : '[]' ?>;
            
            let chartConfig = {};
            
            switch(reportType) {
                case 'inventory':
                    chartConfig = {
                        type: 'pie',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                data: chartValues,
                                backgroundColor: [
                                    '#E74C3C',  // Out of Stock - Danger
                                    '#F9A620',  // Low Stock - Warning
                                    '#27AE60'   // In Stock - Success
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: '#E0E0E0'
                                    }
                                }
                            }
                        }
                    };
                    break;
                    
                case 'movements':
                    // Convert the PHP data to a format Chart.js can use
                    const dates = chartLabels;
                    const movementData = chartValues;
                    
                    const datasets = [];
                    const colors = {
                        'PURCHASE': '#27AE60',  // Green
                        'SALE': '#3498DB',      // Blue
                        'ADJUST': '#F9A620',    // Amber
                        'TRANSFER': '#9B59B6'   // Purple
                    };
                    
                    for (const type in movementData) {
                        const typeData = [];
                        dates.forEach(date => {
                            typeData.push(movementData[type][date] || 0);
                        });
                        
                        datasets.push({
                            label: type,
                            data: typeData,
                            backgroundColor: colors[type],
                            borderColor: colors[type],
                            borderWidth: 1
                        });
                    }
                    
                    chartConfig = {
                        type: 'bar',
                        data: {
                            labels: dates,
                            datasets: datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(255, 255, 255, 0.1)'
                                    },
                                    ticks: {
                                        color: '#E0E0E0'
                                    }
                                },
                                x: {
                                    grid: {
                                        color: 'rgba(255, 255, 255, 0.1)'
                                    },
                                    ticks: {
                                        color: '#E0E0E0'
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: '#E0E0E0'
                                    }
                                }
                            }
                        }
                    };
                    break;
                    
                case 'orders':
                    chartConfig = {
                        type: 'doughnut',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                data: chartValues,
                                backgroundColor: [
                                    '#3498DB',  // OPEN - Primary
                                    '#27AE60',  // RECEIVED - Success
                                    '#95A5A6'   // CANCELLED - Secondary
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: '#E0E0E0'
                                    }
                                }
                            }
                        }
                    };
                    break;
                    
                case 'suppliers':
                    chartConfig = {
                        type: 'bar',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Total Spend ($)',
                                data: chartValues,
                                backgroundColor: '#D2691E',
                                borderWidth: 0
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(255, 255, 255, 0.1)'
                                    },
                                    ticks: {
                                        color: '#E0E0E0'
                                    }
                                },
                                y: {
                                    grid: {
                                        color: 'rgba(255, 255, 255, 0.1)'
                                    },
                                    ticks: {
                                        color: '#E0E0E0'
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    };
                    break;
            }
            
            new Chart(ctx, chartConfig);
        });
        
        // Toggle custom date fields based on date range selection
        function toggleCustomDateFields() {
            const dateRange = document.getElementById('dateRange').value;
            const customDateFields = document.querySelectorAll('.custom-date-fields');
            
            customDateFields.forEach(field => {
                field.style.display = dateRange === 'custom' ? 'block' : 'none';
            });
            
            if (dateRange !== 'custom') {
                document.forms[0].submit();
            }
        }
        
        // Export report data to CSV
        function exportReport() {
            const reportType = '<?= $reportType ?>';
            const startDate = '<?= $startDate ?>';
            const endDate = '<?= $endDate ?>';
            
            // Create CSV content based on report type
            let csvContent = '';
            let filename = '';
            
            switch(reportType) {
                case 'inventory':
                    filename = 'inventory_report.csv';
                    // CSV headers
                    csvContent = 'SKU,Product Name,On Hand,Reorder Level,Status\n';
                    
                    // Add data rows
                    <?php foreach ($reportData as $item): ?>
                        csvContent += '<?= $item['sku'] ?>,<?= addslashes($item['name']) ?>,<?= $item['on_hand'] ?>,<?= $item['reorder_level'] ?>,<?= $item['stock_status'] ?>\n';
                    <?php endforeach; ?>
                    break;
                    
                case 'movements':
                    filename = 'stock_movements_' + startDate + '_to_' + endDate + '.csv';
                    // CSV headers
                    csvContent = 'Date,Product,Type,Quantity,Reference\n';
                    
                    // Add data rows
                    <?php foreach ($reportData as $item): ?>
                        csvContent += '<?= date('Y-m-d', strtotime($item['moved_at'])) ?>,<?= addslashes($item['product_name']) ?>,<?= $item['movement_type'] ?>,<?= $item['qty'] ?>,<?= addslashes($item['reference'] ?? 'N/A') ?>\n';
                    <?php endforeach; ?>
                    break;
                    
                case 'orders':
                    filename = 'purchase_orders_' + startDate + '_to_' + endDate + '.csv';
                    // CSV headers
                    csvContent = 'Order #,Date,Supplier,Status,Total\n';
                    
                    // Add data rows
                    <?php foreach ($reportData as $item): ?>
                        csvContent += '<?= $item['id'] ?>,<?= $item['order_date'] ?>,<?= addslashes($item['supplier_name']) ?>,<?= $item['status'] ?>,<?= $item['total_amount'] ?>\n';
                    <?php endforeach; ?>
                    break;
                    
                case 'suppliers':
                    filename = 'supplier_report_' + startDate + '_to_' + endDate + '.csv';
                    // CSV headers
                    csvContent = 'Supplier,Orders,Total Spend,Avg Delivery (days)\n';
                    
                    // Add data rows
                    <?php foreach ($reportData as $item): ?>
                        csvContent += '<?= addslashes($item['name']) ?>,<?= $item['order_count'] ?>,<?= $item['total_spend'] ?? 0 ?>,<?= $item['avg_delivery_days'] ? number_format($item['avg_delivery_days'], 1) : 'N/A' ?>\n';
                    <?php endforeach; ?>
                    break;
            }
            
            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
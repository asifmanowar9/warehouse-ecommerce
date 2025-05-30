<?php
// Only start the session if one hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';
require 'functions.php';

requireLogin();
requireAdminAccess(); // Allow both admin and staff

// For admin-only sections, check role
if (isAdmin()) {
    // Admin-only functionality here
}

// Shared admin/staff functionality

// Check if user is logged in
if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Fetch the username from the database using the user ID stored in session
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['uid']]);
$user = $stmt->fetch();
$username = $user ? $user['username'] : 'Unknown User';

// Store username in session for future use
$_SESSION['username'] = $username;

// Get current date in the required format for queries
$today = date('Y-m-d');

// Get real-time data for dashboard
// 1. Get products below reorder level for alerts
$lowStockQuery = $pdo->prepare("
    SELECT p.*, v.on_hand 
    FROM products p 
    JOIN v_product_stock v ON p.id = v.id 
    WHERE v.on_hand <= p.reorder_level 
    ORDER BY (p.reorder_level - v.on_hand) DESC 
    LIMIT 10
");
$lowStockQuery->execute();
$lowStockProducts = $lowStockQuery->fetchAll();
$lowStockCount = count($lowStockProducts);

// 2. Get incoming purchase orders for today
$incomingPoQuery = $pdo->prepare("
    SELECT po.id, po.order_date, s.name as supplier_name 
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.status = 'OPEN' 
    AND po.order_date <= ?
    ORDER BY po.order_date ASC
    LIMIT 10
");
$incomingPoQuery->execute([$today]);
$incomingPurchaseOrders = $incomingPoQuery->fetchAll();
$incomingCount = count($incomingPurchaseOrders);

// 3. Get recent stock movements for alerts
$recentMovementsQuery = $pdo->prepare("
    SELECT sm.*, p.name as product_name, l.code as location_code, u.username
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    LEFT JOIN locations l ON sm.location_id = l.id
    LEFT JOIN users u ON sm.moved_by = u.id
    ORDER BY sm.moved_at DESC
    LIMIT 5
");
$recentMovementsQuery->execute();
$recentMovements = $recentMovementsQuery->fetchAll();

// 4. Get safety alerts - simulate with location issues
$locationIssuesQuery = $pdo->prepare("
    SELECT l.*, COUNT(sm.id) as movement_count
    FROM locations l
    LEFT JOIN stock_movements sm ON l.id = sm.location_id
    GROUP BY l.id
    ORDER BY movement_count DESC
    LIMIT 3
");
$locationIssuesQuery->execute();
$locationIssues = $locationIssuesQuery->fetchAll();

// 5. Get activities scheduled for today - simulated from stock movements and POs
$scheduledActivities = [];

// Morning inventory check - always present
$scheduledActivities[] = [
    'time' => '08:30',
    'activity' => 'Morning inventory check',
    'location' => 'ZONE B',
    'type' => 'daily'
];

// Add incoming deliveries to schedule
foreach($incomingPurchaseOrders as $index => $po) {
    // Stagger delivery times 
    $hour = 9 + $index;
    $scheduledActivities[] = [
        'time' => sprintf('%02d:00', $hour),
        'activity' => "Supplier delivery ({$po['supplier_name']})",
        'location' => 'DOCK ' . ($index + 1),
        'type' => 'delivery',
        'po_id' => $po['id']
    ];
    
    // Only add 3 max to the schedule
    if ($index >= 2) break;
}

// Add outbound shipment if we have stock movements
if (!empty($recentMovements)) {
    foreach($recentMovements as $index => $movement) {
        if ($movement['movement_type'] == 'SALE' || $movement['qty'] < 0) {
            $scheduledActivities[] = [
                'time' => '14:30',
                'activity' => 'Outbound shipment preparation',
                'location' => $movement['location_code'] ? $movement['location_code'] : 'ZONE A',
                'type' => 'outbound'
            ];
            break;
        }
    }
}

// Sort activities by time
usort($scheduledActivities, function($a, $b) {
    return strtotime($a['time']) - strtotime($b['time']);
});

// Limit to 3 activities
$scheduledActivities = array_slice($scheduledActivities, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Include the navbar -->
    <?php include 'includes/navbar.php'; ?>

    <header>
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">OPERATIONS DASHBOARD</h1>
            <p class="lead mb-0">Warehouse #WH-<?= sprintf('%03d', rand(100, 999)) ?> | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <div class="row g-4">
            <div class="col-md-4">
                <a href="inventory.php" class="text-decoration-none">
                    <div class="card-glass p-4 h-100 text-center">
                        <div class="industrial-icon">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                        <h4 class="fw-bold mb-2">INVENTORY</h4>
                        <p>Manage stock levels, products, and storage locations.</p>
                        <div class="mt-3">
                            <span class="badge bg-warning"><?= $lowStockCount ?> LOW STOCK</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="suppliers.php" class="text-decoration-none">
                    <div class="card-glass p-4 h-100 text-center">
                        <div class="industrial-icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h4 class="fw-bold mb-2">SUPPLIERS</h4>
                        <p>Track vendors, purchase orders, and shipments.</p>
                        <div class="mt-3">
                            <span class="badge bg-success"><?= $incomingCount ?> INCOMING</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="reports.php" class="text-decoration-none">
                    <div class="card-glass p-4 h-100 text-center">
                        <div class="industrial-icon">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <h4 class="fw-bold mb-2">REPORTS</h4>
                        <p>Generate logistics data, usage metrics and audit logs.</p>
                        <div class="mt-3">
                            <span class="badge bg-secondary">MONTHLY REPORTS</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-6">
                <div class="card-glass p-4 h-100">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
                        ALERTS
                    </h5>
                    <ul class="list-group list-group-flush">
                        <?php if (!empty($locationIssues)): ?>
                            <li class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span>Shelf <?= htmlspecialchars($locationIssues[0]['code']) ?> requires inspection</span>
                                <span class="badge bg-warning">SAFETY</span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($lowStockCount > 0): ?>
                            <li class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span><?= $lowStockCount ?> products below reorder level</span>
                                <span class="badge bg-danger">STOCK</span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($incomingCount > 0): ?>
                            <li class="d-flex justify-content-between align-items-center py-2">
                                <span>Order #<?= $incomingPurchaseOrders[0]['id'] ?> arriving today</span>
                                <span class="badge bg-primary">LOGISTICS</span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (empty($locationIssues) && $lowStockCount == 0 && $incomingCount == 0): ?>
                            <li class="py-2">
                                <span>No alerts at this time</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-glass p-4 h-100">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-calendar-check-fill me-2 text-secondary"></i>
                        TODAY'S SCHEDULE
                    </h5>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($scheduledActivities as $index => $activity): ?>
                            <li class="d-flex justify-content-between align-items-center <?= $index < count($scheduledActivities) - 1 ? 'border-bottom' : '' ?> py-2">
                                <span><?= htmlspecialchars($activity['time']) ?> - <?= htmlspecialchars($activity['activity']) ?></span>
                                <span><?= htmlspecialchars($activity['location']) ?></span>
                            </li>
                        <?php endforeach; ?>
                        
                        <?php if (empty($scheduledActivities)): ?>
                            <li class="py-2">
                                <span>No activities scheduled for today</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <?php if (isAdmin()): ?>
            <!-- Staff Management - Only visible to admins -->
            <div class="card-glass p-4 mb-4">
                <h4 class="fw-bold mb-3">STAFF MANAGEMENT</h4>
                <p>Manage staff accounts and permissions</p>
                <a href="staff_management.php" class="btn btn-primary">
                    <i class="bi bi-people-fill me-1"></i> Manage Staff
                </a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="mt-auto text-center py-3 text-light">
        <div class="container d-flex justify-content-between align-items-center">
            <span>© <?=date('Y')?> WAREHOUSE MANAGEMENT SYSTEM</span>
            <span>USER: <?= htmlspecialchars($_SESSION['username']) ?></span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
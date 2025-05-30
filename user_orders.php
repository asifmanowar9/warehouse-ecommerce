<?php
require 'db.php';

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Fetch the username from the session
$username = $_SESSION['username'] ?? 'Guest';
$userId = $_SESSION['uid'];

// Get all orders for the user
try {
    $stmt = $pdo->prepare("SELECT *, 
                         (SELECT COUNT(*) FROM order_items WHERE order_id = orders.id) as item_count
                         FROM orders 
                         WHERE user_id = ? 
                         ORDER BY order_date DESC");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $orders = [];
    $errorMsg = "Database error: " . $e->getMessage();
}

// Get cart count for navigation badge
$cartCount = 0;
if (!empty($_SESSION['uid'])) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['uid']]);
        $cartCount = $stmt->fetchColumn();
    } catch(PDOException $e) {
        // Silently handle error
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Warehouse Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- User Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">            <a href="user_home.php" class="navbar-brand fw-bold">
                <span class="logo-circle me-2">W</span>WAREHOUSE STORE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto me-3">
                    <li class="nav-item">
                        <a class="nav-link" href="user_home.php">HOME</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user_products.php">PRODUCTS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="user_orders.php">MY ORDERS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="user_cart.php">
                            CART
                            <?php if ($cartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $cartCount ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <a class="btn btn-outline-light" href="logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i> LOGOUT
                </a>
            </div>
        </div>
    </nav>

    <header>
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">MY ORDERS</h1>
            <p class="lead mb-0">Order history and tracking | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($orders)): ?>
            <div class="card-glass p-4 mb-4">
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>ORDER #</th>
                                <th>DATE</th>
                                <th>ITEMS</th>
                                <th>TOTAL</th>
                                <th>STATUS</th>
                                <th class="text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?= $order['id'] ?></strong></td>
                                    <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                    <td><?= $order['item_count'] ?> items</td>
                                    <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'bg-primary';
                                        if ($order['status'] === 'COMPLETED') {
                                            $statusClass = 'bg-success';
                                        } elseif ($order['status'] === 'CANCELLED') {
                                            $statusClass = 'bg-danger';
                                        } elseif ($order['status'] === 'SHIPPED') {
                                            $statusClass = 'bg-info';
                                        }
                                        ?>
                                        <span class="badge <?= $statusClass ?> rounded-pill"><?= $order['status'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="user_order_details.php?id=<?= $order['id'] ?>" class="btn btn-outline-light btn-sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card-glass p-5 text-center">
                <i class="bi bi-bag-x text-muted" style="font-size: 4rem;"></i>
                <h3 class="mt-4 mb-3">No Orders Found</h3>
                <p class="mb-4">You haven't placed any orders yet.</p>
                <a href="user_products.php" class="btn btn-primary">
                    <i class="bi bi-shop me-1"></i> Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="mt-auto text-center py-3 text-light">
        <div class="container d-flex justify-content-between align-items-center">
            <span>© <?= date('Y') ?> WAREHOUSE MANAGEMENT SYSTEM</span>
            <span>USER: <?= htmlspecialchars($username) ?></span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
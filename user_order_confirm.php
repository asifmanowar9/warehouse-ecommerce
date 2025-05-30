<?php
// filepath: d:\xampp\htdocs\hema3\user_order_confirm.php
require 'db.php';

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Fetch the username from the session
$username = $_SESSION['username'] ?? 'Guest';
$userId = $_SESSION['uid'];

// Get order ID from query string
$orderId = intval($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    header('Location: user_orders.php');
    exit;
}

// Get order details
try {
    // Check if order belongs to user
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $errorMsg = "Order not found";
    } else {
        // Get order items
        $stmt = $pdo->prepare("SELECT oi.*, p.name, p.sku, p.image_path 
                              FROM order_items oi
                              JOIN products p ON oi.product_id = p.id
                              WHERE oi.order_id = ?");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $subtotal = 0;
        $itemCount = count($orderItems);
        
        foreach ($orderItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // Shipping cost is 10
        $shippingCost = 10;
    }
    
} catch(PDOException $e) {
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
    <title>Order Confirmation - Warehouse Store</title>
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
                        <a class="nav-link" href="user_orders.php">MY ORDERS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="user_cart.php">
                            CART
                            <?php if ($cartCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $cartCount ?>
                                    <span class="visually-hidden">items in cart</span>
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
            <h1 class="display-5 fw-bold mb-3">ORDER CONFIRMATION</h1>
            <p class="lead mb-0">Thank you for your purchase | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php elseif (isset($order)): ?>
            <div class="card-glass p-4 mb-4">
                <div class="text-center mb-4">
                    <div class="industrial-icon mb-3" style="font-size: 3rem;">
                        <i class="bi bi-check-circle-fill text-success"></i>
                    </div>
                    <h2 class="fw-bold">Thank You!</h2>
                    <p class="lead">Your order has been placed successfully.</p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3">Order Details</h5>
                        <div class="mb-2">
                            <strong>Order #:</strong> <?= $orderId ?>
                        </div>
                        <div class="mb-2">
                            <strong>Date:</strong> <?= date('F j, Y', strtotime($order['order_date'])) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong> 
                            <span class="badge bg-primary"><?= $order['status'] ?></span>
                        </div>
                        <div>
                            <strong>Payment Method:</strong> <?= $order['payment_method'] ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3">Shipping Address</h5>
                        <address>
                            <?= htmlspecialchars($order['shipping_address']) ?><br>
                            <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> <?= htmlspecialchars($order['shipping_zipcode']) ?>
                        </address>
                    </div>
                </div>
                
                <h5 class="fw-bold mb-3">Order Summary</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>PRODUCT</th>
                                <th>PRICE</th>
                                <th class="text-center">QUANTITY</th>
                                <th class="text-end">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="img-thumbnail me-3" style="max-height: 50px; max-width: 50px;">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                                    <i class="bi bi-image text-light"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($item['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($item['sku']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>$<?= number_format($item['price'], 2) ?></td>
                                    <td class="text-center"><?= $item['quantity'] ?></td>
                                    <td class="text-end">$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end">Subtotal:</td>
                                <td class="text-end">$<?= number_format($subtotal, 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end">Shipping:</td>
                                <td class="text-end">$<?= number_format($shippingCost, 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total:</td>
                                <td class="text-end fw-bold text-primary">$<?= number_format($order['total_amount'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="text-center">
                    <p>An email with your order details has been sent to your registered email address.</p>
                    <div class="mt-4">
                        <a href="user_orders.php" class="btn btn-primary me-2">
                            <i class="bi bi-list-ul me-1"></i> View All Orders
                        </a>
                        <a href="user_home.php" class="btn btn-outline-light">
                            <i class="bi bi-house me-1"></i> Return to Home
                        </a>
                    </div>
                </div>
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
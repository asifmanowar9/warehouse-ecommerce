<?php
require 'db.php';

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Fetch the username from the session
$username = $_SESSION['username'] ?? 'Guest';
$userId = $_SESSION['uid'];

// Get order ID from query string
$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header('Location: user_orders.php');
    exit; // Fixed typo here
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
        $stmt = $pdo->prepare("SELECT oi.*, p.name, p.sku, p.image_path, p.description,
                              COALESCE(s.name, 'N/A') as supplier
                              FROM order_items oi
                              JOIN products p ON oi.product_id = p.id
                              LEFT JOIN suppliers s ON p.supplier_id = s.id
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
        
        // Check for cart count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cartCount = $stmt->fetchColumn();
    }
    
} catch(PDOException $e) {
    $errorMsg = "Database error: " . $e->getMessage();
}

// Get order status history
try {
    $stmt = $pdo->prepare("SELECT * FROM order_status_history 
                          WHERE order_id = ? 
                          ORDER BY timestamp DESC");
    $stmt->execute([$orderId]);
    $statusHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $statusHistory = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Warehouse Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- User Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand fw-bold">
                <span class="logo-circle me-2">W</span>WAREHOUSE STORE
            </span>
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
            <h1 class="display-5 fw-bold mb-3">ORDER DETAILS</h1>
            <p class="lead mb-0">Order #<?= $orderId ?> | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php elseif (isset($order)): ?>
            <div class="card-glass p-4 mb-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3">Order Information</h5>
                        <div class="mb-2">
                            <strong>Order #:</strong> <?= $orderId ?>
                        </div>
                        <div class="mb-2">
                            <strong>Date:</strong> <?= date('F j, Y', strtotime($order['order_date'])) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong> 
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
                            <span class="badge <?= $statusClass ?>"><?= $order['status'] ?></span>
                        </div>
                        <div class="mb-2">
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
                
                <?php if (!empty($statusHistory)): ?>
                <div class="mb-4">
                    <h5 class="fw-bold mb-3">Order Status History</h5>
                    <ul class="list-group card-glass-inner">
                        <?php foreach ($statusHistory as $status): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-light text-dark me-2"><?= date('M j, Y g:i A', strtotime($status['timestamp'])) ?></span>
                                Status changed to <strong><?= $status['status'] ?></strong>
                            </div>
                            <?php if (!empty($status['notes'])): ?>
                            <span class="text-muted"><?= htmlspecialchars($status['notes']) ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <h5 class="fw-bold mb-3">Order Items</h5>
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
                                                <?php if (!empty($item['supplier']) && $item['supplier'] != 'N/A'): ?>
                                                <div class="small text-muted">By: <?= htmlspecialchars($item['supplier']) ?></div>
                                                <?php endif; ?>
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
                
                <div class="d-flex justify-content-between">
                    <a href="user_orders.php" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left me-1"></i> Back to Orders
                    </a>
                    
                    <?php if ($order['status'] === 'PENDING'): ?>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                        <i class="bi bi-x-circle me-1"></i> Cancel Order
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card-glass p-5 text-center">
                <i class="bi bi-exclamation-circle text-warning" style="font-size: 4rem;"></i>
                <h3 class="mt-4 mb-3">Order Not Found</h3>
                <p class="mb-4">The requested order could not be found or you don't have permission to view it.</p>
                <a href="user_orders.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-1"></i> Return to Orders
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

    <!-- Cancel Order Modal -->
    <?php if (isset($order) && $order['status'] === 'PENDING'): ?>
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Cancel Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-question-circle text-warning" style="font-size: 3rem;"></i>
                    <p class="my-3">Are you sure you want to cancel this order?</p>
                    <p class="small text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">No, Keep Order</button>
                    <button type="button" id="confirmCancelOrderBtn" class="btn btn-danger" data-order-id="<?= $orderId ?>">
                        Yes, Cancel Order
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Cancel Order Functionality
            const cancelBtn = document.getElementById('confirmCancelOrderBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    const orderId = this.dataset.orderId;
                    
                    fetch('user_order_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=cancel_order&order_id=${orderId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while cancelling the order.');
                    });
                });
            }
        });
    </script>
</body>
</html>
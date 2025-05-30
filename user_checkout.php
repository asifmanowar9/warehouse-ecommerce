<?php
require 'db.php';

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Fetch the username from the session
$username = $_SESSION['username'] ?? 'Guest';
$userId = $_SESSION['uid'];

// Get cart items
try {
    $stmt = $pdo->prepare("SELECT c.id as cart_id, c.quantity, p.*, 
                          v.on_hand as stock, s.name as supplier,
                          (p.unit_price * c.quantity) as subtotal 
                          FROM cart c
                          JOIN products p ON c.product_id = p.id
                          LEFT JOIN v_product_stock v ON p.id = v.id
                          LEFT JOIN suppliers s ON p.supplier_id = s.id
                          WHERE c.user_id = ?");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate cart totals
    $cartTotal = 0;
    $itemCount = count($cartItems);
    
    foreach ($cartItems as $item) {
        $cartTotal += $item['subtotal'];
    }
    
    // Add shipping cost
    $shippingCost = 10;
    $orderTotal = $cartTotal + $shippingCost;
    
} catch(PDOException $e) {
    $cartItems = [];
    $cartTotal = 0;
    $itemCount = 0;
    $orderTotal = 0;
    $errorMsg = "Database error: " . $e->getMessage();
}

// Process order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Get form data
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zipcode = trim($_POST['zipcode'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        
        // Validate form data
        if (empty($fullName) || empty($email) || empty($address) || empty($city) || empty($zipcode) || empty($paymentMethod)) {
            $errorMsg = "Please fill out all required fields";
        } else if (empty($cartItems)) {
            $errorMsg = "Your cart is empty";
        } else {
            // Create order
            $stmt = $pdo->prepare("INSERT INTO orders 
                                  (user_id, order_date, shipping_address, shipping_city, shipping_state,
                                  shipping_zipcode, payment_method, total_amount, status) 
                                  VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, 'PENDING')");
            $stmt->execute([
                $userId, 
                $address, 
                $city, 
                $state, 
                $zipcode, 
                $paymentMethod, 
                $orderTotal
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Add order items
            foreach ($cartItems as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items 
                                      (order_id, product_id, quantity, price) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $orderId, 
                    $item['id'], 
                    $item['quantity'], 
                    $item['unit_price']
                ]);
                
                // Update stock in stock_movements table
                $stmt = $pdo->prepare("INSERT INTO stock_movements 
                                      (product_id, movement_type, qty, reference, moved_by) 
                                      VALUES (?, 'SALE', ?, ?, ?)");
                $stmt->execute([
                    $item['id'],
                    $item['quantity'],
                    'Order #' . $orderId,
                    $userId
                ]);
            }
            
            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $pdo->commit();
            
            // Redirect to order confirmation
            header("Location: user_order_confirm.php?order_id=" . $orderId);
            exit;
        }
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $errorMsg = "Order processing error: " . $e->getMessage();
    }
}

// Get cart count for navigation badge (already available in this file as $itemCount)
$cartCount = $itemCount;

// Include the navbar
include 'includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Warehouse Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <header>
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">CHECKOUT</h1>
            <p class="lead mb-0">Complete your purchase | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($itemCount > 0): ?>
            <form method="post" action="user_checkout.php">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card-glass p-4 mb-4">
                            <h5 class="fw-bold mb-4">SHIPPING INFORMATION</h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="full_name" class="form-label">Full Name*</label>
                                    <input type="text" class="form-control search-box" id="full_name" name="full_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address*</label>
                                    <input type="email" class="form-control search-box" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control search-box" id="phone" name="phone">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address*</label>
                                <input type="text" class="form-control search-box" id="address" name="address" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="city" class="form-label">City*</label>
                                    <input type="text" class="form-control search-box" id="city" name="city" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="state" class="form-label">State/Province</label>
                                    <input type="text" class="form-control search-box" id="state" name="state">
                                </div>
                                <div class="col-md-4">
                                    <label for="zipcode" class="form-label">ZIP/Postal Code*</label>
                                    <input type="text" class="form-control search-box" id="zipcode" name="zipcode" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-glass p-4 mb-4">
                            <h5 class="fw-bold mb-4">PAYMENT METHOD</h5>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="CREDIT_CARD" checked>
                                <label class="form-check-label" for="credit_card">
                                    <i class="bi bi-credit-card me-2"></i> Credit/Debit Card
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="PAYPAL">
                                <label class="form-check-label" for="paypal">
                                    <i class="bi bi-paypal me-2"></i> PayPal
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="cod" value="COD">
                                <label class="form-check-label" for="cod">
                                    <i class="bi bi-cash-coin me-2"></i> Cash on Delivery
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="user_cart.php" class="btn btn-outline-light">
                                <i class="bi bi-arrow-left me-1"></i> Back to Cart
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card-glass p-4 mb-4">
                            <h5 class="fw-bold mb-4">ORDER SUMMARY</h5>
                            
                            <div class="mb-3">
                                <div class="small text-muted mb-2">ITEMS (<?= $itemCount ?>)</div>
                                <?php foreach ($cartItems as $item): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <div>
                                            <span class="fw-bold"><?= $item['quantity'] ?>x</span> <?= htmlspecialchars(substr($item['name'], 0, 20)) ?><?= strlen($item['name']) > 20 ? '...' : '' ?>
                                        </div>
                                        <div>$<?= number_format($item['subtotal'], 2) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <hr class="border-secondary">
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span>$<?= number_format($cartTotal, 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping</span>
                                <span>$<?= number_format($shippingCost, 2) ?></span>
                            </div>
                            <hr class="border-secondary">
                            <div class="d-flex justify-content-between mb-4 fw-bold">
                                <span>Total</span>
                                <span class="text-primary fs-5">$<?= number_format($orderTotal, 2) ?></span>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-lock-fill me-1"></i> Complete Order
                            </button>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-shield-lock me-1"></i> Your payment information is encrypted
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="card-glass p-5 text-center">
                <i class="bi bi-cart-x text-muted" style="font-size: 4rem;"></i>
                <h3 class="mt-4 mb-3">Your cart is empty</h3>
                <p class="mb-4">You need to add products to your cart before checking out.</p>
                <a href="user_products.php" class="btn btn-primary">
                    <i class="bi bi-shop me-1"></i> Browse Products
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
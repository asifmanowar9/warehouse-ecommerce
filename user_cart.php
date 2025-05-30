<?php

require 'db.php';

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Fetch the username from the session
$username = $_SESSION['username'] ?? 'Guest';

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
    $stmt->execute([$_SESSION['uid']]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate cart totals
    $cartTotal = 0;
    $itemCount = count($cartItems);
    
    foreach ($cartItems as $item) {
        $cartTotal += $item['subtotal'];
    }
    
} catch(PDOException $e) {
    $cartItems = [];
    $cartTotal = 0;
    $itemCount = 0;
    $errorMsg = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Warehouse Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- User Navigation Bar -->
    <?php
    // Get cart count for navigation badge (already available in this file as $itemCount)
    $cartCount = $itemCount;

    // Include the navbar
    include 'includes/navbar.php';
    ?>

    <header>
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">YOUR SHOPPING CART</h1>
            <p class="lead mb-0">Review your items | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= $errorMsg ?>
            </div>
        <?php endif; ?>
        
        <?php if ($itemCount > 0): ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card-glass p-4 mb-4">
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>PRODUCT</th>
                                        <th>PRICE</th>
                                        <th class="text-center">QUANTITY</th>
                                        <th class="text-end">SUBTOTAL</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartItems as $item): ?>
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
                                            <td>$<?= number_format($item['unit_price'], 2) ?></td>
                                            <td class="text-center">
                                                <div class="input-group input-group-sm quantity-control" style="width: 120px;">
                                                    <button class="btn btn-outline-light quantity-btn" data-action="decrease" data-id="<?= $item['cart_id'] ?>">-</button>
                                                    <input type="text" class="form-control text-center search-box" value="<?= $item['quantity'] ?>" readonly>
                                                    <button class="btn btn-outline-light quantity-btn" data-action="increase" data-id="<?= $item['cart_id'] ?>" <?= $item['quantity'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold">$<?= number_format($item['subtotal'], 2) ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-outline-danger btn-sm remove-item" data-id="<?= $item['cart_id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="user_products.php" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left me-1"></i> Continue Shopping
                        </a>
                        <button id="clearCartBtn" class="btn btn-outline-danger">
                            <i class="bi bi-trash me-1"></i> Clear Cart
                        </button>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card-glass p-4">
                        <h5 class="fw-bold mb-4">ORDER SUMMARY</h5>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span>$<?= number_format($cartTotal, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <span>$<?= number_format(10, 2) ?></span>
                        </div>
                        <hr class="border-secondary">
                        <div class="d-flex justify-content-between mb-4 fw-bold">
                            <span>Total</span>
                            <span class="text-primary fs-5">$<?= number_format($cartTotal + 10, 2) ?></span>
                        </div>
                        
                        <a href="user_checkout.php" class="btn btn-primary w-100">
                            <i class="bi bi-credit-card me-1"></i> Proceed to Checkout
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card-glass p-5 text-center">
                <i class="bi bi-cart-x text-muted" style="font-size: 4rem;"></i>
                <h3 class="mt-4 mb-3">Your cart is empty</h3>
                <p class="mb-4">Looks like you haven't added any products to your cart yet.</p>
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

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-question-circle text-warning" style="font-size: 3rem;"></i>
                    <p class="my-3" id="confirmMessage">Are you sure you want to perform this action?</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmBtn" class="btn btn-danger">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            let currentAction = null;
            let currentId = null;
            
            // Quantity adjustment
            document.querySelectorAll('.quantity-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const cartId = this.dataset.id;
                    
                    fetch('user_cart_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=update_quantity&cart_id=${cartId}&adjustment=${action === 'increase' ? 1 : -1}`
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
                        alert('An error occurred while updating the cart.');
                    });
                });
            });
            
            // Remove item from cart
            document.querySelectorAll('.remove-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    const cartId = this.dataset.id;
                    document.getElementById('confirmMessage').textContent = 'Are you sure you want to remove this item from your cart?';
                    currentAction = 'remove';
                    currentId = cartId;
                    confirmModal.show();
                });
            });
            
            // Clear entire cart
            document.getElementById('clearCartBtn').addEventListener('click', function() {
                document.getElementById('confirmMessage').textContent = 'Are you sure you want to clear your entire cart?';
                currentAction = 'clear';
                confirmModal.show();
            });
            
            // Confirmation button click
            document.getElementById('confirmBtn').addEventListener('click', function() {
                let url, body;
                
                if (currentAction === 'remove') {
                    url = 'user_cart_actions.php';
                    body = `action=remove_item&cart_id=${currentId}`;
                } else if (currentAction === 'clear') {
                    url = 'user_cart_actions.php';
                    body = 'action=clear_cart';
                }
                
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: body
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
                    alert('An error occurred during the operation.');
                });
                
                confirmModal.hide();
            });
        });
    </script>
</body>
</html>
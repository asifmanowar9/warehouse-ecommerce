<?php
// Start the session if one hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Fetch the username from the session
$username = $_SESSION['username'] ?? 'Guest';

// Get featured products (newest or featured items)
try {
    $stmt = $pdo->prepare("SELECT p.*, v.on_hand as stock, s.name as supplier 
                         FROM products p 
                         LEFT JOIN v_product_stock v ON p.id = v.id
                         LEFT JOIN suppliers s ON p.supplier_id = s.id
                         ORDER BY p.id DESC LIMIT 6");
    $stmt->execute();
    $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $featuredProducts = [];
    $errorMsg = "Database error: " . $e->getMessage();
}

// Get bestselling products
try {
    $stmt = $pdo->prepare("SELECT p.*, v.on_hand as stock, COUNT(sm.id) as sales_count, s.name as supplier 
                         FROM products p 
                         LEFT JOIN v_product_stock v ON p.id = v.id
                         LEFT JOIN stock_movements sm ON p.id = sm.product_id AND sm.movement_type = 'SALE'
                         LEFT JOIN suppliers s ON p.supplier_id = s.id
                         GROUP BY p.id
                         ORDER BY sales_count DESC LIMIT 4");
    $stmt->execute();
    $bestsellingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $bestsellingProducts = [];
}

// Check if there are any items in the user's cart
$cartCount = 0;
if (!empty($_SESSION['uid'])) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['uid']]);
        $cartCount = $stmt->fetchColumn();
    } catch(PDOException $e) {
        // Silently fail
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Store - Home</title>
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
            <h1 class="display-5 fw-bold mb-3">WELCOME TO OUR STORE</h1>
            <p class="lead mb-0">Browse our latest products | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <!-- Hero Banner -->
        <div class="card-glass mb-5">
            <div class="row g-0">
                <div class="col-md-8">
                    <div class="p-4 p-md-5">
                        <h2 class="fw-bold mb-3">Premium Quality Warehouse Products</h2>
                        <p class="mb-4">Explore our extensive catalog of high-quality products with fast shipping and competitive prices.</p>
                        <a href="user_products.php" class="btn btn-primary">SHOP NOW</a>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-center justify-content-center p-4">
                    <div class="industrial-icon" style="font-size: 5rem;">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Featured Products -->
        <h3 class="fw-bold mb-4">FEATURED PRODUCTS</h3>
        <div class="row g-4 mb-5">
            <?php if (count($featuredProducts) > 0): ?>
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="col-md-4 col-lg-2">
                        <div class="card-glass h-100 product-card">
                            <div class="text-center pt-3">
                                <?php if (!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-img">
                                <?php else: ?>
                                    <div class="placeholder-img d-flex align-items-center justify-content-center">
                                        <i class="bi bi-image text-light"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title fw-bold mb-1"><?= htmlspecialchars($product['name']) ?></h5>
                                <p class="card-text small text-muted mb-2"><?= substr(htmlspecialchars($product['description'] ?? ''), 0, 50) ?><?= strlen($product['description'] ?? '') > 50 ? '...' : '' ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <span class="fw-bold text-primary">$<?= number_format($product['unit_price'], 2) ?></span>
                                    <?php
                                    $stockClass = 'bg-success';
                                    if ($product['stock'] <= 0) {
                                        $stockClass = 'bg-danger';
                                    } elseif ($product['stock'] <= $product['reorder_level']) {
                                        $stockClass = 'bg-warning';
                                    }
                                    ?>
                                    <span class="badge <?= $stockClass ?> rounded-pill">
                                        <?= $product['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                                    </span>
                                </div>
                                
                                <a href="user_product_detail.php?id=<?= $product['id'] ?>" class="btn btn-outline-light mt-3">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Bestselling Products -->
        <h3 class="fw-bold mb-4">BESTSELLING PRODUCTS</h3>
        <div class="row g-4">
            <?php if (count($bestsellingProducts) > 0): ?>
                <?php foreach ($bestsellingProducts as $product): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card-glass h-100 product-card">
                            <div class="text-center pt-3">
                                <?php if (!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-img">
                                <?php else: ?>
                                    <div class="placeholder-img d-flex align-items-center justify-content-center">
                                        <i class="bi bi-image text-light"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title fw-bold mb-1"><?= htmlspecialchars($product['name']) ?></h5>
                                <p class="card-text small text-muted mb-2"><?= substr(htmlspecialchars($product['description'] ?? ''), 0, 60) ?><?= strlen($product['description'] ?? '') > 60 ? '...' : '' ?></p>
                                
                                <?php if ($product['supplier']): ?>
                                    <p class="small mb-2">
                                        <span class="text-muted">Supplier:</span> 
                                        <?= htmlspecialchars($product['supplier']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <span class="fw-bold text-primary fs-5">$<?= number_format($product['unit_price'], 2) ?></span>
                                    <?php
                                    $stockClass = 'bg-success';
                                    $stockText = 'In Stock';
                                    if ($product['stock'] <= 0) {
                                        $stockClass = 'bg-danger';
                                        $stockText = 'Out of Stock';
                                    } elseif ($product['stock'] <= $product['reorder_level']) {
                                        $stockClass = 'bg-warning';
                                        $stockText = 'Low Stock';
                                    }
                                    ?>
                                    <span class="badge <?= $stockClass ?> rounded-pill">
                                        <?= $stockText ?>
                                    </span>
                                </div>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <a href="user_product_detail.php?id=<?= $product['id'] ?>" class="btn btn-outline-light">View Details</a>
                                    <?php if ($product['stock'] > 0): ?>
                                        <button type="button" class="btn btn-primary add-to-cart-btn" data-product-id="<?= $product['id'] ?>">
                                            <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary" disabled>
                                            <i class="bi bi-cart-x me-1"></i> Out of Stock
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted">No bestselling products to display</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="mt-auto text-center py-3 text-light">
        <div class="container d-flex justify-content-between align-items-center">
            <span>© <?= date('Y') ?> WAREHOUSE MANAGEMENT SYSTEM</span>
            <span>USER: <?= htmlspecialchars($username) ?></span>
        </div>
    </footer>

    <!-- Add to Cart Success Modal -->
    <div class="modal fade" id="addToCartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Success</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <p class="my-3">Product added to your cart!</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Continue Shopping</button>
                    <a href="user_cart.php" class="btn btn-primary">View Cart</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add to Cart functionality
        document.addEventListener('DOMContentLoaded', function() {
            const addToCartBtns = document.querySelectorAll('.add-to-cart-btn');
            const addToCartModal = new bootstrap.Modal(document.getElementById('addToCartModal'));
            
            addToCartBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    
                    // Add to cart via AJAX
                    fetch('user_cart_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=add_to_cart&product_id=' + productId + '&quantity=1'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show the success modal
                            addToCartModal.show();
                            
                            // Update cart badge count
                            const cartBadge = document.querySelector('.nav-link.position-relative .badge');
                            if (cartBadge) {
                                cartBadge.textContent = data.cart_count;
                            } else {
                                // Create new badge if it doesn't exist
                                const cartLink = document.querySelector('.nav-link.position-relative');
                                if (cartLink) {
                                    const newBadge = document.createElement('span');
                                    newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                                    newBadge.textContent = data.cart_count;
                                    cartLink.appendChild(newBadge);
                                }
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while adding the product to your cart.');
                    });
                });
            });
        });
    </script>
</body>
</html>
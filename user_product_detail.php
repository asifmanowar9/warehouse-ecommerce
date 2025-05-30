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

// Get product ID from query string
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    header('Location: user_products.php');
    exit;
}

// Get product details
try {
    $stmt = $pdo->prepare("SELECT p.*, v.on_hand as stock, s.name as supplier 
                         FROM products p 
                         LEFT JOIN v_product_stock v ON p.id = v.id
                         LEFT JOIN suppliers s ON p.supplier_id = s.id
                         WHERE p.id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $errorMsg = "Product not found";
    }
} catch(PDOException $e) {
    $errorMsg = "Database error: " . $e->getMessage();
}

// Get similar products (based on same category or supplier)
try {
    $similarProductsQuery = "SELECT p.*, v.on_hand as stock
                           FROM products p 
                           LEFT JOIN v_product_stock v ON p.id = v.id
                           WHERE p.id != ?";
    
    $params = [$productId];
    
    if (!empty($product['category'])) {
        $similarProductsQuery .= " AND p.category = ?";
        $params[] = $product['category'];
    } else if (!empty($product['supplier_id'])) {
        $similarProductsQuery .= " AND p.supplier_id = ?";
        $params[] = $product['supplier_id'];
    }
    
    $similarProductsQuery .= " ORDER BY RAND() LIMIT 4";
    
    $stmt = $pdo->prepare($similarProductsQuery);
    $stmt->execute($params);
    $similarProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $similarProducts = [];
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
    <title><?= isset($product) ? htmlspecialchars($product['name']) : 'Product Details' ?> - Warehouse Store</title>
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
            <h1 class="display-5 fw-bold mb-3">PRODUCT DETAILS</h1>
            <p class="lead mb-0">View specifications | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
            <div class="text-center mt-4">
                <a href="user_products.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-1"></i> Return to Products
                </a>
            </div>
        <?php elseif (isset($product)): ?>
            <!-- Product Details -->
            <div class="card-glass mb-5">
                <div class="row g-0">
                    <div class="col-md-5">
                        <div class="product-detail-img-container p-4 text-center d-flex align-items-center justify-content-center">
                            <?php if (!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                                <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-detail-img">
                            <?php else: ?>
                                <div class="placeholder-img-large d-flex align-items-center justify-content-center">
                                    <i class="bi bi-image text-light" style="font-size: 5rem;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="p-4 p-md-5">
                            <h2 class="fw-bold mb-2"><?= htmlspecialchars($product['name']) ?></h2>
                            
                            <?php if (!empty($product['sku'])): ?>
                                <p class="text-muted mb-3">SKU: <?= htmlspecialchars($product['sku']) ?></p>
                            <?php endif; ?>
                            
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
                            <div class="mb-3">
                                <span class="badge <?= $stockClass ?>"><?= $stockText ?></span>
                                <?php if ($product['stock'] > 0): ?>
                                    <span class="ms-2 text-muted"><?= $product['stock'] ?> units available</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <span class="fs-1 fw-bold text-primary">$<?= number_format($product['unit_price'], 2) ?></span>
                                <?php if (!empty($product['supplier'])): ?>
                                    <div class="mt-2">
                                        <span class="text-muted">Supplier: </span>
                                        <span><?= htmlspecialchars($product['supplier']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['category'])): ?>
                                    <div>
                                        <span class="text-muted">Category: </span>
                                        <span><?= htmlspecialchars($product['category']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($product['stock'] > 0): ?>
                                <div class="mb-4">
                                    <form id="addToCartForm" class="d-flex align-items-center">
                                        <label class="me-3" for="quantity">Quantity:</label>
                                        <div class="input-group" style="width: 150px;">
                                            <button type="button" class="btn btn-outline-light" id="decreaseQty">-</button>
                                            <input type="number" id="quantity" class="form-control text-center search-box" value="1" min="1" max="<?= $product['stock'] ?>" required>
                                            <button type="button" class="btn btn-outline-light" id="increaseQty">+</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <button type="button" id="addToCartBtn" class="btn btn-primary btn-lg">
                                    <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-lg" disabled>
                                    <i class="bi bi-cart-x me-1"></i> Out of Stock
                                </button>
                                <p class="mt-3 text-muted">This product is currently out of stock. Please check back later.</p>
                            <?php endif; ?>
                            
                            <a href="user_products.php" class="btn btn-outline-light btn-lg ms-2">
                                <i class="bi bi-arrow-left me-1"></i> Back to Products
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Description -->
            <div class="card-glass p-4 mb-5">
                <h4 class="fw-bold mb-3">Product Description</h4>
                <div class="product-description">
                    <?php if (!empty($product['description'])): ?>
                        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    <?php else: ?>
                        <p class="text-muted">No detailed description available for this product.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Similar Products -->
            <?php if (!empty($similarProducts)): ?>
                <h4 class="fw-bold mb-4">You May Also Like</h4>
                <div class="row g-4">
                    <?php foreach ($similarProducts as $similarProduct): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card-glass h-100 product-card">
                                <div class="text-center pt-3">
                                    <?php if (!empty($similarProduct['image_path']) && file_exists($similarProduct['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($similarProduct['image_path']) ?>" alt="<?= htmlspecialchars($similarProduct['name']) ?>" class="product-img">
                                    <?php else: ?>
                                        <div class="placeholder-img d-flex align-items-center justify-content-center">
                                            <i class="bi bi-image text-light"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title fw-bold mb-1"><?= htmlspecialchars($similarProduct['name']) ?></h5>
                                    <p class="card-text small text-muted mb-2"><?= substr(htmlspecialchars($similarProduct['description'] ?? ''), 0, 60) ?><?= strlen($similarProduct['description'] ?? '') > 60 ? '...' : '' ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <span class="fw-bold text-primary">$<?= number_format($similarProduct['unit_price'], 2) ?></span>
                                    </div>
                                    
                                    <a href="user_product_detail.php?id=<?= $similarProduct['id'] ?>" class="btn btn-outline-light mt-3">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($product) && $product['stock'] > 0): ?>
            // Quantity controls
            const quantityInput = document.getElementById('quantity');
            const decreaseBtn = document.getElementById('decreaseQty');
            const increaseBtn = document.getElementById('increaseQty');
            const maxStock = <?= $product['stock'] ?>;
            
            decreaseBtn.addEventListener('click', function() {
                const currentValue = parseInt(quantityInput.value);
                if (currentValue > 1) {
                    quantityInput.value = currentValue - 1;
                }
            });
            
            increaseBtn.addEventListener('click', function() {
                const currentValue = parseInt(quantityInput.value);
                if (currentValue < maxStock) {
                    quantityInput.value = currentValue + 1;
                }
            });
            
            // Validate input changes
            quantityInput.addEventListener('change', function() {
                let value = parseInt(this.value);
                if (isNaN(value) || value < 1) {
                    value = 1;
                } else if (value > maxStock) {
                    value = maxStock;
                }
                this.value = value;
            });
            
            // Add to Cart button
            const addToCartBtn = document.getElementById('addToCartBtn');
            const addToCartModal = new bootstrap.Modal(document.getElementById('addToCartModal'));
            
            addToCartBtn.addEventListener('click', function() {
                const quantity = parseInt(quantityInput.value);
                const productId = <?= $productId ?>;
                
                // Add to cart via AJAX
                fetch('user_cart_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=add_to_cart&product_id=' + productId + '&quantity=' + quantity
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
            <?php endif; ?>
        });
    </script>
    
    <style>
    /* Additional CSS for product details page */
    .product-detail-img-container {
        height: 400px;
    }
    
    .product-detail-img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    .placeholder-img-large {
        height: 300px;
        width: 100%;
        background-color: rgba(255,255,255,0.1);
        border-radius: 10px;
    }
    
    .product-description {
        line-height: 1.6;
    }
    
    @media (max-width: 768px) {
        .product-detail-img-container {
            height: 300px;
        }
    }
    </style>
</body>
</html>
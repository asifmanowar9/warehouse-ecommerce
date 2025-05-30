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

// Initialize variables for filtering and pagination
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Base query for products
$baseQuery = "SELECT p.*, v.on_hand as stock, s.name as supplier 
             FROM products p 
             LEFT JOIN v_product_stock v ON p.id = v.id
             LEFT JOIN suppliers s ON p.supplier_id = s.id";

$countQuery = "SELECT COUNT(*) FROM products p";
$whereConditions = [];
$params = [];

// Add search condition
if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add category condition (if you have categories)
if (!empty($category)) {
    $whereConditions[] = "p.category = ?";
    $params[] = $category;
}

// Combine where conditions
if (!empty($whereConditions)) {
    $whereClause = " WHERE " . implode(" AND ", $whereConditions);
    $baseQuery .= $whereClause;
    $countQuery .= $whereClause;
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $baseQuery .= " ORDER BY p.unit_price ASC";
        break;
    case 'price_desc':
        $baseQuery .= " ORDER BY p.unit_price DESC";
        break;
    case 'name_desc':
        $baseQuery .= " ORDER BY p.name DESC";
        break;
    case 'newest':
        $baseQuery .= " ORDER BY p.id DESC";
        break;
    case 'name_asc':
    default:
        $baseQuery .= " ORDER BY p.name ASC";
        break;
}

// Add pagination
$baseQuery .= " LIMIT $perPage OFFSET $offset";

try {
    // Get total products count for pagination
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalProducts = $stmt->fetchColumn();
    $totalPages = ceil($totalProducts / $perPage);
    
    // Get products for current page
    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $products = [];
    $errorMsg = "Database error: " . $e->getMessage();
}

// Get categories (if you have a categories table)
// If not, you can remove this part
try {
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $categories = [];
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
    <title>Products - Warehouse Store</title>
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
            <h1 class="display-5 fw-bold mb-3">BROWSE OUR PRODUCTS</h1>
            <p class="lead mb-0">Find what you need | <?= date('Y-m-d') ?> | User: <?= htmlspecialchars($username) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <!-- Filter and Search Section -->
        <div class="card-glass p-4 mb-5">
            <div class="row">
                <div class="col-md-6">
                    <form method="get" class="d-flex mb-3 mb-md-0">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control search-box" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-6 d-flex">
                    <?php if (!empty($categories)): ?>
                    <div class="dropdown me-2">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="categoryDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?= !empty($category) ? htmlspecialchars($category) : 'All Categories' ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="categoryDropdown">
                            <li><a class="dropdown-item <?= empty($category) ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['category' => ''])) ?>">All Categories</a></li>
                            <?php foreach ($categories as $cat): ?>
                                <li><a class="dropdown-item <?= $category == $cat ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['category' => $cat])) ?>"><?= htmlspecialchars($cat) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div class="dropdown ms-auto">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            Sort by: 
                            <?php 
                            $sortLabels = [
                                'name_asc' => 'Name (A-Z)',
                                'name_desc' => 'Name (Z-A)',
                                'price_asc' => 'Price (Low to High)',
                                'price_desc' => 'Price (High to Low)',
                                'newest' => 'Newest First'
                            ];
                            echo $sortLabels[$sort] ?? $sortLabels['name_asc'];
                            ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="sortDropdown">
                            <?php foreach ($sortLabels as $sortKey => $sortLabel): ?>
                                <li><a class="dropdown-item <?= $sort == $sortKey ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort' => $sortKey])) ?>"><?= htmlspecialchars($sortLabel) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>
        
        <!-- Products Grid -->
        <div class="row g-4">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
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
                    <div class="card-glass p-5">
                        <i class="bi bi-search text-muted" style="font-size: 4rem;"></i>
                        <h3 class="mt-4 mb-3">No products found</h3>
                        <p class="mb-4">We couldn't find any products matching your search criteria.</p>
                        <a href="user_products.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-1"></i> Clear Filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center mt-5">
                <nav aria-label="Product pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        // Display at most 5 page links
                        $startPage = max(1, min($page - 2, $totalPages - 4));
                        $endPage = min($startPage + 4, $totalPages);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
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
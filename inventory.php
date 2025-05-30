<?php
require 'db.php';

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}  

// Use $pdo from db.php for all database operations
$conn = $pdo;

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    // Add new product
    if (isset($_POST['action']) && $_POST['action'] === 'add_product') {
        try {
            $conn->beginTransaction();
            
            // Handle image upload
            $imagePath = null;
            if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/products/';
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($_FILES['productImage']['name'], PATHINFO_EXTENSION);
                $uniqueFilename = uniqid('product_') . '.' . $fileExtension;
                $targetFile = $uploadDir . $uniqueFilename;
                
                // Validate image file
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($_FILES['productImage']['tmp_name']);
                
                if (in_array($fileType, $allowedTypes) && $_FILES['productImage']['size'] < 5000000) { // 5MB limit
                    if (move_uploaded_file($_FILES['productImage']['tmp_name'], $targetFile)) {
                        $imagePath = $targetFile;
                    }
                }
            }
            
            // Insert into products table with image path
            $stmt = $conn->prepare("INSERT INTO products (sku, name, description, image_path, unit_price, reorder_level, supplier_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['sku'],
                $_POST['name'],
                $_POST['description'],
                $imagePath,
                $_POST['unitPrice'],
                $_POST['reorderLevel'],
                !empty($_POST['supplier']) ? $_POST['supplier'] : null
            ]);
            
            $productId = $conn->lastInsertId();
            
            // Insert initial stock if provided
            if (!empty($_POST['initialStock']) && $_POST['initialStock'] > 0) {
                $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, qty, reference, moved_by) 
                                       VALUES (?, 'PURCHASE', ?, 'Initial stock', ?)");
                $stmt->execute([
                    $productId,
                    $_POST['initialStock'],
                    $_SESSION['uid'] ?? 1
                ]);
            }
            
            $conn->commit();
            $response = ['success' => true, 'message' => 'Product added successfully'];
        } catch (PDOException $e) {
            $conn->rollBack();
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }
    
    // Update existing product
    if (isset($_POST['action']) && $_POST['action'] === 'edit_product') {
        try {
            $productId = $_POST['productId'];
            
            // Handle image upload for update
            $imagePath = null;
            $keepExistingImage = isset($_POST['keepExistingImage']) && $_POST['keepExistingImage'] == '1';
            
            // First, get the current image path
            $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $currentImagePath = $stmt->fetch(PDO::FETCH_COLUMN);
            
            if ($keepExistingImage) {
                $imagePath = $currentImagePath; // Keep existing image
            }
            
            // Handle new image upload
            if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/products/';
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($_FILES['productImage']['name'], PATHINFO_EXTENSION);
                $uniqueFilename = uniqid('product_') . '.' . $fileExtension;
                $targetFile = $uploadDir . $uniqueFilename;
                
                // Validate image file
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($_FILES['productImage']['tmp_name']);
                
                if (in_array($fileType, $allowedTypes) && $_FILES['productImage']['size'] < 5000000) { // 5MB limit
                    if (move_uploaded_file($_FILES['productImage']['tmp_name'], $targetFile)) {
                        // Delete old image if it exists and we're uploading a new one
                        if (!empty($currentImagePath) && file_exists($currentImagePath)) {
                            unlink($currentImagePath);
                        }
                        $imagePath = $targetFile;
                    }
                }
            } elseif (!$keepExistingImage) {
                // User wants to remove image without uploading a new one
                if (!empty($currentImagePath) && file_exists($currentImagePath)) {
                    unlink($currentImagePath);
                }
                $imagePath = null;
            }
            
            // Update product in database with image path
            $stmt = $conn->prepare("UPDATE products SET sku = ?, name = ?, description = ?, 
                                  image_path = ?, unit_price = ?, reorder_level = ?, supplier_id = ? 
                                  WHERE id = ?");
            $stmt->execute([
                $_POST['sku'],
                $_POST['name'],
                $_POST['description'],
                $imagePath,
                $_POST['unitPrice'],
                $_POST['reorderLevel'],
                !empty($_POST['supplier']) ? $_POST['supplier'] : null,
                $productId
            ]);
            
            $response = ['success' => true, 'message' => 'Product updated successfully'];
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }
    
    // Delete product
    if (isset($_POST['action']) && $_POST['action'] === 'delete_product') {
        try {
            // Get the image path first
            $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
            $stmt->execute([$_POST['productId']]);
            $imagePath = $stmt->fetch(PDO::FETCH_COLUMN);
            
            // Delete the product
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_POST['productId']]);
            
            // Delete the image file if it exists
            if (!empty($imagePath) && file_exists($imagePath)) {
                unlink($imagePath);
            }
            
            $response = ['success' => true, 'message' => 'Product deleted successfully'];
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }
    
    // Adjust stock
    if (isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
        try {
            $conn->beginTransaction();
            
            // Get current stock
            $stmt = $conn->prepare("SELECT SUM(CASE WHEN movement_type IN ('PURCHASE') THEN qty 
                                       WHEN movement_type IN ('SALE', 'ADJUST') THEN -qty 
                                       ELSE 0 END) as current_stock 
                                FROM stock_movements 
                                WHERE product_id = ?");
            $stmt->execute([$_POST['productId']]);
            $currentStock = $stmt->fetch(PDO::FETCH_ASSOC)['current_stock'] ?? 0;
            
            // Determine movement type and sign
            $movementType = $_POST['movementType'];
            $quantity = abs(intval($_POST['quantity']));
            
            // Insert stock movement
            $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, qty, reference, moved_by) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['productId'],
                $movementType,
                $quantity,
                $_POST['reference'] ?? '',
                $_SESSION['uid'] ?? 1
            ]);
            
            $conn->commit();
            $response = ['success' => true, 'message' => 'Stock adjusted successfully'];
        } catch (PDOException $e) {
            $conn->rollBack();
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }
    
    // Get product details (for edit modal)
    if (isset($_POST['action']) && $_POST['action'] === 'get_product') {
        try {
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$_POST['productId']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $response = ['success' => true, 'product' => $product];
            } else {
                $response = ['success' => false, 'message' => 'Product not found'];
            }
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }
    
    echo json_encode($response);
    exit;
}

// Get query params for filtering
$supplierFilter = isset($_GET['supplier']) ? intval($_GET['supplier']) : null;

// Get inventory data with view
try {
    $queryParams = [];
    $supplierWhere = "";
    
    if ($supplierFilter) {
        $supplierWhere = " WHERE p.supplier_id = ?";
        $queryParams[] = $supplierFilter;
    }
    
    // Using the existing view v_product_stock - now including image_path
    $stmt = $conn->prepare("SELECT p.id, p.sku, p.name, p.description, p.image_path, p.unit_price, 
                  COALESCE(vs.on_hand, 0) as stock,
                  p.reorder_level, s.name as supplier, s.id as supplier_id
                  FROM products p 
                  LEFT JOIN v_product_stock vs ON p.id = vs.id
                  LEFT JOIN suppliers s ON p.supplier_id = s.id" . $supplierWhere . "
                  ORDER BY p.name");
    
    $stmt->execute($queryParams);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $products = [];
    $errorMsg = "Database error: " . $e->getMessage();
}

// Get supplier list
try {
    $stmt = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $suppliers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
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
            <h1 class="display-5 fw-bold mb-3">INVENTORY CONTROL</h1>
            <p class="lead mb-0">Storage Facility #101 | Total SKUs: <?= count($products) ?> | User: <?= htmlspecialchars($_SESSION['username'] ?? 'Tanmoy027') ?></p>
        </div>
    </header>

    <main class="container py-4">
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= $errorMsg ?>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="card-glass p-3">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-light border-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control search-box" placeholder="SEARCH INVENTORY...">
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <?php if ($supplierFilter): ?>
                                <a href="inventory.php" class="btn btn-warning me-2">
                                    <i class="bi bi-funnel me-1"></i> CLEAR FILTER
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="bi bi-plus-lg me-1"></i> NEW ITEM
                            </button>
                            <div class="btn-group">
                                <button class="btn btn-outline-light" title="Scan Barcode">
                                    <i class="bi bi-upc-scan"></i>
                                </button>
                                <button class="btn btn-outline-light" title="Export Data" id="exportBtn">
                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                </button>
                                <button class="btn btn-outline-light" title="Print" onclick="window.print()">
                                    <i class="bi bi-printer"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card-glass p-3 text-center">
                    <h2 class="fw-bold text-secondary mb-1">TOTAL</h2>
                    <h3 class="display-5 fw-bold text-primary mb-0"><?= count($products) ?></h3>
                    <p class="small text-light">PRODUCT TYPES</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-glass p-3 text-center">
                    <h2 class="fw-bold text-secondary mb-1">STOCK VALUE</h2>
                    <?php 
                    $totalValue = 0;
                    foreach ($products as $product) {
                        $totalValue += $product['unit_price'] * $product['stock'];
                    }
                    ?>
                    <h3 class="display-5 fw-bold text-primary mb-0">$<?= number_format($totalValue, 0) ?></h3>
                    <p class="small text-light">INVENTORY VALUE</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-glass p-3 text-center">
                    <h2 class="fw-bold text-secondary mb-1">LOW STOCK</h2>
                    <?php 
                    $lowStock = 0;
                    foreach ($products as $product) {
                        if ($product['stock'] <= $product['reorder_level']) {
                            $lowStock++;
                        }
                    }
                    ?>
                    <h3 class="display-5 fw-bold text-danger mb-0"><?= $lowStock ?></h3>
                    <p class="small text-light">NEEDS ATTENTION</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-glass p-3 text-center">
                    <h2 class="fw-bold text-secondary mb-1">LOCATIONS</h2>
                    <?php
                    try {
                        $stmt = $conn->query("SELECT COUNT(*) as count FROM locations");
                        $locations = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {
                        $locations = ['count' => 0];
                    }
                    ?>
                    <h3 class="display-5 fw-bold text-primary mb-0"><?= $locations['count'] ?? 0 ?></h3>
                    <p class="small text-light">STORAGE ZONES</p>
                </div>
            </div>
        </div>

        <div class="table-responsive card-glass">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 80px;">IMAGE</th>
                        <th>
                            <div class="d-flex align-items-center">
                                SKU
                                <span class="ms-1"><i class="bi bi-sort-alpha-down"></i></span>
                            </div>
                        </th>
                        <th>NAME</th>
                        <th>DESCRIPTION</th>
                        <th class="text-center">STOCK</th>
                        <th class="text-end">UNIT PRICE</th>
                        <th>SUPPLIER</th>
                        <th class="text-center">ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <tr data-product-id="<?= $product['id'] ?>">
                                <td class="text-center">
                                    <?php if (!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-thumbnail" style="max-height: 50px; max-width: 50px;">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <i class="bi bi-image text-light"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?= htmlspecialchars($product['sku']) ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($product['description'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?php
                                    $stockClass = 'bg-success';
                                    if ($product['stock'] <= $product['reorder_level']) {
                                        $stockClass = 'bg-danger';
                                    } elseif ($product['stock'] <= $product['reorder_level'] * 1.5) {
                                        $stockClass = 'bg-warning';
                                    }
                                    ?>
                                    <span class="badge <?= $stockClass ?> rounded-pill">
                                        <?= htmlspecialchars($product['stock']) ?>
                                    </span>
                                </td>
                                <td class="text-end">$<?= number_format($product['unit_price'], 2) ?></td>
                                <td>
                                    <?php if($product['supplier']): ?>
                                        <a href="inventory.php?supplier=<?= $product['supplier_id'] ?>" class="text-decoration-none text-primary">
                                            <?= htmlspecialchars($product['supplier']) ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-dark" title="Adjust Stock" onclick="adjustStock(<?= $product['id'] ?>)">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </button>
                                        <button class="btn btn-outline-dark" title="Edit" onclick="editProduct(<?= $product['id'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Delete" onclick="deleteProduct(<?= $product['id'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="bi bi-inbox text-muted" style="font-size: 2.5rem;"></i>
                                    <h5 class="mt-2 mb-1">No products found</h5>
                                    <p class="text-muted small">Add your first product to get started</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addProductForm" enctype="multipart/form-data">
                        <input type="hidden" id="productId" name="productId">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sku" class="form-label">SKU*</label>
                                <input type="text" class="form-control search-box" id="sku" name="sku" required>
                            </div>
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name*</label>
                                <input type="text" class="form-control search-box" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control search-box" id="description" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="productImage" class="form-label">Product Image</label>
                            <input type="file" class="form-control search-box" id="productImage" name="productImage" accept="image/*">
                            <div class="mt-2" id="imagePreviewContainer" style="display: none;">
                                <img id="imagePreview" src="#" alt="Preview" class="img-thumbnail" style="max-height: 150px">
                                <button type="button" class="btn btn-sm btn-outline-danger mt-1" id="removeImage">Remove</button>
                            </div>
                            <input type="hidden" id="keepExistingImage" name="keepExistingImage" value="1">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="unitPrice" class="form-label">Unit Price*</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control search-box" id="unitPrice" name="unitPrice" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="initialStock" class="form-label">Initial Stock*</label>
                                <input type="number" class="form-control search-box" id="initialStock" name="initialStock" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="reorderLevel" class="form-label">Reorder Level*</label>
                                <input type="number" class="form-control search-box" id="reorderLevel" name="reorderLevel" min="0" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="supplier" class="form-label">Supplier</label>
                                <select class="form-select search-box" id="supplier" name="supplier">
                                    <option value="" selected>-- Select Supplier --</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveProductBtn">Save Product</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="adjustStockForm">
                        <input type="hidden" id="adjustProductId" name="productId">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <div class="form-control search-box" id="productNameDisplay" readonly>-</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <div class="form-control search-box" id="currentStockDisplay" readonly>-</div>
                        </div>
                        <div class="mb-3">
                            <label for="movementType" class="form-label">Movement Type*</label>
                            <select class="form-select search-box" id="movementType" name="movementType" required>
                                <option value="PURCHASE">Stock In (Purchase)</option>
                                <option value="SALE">Stock Out (Sale)</option>
                                <option value="ADJUST">Stock Adjustment</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity*</label>
                            <input type="number" class="form-control search-box" id="quantity" name="quantity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="reference" class="form-label">Reference</label>
                            <input type="text" class="form-control search-box" id="reference" name="reference" placeholder="PO#, Invoice#, etc.">
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveStockBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-auto text-center py-3 small text-white-50" style="background:var(--dark)">
        © <?=date('Y')?> Warehouse Management System | Updated: 2025-05-23 17:17:02 | User: <?= htmlspecialchars($_SESSION['username'] ?? 'Tanmoy027') ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableBody = document.getElementById('productTableBody');
            const rows = tableBody.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    const cellValue = cells[j].textContent || cells[j].innerText;
                    if (cellValue.toLowerCase().indexOf(searchValue) > -1) {
                        found = true;
                        break;
                    }
                }
                
                if (found) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        });

        // Image preview functionality
        document.getElementById('productImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const imagePreview = document.getElementById('imagePreview');
            const keepExistingImage = document.getElementById('keepExistingImage');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreviewContainer.style.display = 'block';
                    keepExistingImage.value = '0'; // New image will replace any existing one
                }
                reader.readAsDataURL(file);
            } else {
                imagePreviewContainer.style.display = 'none';
            }
        });

        document.getElementById('removeImage').addEventListener('click', function() {
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const productImage = document.getElementById('productImage');
            const keepExistingImage = document.getElementById('keepExistingImage');
            
            imagePreviewContainer.style.display = 'none';
            productImage.value = ''; // Clear the file input
            keepExistingImage.value = '0'; // Indicate we want to remove the image
        });

        // Product functions
        function adjustStock(productId) {
            // Get the product details
            const productRow = document.querySelector(`tr[data-product-id="${productId}"]`);
            if (!productRow) return;
            
            // Get product name and current stock from the row
            const productName = productRow.cells[2].textContent; // Adjusted for new image column
            const currentStock = productRow.querySelector('.badge').textContent.trim();
            
            // Set values in the form
            document.getElementById('adjustProductId').value = productId;
            document.getElementById('productNameDisplay').textContent = productName;
            document.getElementById('currentStockDisplay').textContent = currentStock;
            document.getElementById('quantity').value = '1';
            document.getElementById('reference').value = '';
            
            // Show the modal
            const adjustStockModal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
            adjustStockModal.show();
        }
        
        function editProduct(productId) {
            // Reset form
            document.getElementById('addProductForm').reset();
            document.getElementById('productId').value = productId;
            
            // Change modal title
            document.querySelector('#addProductModal .modal-title').textContent = 'Edit Product';
            
            // Fetch product data via AJAX
            const formData = new FormData();
            formData.append('action', 'get_product');
            formData.append('productId', productId);
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate form fields
                    const product = data.product;
                    document.getElementById('sku').value = product.sku;
                    document.getElementById('name').value = product.name;
                    document.getElementById('description').value = product.description || '';
                    document.getElementById('unitPrice').value = product.unit_price;
                    document.getElementById('initialStock').value = '0';
                    document.getElementById('initialStock').disabled = true;
                    document.getElementById('reorderLevel').value = product.reorder_level;
                    document.getElementById('supplier').value = product.supplier_id || '';
                    
                    // Handle image preview
                    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
                    const imagePreview = document.getElementById('imagePreview');
                    const keepExistingImage = document.getElementById('keepExistingImage');
                    
                    if (product.image_path) {
                        imagePreview.src = product.image_path;
                        imagePreviewContainer.style.display = 'block';
                        keepExistingImage.value = '1'; // Keep existing image by default
                    } else {
                        imagePreviewContainer.style.display = 'none';
                        keepExistingImage.value = '0';
                    }
                    
                    // Show the modal
                    const addProductModal = new bootstrap.Modal(document.getElementById('addProductModal'));
                    addProductModal.show();
                } else {
                    alert('Error fetching product details: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while fetching product details.');
            });
        }
        
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                // Send delete request via AJAX
                const formData = new FormData();
                formData.append('action', 'delete_product');
                formData.append('productId', productId);
                
                fetch('inventory.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload(); // Refresh the page
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the product.');
                });
            }
        }
        
        // Save stock adjustment
        document.getElementById('saveStockBtn').addEventListener('click', function() {
            const form = document.getElementById('adjustStockForm');
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Send data via AJAX
            const formData = new FormData(form);
            formData.append('action', 'adjust_stock');
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload(); // Refresh the page
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adjusting the stock.');
            });
        });
        
        // Save new product or update existing product
        document.getElementById('saveProductBtn').addEventListener('click', function() {
            const form = document.getElementById('addProductForm');
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Determine if this is an add or edit operation
            const productId = document.getElementById('productId').value;
            const action = productId ? 'edit_product' : 'add_product';
            
            // Use FormData to handle file uploads
            const formData = new FormData(form);
            formData.append('action', action);
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload(); // Refresh the page
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the product.');
            });
        });
        
        // Reset form and modal title when adding a new product
        document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addProductForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('initialStock').disabled = false;
            document.getElementById('imagePreviewContainer').style.display = 'none';
            document.getElementById('keepExistingImage').value = '1';
            document.querySelector('#addProductModal .modal-title').textContent = 'Add New Product';
        });
        
        // Export inventory data to CSV
        document.getElementById('exportBtn').addEventListener('click', function() {
            const table = document.querySelector('.table');
            const headers = Array.from(table.querySelectorAll('th')).map(th => {
                // Get the text content without the sort icon
                const text = th.textContent.trim();
                return text.replace(/[\n\r]+/g, ' ').trim();
            });
            
            const rows = Array.from(table.querySelectorAll('tbody tr')).map(row => {
                return Array.from(row.querySelectorAll('td')).map((cell, index) => {
                    // Skip the image column (first column) for the CSV
                    if (index === 0) return ''; // Or return a placeholder like "Image URL"
                    return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
                });
            });
            
            // Create CSV content
            let csvContent = headers.join(',') + '\n';
            rows.forEach(row => {
                csvContent += row.join(',') + '\n';
            });
            
            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'inventory_export_<?= date('Ymd') ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
</body>
</html>
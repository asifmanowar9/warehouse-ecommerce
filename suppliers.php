<?php
// Start of suppliers.php

require 'db.php';
require 'functions.php';

requireLogin();
requireAdminAccess();

// Use $pdo from db.php for all database operations
$conn = $pdo;

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new supplier
    if (isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
        try {
            $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_name, phone, email, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['contact_name'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['address']
            ]);
            $successMessage = "Supplier added successfully!";
        } catch(PDOException $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }
    }
    
    // Process AJAX requests
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'Invalid action'];
        
        // Edit supplier
        if ($_POST['action'] === 'edit_supplier') {
            try {
                $stmt = $conn->prepare("UPDATE suppliers SET name = ?, contact_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['contact_name'],
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['address'],
                    $_POST['supplierId']
                ]);
                
                $response = ['success' => true, 'message' => 'Supplier updated successfully'];
            } catch(PDOException $e) {
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
        }
        
        // Delete supplier
        else if ($_POST['action'] === 'delete_supplier') {
            try {
                // Check if supplier has associated products
                $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
                $stmt->execute([$_POST['supplierId']]);
                $productCount = $stmt->fetchColumn();
                
                if ($productCount > 0) {
                    $response = ['success' => false, 'message' => 'Cannot delete supplier with associated products'];
                } else {
                    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
                    $stmt->execute([$_POST['supplierId']]);
                    $response = ['success' => true, 'message' => 'Supplier deleted successfully'];
                }
            } catch(PDOException $e) {
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
        }
        
        // Get supplier details
        else if ($_POST['action'] === 'get_supplier') {
            try {
                $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
                $stmt->execute([$_POST['supplierId']]);
                $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($supplier) {
                    $response = ['success' => true, 'supplier' => $supplier];
                } else {
                    $response = ['success' => false, 'message' => 'Supplier not found'];
                }
            } catch(PDOException $e) {
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
        }
        
        // Get supplier products
        else if ($_POST['action'] === 'get_supplier_products') {
            try {
                if (empty($_POST['supplierId'])) {
                    $response = ['success' => false, 'message' => 'Supplier ID is required'];
                } else {
                    $stmt = $conn->prepare("SELECT p.id, p.sku, p.name, p.unit_price 
                                           FROM products p 
                                           WHERE p.supplier_id = ? 
                                           ORDER BY p.name");
                    $stmt->execute([$_POST['supplierId']]);
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($products) > 0) {
                        $response = ['success' => true, 'products' => $products, 'count' => count($products)];
                    } else {
                        $response = ['success' => false, 'message' => 'No products found for this supplier. Please add products first.'];
                    }
                }
            } catch(PDOException $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }
        
        // Create purchase order
        else if ($_POST['action'] === 'create_purchase_order') {
            try {
                $conn->beginTransaction();
                
                // Insert purchase order
                $stmt = $conn->prepare("INSERT INTO purchase_orders (supplier_id, order_date, status, total_amount, ordered_by) 
                                      VALUES (?, ?, 'OPEN', ?, ?)");
                $stmt->execute([
                    $_POST['supplier_id'],
                    $_POST['order_date'],
                    $_POST['total_amount'],
                    $_SESSION['uid']
                ]);
                
                $poId = $conn->lastInsertId();
                
                // Insert purchase order items
                $items = json_decode($_POST['items'], true);
                foreach ($items as $item) {
                    $stmt = $conn->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty_ordered, unit_cost) 
                                          VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $poId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_cost']
                    ]);
                }
                
                $conn->commit();
                $response = ['success' => true, 'message' => 'Purchase order created successfully', 'po_id' => $poId];
            } catch(PDOException $e) {
                $conn->rollBack();
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
        }
        
        // Check if supplier has products
        else if ($_POST['action'] === 'check_supplier_products') {
            try {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
                $stmt->execute([$_POST['supplier_id']]);
                $productCount = $stmt->fetchColumn();
                
                $response = ['success' => true, 'hasProducts' => ($productCount > 0), 'count' => $productCount];
            } catch(PDOException $e) {
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
        }
        
        echo json_encode($response);
        exit;
    }
}

// Get suppliers data
try {
    $stmt = $conn->query("SELECT s.*, 
                         (SELECT COUNT(*) FROM products WHERE supplier_id = s.id) as product_count,
                         (SELECT COALESCE(SUM(p.unit_price * COALESCE(vs.on_hand, 0)), 0)
                          FROM products p 
                          LEFT JOIN v_product_stock vs ON p.id = vs.id
                          WHERE p.supplier_id = s.id) as inventory_value
                         FROM suppliers s
                         ORDER BY s.name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $suppliers = [];
    $errorMsg = "Database error: " . $e->getMessage();
}

// Get latest purchase orders
try {
    $stmt = $conn->query("SELECT po.id, po.order_date, po.status, s.name as supplier_name, 
                         po.total_amount, u.username as ordered_by
                         FROM purchase_orders po
                         LEFT JOIN suppliers s ON po.supplier_id = s.id
                         LEFT JOIN users u ON po.ordered_by = u.id
                         ORDER BY po.order_date DESC
                         LIMIT 5");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $recentOrders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management</title>
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
            <h1 class="display-5 fw-bold mb-3">SUPPLIER MANAGEMENT</h1>
            <p class="lead mb-0">Vendors: <?= count($suppliers) ?> | Last Order: <?= !empty($recentOrders) ? date('M d, Y', strtotime($recentOrders[0]['order_date'])) : 'N/A' ?> | User: <?= htmlspecialchars($_SESSION['username'] ?? 'Tanmoy027') ?></p>
        </div>
    </header>

    <main class="container py-4">
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= $errorMsg ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $successMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                                <input type="text" id="searchInput" class="form-control search-box" placeholder="SEARCH SUPPLIERS...">
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                <i class="bi bi-plus-lg me-1"></i> NEW SUPPLIER
                            </button>
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addPurchaseOrderModal">
                                <i class="bi bi-cart-plus me-1"></i> NEW ORDER
                            </button>
                            <div class="btn-group d-inline-block">
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
            <div class="col-md-8 mb-4 mb-md-0">
                <!-- Suppliers Table -->
                <div class="table-responsive card-glass">
                    <table class="table table-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>
                                    <div class="d-flex align-items-center">
                                        SUPPLIER NAME
                                        <span class="ms-1"><i class="bi bi-sort-alpha-down"></i></span>
                                    </div>
                                </th>
                                <th>CONTACT</th>
                                <th>EMAIL</th>
                                <th class="text-center">PRODUCTS</th>
                                <th class="text-end">VALUE</th>
                                <th class="text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="supplierTableBody">
                            <?php if (count($suppliers) > 0): ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <tr data-supplier-id="<?= $supplier['id'] ?>">
                                        <td class="fw-bold"><?= htmlspecialchars($supplier['name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($supplier['contact_name'] ?? 'N/A') ?>
                                            <?php if (!empty($supplier['phone'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($supplier['phone']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($supplier['email'] ?? 'N/A') ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill">
                                                <?= $supplier['product_count'] ?? 0 ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            $<?= number_format($supplier['inventory_value'] ?? 0, 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="inventory.php?supplier=<?= $supplier['id'] ?>" class="btn btn-outline-light" title="View Products">
                                                    <i class="bi bi-box-seam"></i>
                                                </a>
                                                <button class="btn btn-outline-light order-btn" title="Create Order" data-supplier-id="<?= $supplier['id'] ?>" data-product-count="<?= $supplier['product_count'] ?>">
                                                    <i class="bi bi-cart"></i>
                                                </button>
                                                <button class="btn btn-outline-light" title="Edit" onclick="editSupplier(<?= $supplier['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" title="Delete" onclick="deleteSupplier(<?= $supplier['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bi bi-building text-muted" style="font-size: 2.5rem;"></i>
                                            <h5 class="mt-2 mb-1">No suppliers found</h5>
                                            <p class="text-muted small">Add your first supplier to get started</p>
                                            <button class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                                <i class="bi bi-plus-circle me-1"></i> Add Supplier
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <!-- Recent Orders Panel -->
                <div class="card-glass">
                    <div class="p-3 border-bottom border-secondary">
                        <h5 class="fw-bold m-0">RECENT PURCHASE ORDERS</h5>
                    </div>
                    <div class="p-3">
                        <?php if (count($recentOrders) > 0): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="mb-3 pb-3 border-bottom border-secondary border-opacity-25">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1 fw-bold">PO# <?= $order['id'] ?></h6>
                                        <?php
                                            $statusClass = 'bg-primary';
                                            if ($order['status'] === 'RECEIVED') {
                                                $statusClass = 'bg-success';
                                            } elseif ($order['status'] === 'CANCELLED') {
                                                $statusClass = 'bg-danger';
                                            }
                                        ?>
                                        <span class="badge <?= $statusClass ?> rounded-pill"><?= $order['status'] ?></span>
                                    </div>
                                    <div class="small">
                                        <div><strong>Supplier:</strong> <?= htmlspecialchars($order['supplier_name']) ?></div>
                                        <div><strong>Date:</strong> <?= date('M d, Y', strtotime($order['order_date'])) ?></div>
                                        <div><strong>Amount:</strong> $<?= number_format($order['total_amount'], 2) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="purchase_orders.php" class="btn btn-sm btn-outline-light">
                                    View All Purchase Orders
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-clipboard text-muted" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No recent orders found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Add New Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addSupplierForm" method="POST">
                        <input type="hidden" name="action" value="add_supplier">
                        <input type="hidden" id="supplierId" name="supplierId">
                        <div class="mb-3">
                            <label for="name" class="form-label">Company Name*</label>
                            <input type="text" class="form-control search-box" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="contact_name" class="form-label">Contact Person</label>
                            <input type="text" class="form-control search-box" id="contact_name" name="contact_name">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control search-box" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control search-box" id="email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control search-box" id="address" name="address" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">CANCEL</button>
                    <button type="button" id="saveSupplierBtn" class="btn btn-primary">SAVE SUPPLIER</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Purchase Order Modal -->
    <div class="modal fade" id="addPurchaseOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Create Purchase Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPurchaseOrderForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="po_supplier" class="form-label">Supplier*</label>
                                <select class="form-select search-box" id="po_supplier" name="supplier_id" required>
                                    <option value="" selected>-- Select Supplier --</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>" data-product-count="<?= $supplier['product_count'] ?>">
                                            <?= htmlspecialchars($supplier['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="po_date" class="form-label">Order Date*</label>
                                <input type="date" class="form-control search-box" id="po_date" name="order_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <h6 class="fw-bold mt-4 mb-3">Order Items</h6>
                        <div class="table-responsive">
                            <table class="table table-custom" id="orderItemsTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Quantity</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="emptyOrderRow">
                                        <td colspan="5" class="text-center py-3">
                                            <p class="mb-1">No items added to this order yet</p>
                                            <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                                                <i class="bi bi-plus-circle me-1"></i> Add Item
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end">Order Total:</td>
                                        <td class="text-end fw-bold" id="orderTotal">$0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div id="itemTemplate" class="d-none">
                            <tr class="order-item-row">
                                <td>
                                    <select class="form-select search-box product-select" name="items[__INDEX__][product_id]" required>
                                        <option value="">-- Select Product --</option>
                                        <!-- This will be populated via AJAX when supplier is selected -->
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control search-box text-end item-qty" 
                                           name="items[__INDEX__][qty]" min="1" value="1" required>
                                </td>
                                <td>
                                    <input type="number" class="form-control search-box text-end item-cost" 
                                           name="items[__INDEX__][cost]" step="0.01" min="0.01" required>
                                </td>
                                <td class="text-end item-total">$0.00</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">CANCEL</button>
                    <button type="button" id="saveOrderBtn" class="btn btn-primary">CREATE ORDER</button>
                </div>
            </div>
        </div>
    </div>

    <!-- No Products Modal -->
    <div class="modal fade" id="noProductsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">No Products Available</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                    <p class="my-3">No products found for this supplier.</p>
                    <p>You need to add products before you can create purchase orders.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">CANCEL</button>
                    <a href="#" id="addProductBtn" class="btn btn-primary">ADD PRODUCTS</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                    <p class="my-3" id="errorModalMessage">An error occurred.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-auto text-center py-3 small text-white-50" style="background:var(--dark)">
        © <?=date('Y')?> Warehouse Management System | <?= date('Y-m-d H:i:s', strtotime('2025-05-17 03:17:06')) ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap modals
        var purchaseOrderModal = new bootstrap.Modal(document.getElementById('addPurchaseOrderModal'));
        var noProductsModal = new bootstrap.Modal(document.getElementById('noProductsModal'));
        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));

        // Utility function to safely add event listeners with null/undefined check
        function addSafeEventListener(element, event, handler) {
            if (element) {
                element.addEventListener(event, handler);
            }
        }
        
        // Show error in modal instead of alert
        function showError(message) {
            var errorMsg = document.getElementById('errorModalMessage');
            if (errorMsg) {
                errorMsg.textContent = message;
                errorModal.show();
            } else {
                // Fallback to alert if modal not available
                alert(message);
            }
        }
        
        // Search functionality
        addSafeEventListener(document.getElementById('searchInput'), 'keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableBody = document.getElementById('supplierTableBody');
            if (!tableBody) return;
            
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

        // Add click handler for order buttons - using event delegation to avoid null issues
        document.addEventListener('click', function(event) {
            // Handle order button clicks
            if (event.target.closest('.order-btn')) {
                const button = event.target.closest('.order-btn');
                const supplierId = button.dataset.supplierId;
                const productCount = parseInt(button.dataset.productCount) || 0;
                
                if (productCount === 0) {
                    // Show no products modal
                    const addProductBtn = document.getElementById('addProductBtn');
                    if (addProductBtn) {
                        addProductBtn.href = 'inventory.php?supplier=' + supplierId;
                    }
                    noProductsModal.show();
                } else {
                    // Show order modal and load products
                    const poSupplierSelect = document.getElementById('po_supplier');
                    if (poSupplierSelect) {
                        poSupplierSelect.value = supplierId;
                    }
                    loadSupplierProducts(supplierId);
                    purchaseOrderModal.show();
                }
            }
        });

        // Supplier management functions
        function editSupplier(supplierId) {
            // Reset form
            var form = document.getElementById('addSupplierForm');
            if (!form) return;
            
            form.reset();
            
            var supplierIdField = document.getElementById('supplierId');
            if (supplierIdField) {
                supplierIdField.value = supplierId;
            }
            
            // Change modal title
            var modalTitle = document.querySelector('#addSupplierModal .modal-title');
            if (modalTitle) {
                modalTitle.textContent = 'Edit Supplier';
            }
            
            var actionField = form.querySelector('input[name="action"]');
            if (actionField) {
                actionField.value = 'edit_supplier';
            }
            
            // Fetch supplier data via AJAX
            const formData = new FormData();
            formData.append('action', 'get_supplier');
            formData.append('supplierId', supplierId);
            
            fetch('suppliers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Populate form fields
                    const supplier = data.supplier;
                    document.getElementById('name').value = supplier.name || '';
                    
                    var contactNameField = document.getElementById('contact_name');
                    if (contactNameField) contactNameField.value = supplier.contact_name || '';
                    
                    var phoneField = document.getElementById('phone');
                    if (phoneField) phoneField.value = supplier.phone || '';
                    
                    var emailField = document.getElementById('email');
                    if (emailField) emailField.value = supplier.email || '';
                    
                    var addressField = document.getElementById('address');
                    if (addressField) addressField.value = supplier.address || '';
                    
                    // Show the modal
                    var addSupplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
                    addSupplierModal.show();
                } else {
                    showError('Error fetching supplier details: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred while fetching supplier details: ' + error.message);
            });
        }
        
        function deleteSupplier(supplierId) {
            if (confirm('Are you sure you want to delete this supplier?')) {
                // Send delete request via AJAX
                const formData = new FormData();
                formData.append('action', 'delete_supplier');
                formData.append('supplierId', supplierId);
                
                fetch('suppliers.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload(); // Refresh the page
                    } else {
                        showError('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred while deleting the supplier: ' + error.message);
                });
            }
        }
        
        // Purchase Order functionality
        let itemCount = 0;
        
        // Add Item button - using safe event listener
        addSafeEventListener(document.getElementById('addItemBtn'), 'click', function() {
            const supplierSelect = document.getElementById('po_supplier');
            if (!supplierSelect) return;
            
            const supplier_id = supplierSelect.value;
            if (!supplier_id) {
                showError('Please select a supplier first.');
                return;
            }
            
            // Add item row and load products
            const newRow = addOrderItem();
            if (newRow) {
                loadProductsForRow(supplier_id, newRow);
            }
        });

        // Load supplier products when supplier is selected
        addSafeEventListener(document.getElementById('po_supplier'), 'change', function() {
            const supplierId = this.value;
            if (supplierId) {
                // Get product count from the option data
                const option = this.options[this.selectedIndex];
                if (!option) return;
                
                const productCount = parseInt(option.dataset.productCount) || 0;
                
                if (productCount === 0) {
                    // Show warning modal
                    const addProductBtn = document.getElementById('addProductBtn');
                    if (addProductBtn) {
                        addProductBtn.href = 'inventory.php?supplier=' + supplierId;
                    }
                    noProductsModal.show();
                    
                    // Reset select
                    this.value = '';
                } else {
                    // Load products
                    loadSupplierProducts(supplierId);
                }
            }
        });
        
        function loadSupplierProducts(supplierId) {
            if (!supplierId) return;
            
            // Clear existing items
            const tbody = document.querySelector('#orderItemsTable tbody');
            if (!tbody) return;
            
            const emptyRow = document.getElementById('emptyOrderRow');
            
            // Remove existing product rows
            const existingRows = tbody.querySelectorAll('.order-item-row');
            existingRows.forEach(row => {
                row.parentNode.removeChild(row);
            });
            
            // Show empty row
            if (emptyRow) {
                emptyRow.style.display = '';
            }
            
            // Reset total
            const orderTotal = document.getElementById('orderTotal');
            if (orderTotal) {
                orderTotal.textContent = '$0.00';
            }
            
            // Reset item count
            itemCount = 0;
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'get_supplier_products');
            formData.append('supplierId', supplierId);
            
            fetch('suppliers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.products && data.products.length > 0) {
                    // Add first item
                    const newRow = addOrderItem();
                    if (newRow) {
                        const productSelect = newRow.querySelector('.product-select');
                        if (productSelect) {
                            populateProductDropdown(productSelect, data.products);
                        }
                    }
                } else {
                    // Show warning modal
                    const addProductBtn = document.getElementById('addProductBtn');
                    if (addProductBtn) {
                        addProductBtn.href = 'inventory.php?supplier=' + supplierId;
                    }
                    noProductsModal.show();
                    
                    // Hide purchase order modal
                    if (purchaseOrderModal) {
                        purchaseOrderModal.hide();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error loading products: ' + error.message);
            });
        }
        
        function loadProductsForRow(supplierId, rowElement) {
            if (!supplierId || !rowElement) return;
            
            const formData = new FormData();
            formData.append('action', 'get_supplier_products');
            formData.append('supplierId', supplierId);
            
            fetch('suppliers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.products && data.products.length > 0) {
                    const productSelect = rowElement.querySelector('.product-select');
                    if (productSelect) {
                        populateProductDropdown(productSelect, data.products);
                    }
                } else {
                    // Show warning modal
                    const addProductBtn = document.getElementById('addProductBtn');
                    if (addProductBtn) {
                        addProductBtn.href = 'inventory.php?supplier=' + supplierId;
                    }
                    noProductsModal.show();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error loading products: ' + error.message);
            });
        }
        
        function populateProductDropdown(selectElement, products) {
            if (!selectElement || !products) return;
            
            // Clear existing options except the first one
            while (selectElement.options.length > 1) {
                selectElement.remove(1);
            }
            
            // Add product options
            products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = `${product.sku} - ${product.name} ($${parseFloat(product.unit_price).toFixed(2)})`;
                option.dataset.price = product.unit_price;
                selectElement.appendChild(option);
            });
            
            // Set up change event only once
            if (!selectElement.dataset.eventSet) {
                selectElement.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (!selectedOption) return;
                    
                    const row = this.closest('.order-item-row');
                    if (!row) return;
                    
                    if (selectedOption && selectedOption.dataset.price) {
                        const costInput = row.querySelector('.item-cost');
                        if (costInput) {
                            costInput.value = selectedOption.dataset.price;
                            updateItemTotal.call(costInput);
                        }
                    }
                });
                selectElement.dataset.eventSet = 'true';
            }
        }
        
        function addOrderItem() {
            const tbody = document.querySelector('#orderItemsTable tbody');
            if (!tbody) return null;
            
            const emptyRow = document.getElementById('emptyOrderRow');
            const template = document.getElementById('itemTemplate');
            if (!template) return null;
            
            const templateHTML = template.innerHTML;
            if (!templateHTML) return null;
            
            // Hide the empty row message
            if (emptyRow) {
                emptyRow.style.display = 'none';
            }
            
            // Create new row for the item
            const newRow = document.createElement('tr');
            newRow.className = 'order-item-row';
            newRow.innerHTML = templateHTML.replace(/__INDEX__/g, itemCount++);
            tbody.appendChild(newRow);
            
            // Add event listeners
            const qtyInput = newRow.querySelector('.item-qty');
            if (qtyInput) {
                qtyInput.addEventListener('input', updateItemTotal);
            }
            
            const costInput = newRow.querySelector('.item-cost');
            if (costInput) {
                costInput.addEventListener('input', updateItemTotal);
            }
            
            const removeBtn = newRow.querySelector('.remove-item-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    newRow.remove();
                    updateOrderTotal();
                    
                    // Show empty row if no items left
                    const itemRows = document.querySelectorAll('.order-item-row');
                    if (itemRows.length === 0 && emptyRow) {
                        emptyRow.style.display = '';
                    }
                });
            }
            
            return newRow;
        }
        
        function updateItemTotal() {
            const row = this.closest('.order-item-row');
            if (!row) return;
            
            const qtyInput = row.querySelector('.item-qty');
            const costInput = row.querySelector('.item-cost');
            const totalCell = row.querySelector('.item-total');
            if (!qtyInput || !costInput || !totalCell) return;
            
            const qty = parseFloat(qtyInput.value) || 0;
            const cost = parseFloat(costInput.value) || 0;
            const total = qty * cost;
            
            totalCell.textContent = '$' + total.toFixed(2);
            updateOrderTotal();
        }
        
        function updateOrderTotal() {
            let total = 0;
            document.querySelectorAll('.item-total').forEach(cell => {
                const value = parseFloat(cell.textContent.replace('$', '')) || 0;
                total += value;
            });
            
            const orderTotalEl = document.getElementById('orderTotal');
            if (orderTotalEl) {
                orderTotalEl.textContent = '$' + total.toFixed(2);
            }
        }
        
        // Save supplier
        addSafeEventListener(document.getElementById('saveSupplierBtn'), 'click', function() {
            const form = document.getElementById('addSupplierForm');
            if (!form) return;
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Determine if this is an add or edit operation
            const supplierId = document.getElementById('supplierId')?.value;
            const isEdit = supplierId ? true : false;
            
            // If editing, send via AJAX
            if (isEdit) {
                const formData = new FormData(form);
                
                fetch('suppliers.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload(); // Refresh the page
                    } else {
                        showError('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred while saving the supplier: ' + error.message);
                });
            } else {
                // For new supplier, submit the form
                form.submit();
            }
        });
        
        // Create purchase order
        addSafeEventListener(document.getElementById('saveOrderBtn'), 'click', function() {
            const form = document.getElementById('addPurchaseOrderForm');
            if (!form) return;
            
            // Validate supplier and date
            const supplierSelect = document.getElementById('po_supplier');
            const dateInput = document.getElementById('po_date');
            if (!supplierSelect || !dateInput) return;
            
            const supplier = supplierSelect.value;
            const orderDate = dateInput.value;
            
            if (!supplier || !orderDate) {
                showError('Please select a supplier and order date.');
                return;
            }
            
            // Check if there are items
            const items = document.querySelectorAll('.order-item-row');
            if (items.length === 0) {
                showError('Please add at least one item to the order.');
                return;
            }
            
            // Validate each item
            let valid = true;
            let orderItems = [];
            
            items.forEach((item, index) => {
                const productSelect = item.querySelector('.product-select');
                const qtyInput = item.querySelector('.item-qty');
                const costInput = item.querySelector('.item-cost');
                
                if (!productSelect || !qtyInput || !costInput) {
                    valid = false;
                    return;
                }
                
                const productId = productSelect.value;
                const qty = qtyInput.value;
                const cost = costInput.value;
                
                if (!productId || !qty || !cost) {
                    valid = false;
                    return;
                }
                
                orderItems.push({
                    product_id: productId,
                    quantity: parseInt(qty),
                    unit_cost: parseFloat(cost),
                    total_cost: parseFloat(qty) * parseFloat(cost)
                });
            });
            
            if (!valid) {
                showError('Please fill in all required fields for each item.');
                return;
            }
            
            // Get order total
            const orderTotalEl = document.getElementById('orderTotal');
            if (!orderTotalEl) return;
            
            const totalAmount = parseFloat(orderTotalEl.textContent.replace('$', '')) || 0;
            
            // Create and send form data
            const formData = new FormData();
            formData.append('action', 'create_purchase_order');
            formData.append('supplier_id', supplier);
            formData.append('order_date', orderDate);
            formData.append('total_amount', totalAmount);
            formData.append('items', JSON.stringify(orderItems));
            
            fetch('suppliers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then data => {
                if (data.success) {
                    alert(data.message);
                    location.reload(); // Refresh the page
                } else {
                    showError('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred while creating the order: ' + error.message);
            });
        });
        
        // Reset form and modal title when adding a new supplier
        const addSupplierModal = document.getElementById('addSupplierModal');
        if (addSupplierModal) {
            addSupplierModal.addEventListener('hidden.bs.modal', function() {
                const form = document.getElementById('addSupplierForm');
                if (form) form.reset();
                
                const supplierIdField = document.getElementById('supplierId');
                if (supplierIdField) supplierIdField.value = '';
                
                const modalTitle = this.querySelector('.modal-title');
                if (modalTitle) modalTitle.textContent = 'Add New Supplier';
                
                const actionField = form?.querySelector('input[name="action"]');
                if (actionField) actionField.value = 'add_supplier';
            });
        }
        
        // Export supplier data to CSV
        addSafeEventListener(document.getElementById('exportBtn'), 'click', function() {
            const table = document.querySelector('.table');
            if (!table) return;
            
            const headers = Array.from(table.querySelectorAll('th')).map(th => {
                // Get the text content without the sort icon
                const text = th.textContent.trim();
                return text.replace(/[\n\r]+/g, ' ').trim();
            });
            
            const rows = Array.from(table.querySelectorAll('tbody tr')).map(row => {
                return Array.from(row.querySelectorAll('td')).map(cell => {
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
            link.setAttribute('download', 'suppliers_export_<?= date('Ymd') ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
</body>
</html>
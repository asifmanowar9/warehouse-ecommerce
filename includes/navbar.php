<?php
// Make sure session is started and functions are loaded
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If these files haven't been included yet, include them
if (!function_exists('isAdmin')) {
    require_once dirname(__DIR__) . '/functions.php';
}

// Get username from session
$username = $_SESSION['username'] ?? 'Guest';
?>

<!-- Different navigation for different roles -->
<?php if (hasAdminAccess()): ?>
    <!-- Admin/Staff Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a href="index.php" class="navbar-brand fw-bold">
                <span class="logo-circle me-2">W</span>WAREHOUSE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto me-3">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">DASHBOARD</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : '' ?>" href="inventory.php">INVENTORY</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : '' ?>" href="suppliers.php">SUPPLIERS</a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'staff_management.php' ? 'active' : '' ?>" href="staff_management.php">STAFF and USERS</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="reports.php">REPORTS</a>
                    </li>
                </ul>
                <a class="btn btn-outline-light" href="logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i> LOGOUT
                </a>
            </div>
        </div>
    </nav>
<?php else: ?>
    <!-- Regular User Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a href="user_home.php" class="navbar-brand fw-bold">
                <span class="logo-circle me-2">W</span>WAREHOUSE STORE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto me-3">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'user_home.php' ? 'active' : '' ?>" href="user_home.php">HOME</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'user_products.php' ? 'active' : '' ?>" href="user_products.php">PRODUCTS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'user_orders.php' ? 'active' : '' ?>" href="user_orders.php">MY ORDERS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'user_cart.php' ? 'active' : '' ?> position-relative" href="user_cart.php">
                            CART
                            <?php if (isset($cartCount) && $cartCount > 0): ?>
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
<?php endif; ?>
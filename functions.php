<?php
/**
 * Helper functions for the Warehouse Management System
 */

/**
 * Check if current user is an admin
 * @return bool True if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if current user is staff
 * @return bool True if user is staff
 */
function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

/**
 * Check if current user is a regular user
 * @return bool True if user is regular user
 */
function isUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

/**
 * Check if current user has admin privileges (admin or staff)
 * @return bool True if user has admin privileges
 */
function hasAdminAccess() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff');
}

/**
 * Restrict page to admin only
 * Redirects to appropriate page if not admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        if (isStaff()) {
            // Redirect staff to staff dashboard with message
            header('Location: index.php?error=admin_only');
        } else {
            // Redirect regular users to user home
            header('Location: user_home.php');
        }
        exit;
    }
}

/**
 * Restrict page to staff and admin only
 * Redirects to user home if regular user
 */
function requireAdminAccess() {
    if (!hasAdminAccess()) {
        header('Location: user_home.php');
        exit;
    }
}

/**
 * Restrict page to authenticated users
 * Redirects to login if not logged in
 */
function requireLogin() {
    if (empty($_SESSION['uid'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Format a date string to a more readable format
 * @param string $dateString The date string to format
 * @param string $format The format to use (default: 'M j, Y')
 * @return string Formatted date
 */
function formatDate($dateString, $format = 'M j, Y') {
    $date = new DateTime($dateString);
    return $date->format($format);
}

/**
 * Format a number as currency
 * @param float $number The number to format
 * @param int $decimals Number of decimal places (default: 2)
 * @return string Formatted currency
 */
function formatCurrency($number, $decimals = 2) {
    return '$' . number_format($number, $decimals);
}

/**
 * Generate a random reference number
 * @param string $prefix Prefix for the reference number
 * @return string Reference number
 */
function generateReferenceNumber($prefix = 'REF') {
    return $prefix . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Sanitize and filter input data
 * @param string $data The data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Create a breadcrumb navigation
 * @param array $items Array of breadcrumb items with 'title' and 'url'
 * @return string HTML for breadcrumb navigation
 */
function breadcrumb($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    $lastIndex = count($items) - 1;
    
    foreach ($items as $index => $item) {
        if ($index === $lastIndex) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $item['title'] . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . $item['url'] . '">' . $item['title'] . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Check if a file exists and is readable
 * @param string $filePath Path to the file
 * @return bool True if file exists and is readable
 */
function fileExists($filePath) {
    return file_exists($filePath) && is_readable($filePath);
}

/**
 * Log an action to database or file
 * @param string $action The action to log
 * @param string $details Details about the action
 * @param int $userId ID of user performing the action
 * @return bool True on success, false on failure
 */
function logAction($action, $details, $userId = null) {
    global $pdo;
    
    if (!$userId && isset($_SESSION['uid'])) {
        $userId = $_SESSION['uid'];
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, timestamp) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$userId, $action, $details]);
    } catch (PDOException $e) {
        // Fallback to file logging if database logging fails
        $logFile = __DIR__ . '/logs/activity.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] User:$userId Action:$action Details:$details\n";
        
        return file_put_contents($logFile, $logEntry, FILE_APPEND) !== false;
    }
}

/**
 * Check if the current user has permission to perform an action
 * @param string $action The action to check permission for
 * @return bool True if allowed, false otherwise
 */
function userCan($action) {
    // Define permissions based on roles
    $permissions = [
        'admin' => ['manage_users', 'manage_inventory', 'manage_suppliers', 'view_reports', 'manage_staff'],
        'staff' => ['manage_inventory', 'manage_suppliers', 'view_reports'],
        'user' => ['view_products', 'place_orders', 'view_orders']
    ];
    
    $userRole = $_SESSION['role'] ?? 'guest';
    
    // Check if the user's role has the required permission
    if (isset($permissions[$userRole]) && in_array($action, $permissions[$userRole])) {
        return true;
    }
    
    return false;
}
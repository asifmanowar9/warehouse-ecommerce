<?php
require 'db.php';

// Check if user is logged in
if (empty($_SESSION['uid'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['uid'];
$response = ['success' => false];

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'cancel_order':
            // Cancel an order
            $orderId = intval($_POST['order_id'] ?? 0);
            
            if ($orderId <= 0) {
                $response = ['success' => false, 'message' => 'Invalid order ID'];
                break;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Check if order belongs to user and is in a cancelable state
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'PENDING'");
                $stmt->execute([$orderId, $userId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$order) {
                    $response = ['success' => false, 'message' => 'Order not found or cannot be canceled'];
                    break;
                }
                
                // Update order status
                $stmt = $pdo->prepare("UPDATE orders SET status = 'CANCELLED' WHERE id = ?");
                $stmt->execute([$orderId]);
                
                // Record status change in history
                $stmt = $pdo->prepare("INSERT INTO order_status_history 
                                      (order_id, status, notes, timestamp) 
                                      VALUES (?, 'CANCELLED', 'Cancelled by customer', NOW())");
                $stmt->execute([$orderId]);
                
                // Return items to inventory (revert the stock movement)
                $stmt = $pdo->prepare("SELECT oi.product_id, oi.quantity 
                                      FROM order_items oi 
                                      WHERE oi.order_id = ?");
                $stmt->execute([$orderId]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($items as $item) {
                    $stmt = $pdo->prepare("INSERT INTO stock_movements 
                                          (product_id, movement_type, qty, reference, moved_by) 
                                          VALUES (?, 'ADJUST', ?, ?, ?)");
                    $stmt->execute([
                        $item['product_id'],
                        $item['quantity'],  // Positive number to add back to inventory
                        'Return from cancelled order #' . $orderId,
                        $userId
                    ]);
                }
                
                $pdo->commit();
                $response = ['success' => true, 'message' => 'Order cancelled successfully'];
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
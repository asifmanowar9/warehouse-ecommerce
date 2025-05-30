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
        case 'add_to_cart':
            // Add item to cart
            $productId = intval($_POST['product_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);
            
            if ($productId <= 0 || $quantity <= 0) {
                $response = ['success' => false, 'message' => 'Invalid product or quantity'];
                break;
            }
            
            try {
                // Check if product exists and has enough stock
                $stmt = $pdo->prepare("SELECT p.id, v.on_hand as stock FROM products p 
                                      JOIN v_product_stock v ON p.id = v.id 
                                      WHERE p.id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    $response = ['success' => false, 'message' => 'Product not found'];
                    break;
                }
                
                if ($product['stock'] < $quantity) {
                    $response = ['success' => false, 'message' => 'Not enough stock available'];
                    break;
                }
                
                // Check if product already in cart
                $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
                $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cartItem) {
                    // Update quantity if already in cart
                    $newQuantity = $cartItem['quantity'] + $quantity;
                    
                    // Check if new quantity exceeds stock
                    if ($newQuantity > $product['stock']) {
                        $response = ['success' => false, 'message' => 'Cannot add more units than available in stock'];
                        break;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $stmt->execute([$newQuantity, $cartItem['id']]);
                } else {
                    // Add new item to cart
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, date_added) 
                                          VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$userId, $productId, $quantity]);
                }
                
                // Get updated cart count
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
                $stmt->execute([$userId]);
                $cartCount = $stmt->fetchColumn();
                
                $response = [
                    'success' => true, 
                    'message' => 'Product added to cart successfully',
                    'cart_count' => $cartCount
                ];
                
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
            break;
            
        case 'update_quantity':
            // Update item quantity
            $cartId = intval($_POST['cart_id'] ?? 0);
            $adjustment = intval($_POST['adjustment'] ?? 0);
            
            if ($cartId <= 0 || $adjustment == 0) {
                $response = ['success' => false, 'message' => 'Invalid cart item or adjustment'];
                break;
            }
            
            try {
                // Get current cart item
                $stmt = $pdo->prepare("SELECT c.id, c.product_id, c.quantity, v.on_hand as stock 
                                      FROM cart c
                                      JOIN v_product_stock v ON c.product_id = v.id
                                      WHERE c.id = ? AND c.user_id = ?");
                $stmt->execute([$cartId, $userId]);
                $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$cartItem) {
                    $response = ['success' => false, 'message' => 'Cart item not found'];
                    break;
                }
                
                $newQuantity = $cartItem['quantity'] + $adjustment;
                
                // Remove item if quantity is 0 or less
                if ($newQuantity <= 0) {
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
                    $stmt->execute([$cartId]);
                    $response = ['success' => true, 'message' => 'Item removed from cart'];
                    break;
                }
                
                // Check if new quantity exceeds stock
                if ($newQuantity > $cartItem['stock']) {
                    $response = ['success' => false, 'message' => 'Not enough stock available'];
                    break;
                }
                
                // Update quantity
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $cartId]);
                
                $response = ['success' => true, 'message' => 'Quantity updated successfully'];
                
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
            break;
            
        case 'remove_item':
            // Remove item from cart
            $cartId = intval($_POST['cart_id'] ?? 0);
            
            if ($cartId <= 0) {
                $response = ['success' => false, 'message' => 'Invalid cart item'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cartId, $userId]);
                
                if ($stmt->rowCount() > 0) {
                    $response = ['success' => true, 'message' => 'Item removed successfully'];
                } else {
                    $response = ['success' => false, 'message' => 'Item could not be removed'];
                }
                
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
            break;
            
        case 'clear_cart':
            // Clear entire cart
            try {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                $response = ['success' => true, 'message' => 'Cart cleared successfully'];
                
            } catch (PDOException $e) {
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
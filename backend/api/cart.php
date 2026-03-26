<?php
/**
 * Cart API
 */

require_once 'config.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$identifier = getSessionIdentifier();

// ==================== GET CART ====================
if ($method === 'GET') {
    try {
        $sql = "SELECT 
                    c.*,
                    p.name as product_name,
                    p.slug as product_slug,
                    p.price,
                    p.discount_percentage,
                    p.final_price,
                    p.image_url,
                    p.stock_quantity,
                    (p.final_price * c.quantity) as subtotal
                FROM cart c
                INNER JOIN products p ON c.product_id = p.id
                WHERE c.session_id = :session_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':session_id' => $identifier['session_id']]);
        $items = $stmt->fetchAll();
        
        $total = 0;
        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['product_id'] = (int)$item['product_id'];
            $item['quantity'] = (int)$item['quantity'];
            $item['price'] = (float)$item['price'];
            $item['final_price'] = (float)$item['final_price'];
            $item['subtotal'] = (float)$item['subtotal'];
            $total += $item['subtotal'];
        }
        
        // Calculate shipping
        $shipping = ($total > 0 && $total < FREE_SHIPPING_THRESHOLD) ? SHIPPING_CHARGE : 0;
        $grandTotal = $total + $shipping;
        
        sendSuccess('Cart fetched successfully', [
            'items' => $items,
            'subtotal' => round($total, 2),
            'shipping' => $shipping,
            'total' => round($grandTotal, 2),
            'item_count' => count($items)
        ]);
        
    } catch (PDOException $e) {
        sendError('Failed to fetch cart: ' . $e->getMessage(), 500);
    }
}

// ==================== ADD TO CART ====================
if ($method === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['product_id'])) {
            sendError('Product ID is required', 400);
        }
        
        $product_id = (int)$input['product_id'];
        $quantity = isset($input['quantity']) ? max(1, (int)$input['quantity']) : 1;
        
        // Check product exists and has stock
        $checkStmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = :id AND is_active = 1");
        $checkStmt->execute([':id' => $product_id]);
        $product = $checkStmt->fetch();
        
        if (!$product) {
            sendError('Product not found', 404);
        }
        
        if ($product['stock_quantity'] < $quantity) {
            sendError('Insufficient stock', 400);
        }
        
        // Check if already in cart
        $existStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = :session_id AND product_id = :product_id");
        $existStmt->execute([
            ':session_id' => $identifier['session_id'],
            ':product_id' => $product_id
        ]);
        $existing = $existStmt->fetch();
        
        if ($existing) {
            // Update quantity
            $newQty = min($existing['quantity'] + $quantity, MAX_CART_QUANTITY, $product['stock_quantity']);
            $updateStmt = $pdo->prepare("UPDATE cart SET quantity = :quantity WHERE id = :id");
            $updateStmt->execute([':quantity' => $newQty, ':id' => $existing['id']]);
            
            sendSuccess('Cart updated successfully');
        } else {
            // Insert new
            $insertStmt = $pdo->prepare("INSERT INTO cart (session_id, user_id, product_id, quantity) VALUES (:session_id, :user_id, :product_id, :quantity)");
            $insertStmt->execute([
                ':session_id' => $identifier['session_id'],
                ':user_id' => $identifier['user_id'],
                ':product_id' => $product_id,
                ':quantity' => min($quantity, MAX_CART_QUANTITY)
            ]);
            
            sendSuccess('Added to cart successfully', ['cart_id' => $pdo->lastInsertId()]);
        }
        
    } catch (PDOException $e) {
        sendError('Failed to add to cart: ' . $e->getMessage(), 500);
    }
}

// ==================== UPDATE CART QUANTITY ====================
if ($method === 'PUT') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['cart_id']) || empty($input['quantity'])) {
            sendError('Cart ID and quantity are required', 400);
        }
        
        $quantity = max(1, min((int)$input['quantity'], MAX_CART_QUANTITY));
        
        $stmt = $pdo->prepare("UPDATE cart SET quantity = :quantity WHERE id = :id AND session_id = :session_id");
        $stmt->execute([
            ':quantity' => $quantity,
            ':id' => $input['cart_id'],
            ':session_id' => $identifier['session_id']
        ]);
        
        sendSuccess('Cart updated successfully');
        
    } catch (PDOException $e) {
        sendError('Failed to update cart: ' . $e->getMessage(), 500);
    }
}

// ==================== REMOVE FROM CART ====================
if ($method === 'DELETE') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['cart_id'])) {
            sendError('Cart ID is required', 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = :id AND session_id = :session_id");
        $stmt->execute([
            ':id' => $input['cart_id'],
            ':session_id' => $identifier['session_id']
        ]);
        
        sendSuccess('Item removed from cart');
        
    } catch (PDOException $e) {
        sendError('Failed to remove item: ' . $e->getMessage(), 500);
    }
}
?>

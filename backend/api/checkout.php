<?php
/**
 * Checkout API
 */

require_once 'config.php';

$pdo = getDBConnection();
$identifier = getSessionIdentifier();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'shipping_city', 'shipping_state', 'shipping_pincode'];
        $errors = validateRequired($input, $required);
        
        if (!empty($errors)) {
            sendError('Validation failed', 400, $errors);
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get cart items
        $cartStmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.discount_percentage, p.final_price, p.image_url, p.stock_quantity
            FROM cart c
            INNER JOIN products p ON c.product_id = p.id
            WHERE c.session_id = :session_id
        ");
        $cartStmt->execute([':session_id' => $identifier['session_id']]);
        $cartItems = $cartStmt->fetchAll();
        
        if (empty($cartItems)) {
            $pdo->rollBack();
            sendError('Cart is empty', 400);
        }
        
        // Calculate totals
        $subtotal = 0;
        foreach ($cartItems as $item) {
            // Check stock
            if ($item['stock_quantity'] < $item['quantity']) {
                $pdo->rollBack();
                sendError('Insufficient stock for ' . $item['name'], 400);
            }
            $subtotal += $item['final_price'] * $item['quantity'];
        }
        
        $shipping = ($subtotal < FREE_SHIPPING_THRESHOLD) ? SHIPPING_CHARGE : 0;
        $tax = round($subtotal * TAX_PERCENTAGE, 2);
        $total = $subtotal + $shipping + $tax;
        
        // Generate order number
        $orderNumber = generateOrderNumber();
        
        // Create order
        $orderStmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, user_id, session_id, customer_name, customer_email, customer_phone,
                shipping_address, shipping_city, shipping_state, shipping_pincode,
                subtotal, shipping_charge, tax_amount, total_amount,
                payment_method, payment_status, order_status
            ) VALUES (
                :order_number, :user_id, :session_id, :customer_name, :customer_email, :customer_phone,
                :shipping_address, :shipping_city, :shipping_state, :shipping_pincode,
                :subtotal, :shipping_charge, :tax_amount, :total_amount,
                :payment_method, 'pending', 'pending'
            )
        ");
        
        $orderStmt->execute([
            ':order_number' => $orderNumber,
            ':user_id' => $identifier['user_id'],
            ':session_id' => $identifier['session_id'],
            ':customer_name' => sanitizeInput($input['customer_name']),
            ':customer_email' => sanitizeInput($input['customer_email']),
            ':customer_phone' => sanitizeInput($input['customer_phone']),
            ':shipping_address' => sanitizeInput($input['shipping_address']),
            ':shipping_city' => sanitizeInput($input['shipping_city']),
            ':shipping_state' => sanitizeInput($input['shipping_state']),
            ':shipping_pincode' => sanitizeInput($input['shipping_pincode']),
            ':subtotal' => $subtotal,
            ':shipping_charge' => $shipping,
            ':tax_amount' => $tax,
            ':total_amount' => $total,
            ':payment_method' => sanitizeInput($input['payment_method'] ?? 'cod')
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Create order items and update stock
        $itemStmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, price, discount_percentage, subtotal)
            VALUES (:order_id, :product_id, :product_name, :product_image, :quantity, :price, :discount_percentage, :subtotal)
        ");
        
        $stockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id");
        
        foreach ($cartItems as $item) {
            $itemSubtotal = $item['final_price'] * $item['quantity'];
            
            $itemStmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $item['product_id'],
                ':product_name' => $item['name'],
                ':product_image' => $item['image_url'],
                ':quantity' => $item['quantity'],
                ':price' => $item['price'],
                ':discount_percentage' => $item['discount_percentage'],
                ':subtotal' => $itemSubtotal
            ]);
            
            $stockStmt->execute([
                ':quantity' => $item['quantity'],
                ':product_id' => $item['product_id']
            ]);
        }
        
        // Clear cart
        $clearStmt = $pdo->prepare("DELETE FROM cart WHERE session_id = :session_id");
        $clearStmt->execute([':session_id' => $identifier['session_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        sendSuccess('Order placed successfully', [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'total_amount' => round($total, 2)
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        sendError('Checkout failed: ' . $e->getMessage(), 500);
    }
}
?>

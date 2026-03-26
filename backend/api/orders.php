<?php
/**
 * Orders API
 */

require_once 'config.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$identifier = getSessionIdentifier();

// ==================== GET ORDERS ====================
if ($method === 'GET') {
    try {
        // Single order by ID
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT o.*, COUNT(oi.id) as item_count
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.id = :id AND o.session_id = :session_id
                GROUP BY o.id
            ");
            $stmt->execute([
                ':id' => $_GET['id'],
                ':session_id' => $identifier['session_id']
            ]);
            $order = $stmt->fetch();
            
            if (!$order) {
                sendError('Order not found', 404);
            }
            
            // Get order items
            $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
            $itemsStmt->execute([':order_id' => $order['id']]);
            $order['items'] = $itemsStmt->fetchAll();
            
            sendSuccess('Order fetched successfully', $order);
        } 
        // All orders for session
        else {
            $stmt = $pdo->prepare("
                SELECT o.*, COUNT(oi.id) as item_count
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.session_id = :session_id
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([':session_id' => $identifier['session_id']]);
            $orders = $stmt->fetchAll();
            
            sendSuccess('Orders fetched successfully', $orders);
        }
        
    } catch (PDOException $e) {
        sendError('Failed to fetch orders: ' . $e->getMessage(), 500);
    }
}
?>

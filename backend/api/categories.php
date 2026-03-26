<?php
/**
 * Categories API
 */

require_once 'config.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Fetch all active categories
        $sql = "SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order, name";
        $stmt = $pdo->query($sql);
        $categories = $stmt->fetchAll();
        
        // For each category, fetch subcategories
        foreach ($categories as &$category) {
            $category['id'] = (int)$category['id'];
            
            $subSql = "SELECT id, name, slug 
                       FROM subcategories 
                       WHERE category_id = :category_id AND is_active = 1 
                       ORDER BY name";
            $subStmt = $pdo->prepare($subSql);
            $subStmt->execute([':category_id' => $category['id']]);
            $subcategories = $subStmt->fetchAll();
            
            foreach ($subcategories as &$sub) {
                $sub['id'] = (int)$sub['id'];
            }
            
            $category['subcategories'] = $subcategories;
        }
        
        sendSuccess('Categories fetched successfully', $categories);
        
    } catch (PDOException $e) {
        sendError('Failed to fetch categories: ' . $e->getMessage(), 500);
    }
}
?>

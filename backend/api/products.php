<?php
/**
 * Products API
 * Handles all product-related operations
 */

require_once 'config.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ==================== GET PRODUCTS ====================
if ($method === 'GET' && empty($_GET['id'])) {
    
    try {
        // Build base query
        $sql = "SELECT 
                    p.*,
                    c.name as category_name,
                    c.slug as category_slug,
                    COALESCE(s.name, '') as subcategory_name,
                    COALESCE(s.slug, '') as subcategory_slug
                FROM products p
                INNER JOIN categories c ON p.category_id = c.id
                LEFT JOIN subcategories s ON p.subcategory_id = s.id
                WHERE p.is_active = 1";
        
        $params = [];
        
        // Filter by category slug
        if (!empty($_GET['category'])) {
            $sql .= " AND c.slug = :category";
            $params[':category'] = $_GET['category'];
        }
        
        // Filter by subcategory slug
        if (!empty($_GET['subcategory'])) {
            $sql .= " AND s.slug = :subcategory";
            $params[':subcategory'] = $_GET['subcategory'];
        }
        
        // Filter by featured
        if (isset($_GET['featured']) && $_GET['featured'] == '1') {
            $sql .= " AND p.is_featured = 1";
        }
        
        // Filter by trending
        if (isset($_GET['trending']) && $_GET['trending'] == '1') {
            $sql .= " AND p.is_trending = 1";
        }
        
        // Filter by new launch
        if (isset($_GET['new_launch']) && $_GET['new_launch'] == '1') {
            $sql .= " AND p.is_new_launch = 1";
        }
        
        // Search by name or description
        if (!empty($_GET['search'])) {
            $sql .= " AND (p.name LIKE :search OR p.description LIKE :search)";
            $params[':search'] = '%' . $_GET['search'] . '%';
        }
        
        // Price range filter
        if (!empty($_GET['min_price'])) {
            $sql .= " AND p.final_price >= :min_price";
            $params[':min_price'] = $_GET['min_price'];
        }
        
        if (!empty($_GET['max_price'])) {
            $sql .= " AND p.final_price <= :max_price";
            $params[':max_price'] = $_GET['max_price'];
        }
        
        // Get total count for pagination
        $countSql = preg_replace('/SELECT .* FROM/', 'SELECT COUNT(*) as total FROM', $sql);
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalProducts = $countStmt->fetchColumn();
        
        // Sorting
        $sort = $_GET['sort'] ?? 'newest';
        switch ($sort) {
            case 'price_low':
                $sql .= " ORDER BY p.final_price ASC";
                break;
            case 'price_high':
                $sql .= " ORDER BY p.final_price DESC";
                break;
            case 'rating':
                $sql .= " ORDER BY p.rating DESC";
                break;
            case 'popular':
                $sql .= " ORDER BY p.view_count DESC";
                break;
            case 'name':
                $sql .= " ORDER BY p.name ASC";
                break;
            default:
                $sql .= " ORDER BY p.created_at DESC";
        }
        
        // Pagination
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind pagination params
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        // Bind other params
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $products = $stmt->fetchAll();
        
        // Format products
        foreach ($products as &$product) {
            $product['id'] = (int)$product['id'];
            $product['price'] = (float)$product['price'];
            $product['discount_percentage'] = (int)$product['discount_percentage'];
            $product['final_price'] = (float)$product['final_price'];
            $product['stock_quantity'] = (int)$product['stock_quantity'];
            $product['rating'] = (float)$product['rating'];
            $product['review_count'] = (int)$product['review_count'];
            $product['is_featured'] = (bool)$product['is_featured'];
            $product['is_trending'] = (bool)$product['is_trending'];
            $product['is_new_launch'] = (bool)$product['is_new_launch'];
            
            // Parse JSON fields
            $product['benefits'] = $product['benefits'] ? json_decode($product['benefits'], true) : [];
            $product['image_gallery'] = $product['image_gallery'] ? json_decode($product['image_gallery'], true) : [];
            
            // Calculate savings
            if ($product['discount_percentage'] > 0) {
                $product['savings'] = round($product['price'] - $product['final_price'], 2);
            }
        }
        
        // Pagination meta
        $totalPages = ceil($totalProducts / $limit);
        
        sendSuccess('Products fetched successfully', $products, [
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => (int)$totalProducts,
                'items_per_page' => $limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Failed to fetch products: ' . $e->getMessage(), 500);
    }
}

// ==================== GET SINGLE PRODUCT ====================
if ($method === 'GET' && !empty($_GET['id'])) {
    
    try {
        $sql = "SELECT 
                    p.*,
                    c.name as category_name,
                    c.slug as category_slug,
                    COALESCE(s.name, '') as subcategory_name,
                    COALESCE(s.slug, '') as subcategory_slug
                FROM products p
                INNER JOIN categories c ON p.category_id = c.id
                LEFT JOIN subcategories s ON p.subcategory_id = s.id
                WHERE p.id = :id AND p.is_active = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $_GET['id']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            sendError('Product not found', 404);
        }
        
        // Format product
        $product['id'] = (int)$product['id'];
        $product['price'] = (float)$product['price'];
        $product['final_price'] = (float)$product['final_price'];
        $product['rating'] = (float)$product['rating'];
        $product['benefits'] = $product['benefits'] ? json_decode($product['benefits'], true) : [];
        $product['image_gallery'] = $product['image_gallery'] ? json_decode($product['image_gallery'], true) : [];
        
        // Increment view count
        $updateStmt = $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = :id");
        $updateStmt->execute([':id' => $_GET['id']]);
        
        // Get related products
        $relatedSql = "SELECT id, name, slug, price, final_price, discount_percentage, image_url, rating 
                       FROM products 
                       WHERE category_id = :category_id 
                       AND id != :product_id 
                       AND is_active = 1 
                       LIMIT 4";
        $relatedStmt = $pdo->prepare($relatedSql);
        $relatedStmt->execute([
            ':category_id' => $product['category_id'],
            ':product_id' => $product['id']
        ]);
        $relatedProducts = $relatedStmt->fetchAll();
        
        $product['related_products'] = $relatedProducts;
        
        sendSuccess('Product fetched successfully', $product);
        
    } catch (PDOException $e) {
        sendError('Failed to fetch product: ' . $e->getMessage(), 500);
    }
}
?>

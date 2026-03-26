<?php
/**
 * Database Configuration
 * Beauty E-commerce Backend
 */

// CORS Headers - Allow React to connect
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'beauty_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('ITEMS_PER_PAGE', 12);
define('MAX_CART_QUANTITY', 10);
define('SHIPPING_CHARGE', 50);
define('FREE_SHIPPING_THRESHOLD', 1000);
define('TAX_PERCENTAGE', 0); // 0% GST for now

/**
 * Get Database Connection
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            sendError('Database connection failed: ' . $e->getMessage(), 500);
            exit();
        }
    }
    
    return $pdo;
}

/**
 * Send JSON Success Response
 */
function sendSuccess($message = 'Success', $data = null, $meta = []) {
    $response = [
        'success' => true,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if (!empty($meta)) {
        $response['meta'] = $meta;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

/**
 * Send JSON Error Response
 */
function sendError($message = 'Error occurred', $code = 400, $errors = []) {
    http_response_code($code);
    $response = [
        'success' => false,
        'message' => $message
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

/**
 * Get Session ID or User ID
 */
function getSessionIdentifier() {
    session_start();
    return [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null
    ];
}

/**
 * Validate Required Fields
 */
function validateRequired($data, $required_fields) {
    $errors = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

/**
 * Sanitize Input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate Order Number
 */
function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Generate Slug from String
 */
function generateSlug($string) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    return $slug;
}
?>

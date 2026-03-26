<?php
/**
 * Authentication API
 */

require_once 'config.php';

$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// ==================== SIGNUP ====================
if ($action === 'signup') {
    try {
        $required = ['name', 'email', 'password'];
        $errors = validateRequired($input, $required);
        
        if (!empty($errors)) {
            sendError('Validation failed', 400, $errors);
        }
        
        // Check if email exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $checkStmt->execute([':email' => $input['email']]);
        
        if ($checkStmt->fetch()) {
            sendError('Email already registered', 400);
        }
        
        // Hash password
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, phone) 
            VALUES (:name, :email, :password, :phone)
        ");
        
        $stmt->execute([
            ':name' => sanitizeInput($input['name']),
            ':email' => sanitizeInput($input['email']),
            ':password' => $hashedPassword,
            ':phone' => sanitizeInput($input['phone'] ?? '')
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Set session
        session_start();
        $_SESSION['user_id'] = $userId;
        $_SESSION['name'] = $input['name'];
        $_SESSION['email'] = $input['email'];
        
        sendSuccess('Signup successful', [
            'user' => [
                'id' => $userId,
                'name' => $input['name'],
                'email' => $input['email']
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Signup failed: ' . $e->getMessage(), 500);
    }
}

// ==================== LOGIN ====================
if ($action === 'login') {
    try {
        $required = ['email', 'password'];
        $errors = validateRequired($input, $required);
        
        if (!empty($errors)) {
            sendError('Validation failed', 400, $errors);
        }
        
        // Fetch user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $input['email']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($input['password'], $user['password'])) {
            sendError('Invalid email or password', 401);
        }
        
        // Set session
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        sendSuccess('Login successful', [
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => (bool)$user['is_admin']
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Login failed: ' . $e->getMessage(), 500);
    }
}

// ==================== LOGOUT ====================
if ($action === 'logout') {
    session_start();
    session_destroy();
    sendSuccess('Logged out successfully');
}

// ==================== GET USER ====================
if ($action === 'get_user') {
    session_start();
    if (isset($_SESSION['user_id'])) {
        sendSuccess('User found', [
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['name'],
                'email' => $_SESSION['email'],
                'is_admin' => $_SESSION['is_admin'] ?? false
            ]
        ]);
    } else {
        sendError('Not logged in', 401);
    }
}
?>

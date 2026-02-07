<?php
// includes/session.php
/**
 * Session Management for MarketConnect
 * Handles user authentication and session data
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * Get current user ID
 * @return int|null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user type
 * @return string|null
 */
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Get current user name
 * @return string
 */
function getUserName() {
    return $_SESSION['full_name'] ?? 'Guest';
}

/**
 * Get current user email
 * @return string|null
 */
function getUserEmail() {
    return $_SESSION['email'] ?? null;
}

/**
 * Set user session data after successful login
 * @param array $user User data from database
 */
function setUserSession($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['logged_in_at'] = time();
    
    // Update last login in database
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

/**
 * Destroy user session (logout)
 */
function destroySession() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit();
    }
}

/**
 * Require specific user type
 * @param string $type Required user type
 */
function requireUserType($type) {
    requireLogin();
    
    if (getUserType() !== $type) {
        header("Location: /index.php");
        exit();
    }
}

/**
 * Check if user is admin
 * @return bool
 */
function isAdmin() {
    return getUserType() === 'admin';
}

/**
 * Check if user is vendor
 * @return bool
 */
function isVendor() {
    return getUserType() === 'vendor';
}

/**
 * Check if user is customer
 * @return bool
 */
function isCustomer() {
    return getUserType() === 'customer';
}

/**
 * Require admin access - redirect if not admin
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header("Location: /index.php");
        exit();
    }
}

/**
 * Require vendor access - redirect if not vendor
 */
function requireVendor() {
    requireLogin();
    
    if (!isVendor()) {
        header("Location: /index.php");
        exit();
    }
}

/**
 * Require customer access - redirect if not customer
 */
function requireCustomer() {
    requireLogin();
    
    if (!isCustomer()) {
        header("Location: /index.php");
        exit();
    }
}

/**
 * Prevent CSRF attacks - generate token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message content
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * @return array|null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}
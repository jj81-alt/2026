<?php
// includes/session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_type'] !== 'admin') {
        header("Location: /index.php");
        exit();
    }
}

function requireVendor() {
    requireLogin();
    if ($_SESSION['user_type'] !== 'vendor') {
        header("Location: /index.php");
        exit();
    }
}

function requireCustomer() {
    requireLogin();
    if ($_SESSION['user_type'] !== 'customer') {
        header("Location: /index.php");
        exit();
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function getUserName() {
    return $_SESSION['full_name'] ?? 'User';
}

function setUserSession($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
}

function destroySession() {
    session_unset();
    session_destroy();
}
?>
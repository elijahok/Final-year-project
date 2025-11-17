<?php
require_once __DIR__ . '/../config/config.php';

// Log logout activity
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}

// Destroy session
session_destroy();

// Clear session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header('Location: ' . BASE_URL . '/public/login.php');
exit();
?>

<?php
/**
 * Logout script
 * College Attendance Management System
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Unset all session variables
$_SESSION = [];

// Destroy the session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start a fresh session just for the flash message
session_start();
$_SESSION['flash_message'] = "You have been successfully logged out.";
$_SESSION['flash_type'] = "success";

header("Location: " . BASE_URL . "login.php");
exit;

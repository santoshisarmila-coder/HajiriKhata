<?php
/**
 * Root Index Entrypoint
 * College Attendance Management System
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        header("Location: " . BASE_URL . "admin/dashboard.php");
        exit;
    } elseif ($role === 'teacher') {
        header("Location: " . BASE_URL . "teacher/dashboard.php");
        exit;
    } elseif ($role === 'student') {
        header("Location: " . BASE_URL . "student/dashboard.php");
        exit;
    }
}

// Default redirect to login
header("Location: " . BASE_URL . "login.php");
exit;

<?php
/**
 * Authentication and Authorization Middleware
 * College Attendance Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure session cookies settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

/**
 * Check if a user is currently logged in
 * @return bool
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user details from session
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'            => $_SESSION['user_id'],
        'username'      => $_SESSION['username'] ?? '',
        'full_name'     => $_SESSION['full_name'] ?? '',
        'email'         => $_SESSION['email'] ?? '',
        'role'          => $_SESSION['role'] ?? '',
        'department_id' => $_SESSION['department_id'] ?? null,
        'roll_no'       => $_SESSION['roll_no'] ?? null
    ];
}

/**
 * Restrict page to logged in users only. Redirect to login.php otherwise.
 */
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash_message'] = "Please log in to access this page.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

/**
 * Restrict page access by user roles (e.g. ['admin'], ['admin', 'teacher'])
 * @param string|array $allowed_roles
 */
function require_role($allowed_roles): void {
    require_login();
    
    $allowed = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    $current_role = $_SESSION['role'] ?? '';

    if (!in_array($current_role, $allowed, true)) {
        $_SESSION['flash_message'] = "Unauthorized access. You do not have permission to view that resource.";
        $_SESSION['flash_type'] = "danger";
        
        // Redirect to their respective dashboard
        if ($current_role === 'admin') {
            header("Location: " . BASE_URL . "admin/dashboard.php");
        } elseif ($current_role === 'teacher') {
            header("Location: " . BASE_URL . "teacher/dashboard.php");
        } elseif ($current_role === 'student') {
            header("Location: " . BASE_URL . "student/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "login.php");
        }
        exit;
    }
}

/**
 * If already logged in, redirect to their role-specific dashboard
 */
function redirect_if_logged_in(): void {
    if (is_logged_in()) {
        $role = $_SESSION['role'] ?? '';
        if ($role === 'admin') {
            header("Location: " . BASE_URL . "admin/dashboard.php");
        } elseif ($role === 'teacher') {
            header("Location: " . BASE_URL . "teacher/dashboard.php");
        } elseif ($role === 'student') {
            header("Location: " . BASE_URL . "student/dashboard.php");
        }
        exit;
    }
}

/**
 * Generate CSRF Token
 * @return string
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output hidden CSRF input field for forms
 * @return string
 */
function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

/**
 * Check if an attendance record is within the editable window (24 hours or Admin bypass)
 * @param string $created_at Timestamp
 * @param bool $is_admin
 * @return bool
 */
function can_edit_attendance(string $created_at, bool $is_admin = false): bool {
    if ($is_admin) {
        return true;
    }
    $created_timestamp = strtotime($created_at);
    $diff_hours = (time() - $created_timestamp) / 3600;
    return $diff_hours <= ATTENDANCE_EDIT_HOURS;
}

<?php
/**
 * Database Configuration & Connection (PDO)
 * College Attendance Management System
 */

// Define system constants
if (!defined('APP_NAME')) {
    define('APP_NAME', 'HajiriKhata - College Attendance System');
}
if (!defined('BASE_URL')) {
    // Build the URL from the actual project folder under the web root.
    // This avoids 404s when the project directory name includes spaces, different casing,
    // or when the app is served from a subfolder instead of the server root.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $project_root = realpath(__DIR__ . '/..');
    $document_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $project_path = '';

    if ($project_root && $document_root) {
        $project_root_url = str_replace('\\', '/', $project_root);
        $document_root_url = str_replace('\\', '/', $document_root);

        if (stripos($project_root_url, $document_root_url) === 0) {
            $project_path = substr($project_root_url, strlen($document_root_url));
        }
    }

    if ($project_path === '') {
        $project_path = '/' . basename($project_root ?: dirname(__DIR__));
    }

    $project_segments = array_filter(explode('/', trim($project_path, '/')));
    $project_path = $project_segments ? '/' . implode('/', array_map('rawurlencode', $project_segments)) : '';

    define('BASE_URL', $protocol . $host . $project_path . '/');
}
if (!defined('ATTENDANCE_THRESHOLD')) {
    define('ATTENDANCE_THRESHOLD', 75.0); // 75% minimum required attendance
}
if (!defined('ATTENDANCE_EDIT_HOURS')) {
    define('ATTENDANCE_EDIT_HOURS', 24); // 24 hours edit limit for teachers
}

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'attendance_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton or new PDO instance
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly error handling if database isn't imported yet
            die("<div style='font-family: sans-serif; padding: 30px; margin: 50px auto; max-width: 600px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 8px;'>
                <h3 style='margin-top:0;'>Database Connection Error</h3>
                <p>Could not connect to the database <strong>" . htmlspecialchars(DB_NAME) . "</strong>.</p>
                <p><strong>Details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <hr style='border-top: 1px solid #f5c6cb;'>
                <p style='font-size: 0.9em; margin-bottom: 0;'><strong>Quick Fix:</strong> Please make sure MySQL is running in XAMPP and import <code>schema.sql</code> via phpMyAdmin or MySQL CLI.</p>
            </div>");
        }
    }

    return $pdo;
}

// Instantiate global $pdo for procedural script usage
$pdo = getDBConnection();

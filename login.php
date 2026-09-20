<?php
/**
 * Single Unified Login Page
 * College Attendance Management System
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

redirect_if_logged_in();

$error = '';
$login_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = clean_input($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email OR username = :username LIMIT 1");
            $stmt->execute([
                ':email' => $login_input,
                ':username' => $login_input,
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Prevent Session Fixation
                session_regenerate_id(true);

                $_SESSION['user_id']       = $user['id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['full_name']     = $user['full_name'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['department_id'] = $user['department_id'];
                $_SESSION['roll_no']       = $user['roll_no'];

                // Role-based redirection
                if ($user['role'] === 'admin') {
                    header("Location: " . BASE_URL . "admin/dashboard.php");
                } elseif ($user['role'] === 'teacher') {
                    header("Location: " . BASE_URL . "teacher/dashboard.php");
                } elseif ($user['role'] === 'student') {
                    header("Location: " . BASE_URL . "student/dashboard.php");
                } else {
                    header("Location: " . BASE_URL . "login.php");
                }
                exit;
            } else {
                $error = "Invalid email/username or password.";
            }
        } catch (PDOException $e) {
            $error = "Authentication error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
        }
        .login-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            padding: 2rem;
            color: #ffffff;
            text-align: center;
        }
        .demo-badge-btn {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .demo-badge-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle p-3 mb-2">
            <i class="bi bi-mortarboard-fill fs-2"></i>
        </div>
        <h4 class="fw-bold mb-1">HajariKhata</h4>
        <p class="small text-white-50 mb-0">College Attendance Management System</p>
    </div>

    <div class="p-4">
        <?= display_flash() ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div><?= e($error) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>login.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="login_input" class="form-label fw-semibold text-secondary small">Email or Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="login_input" name="login_input" value="<?= e($login_input) ?>" placeholder="e.g. admin@college.edu" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold text-secondary small">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Enter your password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold mb-3 shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>

            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Autofill helper for demonstration
    function fillCredentials(email, password) {
        document.getElementById('login_input').value = email;
        document.getElementById('password').value = password;
    }

    // Toggle Password Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    if (togglePassword && passwordInput && toggleIcon) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.classList.toggle('bi-eye');
            toggleIcon.classList.toggle('bi-eye-slash');
        });
    }
</script>

</body>
</html>

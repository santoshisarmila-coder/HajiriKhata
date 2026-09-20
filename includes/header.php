<?php
/**
 * Header Layout Template
 * College Attendance Management System
 */

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/db.php';
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$role = $user['role'] ?? '';
$page_title = $page_title ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Custom Application CSS -->
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<div class="d-flex flex-grow-1">
    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- Sidebar Navigation -->
    <aside id="sidebar" class="d-flex flex-column flex-shrink-0">
        <a href="<?= BASE_URL ?>" class="sidebar-brand text-decoration-none">
            <i class="bi bi-mortarboard-fill text-primary fs-4"></i>
            <span>HajiriKhata</span>
        </a>

        <div class="px-3 py-2 border-bottom border-secondary border-opacity-25">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle">
                    <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <div class="text-white small fw-bold text-truncate"><?= e($user['full_name'] ?? 'Guest') ?></div>
                    <span class="badge bg-primary-subtle text-primary text-uppercase" style="font-size: 0.65rem;">
                        <?= e($user['role'] ?? '') ?>
                    </span>
                </div>
            </div>
        </div>

        <ul class="nav nav-pills flex-column mb-auto py-2">
            <?php if ($role === 'admin'): ?>
                <li class="nav-heading">Main</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/dashboard.php" class="nav-link <?= ($current_page === 'dashboard.php' && $current_dir === 'admin') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>

                <li class="nav-heading">Academic Management</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/departments.php" class="nav-link <?= $current_page === 'departments.php' ? 'active' : '' ?>">
                        <i class="bi bi-building"></i> Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/semesters.php" class="nav-link <?= $current_page === 'semesters.php' ? 'active' : '' ?>">
                        <i class="bi bi-calendar3"></i> Semesters
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/subjects.php" class="nav-link <?= $current_page === 'subjects.php' ? 'active' : '' ?>">
                        <i class="bi bi-book"></i> Subjects / Courses
                    </a>
                </li>

                <li class="nav-heading">Users & Assignments</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/teachers.php" class="nav-link <?= $current_page === 'teachers.php' ? 'active' : '' ?>">
                        <i class="bi bi-person-workspace"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/students.php" class="nav-link <?= $current_page === 'students.php' ? 'active' : '' ?>">
                        <i class="bi bi-people"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/assign_students.php" class="nav-link <?= $current_page === 'assign_students.php' ? 'active' : '' ?>">
                        <i class="bi bi-person-plus"></i> Enroll Students
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/assign_teachers.php" class="nav-link <?= $current_page === 'assign_teachers.php' ? 'active' : '' ?>">
                        <i class="bi bi-person-gear"></i> Assign Teachers
                    </a>
                </li>

                <li class="nav-heading">System Reports</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin/reports.php" class="nav-link <?= ($current_page === 'reports.php' || $current_page === 'export.php') ? 'active' : '' ?>">
                        <i class="bi bi-bar-chart-line"></i> Attendance Reports
                    </a>
                </li>

            <?php elseif ($role === 'teacher'): ?>
                <li class="nav-heading">Teacher Portal</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>teacher/dashboard.php" class="nav-link <?= ($current_page === 'dashboard.php' && $current_dir === 'teacher') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>teacher/take_attendance.php" class="nav-link <?= $current_page === 'take_attendance.php' ? 'active' : '' ?>">
                        <i class="bi bi-check2-square"></i> Take Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>teacher/edit_attendance.php" class="nav-link <?= $current_page === 'edit_attendance.php' ? 'active' : '' ?>">
                        <i class="bi bi-pencil-square"></i> Edit Past Records
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>teacher/view_attendance.php" class="nav-link <?= ($current_page === 'view_attendance.php' || $current_page === 'export_subject.php') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Subject Summaries
                    </a>
                </li>

            <?php elseif ($role === 'student'): ?>
                <li class="nav-heading">Student Portal</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>student/dashboard.php" class="nav-link <?= ($current_page === 'dashboard.php' && $current_dir === 'student') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> Overview & Summary
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>student/history.php" class="nav-link <?= $current_page === 'history.php' ? 'active' : '' ?>">
                        <i class="bi bi-clock-history"></i> Attendance History
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="p-3 border-top border-secondary border-opacity-25">
            <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-wrapper">
        <!-- Top Navbar -->
        <header class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button id="sidebarToggle" class="btn btn-light d-lg-none" type="button" aria-label="Toggle Navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h5 class="mb-0 fw-semibold text-slate-800"><?= e($page_title) ?></h5>
            </div>

            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small d-none d-md-inline">
                    <i class="bi bi-calendar-event me-1"></i> <?= date('l, F j, Y') ?>
                </span>
                
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 text-primary"></i>
                        <span class="d-none d-sm-inline fw-medium"><?= e($user['full_name'] ?? 'User') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="dropdown-header">Signed in as <strong><?= e($user['role'] ?? '') ?></strong></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main class="content-body">
            <!-- Global Flash Messages -->
            <?= display_flash() ?>

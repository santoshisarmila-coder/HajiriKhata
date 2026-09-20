<?php
/**
 * Admin Dashboard
 * College Attendance Management System
 */

$page_title = "Admin Dashboard";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Require Admin Role
require_role('admin');

// Fetch System-Wide Analytics
try {
    // 1. Total Students
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $total_students = (int)$stmt->fetchColumn();

    // 2. Total Teachers
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'");
    $total_teachers = (int)$stmt->fetchColumn();

    // 3. Total Departments
    $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
    $total_departments = (int)$stmt->fetchColumn();

    // 4. Total Subjects
    $stmt = $pdo->query("SELECT COUNT(*) FROM subjects");
    $total_subjects = (int)$stmt->fetchColumn();

    // 5. Total Attendance Records & Status Breakdown
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_records,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS total_present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS total_absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS total_late
        FROM attendance
    ");
    $att_stats = $stmt->fetch();
    $total_records = (int)($att_stats['total_records'] ?? 0);
    $total_present = (int)($att_stats['total_present'] ?? 0);
    $total_absent  = (int)($att_stats['total_absent'] ?? 0);
    $total_late    = (int)($att_stats['total_late'] ?? 0);

    $overall_percentage = $total_records > 0 
        ? round((($total_present + $total_late) / $total_records) * 100, 1) 
        : 0;

    // 6. Recent Attendance Sessions
    $stmt = $pdo->query("
        SELECT 
            a.attendance_date,
            a.time_slot,
            s.subject_code,
            s.subject_name,
            u.full_name AS teacher_name,
            COUNT(a.id) AS total_marked,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users u ON a.teacher_id = u.id
        GROUP BY a.attendance_date, a.time_slot, a.subject_id, a.teacher_id
        ORDER BY a.attendance_date DESC, a.created_at DESC
        LIMIT 6
    ");
    $recent_sessions = $stmt->fetchAll();

    // 7. Students with Critical Attendance Shortage (<75%)
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.roll_no,
            u.full_name,
            COUNT(a.id) AS total_classes,
            SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) AS attended_classes,
            ROUND((SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) AS percentage
        FROM users u
        JOIN attendance a ON u.id = a.student_id
        WHERE u.role = 'student'
        GROUP BY u.id
        HAVING total_classes > 0 AND percentage < 75.0
        ORDER BY percentage ASC
        LIMIT 5
    ");
    $critical_students = $stmt->fetchAll();

} catch (PDOException $e) {
    set_flash("Database query error: " . $e->getMessage(), "danger");
    $total_students = $total_teachers = $total_departments = $total_subjects = 0;
    $total_records = $total_present = $total_absent = $total_late = $overall_percentage = 0;
    $recent_sessions = [];
    $critical_students = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Welcome Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Admin Control Center</h3>
            <p class="text-muted mb-0">Overview of college departments, courses, teachers, students, and attendance metrics.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> View Reports
            </a>
            <a href="<?= BASE_URL ?>admin/assign_students.php" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> Enroll Students
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total Students</span>
                        <h3 class="fw-bold my-1 text-slate-800"><?= $total_students ?></h3>
                        <a href="<?= BASE_URL ?>admin/students.php" class="text-primary text-decoration-none small">Manage Students &rarr;</a>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Faculty / Teachers</span>
                        <h3 class="fw-bold my-1 text-slate-800"><?= $total_teachers ?></h3>
                        <a href="<?= BASE_URL ?>admin/teachers.php" class="text-success text-decoration-none small">Manage Faculty &rarr;</a>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Subjects / Courses</span>
                        <h3 class="fw-bold my-1 text-slate-800"><?= $total_subjects ?></h3>
                        <a href="<?= BASE_URL ?>admin/subjects.php" class="text-info text-decoration-none small">Manage Subjects &rarr;</a>
                    </div>
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="bi bi-journal-bookmark-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Avg. Attendance</span>
                        <h3 class="fw-bold my-1 text-slate-800"><?= $overall_percentage ?>%</h3>
                        <span class="small <?= $overall_percentage >= 75 ? 'text-success' : 'text-danger' ?>">
                            <i class="bi <?= $overall_percentage >= 75 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' ?>"></i>
                            <?= $total_records ?> total logs
                        </span>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Chart & Critical Students Alert -->
    <div class="row g-4 mb-4">
        <!-- Attendance Breakdown Chart -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-pie-chart text-primary me-2"></i>Attendance Ratio</span>
                    <span class="badge bg-light text-dark border"><?= $total_records ?> Records</span>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <?php if ($total_records > 0): ?>
                        <div style="max-height: 250px; width: 100%;">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                        <div class="d-flex justify-content-around w-100 mt-3 pt-2 border-top text-center">
                            <div>
                                <small class="text-muted d-block">Present</small>
                                <strong class="text-success"><?= $total_present ?></strong>
                            </div>
                            <div>
                                <small class="text-muted d-block">Late</small>
                                <strong class="text-warning"><?= $total_late ?></strong>
                            </div>
                            <div>
                                <small class="text-muted d-block">Absent</small>
                                <strong class="text-danger"><?= $total_absent ?></strong>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            No attendance records marked yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Shortage Warning List (<75%) -->
        <div class="col-lg-7">
            <div class="card h-100 border-danger border-opacity-25">
                <div class="card-header bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Students Below Required Threshold (< 75%)
                    </span>
                    <span class="badge bg-danger"><?= count($critical_students) ?> Students</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($critical_students)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
                            Excellent! No students currently fall below the 75% attendance threshold.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Roll No</th>
                                        <th>Student Name</th>
                                        <th>Classes</th>
                                        <th>Attended</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($critical_students as $cs): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= e($cs['roll_no'] ?? 'N/A') ?></td>
                                            <td><?= e($cs['full_name']) ?></td>
                                            <td><?= (int)$cs['total_classes'] ?></td>
                                            <td><?= (int)$cs['attended_classes'] ?></td>
                                            <td>
                                                <span class="badge bg-danger px-2 py-1">
                                                    <?= $cs['percentage'] ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Attendance Sessions -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Recent Class Attendance Sessions</span>
            <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-sm btn-outline-secondary">View All Logs</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time Slot</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Total Students</th>
                            <th>Present</th>
                            <th>Late</th>
                            <th>Absent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_sessions)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No attendance sessions recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_sessions as $sess): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= format_date($sess['attendance_date']) ?></span></td>
                                    <td><i class="bi bi-clock me-1 text-muted"></i><?= e($sess['time_slot']) ?></td>
                                    <td>
                                        <strong><?= e($sess['subject_code']) ?></strong> - <?= e($sess['subject_name']) ?>
                                    </td>
                                    <td><?= e($sess['teacher_name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $sess['total_marked'] ?></span></td>
                                    <td><span class="text-success fw-bold"><?= $sess['present_count'] ?></span></td>
                                    <td><span class="text-warning fw-bold"><?= $sess['late_count'] ?></span></td>
                                    <td><span class="text-danger fw-bold"><?= $sess['absent_count'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($total_records > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [<?= $total_present ?>, <?= $total_late ?>, <?= $total_absent ?>],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 15 }
                }
            },
            cutout: '70%'
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

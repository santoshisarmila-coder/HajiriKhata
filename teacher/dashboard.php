<?php
/**
 * Teacher Dashboard
 * College Attendance Management System
 */

$page_title = "Teacher Dashboard";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('teacher');
$user = current_user();
$teacher_id = $user['id'];

try {
    // 1. Fetch assigned subjects with enrolled students count
    $stmt = $pdo->prepare("
        SELECT 
            s.id AS subject_id,
            s.subject_code,
            s.subject_name,
            s.credit_hours,
            d.code AS dept_code,
            sem.name AS sem_name,
            (SELECT COUNT(*) FROM subject_student ss WHERE ss.subject_id = s.id) AS student_count,
            (SELECT COUNT(DISTINCT a.attendance_date) FROM attendance a WHERE a.subject_id = s.id AND a.teacher_id = :teach) AS total_sessions
        FROM subjects s
        JOIN subject_teacher st ON s.id = st.subject_id
        JOIN departments d ON s.department_id = d.id
        JOIN semesters sem ON s.semester_id = sem.id
        WHERE st.teacher_id = :teach_id
        ORDER BY s.subject_code ASC
    ");
    $stmt->execute([':teach' => $teacher_id, ':teach_id' => $teacher_id]);
    $assigned_subjects = $stmt->fetchAll();

    // 2. Count total students across assigned subjects
    $total_students = 0;
    foreach ($assigned_subjects as $subj) {
        $total_students += (int)$subj['student_count'];
    }

    // 3. Count total sessions conducted by teacher this month
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT attendance_date, time_slot, subject_id) 
        FROM attendance 
        WHERE teacher_id = :teach AND MONTH(attendance_date) = MONTH(CURRENT_DATE()) AND YEAR(attendance_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([':teach' => $teacher_id]);
    $sessions_this_month = (int)$stmt->fetchColumn();

    // 4. Today's Marked Sessions
    $stmt = $pdo->prepare("
        SELECT 
            a.attendance_date,
            a.time_slot,
            s.id AS subject_id,
            s.subject_code,
            s.subject_name,
            COUNT(a.id) AS total_marked,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            MAX(a.created_at) AS last_updated
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.teacher_id = :teach AND a.attendance_date = CURRENT_DATE()
        GROUP BY a.attendance_date, a.time_slot, s.id, s.subject_code, s.subject_name
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([':teach' => $teacher_id]);
    $today_sessions = $stmt->fetchAll();

    // 5. Recent Past Attendance Sessions
    $stmt = $pdo->prepare("
        SELECT 
            a.attendance_date,
            a.time_slot,
            s.id AS subject_id,
            s.subject_code,
            s.subject_name,
            COUNT(a.id) AS total_marked,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            MAX(a.created_at) AS created_at
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.teacher_id = :teach
        GROUP BY a.attendance_date, a.time_slot, s.id, s.subject_code, s.subject_name
        ORDER BY a.attendance_date DESC, a.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([':teach' => $teacher_id]);
    $recent_sessions = $stmt->fetchAll();

} catch (PDOException $e) {
    set_flash("Database error: " . $e->getMessage(), "danger");
    $assigned_subjects = [];
    $today_sessions = [];
    $recent_sessions = [];
    $total_students = 0;
    $sessions_this_month = 0;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Welcome Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Welcome back, <?= e($user['full_name']) ?>!</h3>
            <p class="text-muted mb-0">Manage course attendances, conduct new roll calls, and monitor student participation.</p>
        </div>
        <div>
            <a href="take_attendance.php" class="btn btn-primary shadow-sm px-3 py-2">
                <i class="bi bi-check2-square me-1"></i> Take Attendance Now
            </a>
        </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Assigned Subjects</span>
                        <h3 class="fw-bold my-1 text-slate-800"><?= count($assigned_subjects) ?></h3>
                        <span class="text-muted small">Active Teaching Courses</span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-journal-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Enrolled Students</span>
                        <h3 class="fw-bold my-1 text-slate-800"><?= $total_students ?></h3>
                        <span class="text-muted small">Across all assigned classes</span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Classes Marked (This Month)</span>
                        <h3 class="fw-bold my-1 text-slate-800"><?= $sessions_this_month ?></h3>
                        <span class="text-muted small">Recorded sessions</span>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Subjects Cards Grid -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-slate-800"><i class="bi bi-book-half text-primary me-2"></i>My Assigned Subjects</span>
        </div>
        <div class="card-body">
            <?php if (empty($assigned_subjects)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-folder-x fs-1 text-secondary d-block mb-2"></i>
                    You have not been assigned to any subjects yet. Please contact the administrator.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($assigned_subjects as $s): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card border h-100 p-3 shadow-none bg-light bg-opacity-50">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-primary px-2 py-1"><?= e($s['subject_code']) ?></span>
                                    <span class="badge bg-light text-dark border"><?= e($s['dept_code']) ?> | <?= e($s['sem_name']) ?></span>
                                </div>
                                <h5 class="fw-bold text-slate-900 mb-1"><?= e($s['subject_name']) ?></h5>
                                <div class="text-muted small mb-3">
                                    <i class="bi bi-person-badge me-1"></i><?= $s['student_count'] ?> Enrolled Students &bull; <?= $s['total_sessions'] ?> Sessions Taken
                                </div>
                                <div class="mt-auto d-flex gap-2">
                                    <a href="take_attendance.php?subject_id=<?= $s['subject_id'] ?>" class="btn btn-sm btn-primary flex-grow-1">
                                        <i class="bi bi-check2-square me-1"></i> Take Attendance
                                    </a>
                                    <a href="view_attendance.php?subject_id=<?= $s['subject_id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Class Summary">
                                        <i class="bi bi-bar-chart"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Attendance Sessions Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-slate-800"><i class="bi bi-clock-history text-primary me-2"></i>Recent Class Attendance Logs</span>
            <a href="edit_attendance.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil-square me-1"></i> Edit Past Records
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time Slot</th>
                            <th>Subject</th>
                            <th>Total Students</th>
                            <th>Present</th>
                            <th>Late</th>
                            <th>Absent</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_sessions)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No attendance sessions recorded yet. Click "Take Attendance Now" above to begin!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_sessions as $sess): 
                                $editable = can_edit_attendance($sess['created_at']);
                            ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= format_date($sess['attendance_date']) ?></span></td>
                                    <td><small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($sess['time_slot']) ?></small></td>
                                    <td><strong><?= e($sess['subject_code']) ?></strong> - <?= e($sess['subject_name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $sess['total_marked'] ?></span></td>
                                    <td><span class="text-success fw-bold"><?= $sess['present_count'] ?></span></td>
                                    <td><span class="text-warning fw-bold"><?= $sess['late_count'] ?></span></td>
                                    <td><span class="text-danger fw-bold"><?= $sess['absent_count'] ?></span></td>
                                    <td class="text-end">
                                        <?php if ($editable): ?>
                                            <a href="take_attendance.php?subject_id=<?= $sess['subject_id'] ?>&attendance_date=<?= $sess['attendance_date'] ?>&time_slot=<?= urlencode($sess['time_slot']) ?>" class="btn btn-sm btn-outline-primary" title="Edit Session">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border" title="Locked after 24 hours">
                                                <i class="bi bi-lock-fill me-1"></i>Locked
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

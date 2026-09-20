<?php
/**
 * Student Dashboard & Overview
 * College Attendance Management System
 */

$page_title = "Student Dashboard";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('student');
$user = current_user();
$student_id = $user['id'];

try {
    // 1. Fetch Student Profile with Department
    $stmt = $pdo->prepare("
        SELECT u.*, d.name AS dept_name, d.code AS dept_code
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = :id
    ");
    $stmt->execute([':id' => $student_id]);
    $student_info = $stmt->fetch();

    // 2. Fetch Enrolled Subjects & Per-Subject Attendance Statistics
    $stmt = $pdo->prepare("
        SELECT 
            s.id AS subject_id,
            s.subject_code,
            s.subject_name,
            s.credit_hours,
            sem.name AS sem_name,
            d.code AS dept_code,
            (
                SELECT GROUP_CONCAT(t.full_name SEPARATOR ', ')
                FROM subject_teacher st
                JOIN users t ON st.teacher_id = t.id
                WHERE st.subject_id = s.id
            ) AS teachers_assigned,
            (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = s.id AND a.student_id = :sid1) AS total_classes,
            (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = s.id AND a.student_id = :sid2 AND a.status = 'Present') AS present_count,
            (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = s.id AND a.student_id = :sid3 AND a.status = 'Late') AS late_count,
            (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = s.id AND a.student_id = :sid4 AND a.status = 'Absent') AS absent_count
        FROM subjects s
        JOIN subject_student ss ON s.id = ss.subject_id
        JOIN semesters sem ON s.semester_id = sem.id
        JOIN departments d ON s.department_id = d.id
        WHERE ss.student_id = :sid5
        ORDER BY s.subject_code ASC
    ");
    $stmt->execute([
        ':sid1' => $student_id,
        ':sid2' => $student_id,
        ':sid3' => $student_id,
        ':sid4' => $student_id,
        ':sid5' => $student_id
    ]);
    $subjects_attendance = $stmt->fetchAll();

    // 3. Calculate Overall Aggregate Attendance
    $grand_total_classes = 0;
    $grand_present = 0;
    $grand_late = 0;
    $grand_absent = 0;
    $shortage_subjects = [];

    foreach ($subjects_attendance as $sa) {
        $t = (int)$sa['total_classes'];
        $p = (int)$sa['present_count'];
        $l = (int)$sa['late_count'];
        $a = (int)$sa['absent_count'];

        $grand_total_classes += $t;
        $grand_present += $p;
        $grand_late += $l;
        $grand_absent += $a;

        $pct = calculate_attendance_percentage($p, $l, $t);
        if ($t > 0 && $pct < ATTENDANCE_THRESHOLD) {
            $shortage_subjects[] = [
                'code' => $sa['subject_code'],
                'name' => $sa['subject_name'],
                'pct'  => $pct
            ];
        }
    }

    $overall_pct = calculate_attendance_percentage($grand_present, $grand_late, $grand_total_classes);

    // 4. Fetch Recent Attendance History (Last 8 records)
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            s.subject_code,
            s.subject_name,
            t.full_name AS teacher_name
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users t ON a.teacher_id = t.id
        WHERE a.student_id = :sid
        ORDER BY a.attendance_date DESC, a.created_at DESC
        LIMIT 8
    ");
    $stmt->execute([':sid' => $student_id]);
    $recent_history = $stmt->fetchAll();

} catch (PDOException $e) {
    set_flash("Database query error: " . $e->getMessage(), "danger");
    $subjects_attendance = [];
    $recent_history = [];
    $shortage_subjects = [];
    $overall_pct = 0;
    $grand_total_classes = $grand_present = $grand_late = $grand_absent = 0;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Welcome, <?= e($student_info['full_name']) ?>!</h3>
            <p class="text-muted mb-0">
                Roll Number: <span class="fw-semibold text-dark"><?= e($student_info['roll_no'] ?? 'N/A') ?></span> &bull; 
                Department: <span class="fw-semibold text-dark"><?= e($student_info['dept_name'] ?? 'Not Assigned') ?></span>
            </p>
        </div>
        <div>
            <a href="history.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-clock-history me-1"></i> Full Attendance Log
            </a>
        </div>
    </div>

    <!-- Attendance Warning Banner if below threshold -->
    <?php if (!empty($shortage_subjects) || ($grand_total_classes > 0 && $overall_pct < ATTENDANCE_THRESHOLD)): ?>
        <div class="alert alert-danger d-flex align-items-start shadow-sm mb-4 border-danger">
            <i class="bi bi-exclamation-octagon-fill fs-3 text-danger me-3 flex-shrink-0 mt-1"></i>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Attendance Shortage Warning!</h5>
                <p class="mb-2">Your attendance in one or more subjects is below the required <strong><?= ATTENDANCE_THRESHOLD ?>%</strong> minimum threshold. Please contact your subject teacher or academic advisor to avoid examination ineligibility.</p>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($shortage_subjects as $sh): ?>
                        <span class="badge bg-danger text-white px-2.5 py-1.5 fs-6">
                            <i class="bi bi-exclamation-triangle me-1"></i> <?= e($sh['code']) ?>: <?= $sh['pct'] ?>%
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Overall Attendance</span>
                        <h3 class="fw-bold my-1 <?= $overall_pct >= ATTENDANCE_THRESHOLD ? 'text-success' : 'text-danger' ?>">
                            <?= $overall_pct ?>%
                        </h3>
                        <span class="small <?= $overall_pct >= ATTENDANCE_THRESHOLD ? 'text-success' : 'text-danger' ?>">
                            <?= $overall_pct >= ATTENDANCE_THRESHOLD ? '<i class="bi bi-check-circle me-1"></i>Above Requirement' : '<i class="bi bi-exclamation-circle me-1"></i>Below Requirement' ?>
                        </span>
                    </div>
                    <div class="stat-icon <?= $overall_pct >= ATTENDANCE_THRESHOLD ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                        <i class="bi <?= $overall_pct >= ATTENDANCE_THRESHOLD ? 'bi-shield-check' : 'bi-exclamation-triangle' ?>"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Classes Conducted</span>
                        <h3 class="fw-bold my-1 text-slate-800"><?= $grand_total_classes ?></h3>
                        <span class="text-muted small">Total marked lectures</span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Attended Sessions</span>
                        <h3 class="fw-bold my-1 text-success"><?= ($grand_present + $grand_late) ?></h3>
                        <span class="text-muted small"><?= $grand_present ?> Present, <?= $grand_late ?> Late</span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-stat p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Missed / Absent</span>
                        <h3 class="fw-bold my-1 text-danger"><?= $grand_absent ?></h3>
                        <span class="text-danger small">Classes missed</span>
                    </div>
                    <div class="stat-icon bg-danger-subtle text-danger">
                        <i class="bi bi-person-x-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrolled Subjects Attendance Summary Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-slate-800"><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>My Enrolled Subjects Summary</span>
            <span class="badge bg-light text-dark border">Minimum Required: <?= ATTENDANCE_THRESHOLD ?>%</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Semester</th>
                            <th>Faculty Instructor</th>
                            <th>Conducted</th>
                            <th>Attended</th>
                            <th>Absent</th>
                            <th style="min-width: 160px;">Attendance %</th>
                            <th>Status Standing</th>
                            <th class="text-end">History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subjects_attendance)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">You are not enrolled in any subjects currently.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subjects_attendance as $subj): 
                                $t_cls = (int)$subj['total_classes'];
                                $p_cls = (int)$subj['present_count'];
                                $l_cls = (int)$subj['late_count'];
                                $a_cls = (int)$subj['absent_count'];
                                $pct   = calculate_attendance_percentage($p_cls, $l_cls, $t_cls);
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary-subtle text-primary border"><?= e($subj['subject_code']) ?></span>
                                            <div>
                                                <div class="fw-bold"><?= e($subj['subject_name']) ?></div>
                                                <small class="text-muted"><?= (int)$subj['credit_hours'] ?> Credit Hours</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= e($subj['sem_name']) ?></span></td>
                                    <td>
                                        <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($subj['teachers_assigned'] ?? 'TBA') ?></small>
                                    </td>
                                    <td><strong><?= $t_cls ?></strong></td>
                                    <td><span class="text-success fw-bold"><?= ($p_cls + $l_cls) ?></span> <small class="text-muted">(<?= $p_cls ?>P / <?= $l_cls ?>L)</small></td>
                                    <td><span class="text-danger fw-bold"><?= $a_cls ?></span></td>
                                    <td>
                                        <?php if ($t_cls > 0): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar <?= $pct >= ATTENDANCE_THRESHOLD ? 'bg-success' : 'bg-danger' ?>" role="progressbar" style="width: <?= min(100, $pct) ?>%"></div>
                                                </div>
                                                <span class="fw-bold small"><?= $pct ?>%</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">No classes yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($t_cls === 0): ?>
                                            <span class="badge bg-light text-muted border">No Data</span>
                                        <?php elseif ($pct >= ATTENDANCE_THRESHOLD): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-check-circle me-1"></i>Good (<?= $pct ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Shortage (<?= $pct ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="history.php?subject_id=<?= $subj['subject_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Detailed History">
                                            <i class="bi bi-calendar3"></i> Log
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Date-by-Date Attendance Log Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-slate-800"><i class="bi bi-clock-history text-primary me-2"></i>Recent Attendance Records</span>
            <a href="history.php" class="btn btn-sm btn-outline-secondary">View Full History &rarr;</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time Slot</th>
                            <th>Subject</th>
                            <th>Instructor</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_history)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No attendance marked yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_history as $hist): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= format_date($hist['attendance_date']) ?></span></td>
                                    <td><small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($hist['time_slot']) ?></small></td>
                                    <td><strong><?= e($hist['subject_code']) ?></strong> - <?= e($hist['subject_name']) ?></td>
                                    <td><?= e($hist['teacher_name']) ?></td>
                                    <td><?= get_status_badge($hist['status']) ?></td>
                                    <td><small class="text-muted"><?= e($hist['remarks'] ?? '-') ?></small></td>
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

<?php
/**
 * Teacher Subject Attendance Summaries & Student Breakdown
 * College Attendance Management System
 */

$page_title = "Subject Attendance Summary";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('teacher');
$user = current_user();
$teacher_id = $user['id'];

// Fetch teacher's assigned subjects
$stmt = $pdo->prepare("
    SELECT s.id, s.subject_code, s.subject_name, d.code AS dept_code, sem.name AS sem_name
    FROM subjects s
    JOIN subject_teacher st ON s.id = st.subject_id
    JOIN departments d ON s.department_id = d.id
    JOIN semesters sem ON s.semester_id = sem.id
    WHERE st.teacher_id = :teach
    ORDER BY s.subject_code ASC
");
$stmt->execute([':teach' => $teacher_id]);
$assigned_subjects = $stmt->fetchAll();

$selected_subject_id = (int)($_GET['subject_id'] ?? ($assigned_subjects[0]['id'] ?? 0));
$from_date = clean_input($_GET['from_date'] ?? '');
$to_date   = clean_input($_GET['to_date'] ?? '');

$student_summaries = [];
$total_classes_conducted = 0;
$current_subject = null;

if ($selected_subject_id > 0) {
    // Subject Info
    $stmt = $pdo->prepare("
        SELECT s.*, d.name AS dept_name, sem.name AS sem_name
        FROM subjects s
        JOIN departments d ON s.department_id = d.id
        JOIN semesters sem ON s.semester_id = sem.id
        WHERE s.id = :id
    ");
    $stmt->execute([':id' => $selected_subject_id]);
    $current_subject = $stmt->fetch();

    // Query Conditions
    $where = ["a.subject_id = :subj", "a.teacher_id = :teach"];
    $params = [':subj' => $selected_subject_id, ':teach' => $teacher_id];

    if (!empty($from_date)) {
        $where[] = "a.attendance_date >= :from_date";
        $params[':from_date'] = $from_date;
    }
    if (!empty($to_date)) {
        $where[] = "a.attendance_date <= :to_date";
        $params[':to_date'] = $to_date;
    }
    $where_clause = implode(" AND ", $where);

    // Total distinct class sessions
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT attendance_date, time_slot) 
        FROM attendance a
        WHERE {$where_clause}
    ");
    $stmt->execute($params);
    $total_classes_conducted = (int)$stmt->fetchColumn();

    // Enrolled Students with Attendance Breakdown
    $stmt = $pdo->prepare("
        SELECT 
            u.id AS student_id,
            u.roll_no,
            u.full_name,
            u.email,
            COUNT(a.id) AS total_marked,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
        FROM users u
        JOIN subject_student ss ON u.id = ss.student_id
        LEFT JOIN attendance a ON u.id = a.student_id AND {$where_clause}
        WHERE ss.subject_id = :subj_id AND u.role = 'student'
        GROUP BY u.id, u.roll_no, u.full_name, u.email
        ORDER BY u.roll_no ASC
    ");
    // Merge params with subject_id for enrolled list
    $enrolled_params = array_merge($params, [':subj_id' => $selected_subject_id]);
    $stmt->execute($enrolled_params);
    $student_summaries = $stmt->fetchAll();
}

$export_qs = http_build_query([
    'subject_id' => $selected_subject_id,
    'from_date'  => $from_date,
    'to_date'    => $to_date
]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Subject Attendance Summary</h3>
            <p class="text-muted mb-0">Monitor student attendance rates, cumulative percentages, and threshold shortages.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>admin/export.php?format=csv&<?= $export_qs ?>" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Subject CSV
            </a>
            <button type="button" class="btn btn-outline-dark" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Summary
            </button>
        </div>
    </div>

    <!-- Subject Selector Card -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form action="view_attendance.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold small">Choose Course / Subject:</label>
                    <select name="subject_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($assigned_subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_subject_id ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?> (<?= e($s['dept_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold small">From Date (Optional)</label>
                    <input type="date" name="from_date" class="form-control" value="<?= e($from_date) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold small">To Date (Optional)</label>
                    <input type="date" name="to_date" class="form-control" value="<?= e($to_date) ?>">
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($current_subject): ?>
        <!-- Quick Stats Banner -->
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <div class="card p-3 bg-light border shadow-sm text-center">
                    <span class="text-muted small fw-semibold">Total Classes Conducted</span>
                    <h3 class="fw-bold my-1 text-slate-800"><?= $total_classes_conducted ?></h3>
                    <span class="text-muted small">Recorded sessions</span>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card p-3 bg-primary-subtle border-0 shadow-sm text-center">
                    <span class="text-primary small fw-semibold">Enrolled Students</span>
                    <h3 class="fw-bold my-1 text-primary"><?= count($student_summaries) ?></h3>
                    <span class="text-muted small">In class roster</span>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card p-3 bg-success-subtle border-0 shadow-sm text-center">
                    <span class="text-success small fw-semibold">Attendance Threshold</span>
                    <h3 class="fw-bold my-1 text-success"><?= ATTENDANCE_THRESHOLD ?>%</h3>
                    <span class="text-muted small">Minimum requirement</span>
                </div>
            </div>
        </div>

        <!-- Student Roster Attendance Breakdown Table -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold text-slate-800">
                    <i class="bi bi-mortarboard-fill text-primary me-2"></i>
                    <?= e($current_subject['subject_code']) ?> - <?= e($current_subject['subject_name']) ?> (Student Performance)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Classes</th>
                                <th>Present</th>
                                <th>Late</th>
                                <th>Absent</th>
                                <th>Attendance Rate</th>
                                <th>Eligibility Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($student_summaries)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No students enrolled in this subject.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($student_summaries as $sm): 
                                    $t_classes = (int)$sm['total_marked'];
                                    $p_count   = (int)$sm['present_count'];
                                    $l_count   = (int)$sm['late_count'];
                                    $a_count   = (int)$sm['absent_count'];
                                    $pct = calculate_attendance_percentage($p_count, $l_count, $t_classes);
                                ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border fw-semibold"><?= e($sm['roll_no']) ?></span></td>
                                        <td><strong><?= e($sm['full_name']) ?></strong></td>
                                        <td><small class="text-muted"><?= e($sm['email']) ?></small></td>
                                        <td><?= $t_classes ?></td>
                                        <td><span class="text-success fw-bold"><?= $p_count ?></span></td>
                                        <td><span class="text-warning fw-bold"><?= $l_count ?></span></td>
                                        <td><span class="text-danger fw-bold"><?= $a_count ?></span></td>
                                        <td>
                                            <?php if ($t_classes > 0): ?>
                                                <?= get_percentage_badge($pct) ?>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">No logs</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($t_classes === 0): ?>
                                                <span class="badge bg-light text-muted border">Pending</span>
                                            <?php elseif ($pct >= ATTENDANCE_THRESHOLD): ?>
                                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Good Standing</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger text-white"><i class="bi bi-exclamation-triangle-fill me-1"></i>Attendance Shortage</span>
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

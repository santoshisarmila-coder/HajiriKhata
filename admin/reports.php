<?php
/**
 * System-Wide Attendance Reports & Analytics
 * College Attendance Management System
 */

$page_title = "Attendance Reports";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('admin');

// Filter Parameters
$subject_id    = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$department_id = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$student_id    = !empty($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$status        = clean_input($_GET['status'] ?? '');
$from_date     = clean_input($_GET['from_date'] ?? date('Y-m-01')); // Default first of current month
$to_date       = clean_input($_GET['to_date'] ?? date('Y-m-d'));    // Default today

// Build Query Conditions
$where = ["1=1"];
$params = [];

if ($subject_id > 0) {
    $where[] = "a.subject_id = :subject_id";
    $params[':subject_id'] = $subject_id;
}
if ($department_id > 0) {
    $where[] = "s.department_id = :department_id";
    $params[':department_id'] = $department_id;
}
if ($student_id > 0) {
    $where[] = "a.student_id = :student_id";
    $params[':student_id'] = $student_id;
}
if (!empty($status)) {
    $where[] = "a.status = :status";
    $params[':status'] = $status;
}
if (!empty($from_date)) {
    $where[] = "a.attendance_date >= :from_date";
    $params[':from_date'] = $from_date;
}
if (!empty($to_date)) {
    $where[] = "a.attendance_date <= :to_date";
    $params[':to_date'] = $to_date;
}

$where_clause = implode(" AND ", $where);

// Fetch Filter Dropdown Data
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();
$subjects    = $pdo->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code ASC")->fetchAll();
$students    = $pdo->query("SELECT id, roll_no, full_name FROM users WHERE role = 'student' ORDER BY roll_no ASC")->fetchAll();

try {
    // 1. Fetch Aggregated Statistics for Filtered Query
    $stats_query = "
        SELECT 
            COUNT(a.id) AS total_marked,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users u ON a.student_id = u.id
        WHERE {$where_clause}
    ";
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute($params);
    $stats = $stmt->fetch();

    $total_marked  = (int)($stats['total_marked'] ?? 0);
    $present_count = (int)($stats['present_count'] ?? 0);
    $absent_count  = (int)($stats['absent_count'] ?? 0);
    $late_count    = (int)($stats['late_count'] ?? 0);
    $pct_filtered  = $total_marked > 0 ? round((($present_count + $late_count) / $total_marked) * 100, 1) : 0;

    // 2. Fetch Student-Wise Aggregate Summary for this Filter
    $student_summary_query = "
        SELECT 
            u.id AS student_id,
            u.roll_no,
            u.full_name,
            d.code AS dept_code,
            COUNT(a.id) AS total_classes,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_cnt,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_cnt,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_cnt
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users u ON a.student_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE {$where_clause}
        GROUP BY u.id
        ORDER BY u.roll_no ASC
    ";
    $stmt = $pdo->prepare($student_summary_query);
    $stmt->execute($params);
    $student_summaries = $stmt->fetchAll();

    // 3. Detailed Attendance Logs
    $logs_query = "
        SELECT 
            a.*,
            u.roll_no AS student_roll,
            u.full_name AS student_name,
            s.subject_code,
            s.subject_name,
            t.full_name AS teacher_name
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users u ON a.student_id = u.id
        JOIN users t ON a.teacher_id = t.id
        WHERE {$where_clause}
        ORDER BY a.attendance_date DESC, a.created_at DESC
        LIMIT 500
    ";
    $stmt = $pdo->prepare($logs_query);
    $stmt->execute($params);
    $attendance_logs = $stmt->fetchAll();

} catch (PDOException $e) {
    set_flash("Query error generating reports: " . $e->getMessage(), "danger");
    $total_marked = $present_count = $absent_count = $late_count = $pct_filtered = 0;
    $student_summaries = [];
    $attendance_logs = [];
}

// Build Export query string
$export_qs = http_build_query([
    'subject_id'    => $subject_id,
    'department_id' => $department_id,
    'student_id'    => $student_id,
    'status'        => $status,
    'from_date'     => $from_date,
    'to_date'       => $to_date
]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Attendance Reports & Analytics</h3>
            <p class="text-muted mb-0">Generate, filter, and export detailed system attendance records and student summaries.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export.php?format=csv&<?= $export_qs ?>" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to CSV
            </a>
            <button type="button" class="btn btn-outline-dark" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Report / PDF
            </button>
        </div>
    </div>

    <!-- Filter Control Card -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-funnel text-primary me-2"></i>Filter Criteria
        </div>
        <div class="card-body">
            <form action="reports.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Subject / Course</label>
                    <select name="subject_id" class="form-select form-select-sm">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $subject_id ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Department</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $d['id'] == $department_id ? 'selected' : '' ?>>
                                <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Student</label>
                    <select name="student_id" class="form-select form-select-sm">
                        <option value="">All Students</option>
                        <?php foreach ($students as $st): ?>
                            <option value="<?= $st['id'] ?>" <?= $st['id'] == $student_id ? 'selected' : '' ?>>
                                <?= e($st['roll_no']) ?> - <?= e($st['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="Present" <?= $status === 'Present' ? 'selected' : '' ?>>Present</option>
                        <option value="Absent" <?= $status === 'Absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="Late" <?= $status === 'Late' ? 'selected' : '' ?>>Late</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($from_date) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($to_date) ?>">
                </div>

                <div class="col-md-10 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i> Apply Filters
                    </button>
                    <a href="reports.php" class="btn btn-light btn-sm border px-3">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Metrics for Filtered Data -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 bg-light border-0 shadow-sm text-center">
                <span class="text-muted small fw-semibold">Total Logs</span>
                <h4 class="fw-bold my-1"><?= $total_marked ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 bg-success-subtle border-0 shadow-sm text-center">
                <span class="text-success small fw-semibold">Present</span>
                <h4 class="fw-bold my-1 text-success"><?= $present_count ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 bg-warning-subtle border-0 shadow-sm text-center">
                <span class="text-warning-emphasis small fw-semibold">Late</span>
                <h4 class="fw-bold my-1 text-warning-emphasis"><?= $late_count ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 bg-danger-subtle border-0 shadow-sm text-center">
                <span class="text-danger small fw-semibold">Absent</span>
                <h4 class="fw-bold my-1 text-danger"><?= $absent_count ?></h4>
            </div>
        </div>
    </div>

    <!-- Section 1: Student-Wise Percentage Standings -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-slate-800">
                <i class="bi bi-people-fill text-primary me-2"></i>Student Attendance Standing Summary (<?= count($student_summaries) ?> Students)
            </span>
            <span class="badge bg-light text-dark border">Threshold: <?= ATTENDANCE_THRESHOLD ?>%</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Dept</th>
                            <th>Total Classes</th>
                            <th>Present</th>
                            <th>Late</th>
                            <th>Absent</th>
                            <th>Attendance %</th>
                            <th>Status Standing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($student_summaries)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No attendance logs matching selected criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($student_summaries as $sm): 
                                $t_cls = (int)$sm['total_classes'];
                                $p_cls = (int)$sm['present_cnt'];
                                $l_cls = (int)$sm['late_cnt'];
                                $a_cls = (int)$sm['absent_cnt'];
                                $pct = calculate_attendance_percentage($p_cls, $l_cls, $t_cls);
                            ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= e($sm['roll_no']) ?></span></td>
                                    <td><strong><?= e($sm['full_name']) ?></strong></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary"><?= e($sm['dept_code'] ?? 'N/A') ?></span></td>
                                    <td><?= $t_cls ?></td>
                                    <td><span class="text-success fw-semibold"><?= $p_cls ?></span></td>
                                    <td><span class="text-warning fw-semibold"><?= $l_cls ?></span></td>
                                    <td><span class="text-danger fw-semibold"><?= $a_cls ?></span></td>
                                    <td><?= get_percentage_badge($pct) ?></td>
                                    <td>
                                        <?php if ($pct >= ATTENDANCE_THRESHOLD): ?>
                                            <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Eligible</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white"><i class="bi bi-exclamation-triangle me-1"></i>Shortage</span>
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

    <!-- Section 2: Detailed Date-by-Date Attendance Logs -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-slate-800">
                <i class="bi bi-journal-text text-primary me-2"></i>Detailed Attendance Logs (<?= count($attendance_logs) ?>)
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="sticky-top">
                        <tr>
                            <th>Date</th>
                            <th>Time Slot</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance_logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No attendance logs found for current filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance_logs as $log): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= format_date($log['attendance_date']) ?></span></td>
                                    <td><small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($log['time_slot']) ?></small></td>
                                    <td>
                                        <div class="fw-bold"><?= e($log['student_name']) ?></div>
                                        <small class="text-muted"><?= e($log['student_roll']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= e($log['subject_code']) ?></strong> - <?= e($log['subject_name']) ?>
                                    </td>
                                    <td><?= e($log['teacher_name']) ?></td>
                                    <td><?= get_status_badge($log['status']) ?></td>
                                    <td><small class="text-muted"><?= e($log['remarks'] ?? '-') ?></small></td>
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

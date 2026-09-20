<?php
/**
 * Detailed Date-by-Date Attendance History for Students
 * College Attendance Management System
 */

$page_title = "My Attendance History";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('student');
$user = current_user();
$student_id = $user['id'];

// Filter inputs
$subject_id = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$status     = clean_input($_GET['status'] ?? '');
$from_date  = clean_input($_GET['from_date'] ?? '');
$to_date    = clean_input($_GET['to_date'] ?? '');

// Fetch enrolled subjects for student dropdown
$subjects = $pdo->prepare("
    SELECT s.id, s.subject_code, s.subject_name 
    FROM subjects s
    JOIN subject_student ss ON s.id = ss.subject_id
    WHERE ss.student_id = :sid
    ORDER BY s.subject_code ASC
");
$subjects->execute([':sid' => $student_id]);
$enrolled_subjects = $subjects->fetchAll();

// Build query
$where = ["a.student_id = :sid"];
$params = [':sid' => $student_id];

if ($subject_id > 0) {
    $where[] = "a.subject_id = :subject_id";
    $params[':subject_id'] = $subject_id;
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

try {
    // 1. Fetch Summary Stats for the filtered selection
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(a.id) AS total_marked,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
        FROM attendance a
        WHERE {$where_clause}
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch();

    $total_marked  = (int)($stats['total_marked'] ?? 0);
    $present_count = (int)($stats['present_count'] ?? 0);
    $late_count    = (int)($stats['late_count'] ?? 0);
    $absent_count  = (int)($stats['absent_count'] ?? 0);
    $filter_pct    = calculate_attendance_percentage($present_count, $late_count, $total_marked);

    // 2. Fetch Detailed Log Records
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            s.subject_code,
            s.subject_name,
            t.full_name AS teacher_name
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users t ON a.teacher_id = t.id
        WHERE {$where_clause}
        ORDER BY a.attendance_date DESC, a.created_at DESC
    ");
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();

} catch (PDOException $e) {
    set_flash("Error loading attendance history: " . $e->getMessage(), "danger");
    $attendance_records = [];
    $total_marked = $present_count = $late_count = $absent_count = $filter_pct = 0;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Attendance History</h3>
            <p class="text-muted mb-0">Review detailed date-by-date records, instructor remarks, and timestamps.</p>
        </div>
        <button type="button" class="btn btn-outline-dark" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print History
        </button>
    </div>

    <!-- Filters Card -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-funnel text-primary me-2"></i>Filter Records
        </div>
        <div class="card-body">
            <form action="history.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Subject</label>
                    <select name="subject_id" class="form-select form-select-sm">
                        <option value="">All Enrolled Subjects</option>
                        <?php foreach ($enrolled_subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $subject_id ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="Present" <?= $status === 'Present' ? 'selected' : '' ?>>Present</option>
                        <option value="Late" <?= $status === 'Late' ? 'selected' : '' ?>>Late</option>
                        <option value="Absent" <?= $status === 'Absent' ? 'selected' : '' ?>>Absent</option>
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

                <div class="col-md-2 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="history.php" class="btn btn-light btn-sm border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtered Summary KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 bg-light border-0 shadow-sm text-center">
                <span class="text-muted small fw-semibold">Total Classes Filtered</span>
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

    <!-- Attendance History Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-slate-800"><i class="bi bi-journal-check text-primary me-2"></i>Date-by-Date Records (<?= count($attendance_records) ?>)</span>
            <?php if ($total_marked > 0): ?>
                <span class="small fw-semibold">Filtered Attendance: <?= get_percentage_badge($filter_pct) ?></span>
            <?php endif; ?>
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
                        <?php if (empty($attendance_records)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No attendance entries found for the selected filter parameters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance_records as $rec): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border fw-semibold"><?= format_date($rec['attendance_date']) ?></span></td>
                                    <td><small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($rec['time_slot']) ?></small></td>
                                    <td>
                                        <strong><?= e($rec['subject_code']) ?></strong> - <?= e($rec['subject_name']) ?>
                                    </td>
                                    <td><?= e($rec['teacher_name']) ?></td>
                                    <td><?= get_status_badge($rec['status']) ?></td>
                                    <td><small class="text-muted"><?= e($rec['remarks'] ?? '-') ?></small></td>
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

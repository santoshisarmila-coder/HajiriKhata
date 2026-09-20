<?php
/**
 * Edit Past Attendance Sessions
 * College Attendance Management System
 */

$page_title = "Edit Past Attendance";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('teacher');
$user = current_user();
$teacher_id = $user['id'];

// Filter parameters
$subject_id = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$from_date  = clean_input($_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days')));
$to_date    = clean_input($_GET['to_date'] ?? date('Y-m-d'));

// Fetch assigned subjects for filter
$subjects = $pdo->prepare("
    SELECT s.id, s.subject_code, s.subject_name 
    FROM subjects s
    JOIN subject_teacher st ON s.id = st.subject_id
    WHERE st.teacher_id = :teach
    ORDER BY s.subject_code ASC
");
$subjects->execute([':teach' => $teacher_id]);
$assigned_subjects = $subjects->fetchAll();

// Build query
$where = ["a.teacher_id = :teach"];
$params = [':teach' => $teacher_id];

if ($subject_id > 0) {
    $where[] = "a.subject_id = :subject_id";
    $params[':subject_id'] = $subject_id;
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
    $stmt = $pdo->prepare("
        SELECT 
            a.attendance_date,
            a.time_slot,
            s.id AS subject_id,
            s.subject_code,
            s.subject_name,
            COUNT(a.id) AS total_students,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            MAX(a.created_at) AS created_at
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        WHERE {$where_clause}
        GROUP BY a.attendance_date, a.time_slot, s.id, s.subject_code, s.subject_name
        ORDER BY a.attendance_date DESC, a.created_at DESC
    ");
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash("Error fetching attendance sessions: " . $e->getMessage(), "danger");
    $sessions = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Edit Past Attendance Sessions</h3>
            <p class="text-muted mb-0">Review past attendance logs. Records within 24 hours of creation can be adjusted.</p>
        </div>
        <a href="take_attendance.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Roll Call
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-funnel text-primary me-2"></i>Filter Past Sessions
        </div>
        <div class="card-body">
            <form action="edit_attendance.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Subject</label>
                    <select name="subject_id" class="form-select form-select-sm">
                        <option value="">All Assigned Subjects</option>
                        <?php foreach ($assigned_subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $subject_id ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($from_date) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($to_date) ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sessions List -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-calendar2-week text-primary me-2"></i>Attendance Sessions Log (<?= count($sessions) ?>)</span>
            <span class="badge bg-light text-muted border">Edit Window: <?= ATTENDANCE_EDIT_HOURS ?> Hours</span>
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
                            <th>Edit Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No attendance sessions found matching the filter criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sessions as $sess): 
                                $editable = can_edit_attendance($sess['created_at']);
                            ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= format_date($sess['attendance_date']) ?></span></td>
                                    <td><small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($sess['time_slot']) ?></small></td>
                                    <td><strong><?= e($sess['subject_code']) ?></strong> - <?= e($sess['subject_name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $sess['total_students'] ?></span></td>
                                    <td><span class="text-success fw-bold"><?= $sess['present_count'] ?></span></td>
                                    <td><span class="text-warning fw-bold"><?= $sess['late_count'] ?></span></td>
                                    <td><span class="text-danger fw-bold"><?= $sess['absent_count'] ?></span></td>
                                    <td>
                                        <?php if ($editable): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-unlock me-1"></i>Editable
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border" title="Passed 24h limit">
                                                <i class="bi bi-lock-fill me-1"></i>Locked (>24h)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($editable): ?>
                                            <a href="take_attendance.php?subject_id=<?= $sess['subject_id'] ?>&attendance_date=<?= $sess['attendance_date'] ?>&time_slot=<?= urlencode($sess['time_slot']) ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil me-1"></i> Edit Records
                                            </a>
                                        <?php else: ?>
                                            <a href="take_attendance.php?subject_id=<?= $sess['subject_id'] ?>&attendance_date=<?= $sess['attendance_date'] ?>&time_slot=<?= urlencode($sess['time_slot']) ?>" class="btn btn-sm btn-light border text-muted">
                                                <i class="bi bi-eye me-1"></i> View Only
                                            </a>
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

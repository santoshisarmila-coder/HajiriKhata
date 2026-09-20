<?php
/**
 * Interactive Attendance Taking Interface
 * College Attendance Management System
 */

$page_title = "Take Attendance";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('teacher');
$user = current_user();
$teacher_id = $user['id'];

// Default Time Slots
$standard_time_slots = [
    '08:00 AM - 09:00 AM',
    '09:00 AM - 10:00 AM',
    '10:00 AM - 11:00 AM',
    '11:00 AM - 12:00 PM',
    '12:00 PM - 01:00 PM',
    '01:00 PM - 02:00 PM',
    '02:00 PM - 03:00 PM',
    '03:00 PM - 04:00 PM'
];

// Fetch subjects assigned to this teacher
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

// Current Parameters
$selected_subject_id = (int)($_GET['subject_id'] ?? ($_POST['subject_id'] ?? ($assigned_subjects[0]['id'] ?? 0)));
$selected_date       = clean_input($_GET['attendance_date'] ?? ($_POST['attendance_date'] ?? date('Y-m-d')));
$selected_slot       = clean_input($_GET['time_slot'] ?? ($_POST['time_slot'] ?? $standard_time_slots[1]));

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        set_flash("Invalid security token.", "danger");
        header("Location: take_attendance.php?subject_id={$selected_subject_id}&attendance_date={$selected_date}&time_slot=" . urlencode($selected_slot));
        exit;
    }

    $attendance_data = $_POST['attendance'] ?? [];
    $remarks_data    = $_POST['remarks'] ?? [];

    if (empty($attendance_data) || $selected_subject_id <= 0) {
        set_flash("No attendance data submitted or no subject selected.", "warning");
    } else {
        // Check if existing record is older than 24h
        $stmt = $pdo->prepare("
            SELECT MIN(created_at) AS first_created 
            FROM attendance 
            WHERE subject_id = :subj AND attendance_date = :att_date AND time_slot = :slot
        ");
        $stmt->execute([
            ':subj'     => $selected_subject_id,
            ':att_date' => $selected_date,
            ':slot'     => $selected_slot
        ]);
        $existing_created = $stmt->fetchColumn();

        if ($existing_created && !can_edit_attendance($existing_created)) {
            set_flash("Attendance record for this session was created over 24 hours ago and is locked for editing.", "danger");
            header("Location: take_attendance.php?subject_id={$selected_subject_id}&attendance_date={$selected_date}&time_slot=" . urlencode($selected_slot));
            exit;
        }

        // Save / Update Attendance in Transaction
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, time_slot, status, remarks)
                VALUES (:student_id, :subject_id, :teacher_id, :att_date, :time_slot, :status, :remarks)
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status), 
                    remarks = VALUES(remarks), 
                    teacher_id = VALUES(teacher_id),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $count_saved = 0;
            foreach ($attendance_data as $s_id => $st_status) {
                $student_id = (int)$s_id;
                $status_val = in_array($st_status, ['Present', 'Absent', 'Late']) ? $st_status : 'Present';
                $remark_val = clean_input($remarks_data[$student_id] ?? '');

                $stmt->execute([
                    ':student_id' => $student_id,
                    ':subject_id' => $selected_subject_id,
                    ':teacher_id' => $teacher_id,
                    ':att_date'   => $selected_date,
                    ':time_slot'  => $selected_slot,
                    ':status'     => $status_val,
                    ':remarks'    => $remark_val
                ]);
                $count_saved++;
            }

            $pdo->commit();
            set_flash("Attendance successfully recorded for {$count_saved} students!", "success");

        } catch (PDOException $e) {
            $pdo->rollBack();
            set_flash("Failed to save attendance: " . $e->getMessage(), "danger");
        }

        header("Location: take_attendance.php?subject_id={$selected_subject_id}&attendance_date={$selected_date}&time_slot=" . urlencode($selected_slot));
        exit;
    }
}

// Fetch enrolled students for current subject
$enrolled_students = [];
$existing_attendance = [];
$is_locked = false;

if ($selected_subject_id > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.roll_no, u.full_name, u.email, d.code AS dept_code
        FROM users u
        JOIN subject_student ss ON u.id = ss.student_id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE ss.subject_id = :subj AND u.role = 'student'
        ORDER BY u.roll_no ASC, u.full_name ASC
    ");
    $stmt->execute([':subj' => $selected_subject_id]);
    $enrolled_students = $stmt->fetchAll();

    // Check if attendance already marked for this (Subject + Date + Slot)
    $stmt = $pdo->prepare("
        SELECT student_id, status, remarks, created_at
        FROM attendance
        WHERE subject_id = :subj AND attendance_date = :att_date AND time_slot = :slot
    ");
    $stmt->execute([
        ':subj'     => $selected_subject_id,
        ':att_date' => $selected_date,
        ':slot'     => $selected_slot
    ]);
    $records = $stmt->fetchAll();

    if (!empty($records)) {
        foreach ($records as $r) {
            $existing_attendance[$r['student_id']] = [
                'status'     => $r['status'],
                'remarks'    => $r['remarks'],
                'created_at' => $r['created_at']
            ];
            if (!can_edit_attendance($r['created_at'])) {
                $is_locked = true;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Take Class Attendance</h3>
            <p class="text-muted mb-0">Select your class details, mark roll call statuses, and submit records.</p>
        </div>
        <div>
            <a href="view_attendance.php?subject_id=<?= $selected_subject_id ?>" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> View Subject Reports
            </a>
        </div>
    </div>

    <?php if (empty($assigned_subjects)): ?>
        <div class="alert alert-warning shadow-sm">
            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
            You are not assigned to any subjects yet. Please contact the administrator.
        </div>
    <?php else: ?>

        <!-- Class Session Parameters Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-sliders text-primary me-2"></i>Class Session Configuration
            </div>
            <div class="card-body">
                <form action="take_attendance.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Subject / Course <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($assigned_subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_subject_id ? 'selected' : '' ?>>
                                    <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?> (<?= e($s['dept_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Attendance Date <span class="text-danger">*</span></label>
                        <input type="date" name="attendance_date" class="form-control" value="<?= e($selected_date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Time Slot <span class="text-danger">*</span></label>
                        <select name="time_slot" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($standard_time_slots as $slot): ?>
                                <option value="<?= e($slot) ?>" <?= $slot === $selected_slot ? 'selected' : '' ?>><?= e($slot) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($is_locked): ?>
            <div class="alert alert-warning d-flex align-items-center shadow-sm mb-4">
                <i class="bi bi-lock-fill fs-3 text-warning me-3"></i>
                <div>
                    <h6 class="fw-bold mb-1">Attendance Locked</h6>
                    This session was recorded more than 24 hours ago. Editing past records older than 24 hours is restricted to Administrator privileges.
                </div>
            </div>
        <?php elseif (!empty($existing_attendance)): ?>
            <div class="alert alert-info d-flex align-items-center shadow-sm mb-4">
                <i class="bi bi-info-circle-fill fs-4 text-info me-3"></i>
                <div>
                    <h6 class="fw-bold mb-0">Existing Attendance Session Loaded</h6>
                    <small>Attendance for this date and time slot was previously recorded. You can review or adjust marks within the 24h window.</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- Attendance Sheet Card -->
        <div class="card shadow-sm">
            <form action="take_attendance.php" method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="submit_attendance" value="1">
                <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                <input type="hidden" name="attendance_date" value="<?= e($selected_date) ?>">
                <input type="hidden" name="time_slot" value="<?= e($selected_slot) ?>">

                <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6">
                            <i class="bi bi-clipboard-check text-primary me-1"></i>
                            Roster (<?= count($enrolled_students) ?> Enrolled Students)
                        </span>
                    </div>

                    <?php if (!$is_locked && !empty($enrolled_students)): ?>
                        <!-- Quick Mark Actions -->
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="small text-muted fw-semibold me-1">Quick Actions:</span>
                            <button type="button" id="markAllPresent" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-check-all me-1"></i> Mark All Present
                            </button>
                            <button type="button" id="markAllLate" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-clock-history me-1"></i> Mark All Late
                            </button>
                            <button type="button" id="markAllAbsent" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-x-circle me-1"></i> Mark All Absent
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($enrolled_students)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-people fs-2 text-secondary d-block mb-2"></i>
                            No students are currently enrolled in this subject. Please ask the administrator to enroll students.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 120px;">Roll No</th>
                                        <th>Student Details</th>
                                        <th style="width: 320px;" class="text-center">Attendance Status</th>
                                        <th style="width: 250px;">Remarks (Optional)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrolled_students as $idx => $st): 
                                        $sid = $st['id'];
                                        $current_status = $existing_attendance[$sid]['status'] ?? 'Present';
                                        $current_remark = $existing_attendance[$sid]['remarks'] ?? '';
                                    ?>
                                        <tr class="<?= $current_status === 'Present' ? 'table-success table-opacity-10' : ($current_status === 'Absent' ? 'table-danger table-opacity-10' : 'table-warning table-opacity-10') ?>">
                                            <td>
                                                <span class="badge bg-light text-dark border fw-semibold"><?= e($st['roll_no']) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-circle" style="background-color: #e0f2fe; color: #0369a1; width: 32px; height: 32px; font-size: 0.75rem;">
                                                        <?= strtoupper(substr($st['full_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?= e($st['full_name']) ?></div>
                                                        <small class="text-muted"><?= e($st['email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group attendance-toggle-group w-100" role="group" aria-label="Attendance Status for <?= e($st['roll_no']) ?>">
                                                    <!-- Present Radio -->
                                                    <input type="radio" class="btn-check attendance-radio" 
                                                        name="attendance[<?= $sid ?>]" 
                                                        id="status_present_<?= $sid ?>" 
                                                        value="Present" 
                                                        autocomplete="off" 
                                                        <?= $current_status === 'Present' ? 'checked' : '' ?>
                                                        <?= $is_locked ? 'disabled' : '' ?>>
                                                    <label class="btn btn-outline-success btn-sm py-1.5 fw-semibold" for="status_present_<?= $sid ?>">
                                                        <i class="bi bi-check-lg"></i> Present
                                                    </label>

                                                    <!-- Late Radio -->
                                                    <input type="radio" class="btn-check attendance-radio" 
                                                        name="attendance[<?= $sid ?>]" 
                                                        id="status_late_<?= $sid ?>" 
                                                        value="Late" 
                                                        autocomplete="off" 
                                                        <?= $current_status === 'Late' ? 'checked' : '' ?>
                                                        <?= $is_locked ? 'disabled' : '' ?>>
                                                    <label class="btn btn-outline-warning btn-sm py-1.5 fw-semibold" for="status_late_<?= $sid ?>">
                                                        <i class="bi bi-clock"></i> Late
                                                    </label>

                                                    <!-- Absent Radio -->
                                                    <input type="radio" class="btn-check attendance-radio" 
                                                        name="attendance[<?= $sid ?>]" 
                                                        id="status_absent_<?= $sid ?>" 
                                                        value="Absent" 
                                                        autocomplete="off" 
                                                        <?= $current_status === 'Absent' ? 'checked' : '' ?>
                                                        <?= $is_locked ? 'disabled' : '' ?>>
                                                    <label class="btn btn-outline-danger btn-sm py-1.5 fw-semibold" for="status_absent_<?= $sid ?>">
                                                        <i class="bi bi-x-lg"></i> Absent
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    name="remarks[<?= $sid ?>]" 
                                                    class="form-control form-control-sm" 
                                                    placeholder="e.g. Leave, Late 10m" 
                                                    value="<?= e($current_remark) ?>"
                                                    <?= $is_locked ? 'disabled' : '' ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!$is_locked): ?>
                            <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                                <span class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>Click "Save Attendance Record" to commit this roll call to the database.
                                </span>
                                <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm">
                                    <i class="bi bi-check2-circle me-1"></i> Save Attendance Record
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

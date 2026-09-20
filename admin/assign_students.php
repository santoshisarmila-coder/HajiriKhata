<?php
/**
 * Student-to-Subject Enrollment Manager
 * College Attendance Management System
 */

$page_title = "Subject Enrollment";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('admin');

// Selected Subject ID
$subject_id = (int)($_GET['subject_id'] ?? ($_POST['subject_id'] ?? 0));

// Fetch all subjects for the selector
$subjects = $pdo->query("
    SELECT s.id, s.subject_code, s.subject_name, d.code AS dept_code, sem.name AS sem_name
    FROM subjects s
    JOIN departments d ON s.department_id = d.id
    JOIN semesters sem ON s.semester_id = sem.id
    ORDER BY s.subject_code ASC
")->fetchAll();

if ($subject_id === 0 && !empty($subjects)) {
    $subject_id = (int)$subjects[0]['id'];
}

// Handle Enrollment Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        set_flash("Invalid security token.", "danger");
        header("Location: assign_students.php?subject_id={$subject_id}");
        exit;
    }

    if ($action === 'bulk_enroll') {
        $student_ids = $_POST['student_ids'] ?? [];
        if (!empty($student_ids) && $subject_id > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO subject_student (subject_id, student_id) VALUES (?, ?)");
                foreach ($student_ids as $s_id) {
                    $stmt->execute([$subject_id, (int)$s_id]);
                }
                $pdo->commit();
                set_flash(count($student_ids) . " students successfully enrolled into subject!", "success");
            } catch (PDOException $e) {
                $pdo->rollBack();
                set_flash("Enrollment error: " . $e->getMessage(), "danger");
            }
        } else {
            set_flash("Please select at least one student to enroll.", "warning");
        }
        header("Location: assign_students.php?subject_id={$subject_id}");
        exit;
    }

    if ($action === 'remove_student') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id > 0 && $subject_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM subject_student WHERE subject_id = :subj AND student_id = :student");
                $stmt->execute([':subj' => $subject_id, ':student' => $student_id]);
                set_flash("Student removed from subject.", "info");
            } catch (PDOException $e) {
                set_flash("Error removing student: " . $e->getMessage(), "danger");
            }
        }
        header("Location: assign_students.php?subject_id={$subject_id}");
        exit;
    }
}

// Fetch current subject details
$current_subject = null;
if ($subject_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, d.name AS dept_name, sem.name AS sem_name
        FROM subjects s
        JOIN departments d ON s.department_id = d.id
        JOIN semesters sem ON s.semester_id = sem.id
        WHERE s.id = :id
    ");
    $stmt->execute([':id' => $subject_id]);
    $current_subject = $stmt->fetch();
}

// Fetch enrolled students
$enrolled_students = [];
if ($subject_id > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.roll_no, u.full_name, u.email, d.code AS dept_code, ss.enrolled_at
        FROM users u
        JOIN subject_student ss ON u.id = ss.student_id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE ss.subject_id = :subj AND u.role = 'student'
        ORDER BY u.roll_no ASC
    ");
    $stmt->execute([':subj' => $subject_id]);
    $enrolled_students = $stmt->fetchAll();
}

// Fetch available students not yet enrolled in this subject
$available_students = [];
if ($subject_id > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.roll_no, u.full_name, u.email, d.code AS dept_code
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'student' 
          AND u.id NOT IN (SELECT student_id FROM subject_student WHERE subject_id = :subj)
        ORDER BY u.roll_no ASC
    ");
    $stmt->execute([':subj' => $subject_id]);
    $available_students = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Student Enrollment Manager</h3>
            <p class="text-muted mb-0">Enroll and manage student rosters for specific courses and subjects.</p>
        </div>
    </div>

    <!-- Subject Selector Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="assign_students.php" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <label class="form-label fw-semibold text-secondary small">Select Course / Subject:</label>
                    <select name="subject_id" class="form-select form-select-lg" onchange="this.form.submit()">
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $subject_id ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?> (<?= e($s['dept_code']) ?>, <?= e($s['sem_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <?php if ($current_subject): ?>
                        <div class="p-2.5 bg-light rounded w-100 border text-center">
                            <span class="text-muted small">Active Enrolled Count:</span>
                            <strong class="text-primary fs-5 ms-2"><?= count($enrolled_students) ?> Students</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($current_subject): ?>
    <div class="row g-4">
        <!-- Panel 1: Currently Enrolled Students -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-success">
                        <i class="bi bi-person-check-fill me-2"></i>Enrolled Students (<?= count($enrolled_students) ?>)
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Roll No</th>
                                    <th>Student Name</th>
                                    <th>Dept</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($enrolled_students)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No students currently enrolled in this subject.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($enrolled_students as $st): ?>
                                        <tr>
                                            <td><span class="badge bg-light text-dark border"><?= e($st['roll_no']) ?></span></td>
                                            <td>
                                                <div class="fw-bold"><?= e($st['full_name']) ?></div>
                                                <small class="text-muted"><?= e($st['email']) ?></small>
                                            </td>
                                            <td><span class="badge bg-secondary-subtle text-secondary"><?= e($st['dept_code'] ?? 'N/A') ?></span></td>
                                            <td class="text-end">
                                                <form action="assign_students.php" method="POST" class="d-inline" onsubmit="return confirm('Remove student <?= e($st['full_name']) ?> from this subject?');">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="remove_student">
                                                    <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                                                    <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Unenroll student">
                                                        <i class="bi bi-person-dash"></i> Remove
                                                    </button>
                                                </form>
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

        <!-- Panel 2: Available Students to Enroll -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <form action="assign_students.php" method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="bulk_enroll">
                    <input type="hidden" name="subject_id" value="<?= $subject_id ?>">

                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary">
                            <i class="bi bi-person-plus-fill me-2"></i>Available Students (<?= count($available_students) ?>)
                        </span>
                        <?php if (!empty($available_students)): ?>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Enroll Selected
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($available_students)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
                                All eligible students are already enrolled in this subject!
                            </div>
                        <?php else: ?>
                            <div class="p-2.5 bg-light border-bottom d-flex justify-content-between align-items-center">
                                <div class="form-check ms-2">
                                    <input class="form-check-input" type="checkbox" id="selectAllAvailable">
                                    <label class="form-check-label fw-semibold small" for="selectAllAvailable">
                                        Select All (<?= count($available_students) ?>)
                                    </label>
                                </div>
                                <span class="small text-muted">Check students & click "Enroll Selected"</span>
                            </div>

                            <div class="table-responsive" style="max-height: 465px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="sticky-top">
                                        <tr>
                                            <th width="40"></th>
                                            <th>Roll No</th>
                                            <th>Student Name</th>
                                            <th>Dept</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($available_students as $avail): ?>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input available-chk" type="checkbox" name="student_ids[]" value="<?= $avail['id'] ?>">
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= e($avail['roll_no']) ?></span></td>
                                                <td>
                                                    <div class="fw-bold"><?= e($avail['full_name']) ?></div>
                                                    <small class="text-muted"><?= e($avail['email']) ?></small>
                                                </td>
                                                <td><span class="badge bg-secondary-subtle text-secondary"><?= e($avail['dept_code'] ?? 'N/A') ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAllAvailable');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.available-chk').forEach(cb => {
                cb.checked = selectAll.checked;
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Teacher-to-Subject Assignment Manager
 * College Attendance Management System
 */

$page_title = "Teacher Subject Assignments";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('admin');

// Handle Assignment Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        set_flash("Invalid security token.", "danger");
        header("Location: assign_teachers.php");
        exit;
    }

    if ($action === 'assign') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);

        if ($subject_id <= 0 || $teacher_id <= 0) {
            set_flash("Please select both a subject and a faculty member.", "danger");
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO subject_teacher (subject_id, teacher_id) VALUES (:subj, :teach)");
                $stmt->execute([':subj' => $subject_id, ':teach' => $teacher_id]);
                set_flash("Teacher assigned to subject successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("This teacher is already assigned to the selected subject.", "warning");
                } else {
                    set_flash("Error assigning teacher: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: assign_teachers.php");
        exit;
    }

    if ($action === 'unassign') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);

        if ($subject_id > 0 && $teacher_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM subject_teacher WHERE subject_id = :subj AND teacher_id = :teach");
                $stmt->execute([':subj' => $subject_id, ':teach' => $teacher_id]);
                set_flash("Teacher unassigned from subject.", "info");
            } catch (PDOException $e) {
                set_flash("Error unassigning teacher: " . $e->getMessage(), "danger");
            }
        }
        header("Location: assign_teachers.php");
        exit;
    }
}

// Fetch all subjects with their assigned teachers
try {
    $stmt = $pdo->query("
        SELECT 
            s.id AS subject_id,
            s.subject_code,
            s.subject_name,
            d.code AS dept_code,
            sem.name AS sem_name
        FROM subjects s
        JOIN departments d ON s.department_id = d.id
        JOIN semesters sem ON s.semester_id = sem.id
        ORDER BY s.subject_code ASC
    ");
    $subjects = $stmt->fetchAll();

    // Map assigned teachers per subject
    $stmt = $pdo->query("
        SELECT st.subject_id, st.teacher_id, u.full_name, u.email, d.code AS dept_code
        FROM subject_teacher st
        JOIN users u ON st.teacher_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        ORDER BY u.full_name ASC
    ");
    $all_assignments = $stmt->fetchAll();

    $assigned_map = [];
    foreach ($all_assignments as $assign) {
        $assigned_map[$assign['subject_id']][] = $assign;
    }

    // Fetch list of all active teachers
    $teachers = $pdo->query("
        SELECT u.id, u.full_name, u.email, d.code AS dept_code 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'teacher' 
        ORDER BY u.full_name ASC
    ")->fetchAll();

} catch (PDOException $e) {
    set_flash("Error loading assignments: " . $e->getMessage(), "danger");
    $subjects = [];
    $teachers = [];
    $assigned_map = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Teacher Subject Assignments</h3>
            <p class="text-muted mb-0">Assign faculty instructors to lecture and lab subjects.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
            <i class="bi bi-person-plus me-1"></i> New Assignment
        </button>
    </div>

    <!-- Assignments Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <span class="fw-bold"><i class="bi bi-person-gear text-primary me-2"></i>Subject-Faculty Mapping (<?= count($subjects) ?> Subjects)</span>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter subjects or teachers...">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 searchable-table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Dept / Semester</th>
                            <th>Assigned Teachers / Faculty</th>
                            <th class="text-end">Quick Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No subjects found. Please create subjects first.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $s): 
                                $teachers_for_subject = $assigned_map[$s['subject_id']] ?? [];
                            ?>
                                <tr>
                                    <td><span class="badge bg-primary-subtle text-primary border fw-semibold"><?= e($s['subject_code']) ?></span></td>
                                    <td><strong><?= e($s['subject_name']) ?></strong></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= e($s['dept_code']) ?></span>
                                        <span class="small text-muted ms-1"><?= e($s['sem_name']) ?></span>
                                    </td>
                                    <td>
                                        <?php if (empty($teachers_for_subject)): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                <i class="bi bi-exclamation-circle me-1"></i>No Faculty Assigned
                                            </span>
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($teachers_for_subject as $t): ?>
                                                    <div class="d-inline-flex align-items-center bg-light border rounded px-2 py-1 small">
                                                        <i class="bi bi-person-check text-success me-1"></i>
                                                        <span class="fw-medium me-2"><?= e($t['full_name']) ?></span>
                                                        <form action="assign_teachers.php" method="POST" class="d-inline" onsubmit="return confirm('Unassign <?= e($t['full_name']) ?> from <?= e($s['subject_code']) ?>?');">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="action" value="unassign">
                                                            <input type="hidden" name="subject_id" value="<?= $s['subject_id'] ?>">
                                                            <input type="hidden" name="teacher_id" value="<?= $t['teacher_id'] ?>">
                                                            <button type="submit" class="btn btn-link p-0 text-danger" style="line-height: 1;" title="Unassign">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#assignModal"
                                            data-subject-id="<?= $s['subject_id'] ?>">
                                            <i class="bi bi-plus"></i> Assign Teacher
                                        </button>
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

<!-- Modal: Assign Teacher to Subject -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="assign_teachers.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="assign">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-gear text-primary me-2"></i>Assign Teacher to Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject / Course <span class="text-danger">*</span></label>
                    <select name="subject_id" id="modal_subject_id" class="form-select" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['subject_id'] ?>">
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?> (<?= e($s['dept_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Teacher / Faculty Member <span class="text-danger">*</span></label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">Select Faculty Member</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= e($t['full_name']) ?> (<?= e($t['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Teacher</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const assignModal = document.getElementById('assignModal');
    if (assignModal) {
        assignModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const subjId = button.getAttribute('data-subject-id');
            if (subjId) {
                document.getElementById('modal_subject_id').value = subjId;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

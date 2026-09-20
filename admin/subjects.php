<?php
/**
 * Subjects / Courses Management (CRUD)
 * College Attendance Management System
 */

$page_title = "Subjects Management";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('admin');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        set_flash("Invalid security token.", "danger");
        header("Location: subjects.php");
        exit;
    }

    if ($action === 'create') {
        $subject_code  = strtoupper(clean_input($_POST['subject_code'] ?? ''));
        $subject_name  = clean_input($_POST['subject_name'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);
        $semester_id   = (int)($_POST['semester_id'] ?? 0);
        $credit_hours  = (int)($_POST['credit_hours'] ?? 3);

        if (empty($subject_code) || empty($subject_name) || $department_id <= 0 || $semester_id <= 0) {
            set_flash("All required fields must be properly completed.", "danger");
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO subjects (subject_code, subject_name, department_id, semester_id, credit_hours) 
                    VALUES (:code, :name, :dept, :sem, :credits)
                ");
                $stmt->execute([
                    ':code'    => $subject_code,
                    ':name'    => $subject_name,
                    ':dept'    => $department_id,
                    ':sem'     => $semester_id,
                    ':credits' => $credit_hours
                ]);
                set_flash("Subject '{$subject_code} - {$subject_name}' created successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("A subject with code '{$subject_code}' already exists.", "danger");
                } else {
                    set_flash("Error creating subject: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: subjects.php");
        exit;
    }

    if ($action === 'update') {
        $id            = (int)($_POST['id'] ?? 0);
        $subject_code  = strtoupper(clean_input($_POST['subject_code'] ?? ''));
        $subject_name  = clean_input($_POST['subject_name'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);
        $semester_id   = (int)($_POST['semester_id'] ?? 0);
        $credit_hours  = (int)($_POST['credit_hours'] ?? 3);

        if ($id <= 0 || empty($subject_code) || empty($subject_name) || $department_id <= 0 || $semester_id <= 0) {
            set_flash("Invalid subject details provided.", "danger");
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE subjects 
                    SET subject_code = :code, subject_name = :name, department_id = :dept, semester_id = :sem, credit_hours = :credits 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':code'    => $subject_code,
                    ':name'    => $subject_name,
                    ':dept'    => $department_id,
                    ':sem'     => $semester_id,
                    ':credits' => $credit_hours,
                    ':id'      => $id
                ]);
                set_flash("Subject updated successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("A subject with code '{$subject_code}' already exists.", "danger");
                } else {
                    set_flash("Error updating subject: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: subjects.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash("Subject and associated records deleted.", "success");
            } catch (PDOException $e) {
                set_flash("Error deleting subject: " . $e->getMessage(), "danger");
            }
        }
        header("Location: subjects.php");
        exit;
    }
}

// Fetch departments and semesters for select options
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();
$semesters   = $pdo->query("SELECT id, name, code FROM semesters ORDER BY id ASC")->fetchAll();

// Fetch all subjects with Department, Semester, Assigned Teachers, Enrolled Count
try {
    $stmt = $pdo->query("
        SELECT 
            s.*,
            d.name AS department_name,
            d.code AS department_code,
            sem.name AS semester_name,
            (SELECT COUNT(*) FROM subject_student ss WHERE ss.subject_id = s.id) AS enrolled_students,
            (
                SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ')
                FROM subject_teacher st
                JOIN users u ON st.teacher_id = u.id
                WHERE st.subject_id = s.id
            ) AS teachers_assigned
        FROM subjects s
        JOIN departments d ON s.department_id = d.id
        JOIN semesters sem ON s.semester_id = sem.id
        ORDER BY s.id DESC
    ");
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash("Error fetching subjects: " . $e->getMessage(), "danger");
    $subjects = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Subjects & Courses</h3>
            <p class="text-muted mb-0">Create courses, set credit hours, and assign to departments and semesters.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="bi bi-plus-lg me-1"></i> Add New Subject
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <span class="fw-bold"><i class="bi bi-book text-primary me-2"></i>All Subjects (<?= count($subjects) ?>)</span>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter subjects...">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 searchable-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Name</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Credits</th>
                            <th>Assigned Faculty</th>
                            <th>Enrolled</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No subjects registered yet. Click "Add New Subject" to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $s): ?>
                                <tr>
                                    <td><span class="badge bg-primary-subtle text-primary border fw-semibold"><?= e($s['subject_code']) ?></span></td>
                                    <td><strong><?= e($s['subject_name']) ?></strong></td>
                                    <td><?= e($s['department_name']) ?> <span class="text-muted small">(<?= e($s['department_code']) ?>)</span></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($s['semester_name']) ?></span></td>
                                    <td><?= (int)$s['credit_hours'] ?> hrs</td>
                                    <td>
                                        <?php if (!empty($s['teachers_assigned'])): ?>
                                            <span class="text-success"><i class="bi bi-person-check me-1"></i><?= e($s['teachers_assigned']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic small">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="assign_students.php?subject_id=<?= $s['id'] ?>" class="badge bg-secondary text-decoration-none">
                                            <?= $s['enrolled_students'] ?> Students <i class="bi bi-gear-fill ms-1"></i>
                                        </a>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editSubjectModal"
                                            data-id="<?= $s['id'] ?>"
                                            data-code="<?= e($s['subject_code']) ?>"
                                            data-name="<?= e($s['subject_name']) ?>"
                                            data-dept="<?= $s['department_id'] ?>"
                                            data-sem="<?= $s['semester_id'] ?>"
                                            data-credits="<?= $s['credit_hours'] ?>"
                                            title="Edit Subject">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteSubjectModal"
                                            data-id="<?= $s['id'] ?>"
                                            data-name="<?= e($s['subject_name']) ?>"
                                            data-code="<?= e($s['subject_code']) ?>"
                                            title="Delete Subject">
                                            <i class="bi bi-trash"></i>
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

<!-- Modal: Add Subject -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="subjects.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject Code <span class="text-danger">*</span></label>
                    <input type="text" name="subject_code" class="form-control" placeholder="e.g. CS104" required style="text-transform: uppercase;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject Name <span class="text-danger">*</span></label>
                    <input type="text" name="subject_name" class="form-control" placeholder="e.g. Software Engineering" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-select" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?> (<?= e($dept['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
                        <select name="semester_id" class="form-select" required>
                            <option value="">Select Semester</option>
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?= $sem['id'] ?>"><?= e($sem['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Credit Hours</label>
                    <input type="number" name="credit_hours" class="form-control" value="3" min="1" max="10" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Subject</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Subject -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="subjects.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_subject_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject Code <span class="text-danger">*</span></label>
                    <input type="text" name="subject_code" id="edit_subject_code" class="form-control" required style="text-transform: uppercase;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject Name <span class="text-danger">*</span></label>
                    <input type="text" name="subject_name" id="edit_subject_name" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                        <select name="department_id" id="edit_subject_dept" class="form-select" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?> (<?= e($dept['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
                        <select name="semester_id" id="edit_subject_sem" class="form-select" required>
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?= $sem['id'] ?>"><?= e($sem['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Credit Hours</label>
                    <input type="number" name="credit_hours" id="edit_subject_credits" class="form-control" min="1" max="10" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Subject</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Subject Confirmation -->
<div class="modal fade" id="deleteSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="subjects.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_subject_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Subject Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete_subject_title" class="text-danger"></strong>?</p>
                <p class="small text-muted mb-0"><strong>Warning:</strong> All student enrollments, teacher assignments, and attendance logs associated with this subject will also be permanently deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Delete Subject</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editSubjectModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_subject_id').value = button.getAttribute('data-id');
            document.getElementById('edit_subject_code').value = button.getAttribute('data-code');
            document.getElementById('edit_subject_name').value = button.getAttribute('data-name');
            document.getElementById('edit_subject_dept').value = button.getAttribute('data-dept');
            document.getElementById('edit_subject_sem').value = button.getAttribute('data-sem');
            document.getElementById('edit_subject_credits').value = button.getAttribute('data-credits');
        });
    }

    const deleteModal = document.getElementById('deleteSubjectModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('delete_subject_id').value = button.getAttribute('data-id');
            document.getElementById('delete_subject_title').textContent = button.getAttribute('data-code') + ' - ' + button.getAttribute('data-name');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

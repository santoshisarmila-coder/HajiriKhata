<?php
/**
 * Department Management (CRUD)
 * College Attendance Management System
 */

$page_title = "Departments Management";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('admin');

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        set_flash("Invalid security token. Please refresh and try again.", "danger");
        header("Location: departments.php");
        exit;
    }

    if ($action === 'create') {
        $name = clean_input($_POST['name'] ?? '');
        $code = strtoupper(clean_input($_POST['code'] ?? ''));

        if (empty($name) || empty($code)) {
            set_flash("Department Name and Code are required.", "danger");
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (name, code) VALUES (:name, :code)");
                $stmt->execute([':name' => $name, ':code' => $code]);
                set_flash("Department '{$name}' created successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("A department with code '{$code}' already exists.", "danger");
                } else {
                    set_flash("Error creating department: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: departments.php");
        exit;
    }

    if ($action === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = clean_input($_POST['name'] ?? '');
        $code = strtoupper(clean_input($_POST['code'] ?? ''));

        if ($id <= 0 || empty($name) || empty($code)) {
            set_flash("Invalid department information provided.", "danger");
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE departments SET name = :name, code = :code WHERE id = :id");
                $stmt->execute([':name' => $name, ':code' => $code, ':id' => $id]);
                set_flash("Department updated successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("A department with code '{$code}' already exists.", "danger");
                } else {
                    set_flash("Error updating department: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: departments.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash("Department deleted successfully.", "success");
            } catch (PDOException $e) {
                set_flash("Cannot delete department because it has linked subjects or users: " . $e->getMessage(), "danger");
            }
        }
        header("Location: departments.php");
        exit;
    }
}

// Fetch all departments with subjects and students count
try {
    $stmt = $pdo->query("
        SELECT 
            d.*,
            (SELECT COUNT(*) FROM subjects s WHERE s.department_id = d.id) AS subject_count,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = 'student') AS student_count,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = 'teacher') AS teacher_count
        FROM departments d
        ORDER BY d.name ASC
    ");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash("Error fetching departments: " . $e->getMessage(), "danger");
    $departments = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Departments</h3>
            <p class="text-muted mb-0">Manage college academic departments and view enrolled faculty/students count.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
            <i class="bi bi-plus-lg me-1"></i> Add Department
        </button>
    </div>

    <!-- Search & List Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <span class="fw-bold"><i class="bi bi-building text-primary me-2"></i>All Departments (<?= count($departments) ?>)</span>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter departments...">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 searchable-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Department Name</th>
                            <th>Code</th>
                            <th>Subjects</th>
                            <th>Faculty</th>
                            <th>Students</th>
                            <th>Created Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No departments found. Click "Add Department" to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($departments as $idx => $d): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><strong><?= e($d['name']) ?></strong></td>
                                    <td><span class="badge bg-light text-primary border"><?= e($d['code']) ?></span></td>
                                    <td><span class="badge bg-secondary"><?= $d['subject_count'] ?></span></td>
                                    <td><span class="badge bg-info-subtle text-info-emphasis"><?= $d['teacher_count'] ?></span></td>
                                    <td><span class="badge bg-success-subtle text-success-emphasis"><?= $d['student_count'] ?></span></td>
                                    <td><?= format_date($d['created_at']) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editDeptModal"
                                            data-id="<?= $d['id'] ?>"
                                            data-name="<?= e($d['name']) ?>"
                                            data-code="<?= e($d['code']) ?>"
                                            title="Edit Department">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteDeptModal"
                                            data-id="<?= $d['id'] ?>"
                                            data-name="<?= e($d['name']) ?>"
                                            title="Delete Department">
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

<!-- Modal: Add Department -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="departments.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Department Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Computer Science & Engineering" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Department Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. CSE" required style="text-transform: uppercase;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="departments.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_dept_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Department Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_dept_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Department Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="edit_dept_code" class="form-control" required style="text-transform: uppercase;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Department Confirmation -->
<div class="modal fade" id="deleteDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="departments.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_dept_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the department <strong id="delete_dept_name" class="text-danger"></strong>?</p>
                <p class="small text-muted mb-0">This may affect any subjects or courses registered under this department.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Populate Edit Modal
    const editModal = document.getElementById('editDeptModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_dept_id').value = button.getAttribute('data-id');
            document.getElementById('edit_dept_name').value = button.getAttribute('data-name');
            document.getElementById('edit_dept_code').value = button.getAttribute('data-code');
        });
    }

    // Populate Delete Modal
    const deleteModal = document.getElementById('deleteDeptModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('delete_dept_id').value = button.getAttribute('data-id');
            document.getElementById('delete_dept_name').textContent = button.getAttribute('data-name');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

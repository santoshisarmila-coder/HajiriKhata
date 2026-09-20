<?php
/**
 * Semester Management (CRUD)
 * College Attendance Management System
 */

$page_title = "Semesters Management";
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
        header("Location: semesters.php");
        exit;
    }

    if ($action === 'create') {
        $name = clean_input($_POST['name'] ?? '');
        $code = strtoupper(clean_input($_POST['code'] ?? ''));

        if (empty($name) || empty($code)) {
            set_flash("Semester Name and Code are required.", "danger");
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO semesters (name, code) VALUES (:name, :code)");
                $stmt->execute([':name' => $name, ':code' => $code]);
                set_flash("Semester '{$name}' added successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("A semester with code '{$code}' already exists.", "danger");
                } else {
                    set_flash("Error adding semester: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: semesters.php");
        exit;
    }

    if ($action === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = clean_input($_POST['name'] ?? '');
        $code = strtoupper(clean_input($_POST['code'] ?? ''));

        if ($id <= 0 || empty($name) || empty($code)) {
            set_flash("Invalid semester data.", "danger");
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE semesters SET name = :name, code = :code WHERE id = :id");
                $stmt->execute([':name' => $name, ':code' => $code, ':id' => $id]);
                set_flash("Semester updated successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("A semester with code '{$code}' already exists.", "danger");
                } else {
                    set_flash("Error updating semester: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: semesters.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM semesters WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash("Semester deleted successfully.", "success");
            } catch (PDOException $e) {
                set_flash("Cannot delete semester with linked subjects: " . $e->getMessage(), "danger");
            }
        }
        header("Location: semesters.php");
        exit;
    }
}

// Fetch semesters with subject counts
try {
    $stmt = $pdo->query("
        SELECT 
            s.*,
            (SELECT COUNT(*) FROM subjects sub WHERE sub.semester_id = s.id) AS subject_count
        FROM semesters s
        ORDER BY s.id ASC
    ");
    $semesters = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash("Error fetching semesters: " . $e->getMessage(), "danger");
    $semesters = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Academic Semesters</h3>
            <p class="text-muted mb-0">Configure semesters for subject mapping and class schedules.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSemModal">
            <i class="bi bi-plus-lg me-1"></i> Add Semester
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <span class="fw-bold"><i class="bi bi-calendar3 text-primary me-2"></i>All Semesters (<?= count($semesters) ?>)</span>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter semesters...">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 searchable-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Semester Name</th>
                            <th>Code</th>
                            <th>Linked Subjects</th>
                            <th>Created Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($semesters)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No semesters registered yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($semesters as $idx => $s): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><strong><?= e($s['name']) ?></strong></td>
                                    <td><span class="badge bg-light text-primary border"><?= e($s['code']) ?></span></td>
                                    <td><span class="badge bg-secondary"><?= $s['subject_count'] ?> Subjects</span></td>
                                    <td><?= format_date($s['created_at']) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editSemModal"
                                            data-id="<?= $s['id'] ?>"
                                            data-name="<?= e($s['name']) ?>"
                                            data-code="<?= e($s['code']) ?>"
                                            title="Edit Semester">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteSemModal"
                                            data-id="<?= $s['id'] ?>"
                                            data-name="<?= e($s['name']) ?>"
                                            title="Delete Semester">
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

<!-- Modal: Add Semester -->
<div class="modal fade" id="addSemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="semesters.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Add Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Semester Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. First Semester" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Semester Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. SEM-1" required style="text-transform: uppercase;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Semester</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Semester -->
<div class="modal fade" id="editSemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="semesters.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_sem_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Semester Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_sem_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Semester Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="edit_sem_code" class="form-control" required style="text-transform: uppercase;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Semester Confirmation -->
<div class="modal fade" id="deleteSemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="semesters.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_sem_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete_sem_name" class="text-danger"></strong>?</p>
                <p class="small text-muted mb-0">This will remove any subject linkages to this semester.</p>
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
    const editModal = document.getElementById('editSemModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_sem_id').value = button.getAttribute('data-id');
            document.getElementById('edit_sem_name').value = button.getAttribute('data-name');
            document.getElementById('edit_sem_code').value = button.getAttribute('data-code');
        });
    }

    const deleteModal = document.getElementById('deleteSemModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('delete_sem_id').value = button.getAttribute('data-id');
            document.getElementById('delete_sem_name').textContent = button.getAttribute('data-name');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

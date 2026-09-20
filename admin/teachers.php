<?php
/**
 * Faculty / Teacher Management (CRUD)
 * College Attendance Management System
 */

$page_title = "Faculty Management";
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
        header("Location: teachers.php");
        exit;
    }

    if ($action === 'create') {
        $full_name     = clean_input($_POST['full_name'] ?? '');
        $username      = strtolower(clean_input($_POST['username'] ?? ''));
        $email         = strtolower(clean_input($_POST['email'] ?? ''));
        $password      = $_POST['password'] ?? 'teacher123';
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $phone         = clean_input($_POST['phone'] ?? '');

        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            set_flash("Please fill in all mandatory fields.", "danger");
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, role, department_id, phone)
                    VALUES (:user, :email, :pass, :name, 'teacher', :dept, :phone)
                ");
                $stmt->execute([
                    ':user'  => $username,
                    ':email' => $email,
                    ':pass'  => $hash,
                    ':name'  => $full_name,
                    ':dept'  => $department_id,
                    ':phone' => $phone
                ]);
                set_flash("Faculty member '{$full_name}' added successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("Username or Email already registered in system.", "danger");
                } else {
                    set_flash("Error adding teacher: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: teachers.php");
        exit;
    }

    if ($action === 'update') {
        $id            = (int)($_POST['id'] ?? 0);
        $full_name     = clean_input($_POST['full_name'] ?? '');
        $username      = strtolower(clean_input($_POST['username'] ?? ''));
        $email         = strtolower(clean_input($_POST['email'] ?? ''));
        $password      = $_POST['password'] ?? '';
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $phone         = clean_input($_POST['phone'] ?? '');

        if ($id <= 0 || empty($full_name) || empty($username) || empty($email)) {
            set_flash("Invalid teacher details submitted.", "danger");
        } else {
            try {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = :user, email = :email, password = :pass, full_name = :name, department_id = :dept, phone = :phone
                        WHERE id = :id AND role = 'teacher'
                    ");
                    $stmt->execute([
                        ':user'  => $username,
                        ':email' => $email,
                        ':pass'  => $hash,
                        ':name'  => $full_name,
                        ':dept'  => $department_id,
                        ':phone' => $phone,
                        ':id'    => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = :user, email = :email, full_name = :name, department_id = :dept, phone = :phone
                        WHERE id = :id AND role = 'teacher'
                    ");
                    $stmt->execute([
                        ':user'  => $username,
                        ':email' => $email,
                        ':name'  => $full_name,
                        ':dept'  => $department_id,
                        ':phone' => $phone,
                        ':id'    => $id
                    ]);
                }
                set_flash("Teacher profile updated successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("Username or Email already in use by another account.", "danger");
                } else {
                    set_flash("Error updating teacher: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: teachers.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'teacher'");
                $stmt->execute([':id' => $id]);
                set_flash("Teacher account deleted.", "success");
            } catch (PDOException $e) {
                set_flash("Error deleting teacher: " . $e->getMessage(), "danger");
            }
        }
        header("Location: teachers.php");
        exit;
    }
}

// Fetch departments for dropdown
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();

// Fetch all teachers
try {
    $stmt = $pdo->query("
        SELECT 
            u.*,
            d.name AS department_name,
            d.code AS department_code,
            (SELECT COUNT(*) FROM subject_teacher st WHERE st.teacher_id = u.id) AS assigned_subjects_count,
            (SELECT COUNT(DISTINCT a.attendance_date) FROM attendance a WHERE a.teacher_id = u.id) AS classes_conducted
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'teacher'
        ORDER BY u.full_name ASC
    ");
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash("Error fetching teachers: " . $e->getMessage(), "danger");
    $teachers = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Faculty / Teachers</h3>
            <p class="text-muted mb-0">Manage teacher accounts, assign departments, and check assigned classes.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="assign_teachers.php" class="btn btn-outline-primary">
                <i class="bi bi-person-gear me-1"></i> Subject Assignments
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                <i class="bi bi-plus-lg me-1"></i> Add Faculty
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <span class="fw-bold"><i class="bi bi-person-workspace text-primary me-2"></i>All Teachers (<?= count($teachers) ?>)</span>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter teachers...">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 searchable-table">
                    <thead>
                        <tr>
                            <th>Faculty Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Subjects</th>
                            <th>Sessions Taken</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teachers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No teachers registered yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($teachers as $t): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-circle" style="background-color: #dcfce7; color: #15803d; width: 34px; height: 34px; font-size: 0.8rem;">
                                                <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
                                            </div>
                                            <strong><?= e($t['full_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td><code><?= e($t['username']) ?></code></td>
                                    <td><?= e($t['email']) ?></td>
                                    <td>
                                        <?php if (!empty($t['department_name'])): ?>
                                            <span class="badge bg-light text-dark border"><?= e($t['department_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($t['phone'] ?? 'N/A') ?></td>
                                    <td>
                                        <a href="assign_teachers.php" class="badge bg-primary-subtle text-primary border text-decoration-none">
                                            <?= $t['assigned_subjects_count'] ?> Subjects
                                        </a>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= $t['classes_conducted'] ?></span></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editTeacherModal"
                                            data-id="<?= $t['id'] ?>"
                                            data-name="<?= e($t['full_name']) ?>"
                                            data-user="<?= e($t['username']) ?>"
                                            data-email="<?= e($t['email']) ?>"
                                            data-dept="<?= $t['department_id'] ?>"
                                            data-phone="<?= e($t['phone'] ?? '') ?>"
                                            title="Edit Faculty">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteTeacherModal"
                                            data-id="<?= $t['id'] ?>"
                                            data-name="<?= e($t['full_name']) ?>"
                                            title="Delete Faculty">
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

<!-- Modal: Add Teacher -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="teachers.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Add Faculty Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Dr. Robert White" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. rwhite" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. rwhite@college.edu" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" value="teacher123" required>
                        <small class="text-muted">Default: <code>teacher123</code></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+1-555-0199">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">Select Department (Optional)</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?> (<?= e($dept['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Teacher -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="teachers.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_teacher_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Faculty Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="edit_teacher_name" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="edit_teacher_user" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_teacher_email" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep unchanged">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" id="edit_teacher_phone" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department_id" id="edit_teacher_dept" class="form-select">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?> (<?= e($dept['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Teacher -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="teachers.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_teacher_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong id="delete_teacher_name" class="text-danger"></strong>?</p>
                <p class="small text-muted mb-0">Their subject assignments and attendance logs will be impacted.</p>
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
    const editModal = document.getElementById('editTeacherModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_teacher_id').value = button.getAttribute('data-id');
            document.getElementById('edit_teacher_name').value = button.getAttribute('data-name');
            document.getElementById('edit_teacher_user').value = button.getAttribute('data-user');
            document.getElementById('edit_teacher_email').value = button.getAttribute('data-email');
            document.getElementById('edit_teacher_dept').value = button.getAttribute('data-dept') || '';
            document.getElementById('edit_teacher_phone').value = button.getAttribute('data-phone');
        });
    }

    const deleteModal = document.getElementById('deleteTeacherModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('delete_teacher_id').value = button.getAttribute('data-id');
            document.getElementById('delete_teacher_name').textContent = button.getAttribute('data-name');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

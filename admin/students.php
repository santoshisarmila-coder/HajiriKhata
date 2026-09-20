<?php
/**
 * Students Management (CRUD)
 * College Attendance Management System
 */

$page_title = "Students Management";
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
        header("Location: students.php");
        exit;
    }

    if ($action === 'create') {
        $full_name     = clean_input($_POST['full_name'] ?? '');
        $roll_no       = strtoupper(clean_input($_POST['roll_no'] ?? ''));
        $username      = strtolower(clean_input($_POST['username'] ?? ''));
        $email         = strtolower(clean_input($_POST['email'] ?? ''));
        $password      = $_POST['password'] ?? 'student123';
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $phone         = clean_input($_POST['phone'] ?? '');

        if (empty($full_name) || empty($roll_no) || empty($username) || empty($email) || empty($password)) {
            set_flash("Please fill in all mandatory fields.", "danger");
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, role, department_id, roll_no, phone)
                    VALUES (:user, :email, :pass, :name, 'student', :dept, :roll, :phone)
                ");
                $stmt->execute([
                    ':user'  => $username,
                    ':email' => $email,
                    ':pass'  => $hash,
                    ':name'  => $full_name,
                    ':dept'  => $department_id,
                    ':roll'  => $roll_no,
                    ':phone' => $phone
                ]);
                set_flash("Student '{$full_name}' ({$roll_no}) registered successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("Roll Number, Username or Email already exists in the system.", "danger");
                } else {
                    set_flash("Error adding student: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: students.php");
        exit;
    }

    if ($action === 'update') {
        $id            = (int)($_POST['id'] ?? 0);
        $full_name     = clean_input($_POST['full_name'] ?? '');
        $roll_no       = strtoupper(clean_input($_POST['roll_no'] ?? ''));
        $username      = strtolower(clean_input($_POST['username'] ?? ''));
        $email         = strtolower(clean_input($_POST['email'] ?? ''));
        $password      = $_POST['password'] ?? '';
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $phone         = clean_input($_POST['phone'] ?? '');

        if ($id <= 0 || empty($full_name) || empty($roll_no) || empty($username) || empty($email)) {
            set_flash("Invalid student details submitted.", "danger");
        } else {
            try {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = :user, email = :email, password = :pass, full_name = :name, department_id = :dept, roll_no = :roll, phone = :phone
                        WHERE id = :id AND role = 'student'
                    ");
                    $stmt->execute([
                        ':user'  => $username,
                        ':email' => $email,
                        ':pass'  => $hash,
                        ':name'  => $full_name,
                        ':dept'  => $department_id,
                        ':roll'  => $roll_no,
                        ':phone' => $phone,
                        ':id'    => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = :user, email = :email, full_name = :name, department_id = :dept, roll_no = :roll, phone = :phone
                        WHERE id = :id AND role = 'student'
                    ");
                    $stmt->execute([
                        ':user'  => $username,
                        ':email' => $email,
                        ':name'  => $full_name,
                        ':dept'  => $department_id,
                        ':roll'  => $roll_no,
                        ':phone' => $phone,
                        ':id'    => $id
                    ]);
                }
                set_flash("Student profile updated successfully!", "success");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash("Roll Number, Username or Email already exists for another account.", "danger");
                } else {
                    set_flash("Error updating student: " . $e->getMessage(), "danger");
                }
            }
        }
        header("Location: students.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'student'");
                $stmt->execute([':id' => $id]);
                set_flash("Student record and related attendance logs removed.", "success");
            } catch (PDOException $e) {
                set_flash("Error deleting student: " . $e->getMessage(), "danger");
            }
        }
        header("Location: students.php");
        exit;
    }
}

// Fetch departments for dropdown
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();

// Fetch all students with department and attendance stats
try {
    $stmt = $pdo->query("
        SELECT 
            u.*,
            d.name AS department_name,
            d.code AS department_code,
            (SELECT COUNT(*) FROM subject_student ss WHERE ss.student_id = u.id) AS enrolled_subjects_count,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id) AS total_classes,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id AND a.status IN ('Present', 'Late')) AS attended_classes
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'student'
        ORDER BY u.roll_no ASC, u.full_name ASC
    ");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash("Error fetching students: " . $e->getMessage(), "danger");
    $students = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-slate-900 mb-1">Students Directory</h3>
            <p class="text-muted mb-0">Manage registered students, roll numbers, department assignments, and attendance standing.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="assign_students.php" class="btn btn-outline-primary">
                <i class="bi bi-person-plus me-1"></i> Enroll in Subjects
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="bi bi-plus-lg me-1"></i> Add New Student
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <span class="fw-bold"><i class="bi bi-people text-primary me-2"></i>All Students (<?= count($students) ?>)</span>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter students by name, roll no...">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 searchable-table">
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Student Name</th>
                            <th>Department</th>
                            <th>Email / Contact</th>
                            <th>Enrolled</th>
                            <th>Attendance %</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No students registered yet. Click "Add New Student" to enroll.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): 
                                $total_classes = (int)$s['total_classes'];
                                $attended_classes = (int)$s['attended_classes'];
                                $pct = $total_classes > 0 ? round(($attended_classes / $total_classes) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><span class="badge bg-primary-subtle text-primary border fw-semibold"><?= e($s['roll_no'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-circle" style="background-color: #dbeafe; color: #1d4ed8; width: 32px; height: 32px; font-size: 0.75rem;">
                                                <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= e($s['full_name']) ?></div>
                                                <div class="text-muted small">@<?= e($s['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['department_name'])): ?>
                                            <span class="badge bg-light text-dark border"><?= e($s['department_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= e($s['email']) ?></div>
                                        <small class="text-muted"><?= e($s['phone'] ?? 'No phone') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $s['enrolled_subjects_count'] ?> Subjects</span>
                                    </td>
                                    <td>
                                        <?php if ($total_classes > 0): ?>
                                            <?= get_percentage_badge($pct) ?>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?= $attended_classes ?> / <?= $total_classes ?> classes</div>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">No logs yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editStudentModal"
                                            data-id="<?= $s['id'] ?>"
                                            data-name="<?= e($s['full_name']) ?>"
                                            data-roll="<?= e($s['roll_no'] ?? '') ?>"
                                            data-user="<?= e($s['username']) ?>"
                                            data-email="<?= e($s['email']) ?>"
                                            data-dept="<?= $s['department_id'] ?>"
                                            data-phone="<?= e($s['phone'] ?? '') ?>"
                                            title="Edit Student">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteStudentModal"
                                            data-id="<?= $s['id'] ?>"
                                            data-name="<?= e($s['full_name']) ?>"
                                            data-roll="<?= e($s['roll_no'] ?? '') ?>"
                                            title="Delete Student">
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

<!-- Modal: Add Student -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="students.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-semibold">Roll Number <span class="text-danger">*</span></label>
                        <input type="text" name="roll_no" class="form-control" placeholder="e.g. CS-2024-06" required style="text-transform: uppercase;">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. jdoe" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. jdoe@college.edu" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" value="student123" required>
                        <small class="text-muted">Default: <code>student123</code></small>
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
                <button type="submit" class="btn btn-primary">Save Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Student -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="students.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_student_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="edit_student_name" class="form-control" required>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-semibold">Roll Number <span class="text-danger">*</span></label>
                        <input type="text" name="roll_no" id="edit_student_roll" class="form-control" required style="text-transform: uppercase;">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="edit_student_user" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_student_email" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep unchanged">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" id="edit_student_phone" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department_id" id="edit_student_dept" class="form-select">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?> (<?= e($dept['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Student -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="students.php" method="POST" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_student_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Student Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete student <strong id="delete_student_name" class="text-danger"></strong> (<span id="delete_student_roll"></span>)?</p>
                <p class="small text-muted mb-0"><strong>Warning:</strong> All subject enrollments and past attendance records for this student will be permanently deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Delete Record</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editStudentModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_student_id').value = button.getAttribute('data-id');
            document.getElementById('edit_student_name').value = button.getAttribute('data-name');
            document.getElementById('edit_student_roll').value = button.getAttribute('data-roll');
            document.getElementById('edit_student_user').value = button.getAttribute('data-user');
            document.getElementById('edit_student_email').value = button.getAttribute('data-email');
            document.getElementById('edit_student_dept').value = button.getAttribute('data-dept') || '';
            document.getElementById('edit_student_phone').value = button.getAttribute('data-phone');
        });
    }

    const deleteModal = document.getElementById('deleteStudentModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('delete_student_id').value = button.getAttribute('data-id');
            document.getElementById('delete_student_name').textContent = button.getAttribute('data-name');
            document.getElementById('delete_student_roll').textContent = button.getAttribute('data-roll');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

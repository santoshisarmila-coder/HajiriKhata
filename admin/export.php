<?php
/**
 * Attendance Report CSV Exporter
 * College Attendance Management System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Only Admin or Teacher can export
require_role(['admin', 'teacher']);

// Filter Parameters
$subject_id    = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$department_id = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$student_id    = !empty($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$status        = clean_input($_GET['status'] ?? '');
$from_date     = clean_input($_GET['from_date'] ?? '');
$to_date       = clean_input($_GET['to_date'] ?? '');

// If teacher, restrict to their assigned subjects
$user = current_user();
if ($user['role'] === 'teacher') {
    $teacher_id = $user['id'];
}

// Build Query Conditions
$where = ["1=1"];
$params = [];

if ($user['role'] === 'teacher') {
    $where[] = "a.teacher_id = :auth_teacher_id";
    $params[':auth_teacher_id'] = $teacher_id;
}

if ($subject_id > 0) {
    $where[] = "a.subject_id = :subject_id";
    $params[':subject_id'] = $subject_id;
}
if ($department_id > 0) {
    $where[] = "s.department_id = :department_id";
    $params[':department_id'] = $department_id;
}
if ($student_id > 0) {
    $where[] = "a.student_id = :student_id";
    $params[':student_id'] = $student_id;
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
    $query = "
        SELECT 
            a.attendance_date,
            a.time_slot,
            u.roll_no,
            u.full_name AS student_name,
            d.name AS department_name,
            s.subject_code,
            s.subject_name,
            t.full_name AS teacher_name,
            a.status,
            a.remarks,
            a.created_at
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users u ON a.student_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        JOIN users t ON a.teacher_id = t.id
        WHERE {$where_clause}
        ORDER BY a.attendance_date DESC, u.roll_no ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Prepare CSV Download
    $filename = "attendance_report_" . date('Y-m-d_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility
    fputs($output, "\xEF\xBB\xBF");

    // CSV Header row
    fputcsv($output, [
        'Attendance Date',
        'Time Slot',
        'Roll Number',
        'Student Name',
        'Department',
        'Subject Code',
        'Subject Name',
        'Marked By (Teacher)',
        'Status',
        'Remarks',
        'Recorded Timestamp'
    ]);

    // CSV Data rows
    foreach ($records as $row) {
        fputcsv($output, [
            $row['attendance_date'],
            $row['time_slot'],
            $row['roll_no'] ?? 'N/A',
            $row['student_name'],
            $row['department_name'] ?? 'N/A',
            $row['subject_code'],
            $row['subject_name'],
            $row['teacher_name'],
            $row['status'],
            $row['remarks'] ?? '',
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Export generation failed: " . $e->getMessage());
}

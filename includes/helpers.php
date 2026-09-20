<?php
/**
 * Helper Functions & Formatting Utilities
 * College Attendance Management System
 */

/**
 * Escape HTML output for XSS prevention
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Clean & sanitize user input string
 * @param mixed $data
 * @return string
 */
function clean_input($data): string {
    if (is_null($data)) return '';
    $data = trim((string)$data);
    $data = stripslashes($data);
    return $data;
}

/**
 * Set a session flash alert message
 * @param string $message
 * @param string $type success|danger|warning|info
 */
function set_flash(string $message, string $type = 'success'): void {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Retrieve and clear the session flash message
 * @return array|null
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type'    => $_SESSION['flash_type'] ?? 'info'
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $flash;
    }
    return null;
}

/**
 * Render flash alert banner if one exists
 * @return string
 */
function display_flash(): string {
    $flash = get_flash();
    if (!$flash) {
        return '';
    }

    $icon = match ($flash['type']) {
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        'info'    => 'bi-info-circle-fill',
        default   => 'bi-bell-fill'
    };

    return '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                <i class="bi ' . $icon . ' fs-5 me-2"></i>
                <div class="flex-grow-1">' . e($flash['message']) . '</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

/**
 * Calculate attendance percentage
 * @param int $present
 * @param int $late
 * @param int $total
 * @return float
 */
function calculate_attendance_percentage(int $present, int $late, int $total): float {
    if ($total <= 0) {
        return 0.0;
    }
    // Count Present and Late as attended (or Late as 1 attendance unit)
    $attended = $present + $late;
    return round(($attended / $total) * 100, 2);
}

/**
 * Generate a visual badge for attendance status
 * @param string $status Present|Absent|Late
 * @return string
 */
function get_status_badge(string $status): string {
    return match (strtolower(trim($status))) {
        'present' => '<span class="badge bg-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle me-1"></i>Present</span>',
        'absent'  => '<span class="badge bg-danger px-2.5 py-1.5 rounded-pill"><i class="bi bi-x-circle me-1"></i>Absent</span>',
        'late'    => '<span class="badge bg-warning text-dark px-2.5 py-1.5 rounded-pill"><i class="bi bi-clock-history me-1"></i>Late</span>',
        default   => '<span class="badge bg-secondary px-2.5 py-1.5 rounded-pill">' . e($status) . '</span>'
    };
}

/**
 * Generate a visual badge for attendance percentage with threshold indicators
 * @param float $percentage
 * @param float $threshold
 * @return string
 */
function get_percentage_badge(float $percentage, float $threshold = ATTENDANCE_THRESHOLD): string {
    if ($percentage >= 80.0) {
        return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 fs-6 fw-semibold">' . $percentage . '% <i class="bi bi-shield-check ms-1"></i></span>';
    } elseif ($percentage >= $threshold) {
        return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1.5 fs-6 fw-semibold">' . $percentage . '% <i class="bi bi-exclamation-triangle ms-1"></i></span>';
    } else {
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 fs-6 fw-semibold">' . $percentage . '% <i class="bi bi-exclamation-octagon-fill ms-1"></i> (Shortage)</span>';
    }
}

/**
 * Format Date cleanly
 * @param string|null $date
 * @param string $format
 * @return string
 */
function format_date(?string $date, string $format = 'M d, Y'): string {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/**
 * Return JSON response
 * @param array $data
 * @param int $status_code
 */
function json_response(array $data, int $status_code = 200): void {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

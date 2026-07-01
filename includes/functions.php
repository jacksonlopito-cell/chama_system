<?php
/**
 * Core Helper Functions
 */

/**
 * Escape output for HTML
 */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Set flash message
 */
function setFlash($type, $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message HTML
 */
function displayFlash(): void {
    $flash = getFlash();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        $icon = [
            'success' => 'fa-check-circle',
            'danger'  => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info'    => 'fa-info-circle'
        ][$type] ?? 'fa-info-circle';

        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center" role="alert">';
        echo '  <i class="fas ' . $icon . ' me-2"></i>';
        echo '  <div>' . e($message) . '</div>';
        echo '  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Generate a random string
 */
function generateToken($length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Generate member number
 */
function generateMemberNumber($groupCode = 'CHM'): string {
    $db = getConnection();
    $prefix = $groupCode . '-';
    $year = date('Y');

    $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE member_no LIKE ?");
    $stmt->execute([$prefix . $year . '-%']);
    $count = (int)$stmt->fetchColumn();

    return $prefix . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate receipt number
 */
function generateReceiptNo($prefix = 'RCP'): string {
    $db = getConnection();
    $year = date('Y');

    $stmt = $db->prepare("SELECT COUNT(*) FROM contributions WHERE receipt_no LIKE ?");
    $stmt->execute([$prefix . '-' . $year . '-%']);
    $count = (int)$stmt->fetchColumn();

    return $prefix . '-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate loan number
 */
function generateLoanNo(): string {
    $db = getConnection();
    $year = date('Y');

    $stmt = $db->prepare("SELECT COUNT(*) FROM loans WHERE loan_no LIKE ?");
    $stmt->execute(['LN-' . $year . '-%']);
    $count = (int)$stmt->fetchColumn();

    return 'LN-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

/**
 * Format currency
 */
function formatCurrency($amount): string {
    $symbol = 'KSh ';
    $formatted = number_format((float)$amount, 2);
    return $symbol . $formatted;
}

/**
 * Format date
 */
function formatDate($date, $format = 'd/m/Y'): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '-';
}

/**
 * Format datetime
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i:s'): string {
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    return $timestamp ? date($format, $timestamp) : '-';
}

/**
 * Time ago function
 */
function timeAgo($datetime): string {
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '-';

    $diff = time() - $timestamp;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

/**
 * Get user IP address
 */
function getUserIP(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Log activity
 */
function logActivity($userId, $type, $description): void {
    try {
        $db = getConnection();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $description, getUserIP()]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Log audit trail
 */
function logAudit($userId, $username, $action, $table, $recordId = null, $oldValues = null, $newValues = null, $description = null): void {
    try {
        $db = getConnection();

        $oldJson = $oldValues ? json_encode($oldValues) : null;
        $newJson = $newValues ? json_encode($newValues) : null;

        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, username, action, table_name, record_id, old_values, new_values, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $username, $action, $table, $recordId,
            $oldJson, $newJson, $description,
            getUserIP(), $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Failed to audit: " . $e->getMessage());
    }
}

/**
 * Get settings value
 */
function getSetting($key, $default = null): ?string {
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value !== false ? $value : $default;
        return $cache[$key];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Get all settings
 */
function getAllSettings(): array {
    try {
        $db = getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Create notification
 */
function createNotification($userId, $type, $title, $message = null, $link = null, $icon = null): void {
    try {
        $db = getConnection();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $title, $message, $link, $icon]);
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
    }
}

/**
 * Upload file
 */
function uploadFile($file, $targetDir, $allowedExts = null): ?string {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExts = $allowedExts ?? explode(',', ALLOWED_EXTENSIONS);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts)) {
        return null;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return null;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $filename = uniqid() . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    }

    return null;
}

/**
 * Pagination helper
 */
function paginate($totalRecords, $currentPage, $perPage = PAGE_LIMIT): array {
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total'       => $totalRecords,
        'perPage'     => $perPage,
        'currentPage' => $currentPage,
        'totalPages'  => $totalPages,
        'offset'      => $offset,
        'hasPrev'     => $currentPage > 1,
        'hasNext'     => $currentPage < $totalPages,
        'prevPage'    => $currentPage - 1,
        'nextPage'    => $currentPage + 1
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination($pagination, $url = '?'): string {
    if ($pagination['totalPages'] <= 1) return '';

    $html = '<nav><ul class="pagination justify-content-center mb-0">';

    // Prev
    $disabled = $pagination['hasPrev'] ? '' : ' disabled';
    $html .= '<li class="page-item' . $disabled . '">';
    $html .= '<a class="page-link" href="' . $url . 'page=' . $pagination['prevPage'] . '"><i class="fas fa-chevron-left"></i></a>';
    $html .= '</li>';

    // Pages
    $start = max(1, $pagination['currentPage'] - 2);
    $end = min($pagination['totalPages'], $pagination['currentPage'] + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . 'page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['currentPage'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">';
        $html .= '<a class="page-link" href="' . $url . 'page=' . $i . '">' . $i . '</a>';
        $html .= '</li>';
    }

    if ($end < $pagination['totalPages']) {
        if ($end < $pagination['totalPages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . 'page=' . $pagination['totalPages'] . '">' . $pagination['totalPages'] . '</a></li>';
    }

    // Next
    $disabled = $pagination['hasNext'] ? '' : ' disabled';
    $html .= '<li class="page-item' . $disabled . '">';
    $html .= '<a class="page-link" href="' . $url . 'page=' . $pagination['nextPage'] . '"><i class="fas fa-chevron-right"></i></a>';
    $html .= '</li>';

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Generate random color
 */
function randomColor($index = null): string {
    $colors = ['#465fff', '#12b76a', '#f79009', '#f04438', '#ee46bc', '#7a5af8', '#0ba5ec', '#fb6514'];
    if ($index !== null) {
        return $colors[$index % count($colors)];
    }
    return $colors[array_rand($colors)];
}

/**
 * Get member full name
 */
function getMemberName($memberId): string {
    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM members WHERE id = ?");
        $stmt->execute([$memberId]);
        return $stmt->fetchColumn() ?: 'Unknown';
    } catch (Exception $e) {
        return 'Unknown';
    }
}

/**
 * Get status badge HTML
 */
function statusBadge($status): string {
    $map = [
        'active'               => ['bg-success', 'Active'],
        'inactive'             => ['bg-secondary', 'Inactive'],
        'pending'              => ['bg-warning text-dark', 'Pending'],
        'suspended'            => ['bg-danger', 'Suspended'],
        'paid'                 => ['bg-success', 'Paid'],
        'defaulted'            => ['bg-danger', 'Defaulted'],
        'approved'             => ['bg-info text-dark', 'Approved'],
        'rejected'             => ['bg-danger', 'Rejected'],
        'disbursed'            => ['bg-primary', 'Disbursed'],
        'completed'            => ['bg-success', 'Completed'],
        'scheduled'            => ['bg-info text-dark', 'Scheduled'],
        'cancelled'            => ['bg-danger', 'Cancelled'],
        'draft'                => ['bg-secondary', 'Draft'],
        'pending_verification' => ['bg-warning text-dark', 'Pending Verification'],
        'email_verified'       => ['bg-info text-dark', 'Email Verified'],
        'pending_approval'     => ['bg-warning text-dark', 'Pending Approval'],
        'terminated'           => ['bg-dark', 'Terminated'],
    ];

    $status = strtolower($status);
    $classes = $map[$status][0] ?? 'bg-secondary';
    $label = $map[$status][1] ?? ucfirst($status);

    return '<span class="badge ' . $classes . '">' . $label . '</span>';
}

/**
 * Calculate loan interest (reducing balance)
 */
function calculateLoanReducingBalance($principal, $annualRate, $tenureMonths): array {
    $monthlyRate = ($annualRate / 100) / 12;
    $payment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $tenureMonths)) /
               (pow(1 + $monthlyRate, $tenureMonths) - 1);

    $schedule = [];
    $balance = $principal;
    $totalInterest = 0;

    for ($i = 1; $i <= $tenureMonths; $i++) {
        $interest = $balance * $monthlyRate;
        $principalPaid = $payment - $interest;
        $balance -= $principalPaid;
        $totalInterest += $interest;

        $schedule[] = [
            'installment' => $i,
            'principal'   => round($principalPaid, 2),
            'interest'    => round($interest, 2),
            'total'       => round($payment, 2),
            'balance'     => round(max(0, $balance), 2)
        ];
    }

    return [
        'monthlyPayment' => round($payment, 2),
        'totalInterest'  => round($totalInterest, 2),
        'totalAmount'    => round($principal + $totalInterest, 2),
        'schedule'       => $schedule
    ];
}

/**
 * Calculate loan interest (flat rate)
 */
function calculateLoanFlatRate($principal, $annualRate, $tenureMonths): array {
    $totalInterest = $principal * ($annualRate / 100) * ($tenureMonths / 12);
    $totalAmount = $principal + $totalInterest;
    $monthlyPayment = $totalAmount / $tenureMonths;

    $schedule = [];
    $balance = $totalAmount;
    $sumPrincipal = 0;
    $sumInterest = 0;

    for ($i = 1; $i <= $tenureMonths; $i++) {
        $isLast = ($i === $tenureMonths);
        $principalPaid = $isLast ? ($principal - $sumPrincipal) : ($principal / $tenureMonths);
        $interestPaid = $isLast ? ($totalInterest - $sumInterest) : ($totalInterest / $tenureMonths);
        $sumPrincipal += round($principalPaid, 2);
        $sumInterest += round($interestPaid, 2);
        $balance -= $monthlyPayment;

        $schedule[] = [
            'installment' => $i,
            'principal'   => round($principalPaid, 2),
            'interest'    => round($interestPaid, 2),
            'total'       => round($principalPaid + $interestPaid, 2),
            'balance'     => round(max(0, $balance), 2)
        ];
    }

    return [
        'monthlyPayment' => round($monthlyPayment, 2),
        'totalInterest'  => round($totalInterest, 2),
        'totalAmount'    => round($totalAmount, 2),
        'schedule'       => $schedule
    ];
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...'): string {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . $suffix;
}

/**
 * Is AJAX request
 */
function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    // Always include the current CSRF token so AJAX can refresh it
    if (is_array($data)) {
        $data['csrf_token'] = csrfToken();
    }
    echo json_encode($data);
    exit;
}

/**
 * Get month name
 */
function monthName($month): string {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    return $months[(int)$month] ?? '';
}

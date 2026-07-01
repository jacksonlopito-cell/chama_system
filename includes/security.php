<?php
/**
 * Security Functions
 */

/**
 * Set secure HTTP headers
 */
function setSecureHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
         . "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; "
         . "img-src 'self' data:; "
         . "connect-src 'self';";

    header("Content-Security-Policy: " . $csp);
}

/**
 * Sanitize output against XSS
 */
function sanitizeOutput($value) {
    if (is_array($value)) {
        return array_map('sanitizeOutput', $value);
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate CSRF token
 */
function validateCsrf(): bool {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';

    if (empty($token) || empty($stored)) {
        return false;
    }

    return hash_equals($stored, $token);
}

/**
 * Check login attempt limits
 */
function checkLoginAttempts($email): bool {
    $db = getConnection();

    $stmt = $db->prepare("SELECT login_attempts, locked_until FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) return true;

    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return false;
    }

    if ($user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $db->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE email = ?")
           ->execute([LOGIN_LOCKOUT_TIME, $email]);
        return false;
    }

    return true;
}

/**
 * Reset login attempts
 */
function resetLoginAttempts($email): void {
    $db = getConnection();
    $stmt = $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?");
    $stmt->execute([$email]);
}

/**
 * Increment login attempts
 */
function incrementLoginAttempts($email): void {
    $db = getConnection();
    $stmt = $db->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE email = ?");
    $stmt->execute([$email]);
}

/**
 * Password strength check
 */
function isPasswordStrong($password): array {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain an uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain a lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain a number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain a special character';
    }

    return [
        'valid'  => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Encrypt sensitive data
 */
function encryptData($data, $key): string {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function decryptData($data, $key): string {
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Generate secure random password
 */
function generateStrongPassword($length = 12): string {
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $digits = '0123456789';
    $special = '!@#$%^&*()_+-=[]{}|;:';

    $all = $upper . $lower . $digits . $special;
    $password = '';

    // Ensure at least one of each type
    $password .= $upper[random_int(0, strlen($upper) - 1)];
    $password .= $lower[random_int(0, strlen($lower) - 1)];
    $password .= $digits[random_int(0, strlen($digits) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];

    for ($i = strlen($password); $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

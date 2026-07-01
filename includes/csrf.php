<?php
/**
 * CSRF Protection
 */

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token
 */
function csrfToken(): string {
    return generateCsrfToken();
}

/**
 * Get CSRF hidden input field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Verify CSRF token
 */
function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        if (isAjax()) {
            jsonResponse(['error' => 'CSRF token missing'], 403);
        }
        setFlash('danger', 'Security token missing. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        if (isAjax()) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        setFlash('danger', 'Invalid security token. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }

    // Regenerate token after successful verification to prevent reuse
    // This is safe for AJAX because jsonResponse() now includes the new token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Regenerate CSRF token
 */
function regenerateCsrfToken(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

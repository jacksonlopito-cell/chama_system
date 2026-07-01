<?php
/**
 * Authentication & Authorization
 */

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Require authentication - redirects if not logged in
 */
function requireAuth(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please login to continue.');
        redirect(BASE_URL . 'login.php');
    }
    setSessionTimeout();
}

/**
 * Require specific role(s)
 */
function requireRole(...$roles): void {
    requireAuth();
    $userRole = $_SESSION['role_slug'] ?? '';

    if (!in_array($userRole, $roles)) {
        setFlash('danger', 'You do not have permission to access this page.');
        redirect(BASE_URL . 'dashboard.php');
    }
}

/**
 * Check if user has permission
 */
function hasPermission($permissionSlug): bool {
    if (!isLoggedIn()) return false;

    $roleId = $_SESSION['role_id'] ?? 0;

    // Super Admin has all permissions
    if ($roleId == 1) return true;

    static $cache = [];
    $cacheKey = $roleId . '_' . $permissionSlug;

    if (isset($cache[$cacheKey])) return $cache[$cacheKey];

    try {
        $db = getConnection();
        $sql = "SELECT COUNT(*) FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = ? AND p.slug = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$roleId, $permissionSlug]);
        $cache[$cacheKey] = $stmt->fetchColumn() > 0;
        return $cache[$cacheKey];
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require specific permission
 */
function requirePermission($permissionSlug): void {
    requireAuth();
    if (!hasPermission($permissionSlug)) {
        if (isAjax()) {
            jsonResponse(['error' => 'Permission denied'], 403);
        }
        redirect(BASE_URL . 'access-denied.php');
    }
}

/**
 * Authenticate user
 */
function authenticateUser($email, $password, $groupCode): ?array {
    $db = getConnection();

    // Check login attempts
    if (!checkLoginAttempts($email)) {
        return [
            'success' => false,
            'message' => 'Account locked. Too many login attempts. Try again in ' . (LOGIN_LOCKOUT_TIME / 60) . ' minutes.'
        ];
    }

    $stmt = $db->prepare("SELECT u.*, m.status as member_status, m.first_name, m.last_name, m.member_no, m.photo as member_photo
                          FROM users u
                          LEFT JOIN members m ON u.member_id = m.id
                          WHERE u.email = ? AND u.group_code = ?");
    $stmt->execute([$email, $groupCode]);
    $user = $stmt->fetch();

    if (!$user) {
        incrementLoginAttempts($email);
        return [
            'success' => false,
            'message' => 'Invalid email, password, or group code.'
        ];
    }

    if ($user['status'] !== 'active') {
        return [
            'success' => false,
            'message' => 'Account is ' . $user['status'] . '. Contact administrator.'
        ];
    }

    // Check linked member status - must be active to login
    if ($user['member_id'] && $user['member_status'] !== 'active') {
        $statusMessages = [
            'draft'                => 'Your registration is incomplete. Please complete the registration process.',
            'pending_verification' => 'Your email has not been verified. Please check your inbox for the verification link.',
            'email_verified'       => 'Your registration is pending administrator approval. You will be notified once approved.',
            'pending_approval'     => 'Your registration is pending administrator approval. You will be notified once approved.',
            'suspended'            => 'Your member account is suspended. Contact administrator.',
            'terminated'           => 'Your member account has been terminated. Contact administrator.',
        ];
        $msg = $statusMessages[$user['member_status']] ?? 'Your linked member account is ' . ($user['member_status'] ?? 'inactive') . '. Contact administrator.';
        return [
            'success' => false,
            'message' => $msg
        ];
    }

    if (!password_verify($password, $user['password'])) {
        incrementLoginAttempts($email);
        return [
            'success' => false,
            'message' => 'Invalid email, password, or group code.'
        ];
    }

    // Successful login
    resetLoginAttempts($email);

    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Create session
    createUserSession($user);

    // Audit log
    logAudit($user['id'], $user['username'], 'login', 'users', $user['id'], null, null, 'User logged in');

    return [
        'success' => true,
        'user'    => $user
    ];
}

/**
 * Get current user data
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;

    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT u.*, r.name as role_name, r.slug as role_slug
                              FROM users u
                              JOIN roles r ON u.role_id = r.id
                              WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get user permissions
 */
function getUserPermissions($roleId = null): array {
    if ($roleId === null) {
        $roleId = $_SESSION['role_id'] ?? 0;
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT p.slug, p.name, p.module
                              FROM role_permissions rp
                              JOIN permissions p ON rp.permission_id = p.id
                              WHERE rp.role_id = ?");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Count unread notifications
 */
function countUnreadNotifications($userId = null): int {
    if ($userId === null) $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) return 0;

    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get recent notifications
 */
function getRecentNotifications($userId = null, $limit = 5): array {
    if ($userId === null) $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) return [];

    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get dashboard URL based on role
 */
function getDashboardUrl(): string {
    $role = $_SESSION['role_slug'] ?? 'member';
    return BASE_URL . 'dashboard.php';
}

/**
 * Check if remember me token is valid
 */
function checkRememberMe(): void {
    if (isLoggedIn()) return;

    $cookie = $_COOKIE['remember_me'] ?? '';
    if (empty($cookie)) return;

    $parts = explode(':', $cookie);
    if (count($parts) !== 2) return;

    list($userId, $token) = $parts;

    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND remember_token = ? AND status = 'active'");
        $stmt->execute([$userId, $token]);
        $user = $stmt->fetch();

        if ($user) {
            createUserSession($user);

            // Refresh cookie
            $newToken = bin2hex(random_bytes(32));
            $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$newToken, $userId]);

            setcookie('remember_me', $userId . ':' . $newToken, time() + 2592000, '/', '', false, true);
        }
    } catch (Exception $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
}

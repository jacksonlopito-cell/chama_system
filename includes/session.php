<?php
/**
 * Session Management
 */

/**
 * Set session timeout
 */
function setSessionTimeout(): void {
    $timeout = SESSION_TIMEOUT;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        setFlash('warning', 'Session expired. Please login again.');
        redirect(BASE_URL . 'login.php');
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Create user session
 */
function createUserSession($user): void {
    session_regenerate_id(true);

    // If JOIN with members was done in the query, use those fields;
    // otherwise fetch member data separately
    $memberName = $user['first_name'] ?? null;
    $memberLastName = $user['last_name'] ?? null;
    $memberNo = $user['member_no'] ?? null;
    $memberPhoto = $user['member_photo'] ?? $user['photo'] ?? null;

    // Auto-fetch member data if not already joined
    if (empty($memberName) && !empty($user['member_id'])) {
        try {
            $db = getConnection();
            $stmt = $db->prepare("SELECT first_name, last_name, member_no, photo FROM members WHERE id = ?");
            $stmt->execute([$user['member_id']]);
            $m = $stmt->fetch();
            if ($m) {
                $memberName = $m['first_name'];
                $memberLastName = $m['last_name'];
                $memberNo = $m['member_no'];
                $memberPhoto = $m['photo'] ?: $memberPhoto;
            }
        } catch (Exception $e) {
            error_log("Session member fetch error: " . $e->getMessage());
        }
    }

    $_SESSION['user_id']         = (int)$user['id'];
    $_SESSION['role_id']         = (int)$user['role_id'];
    $_SESSION['username']        = $user['username'];
    $_SESSION['email']           = $user['email'];
    $_SESSION['member_id']       = $user['member_id'] ? (int)$user['member_id'] : null;
    $_SESSION['member_name']     = $memberName ? trim($memberName . ' ' . ($memberLastName ?? '')) : null;
    $_SESSION['member_no']       = $memberNo;
    $_SESSION['photo']           = $memberPhoto ?: ($user['photo'] ?? null);
    $_SESSION['group_code']      = $user['group_code'];
    $_SESSION['role_slug']       = getUserRoleSlug($user['role_id']);
    $_SESSION['role_name']       = getUserRoleName($user['role_id']);
    $_SESSION['logged_in']       = true;
    $_SESSION['last_activity']   = time();

    // Track session in database
    try {
        $db = getConnection();
        $stmt = $db->prepare("INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $stmt->execute([
            $user['id'],
            session_id(),
            getUserIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Session tracking error: " . $e->getMessage());
    }
}

/**
 * Destroy session
 */
function destroySession(): void {
    try {
        $db = getConnection();
        $stmt = $db->prepare("UPDATE sessions SET is_active = 0 WHERE session_token = ?");
        $stmt->execute([session_id()]);
    } catch (Exception $e) {
        error_log("Session destroy error: " . $e->getMessage());
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Get user role slug
 */
function getUserRoleSlug($roleId): string {
    static $cache = [];
    if (isset($cache[$roleId])) return $cache[$roleId];

    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT slug FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $slug = $stmt->fetchColumn();
        $cache[$roleId] = $slug ?: 'guest';
        return $cache[$roleId];
    } catch (Exception $e) {
        return 'guest';
    }
}

/**
 * Get user role name
 */
function getUserRoleName($roleId): string {
    static $cache = [];
    if (isset($cache[$roleId])) return $cache[$roleId];

    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $name = $stmt->fetchColumn();
        $cache[$roleId] = $name ?: 'Guest';
        return $cache[$roleId];
    } catch (Exception $e) {
        return 'Guest';
    }
}

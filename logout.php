<?php
/**
 * Logout Script
 */
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    $userId = $_SESSION['user_id'] ?? 0;
    $username = $_SESSION['username'] ?? 'Unknown';

    // Log the logout activity
    logActivity($userId, 'logout', 'User logged out');
    logAudit($userId, $username, 'logout', 'users', $userId, null, null, 'User logged out');

    // Destroy session
    destroySession();
}

// Remove remember me cookie
setcookie('remember_me', '', time() - 3600, '/', '', false, true);

// Redirect to login
setFlash('info', 'You have been logged out successfully.');
redirect(BASE_URL . 'login.php');

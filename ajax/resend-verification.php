<?php
/**
 * AJAX: Resend Verification Email
 *
 * Rate-limited resend with 60-second cooldown.
 * Expects: email (POST)
 * Returns: JSON
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/member_workflow.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
if (empty($email) || !validateEmail($email)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$db = getConnection();
$stmt = $db->prepare("SELECT id, first_name, email, status FROM members WHERE email = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$email]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo json_encode(['success' => false, 'message' => 'No registration found with this email.']);
    exit;
}

if ($member['status'] !== 'pending_verification') {
    echo json_encode(['success' => false, 'message' => 'Email already verified or registration is in "' . $member['status'] . '" status.']);
    exit;
}

$result = resendVerificationEmail((int)$member['id']);

if ($result['success']) {
    echo json_encode(['success' => true, 'message' => 'Verification email resent. Please check your inbox.']);
} else {
    echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Failed to resend verification email.']);
}

<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('manage_users');
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$db = getConnection();
$like = "%$q%";

// Active members without an existing user account
$stmt = $db->prepare("
    SELECT m.id, m.member_no, m.first_name, m.last_name, m.phone, m.email, m.photo, m.national_id
    FROM members m
    WHERE m.group_code = ?
      AND m.status = 'active'
      AND (m.id NOT IN (SELECT u.member_id FROM users u WHERE u.member_id IS NOT NULL) OR m.id NOT IN (SELECT u.member_id FROM users u))
      AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_no LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)
    ORDER BY m.first_name ASC
    LIMIT 20
");
$stmt->execute([$_SESSION['group_code'], $like, $like, $like, $like, $like]);
$members = $stmt->fetchAll();

$results = [];
foreach ($members as $m) {
    $results[] = [
        'id'          => $m['id'],
        'text'        => $m['member_no'] . ' — ' . $m['first_name'] . ' ' . $m['last_name'],
        'first_name'  => $m['first_name'],
        'last_name'   => $m['last_name'],
        'email'       => $m['email'] ?? '',
        'phone'       => $m['phone'],
        'photo'       => $m['photo'] ?? '',
        'national_id' => $m['national_id'] ?? ''
    ];
}

echo json_encode(['results' => $results]);

<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$db = getConnection();
$like = "%$q%";
$results = [];

$members = $db->prepare("SELECT id, member_no as member_number, first_name, last_name, phone FROM members WHERE group_code=? AND (first_name LIKE ? OR last_name LIKE ? OR member_no LIKE ? OR phone LIKE ?) AND status='active' LIMIT 10");
$members->execute([$_SESSION['group_code'], $like, $like, $like, $like]);
foreach ($members as $m) {
    $results[] = ['type' => 'member', 'label' => "{$m['member_number']} - {$m['first_name']} {$m['last_name']}", 'url' => "members.php?action=view&id={$m['id']}"];
}

$users = $db->prepare("SELECT id, username, email FROM users WHERE group_code=? AND (username LIKE ? OR email LIKE ?) LIMIT 10");
$users->execute([$_SESSION['group_code'], $like, $like]);
foreach ($users as $u) {
    $results[] = ['type' => 'user', 'label' => $u['username'] . ' (' . $u['email'] . ')', 'url' => 'users.php'];
}

$loans = $db->prepare("SELECT l.id, m.first_name, m.last_name FROM loans l JOIN members m ON l.member_id=m.id WHERE m.group_code=? AND (m.first_name LIKE ? OR m.last_name LIKE ?) LIMIT 10");
$loans->execute([$_SESSION['group_code'], $like, $like]);
foreach ($loans as $l) {
    $results[] = ['type' => 'loan', 'label' => "Loan - {$l['first_name']} {$l['last_name']}", 'url' => "loan-applications.php?id={$l['id']}"];
}

echo json_encode($results);

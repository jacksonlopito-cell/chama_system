<?php
/**
 * Migration 007: Member-User Integrity
 *
 * Drops members.user_id (circular FK), adds UNIQUE on users.member_id,
 * and migrates all existing users to have a linked member record.
 *
 * Usage: php migrations/run_007.php
 */

require_once __DIR__ . '/../includes/config.php';

$db = getConnection();

echo "=== Member-User Integrity (007) ===\n\n";

// ---- Step 1: Apply schema changes ----
echo "Applying schema changes... ";
$sql = file_get_contents(__DIR__ . '/007_member_user_integrity.sql');
$statements = explode(';', $sql);
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (!empty($stmt)) {
        try {
            $db->exec($stmt);
        } catch (Exception $e) {
            echo "\n  SQL warning: " . $e->getMessage() . "\n";
        }
    }
}
echo "done.\n";

// ---- Step 2: Link existing users to members by email ----
echo "\nLinking users to members by email...\n";
$users = $db->query("SELECT u.id, u.email, u.group_code, u.username FROM users u WHERE u.member_id IS NULL")->fetchAll();
$linkCount = 0;
$saCreated = false;

$linkStmt = $db->prepare("UPDATE users SET member_id = ? WHERE id = ?");

foreach ($users as $user) {
    // Try to find a member with matching email in same group
    $stmt = $db->prepare("SELECT id FROM members WHERE email = ? AND group_code = ? LIMIT 1");
    $stmt->execute([$user['email'], $user['group_code']]);
    $member = $stmt->fetch();

    if ($member) {
        $linkStmt->execute([$member['id'], $user['id']]);
        echo "  Linked user '{$user['username']}' (ID:{$user['id']}) to member ID:{$member['id']}\n";
        $linkCount++;
    }
}

echo "\nLinked $linkCount users to existing members.\n";

// ---- Step 3: Create member records for remaining unlinked users ----
echo "\nCreating member records for unlinked users...\n";
$unlinked = $db->query("SELECT u.id, u.email, u.username, u.group_code, u.phone FROM users u WHERE u.member_id IS NULL")->fetchAll();

$createStmt = $db->prepare("INSERT INTO members (member_no, group_code, first_name, last_name, email, phone, status, date_joined) VALUES (?, ?, ?, ?, ?, ?, 'active', CURDATE())");
$linkStmt = $db->prepare("UPDATE users SET member_id = ? WHERE id = ?");

foreach ($unlinked as $user) {
    // Generate member_no
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $user['group_code']), 0, 3)) ?: 'USR';
    $ts = time();
    $memberNo = $prefix . '-' . $user['id'] . '-' . substr($ts, -4);

    // Split username into first/last name
    $parts = explode(' ', $user['username'], 2);
    $firstName = $parts[0];
    $lastName = $parts[1] ?? $parts[0];

    $createStmt->execute([$memberNo, $user['group_code'], $firstName, $lastName, $user['email'], $user['phone']]);
    $memberId = $db->lastInsertId();

    $linkStmt->execute([$memberId, $user['id']]);
    echo "  Created member '{$firstName} {$lastName}' (NO:{$memberNo}, ID:{$memberId}) for user '{$user['username']}' (ID:{$user['id']})\n";
    $linkCount++;
}

// ---- Step 4: Clean up duplicate FK if present ----
echo "\nCleaning up duplicate FKs...\n";
$fks = $db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME='users' AND REFERENCED_TABLE_NAME='members'")->fetchAll(PDO::FETCH_COLUMN);
if (count($fks) > 1) {
    // Keep the first one, drop the rest
    for ($i = 1; $i < count($fks); $i++) {
        $db->exec("ALTER TABLE users DROP FOREIGN KEY `{$fks[$i]}`");
        echo "  Dropped duplicate FK '{$fks[$i]}'\n";
    }
}
echo "done.\n";

echo "\nMigration complete. Total users linked: $linkCount\n";

<?php
/**
 * Migration 008: Member Registration & Verification Workflow
 *
 * Adds multi-stage registration workflow, email verification,
 * document uploads, and admin approval for member registration.
 *
 * Usage: php migrations/run_008.php
 */

require_once __DIR__ . '/../includes/config.php';

$db = getConnection();

echo "=== Member Registration & Verification Workflow (008) ===\n\n";

// ---- Step 1: Apply schema changes ----
echo "Applying schema changes... ";
$sql = file_get_contents(__DIR__ . '/008_member_verification_workflow.sql');
$statements = explode(';', $sql);
$errors = [];
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (!empty($stmt)) {
        try {
            $db->exec($stmt);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
if ($errors) {
    echo "warnings:\n";
    foreach (array_unique($errors) as $err) {
        echo "  - $err\n";
    }
} else {
    echo "done.\n";
}

// ---- Step 2: Migrate existing records ----
echo "\nMigrating existing member statuses...\n";
$counts = [];
$stmt = $db->query("SELECT DISTINCT status FROM members");
foreach ($stmt as $row) {
    $status = $row['status'];
    $c = $db->prepare("SELECT COUNT(*) FROM members WHERE status = ?");
    $c->execute([$status]);
    $counts[$status] = (int)$c->fetchColumn();
    echo "  $status: {$counts[$status]}\n";
}

// Map old ENUM to new ENUM
// 'pending' -> 'pending_verification' (they haven't been verified yet)
// 'inactive' -> 'terminated' (cleaner semantics)
$db->exec("UPDATE members SET status = 'pending_verification' WHERE status = 'pending'");
$db->exec("UPDATE members SET status = 'terminated' WHERE status = 'inactive'");
echo "\nMapped 'pending' -> 'pending_verification', 'inactive' -> 'terminated'\n";

// Mark all existing active members as email_verified + approved for backward compat
$db->exec("UPDATE members SET email_verified = 1, email_verified_at = updated_at WHERE status = 'active'");
$db->exec("UPDATE members SET approved_by = 1, approved_at = updated_at WHERE status = 'active'");
echo "Existing active members marked as email_verified and pre-approved.\n";

echo "\n=== Migration 008 Complete ===\n";
echo "Next steps:\n";
echo "  1. Verify settings in: http://localhost/chama-system/settings.php?tab=registration\n";
echo "  2. Test registration at: http://localhost/chama-system/register.php\n";
echo "  3. Test email verification flow with valid SMTP settings\n";

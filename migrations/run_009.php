<?php
/**
 * Run Migration 009: Add group_code to share_products
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getConnection();

    echo "Running Migration 009: Add group_code to share_products...\n";

    // Check if column already exists
    $check = $db->query("SHOW COLUMNS FROM share_products LIKE 'group_code'");
    if ($check->fetch()) {
        echo "  group_code already exists in share_products. Skipping.\n";
    } else {
        $db->exec("ALTER TABLE share_products
            ADD COLUMN group_code VARCHAR(20) NOT NULL DEFAULT 'CHAMA001' AFTER status,
            ADD INDEX idx_share_products_group (group_code)");
        echo "  Added group_code to share_products.\n";
    }

    // Verify share_purchases has group_code (it should already exist)
    $check2 = $db->query("SHOW COLUMNS FROM share_purchases LIKE 'group_code'");
    if (!$check2->fetch()) {
        $db->exec("ALTER TABLE share_purchases
            ADD COLUMN group_code VARCHAR(20) NOT NULL DEFAULT 'CHAMA001' AFTER product_id,
            ADD INDEX idx_share_purchases_group (group_code)");
        echo "  Added group_code to share_purchases.\n";
    } else {
        echo "  group_code already exists in share_purchases. Skipping.\n";
    }

    // Check if dividends has group_code
    $check3 = $db->query("SHOW COLUMNS FROM dividends LIKE 'group_code'");
    if (!$check3->fetch()) {
        $db->exec("ALTER TABLE dividends
            ADD COLUMN group_code VARCHAR(20) NOT NULL DEFAULT 'CHAMA001' AFTER created_by,
            ADD INDEX idx_dividends_group (group_code)");
        echo "  Added group_code to dividends.\n";
    } else {
        echo "  group_code already exists in dividends. Skipping.\n";
    }

    // Update existing records with CHAMA001 if empty
    $db->exec("UPDATE share_purchases SET group_code = 'CHAMA001' WHERE group_code = '' OR group_code IS NULL");
    $db->exec("UPDATE share_products SET group_code = 'CHAMA001' WHERE group_code = '' OR group_code IS NULL");
    $db->exec("UPDATE dividends SET group_code = 'CHAMA001' WHERE group_code = '' OR group_code IS NULL");

    echo "Migration 009 completed successfully.\n";
} catch (Exception $e) {
    echo "Migration 009 FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

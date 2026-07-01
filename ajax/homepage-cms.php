<?php
/**
 * Unified AJAX Handler for Homepage CMS
 * 
 * Actions: save_fields, toggle, add_item, update_item, delete_item,
 *         reorder_items, reorder_sections, restore_defaults, restore_revision
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/homepage_cms.php';
requireAuth();
requirePermission('manage_homepage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

verifyCsrf();

$action = $_POST['action'] ?? '';

switch ($action) {

    // ===== Save Section Fields =====
    case 'save_fields':
        $sectionKey = $_POST['section_key'] ?? '';
        if (empty($sectionKey)) {
            jsonResponse(['error' => 'Missing section key'], 400);
        }
        $sec = hp_get_section($sectionKey);
        if (!$sec) {
            jsonResponse(['error' => 'Section not found'], 404);
        }
        $sectionId = (int)$sec['id'];

        // Save fields
        $fields = [];
        $rawFields = $_POST['fields'] ?? '{}';
        if (is_string($rawFields)) {
            $fields = json_decode($rawFields, true) ?? [];
        } elseif (is_array($rawFields)) {
            $fields = $rawFields;
        }

        // Handle file uploads
        $uploadsDir = ROOT_PATH . 'uploads/homepage/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

        foreach ($_FILES as $fKey => $fData) {
            if ($fData['error'] !== UPLOAD_ERR_OK) continue;
            $prefix = 'upload_fields[';
            $fieldName = $fKey;
            if (strpos($fKey, 'upload_') === 0) {
                $fieldName = substr($fKey, 7);
            }
            $filename = uploadFile($fData, $uploadsDir, $allowedExts);
            if ($filename) {
                // Strip any leading fields[ or similar from the field name
                $cleanKey = preg_replace('/^fields?\[?([^\]]+).*$/', '$1', $fieldName);
                $fields[$cleanKey] = 'uploads/homepage/' . $filename;
            }
        }

        // Handle image removals
        foreach ($_POST as $pKey => $pVal) {
            if (strpos($pKey, 'remove_') === 0 && $pVal === '1') {
                $fieldKey = substr($pKey, 7);
                $fields[$fieldKey] = '';
            }
        }

        // Auto-save a revision before saving
        hp_save_revision('save_fields');

        if (hp_save_fields($sectionId, $fields)) {
            // Save items included in the same request
            $itemsRaw = $_POST['items'] ?? '';
            if (!empty($itemsRaw)) {
                $items = json_decode($itemsRaw, true) ?? [];
                foreach ($items as $item) {
                    $itemId = (int)($item['item_id'] ?? 0);
                    $itemFields = $item['fields'] ?? [];
                    if ($itemId && !empty($itemFields)) {
                        hp_update_item($itemId, $itemFields);
                    }
                }
            }
            // Update section title if provided
            if (!empty($_POST['title'])) {
                $db = getConnection();
                $stmt = $db->prepare("UPDATE homepage_sections SET title = ? WHERE id = ?");
                $stmt->execute([$_POST['title'], $sectionId]);
            }
            logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'homepage_sections', $sectionId, null,
                ['section_key' => $sectionKey, 'fields' => array_keys($fields)],
                'Updated homepage section: ' . $sectionKey
            );
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to save fields'], 500);
        }
        break;

    // ===== Toggle Visibility =====
    case 'toggle':
        $key = $_POST['section_key'] ?? '';
        $visible = (int)($_POST['is_visible'] ?? 0);
        if (empty($key)) jsonResponse(['error' => 'Missing section key'], 400);
        hp_save_revision('toggle');
        if (hp_toggle($key, (bool)$visible)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to toggle section'], 500);
        }
        break;

    // ===== Add Item =====
    case 'add_item':
        $sectionKey = $_POST['section_key'] ?? '';
        $sec = hp_get_section($sectionKey);
        if (!$sec) jsonResponse(['error' => 'Section not found'], 404);
        $itemType = $_POST['item_type'] ?? '';
        $data = [];
        if (!empty($itemType)) {
            $data['type'] = $itemType;
        }
        $itemId = hp_add_item((int)$sec['id'], $data, count($sec['items']));
        if ($itemId) {
            hp_save_revision('add_item');
            jsonResponse(['success' => true, 'item_id' => $itemId]);
        } else {
            jsonResponse(['error' => 'Failed to add item'], 500);
        }
        break;

    // ===== Update Item =====
    case 'update_item':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if (!$itemId) jsonResponse(['error' => 'Missing item ID'], 400);
        $data = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'fields[') === 0) {
                $field = substr($key, 7, -1);
                $data[$field] = $val;
            }
        }
        if (hp_update_item($itemId, $data)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to update item'], 500);
        }
        break;

    // ===== Delete Item =====
    case 'delete_item':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if (!$itemId) jsonResponse(['error' => 'Missing item ID'], 400);
        hp_save_revision('delete_item');
        if (hp_delete_item($itemId)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to delete item'], 500);
        }
        break;

    // ===== Reorder Items =====
    case 'reorder_items':
        $sectionKey = $_POST['section_key'] ?? '';
        $order = $_POST['order'] ?? [];
        // Parse: [[item_id, sort_order], ...]
        $parsed = [];
        foreach ($order as $item) {
            if (is_array($item) && count($item) === 2) {
                $parsed[(int)$item[0]] = (int)$item[1];
            }
        }
        $sec = hp_get_section($sectionKey);
        if (!$sec) jsonResponse(['error' => 'Section not found'], 404);
        hp_save_revision('reorder_items');
        if (hp_reorder_items((int)$sec['id'], $parsed)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to reorder items'], 500);
        }
        break;

    // ===== Reorder Sections =====
    case 'reorder_sections':
        $raw = $_POST['order'] ?? [];
        $order = [];
        foreach ($raw as $item) {
            if (is_array($item) && count($item) === 2) {
                $order[] = [trim($item[0]), (int)$item[1]];
            }
        }
        hp_save_revision('reorder_sections');
        if (hp_reorder_sections($order)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to reorder sections'], 500);
        }
        break;

    // ===== Restore Defaults =====
    case 'restore_defaults':
        try {
            $db = getConnection();
            // Clear all data
            $db->exec("DELETE FROM homepage_item_fields");
            $db->exec("DELETE FROM homepage_items");
            $db->exec("DELETE FROM homepage_fields");
            $db->exec("DELETE FROM homepage_sections");
            $db->exec("ALTER TABLE homepage_sections AUTO_INCREMENT = 1");
            $db->exec("ALTER TABLE homepage_fields AUTO_INCREMENT = 1");
            $db->exec("ALTER TABLE homepage_items AUTO_INCREMENT = 1");
            $db->exec("ALTER TABLE homepage_item_fields AUTO_INCREMENT = 1");

            // Re-run the migration seed (uses INSERT IGNORE for sections and INSERT INTO for fields)
            // Suppress migration's echo output to avoid corrupting JSON response
            ob_start();
            require ROOT_PATH . 'migrations/run_006.php';
            ob_end_clean();

            hp_save_revision('restore_defaults');
            logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'homepage_sections', null, null, null, 'Restored homepage to defaults');
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    // ===== Restore Revision =====
    case 'restore_revision':
        $revId = (int)($_POST['revision_id'] ?? 0);
        if (!$revId) jsonResponse(['error' => 'Missing revision ID'], 400);
        if (hp_restore_revision($revId)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to restore revision'], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Unknown action: ' . e($action)], 400);
}

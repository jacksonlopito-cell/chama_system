<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
header('Content-Type: application/json');

$field = $_GET['field'] ?? '';
$query = trim($_GET['q'] ?? '');
$validFields = ['occupation', 'employer', 'emergency_relation'];

if (!in_array($field, $validFields) || strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$db = getConnection();
$like = $query . '%';

// First check location_suggestions table
$stmt = $db->prepare("SELECT DISTINCT value FROM location_suggestions WHERE field_name=? AND value LIKE ? AND (group_code=? OR group_code IS NULL) ORDER BY usage_count DESC, value ASC LIMIT 20");
$stmt->execute([$field, $like, $_SESSION['group_code']]);
$suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Also check members table for existing values
$col = $field === 'emergency_relation' ? 'emergency_relation' : $field;
$stmt2 = $db->prepare("SELECT DISTINCT $col FROM members WHERE $col LIKE ? AND group_code=? AND $col IS NOT NULL AND $col != '' ORDER BY $col ASC LIMIT 20");
$stmt2->execute([$like, $_SESSION['group_code']]);
$existing = $stmt2->fetchAll(PDO::FETCH_COLUMN);

$merged = array_unique(array_merge($suggestions, $existing));
sort($merged);

echo json_encode(array_slice($merged, 0, 20));

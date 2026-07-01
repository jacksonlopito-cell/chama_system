<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$subCountyId = (int)($_GET['sub_county_id'] ?? 0);
if ($subCountyId < 1) {
    echo json_encode([]);
    exit;
}

$db = getConnection();
$stmt = $db->prepare("SELECT id, name FROM wards WHERE sub_county_id=? ORDER BY name");
$stmt->execute([$subCountyId]);
echo json_encode($stmt->fetchAll());

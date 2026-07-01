<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$countyId = (int)($_GET['county_id'] ?? 0);
if ($countyId < 1) {
    echo json_encode([]);
    exit;
}

$db = getConnection();
$stmt = $db->prepare("SELECT id, name FROM sub_counties WHERE county_id=? ORDER BY name");
$stmt->execute([$countyId]);
echo json_encode($stmt->fetchAll());

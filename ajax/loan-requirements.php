<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
header('Content-Type: application/json');
$db = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'product_reqs':
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) { echo json_encode(['requirements' => []]); exit; }
        $stmt = $db->prepare("
            SELECT pr.requirement_id, pr.is_required, rs.rule_value
            FROM loan_product_requirements pr
            LEFT JOIN loan_product_rule_settings rs ON rs.product_id=pr.product_id AND rs.requirement_id=pr.requirement_id
            WHERE pr.product_id=?
            ORDER BY pr.sort_order
        ");
        $stmt->execute([$productId]);
        echo json_encode(['requirements' => $stmt->fetchAll()]);
        break;

    case 'req_options':
        $reqId = (int)($_GET['requirement_id'] ?? 0);
        if (!$reqId) { echo json_encode(['options' => []]); exit; }
        $stmt = $db->prepare("SELECT option_value FROM loan_requirement_options WHERE requirement_id=? ORDER BY sort_order");
        $stmt->execute([$reqId]);
        echo json_encode(['options' => array_column($stmt->fetchAll(), 'option_value')]);
        break;

    case 'product_requirements_for_form':
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) { echo json_encode(['requirements' => []]); exit; }

        $stmt = $db->prepare("
            SELECT r.id, r.label, r.input_type, r.is_required, r.rule_key, r.description,
                   c.name as category_name, c.slug as category_slug,
                   COALESCE(pr.is_required, r.is_required) as product_required,
                   pr.sort_order, rs.rule_value
            FROM loan_requirements r
            JOIN loan_product_requirements pr ON pr.requirement_id = r.id
            LEFT JOIN loan_requirement_categories c ON r.category_id = c.id
            LEFT JOIN loan_product_rule_settings rs ON rs.product_id=pr.product_id AND rs.requirement_id=r.id
            WHERE pr.product_id = ? AND r.is_active = 1
            ORDER BY c.sort_order, pr.sort_order, r.sort_order
        ");
        $stmt->execute([$productId]);
        $requirements = $stmt->fetchAll();

        // Fetch options for dropdown/multi_select
        $reqIds = array_column($requirements, 'id');
        if (!empty($reqIds)) {
            $in = implode(',', array_fill(0, count($reqIds), '?'));
            $optStmt = $db->prepare("SELECT requirement_id, option_value FROM loan_requirement_options WHERE requirement_id IN ($in) ORDER BY sort_order");
            $optStmt->execute($reqIds);
            $allOptions = $optStmt->fetchAll();
            $optionsByReq = [];
            foreach ($allOptions as $o) { $optionsByReq[$o['requirement_id']][] = $o['option_value']; }
            foreach ($requirements as &$r) {
                $r['options'] = $optionsByReq[$r['id']] ?? [];
            }
        }

        echo json_encode(['requirements' => $requirements]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}

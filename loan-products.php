<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('edit_loans');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Loan Products';
$tab = $_GET['tab'] ?? 'products';

// ─── Save product ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    requirePermission('edit_loans');
    verifyCsrf();

    $name = trim($_POST['name'] ?? '');
    $interestRate = (float)($_POST['interest_rate'] ?? 0);
    $interestType = $_POST['interest_type'] ?? 'reducing';
    $minAmount = (float)($_POST['min_amount'] ?? 0);
    $maxAmount = $_POST['max_amount'] !== '' ? (float)$_POST['max_amount'] : null;
    $minTenure = (int)($_POST['min_tenure'] ?? 1);
    $maxTenure = (int)($_POST['max_tenure'] ?? 0);
    $latePenalty = (float)($_POST['late_penalty'] ?? 0);
    $gracePeriod = (int)($_POST['grace_period'] ?? 0);
    $processingFee = (float)($_POST['processing_fee'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    $errors = [];
    if ($name === '') { $errors[] = 'Product name is required.'; }
    if ($interestRate <= 0) { $errors[] = 'Interest rate must be greater than 0.'; }
    if ($interestRate > 100) { $errors[] = 'Interest rate cannot exceed 100%.'; }
    if ($maxTenure <= 0) { $errors[] = 'Max tenure is required.'; }
    if ($minTenure > $maxTenure) { $errors[] = 'Min tenure cannot exceed max tenure.'; }
    if ($maxAmount !== null && $maxAmount <= 0) { $errors[] = 'Max amount must be greater than 0.'; }
    if ($minAmount > ($maxAmount ?? PHP_FLOAT_MAX)) { $errors[] = 'Min amount cannot exceed max amount.'; }
    if ($latePenalty < 0 || $latePenalty > 100) { $errors[] = 'Late penalty must be between 0 and 100.'; }
    if ($processingFee < 0 || $processingFee > 100) { $errors[] = 'Processing fee must be between 0 and 100.'; }
    if ($gracePeriod < 0) { $errors[] = 'Grace period cannot be negative.'; }

    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        redirect('loan-products.php');
    }

    try {
        $id = (int)($_POST['product_id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("UPDATE loan_products SET name=?, interest_rate=?, interest_type=?, max_amount=?, min_amount=?, max_tenure=?, min_tenure=?, late_penalty=?, grace_period=?, processing_fee=?, description=? WHERE id=?");
            $stmt->execute([$name, $interestRate, $interestType, $maxAmount, $minAmount, $maxTenure, $minTenure, $latePenalty, $gracePeriod, $processingFee, $description, $id]);
            setFlash('success', 'Product updated successfully.');
        } else {
            $stmt = $db->prepare("INSERT INTO loan_products (name, interest_rate, interest_type, max_amount, min_amount, max_tenure, min_tenure, late_penalty, grace_period, processing_fee, description, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name, $interestRate, $interestType, $maxAmount, $minAmount, $maxTenure, $minTenure, $latePenalty, $gracePeriod, $processingFee, $description, 'active']);
            $id = $db->lastInsertId();
            setFlash('success', 'Product created successfully.');
        }

        // Sync product requirements
        $db->prepare("DELETE FROM loan_product_requirements WHERE product_id=?")->execute([$id]);
        $db->prepare("DELETE FROM loan_product_rule_settings WHERE product_id=?")->execute([$id]);
        $reqIds = $_POST['requirement_ids'] ?? [];
        if (is_array($reqIds)) {
            $insert = $db->prepare("INSERT INTO loan_product_requirements (product_id, requirement_id, sort_order, is_required) VALUES (?,?,?,?)");
            $ruleInsert = $db->prepare("INSERT INTO loan_product_rule_settings (product_id, requirement_id, rule_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE rule_value=VALUES(rule_value)");
            $ruleValues = $_POST['rule_value'] ?? [];
            foreach ($reqIds as $sort => $reqId) {
                $reqId = (int)$reqId;
                $isReq = in_array($reqId, ($_POST['req_required'] ?? [])) ? 1 : 0;
                $insert->execute([$id, $reqId, $sort, $isReq]);
                if (isset($ruleValues[$reqId]) && $ruleValues[$reqId] !== '') {
                    $ruleInsert->execute([$id, $reqId, trim($ruleValues[$reqId])]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Loan product save error: " . $e->getMessage());
        setFlash('danger', 'An error occurred while saving the product. Please try again.');
    }
    redirect('loan-products.php');
}

// ─── Save / toggle / delete requirement ───
if (isset($_POST['save_requirement'])) {
    verifyCsrf();
    $label = trim($_POST['label'] ?? '');
    $inputType = $_POST['input_type'] ?? 'text';
    $isRequired = isset($_POST['is_required']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $categoryId = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $ruleKey = trim($_POST['rule_key'] ?? '');
    $ruleKey = $ruleKey !== '' ? $ruleKey : null;
    $description = trim($_POST['description'] ?? '');
    $description = $description !== '' ? $description : null;
    $reqId = (int)($_POST['req_id'] ?? 0);
    if ($label === '') { setFlash('danger', 'Requirement label is required.'); redirect('loan-products.php?tab=requirements'); }

    try {
        if ($reqId) {
            $db->prepare("UPDATE loan_requirements SET label=?, input_type=?, is_required=?, sort_order=?, category_id=?, rule_key=?, description=? WHERE id=?")->execute([$label, $inputType, $isRequired, $sortOrder, $categoryId, $ruleKey, $description, $reqId]);
        } else {
            $db->prepare("INSERT INTO loan_requirements (label, input_type, is_required, sort_order, category_id, rule_key, description) VALUES (?,?,?,?,?,?,?)")->execute([$label, $inputType, $isRequired, $sortOrder, $categoryId, $ruleKey, $description]);
            $reqId = $db->lastInsertId();
        }

        // Sync options for dropdown/multi_select
        $db->prepare("DELETE FROM loan_requirement_options WHERE requirement_id=?")->execute([$reqId]);
        if (in_array($inputType, ['dropdown', 'multi_select'])) {
            $optionsText = trim($_POST['options'] ?? '');
            $lines = explode("\n", $optionsText);
            $optStmt = $db->prepare("INSERT INTO loan_requirement_options (requirement_id, option_value, sort_order) VALUES (?,?,?)");
            $sort = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $optStmt->execute([$reqId, $line, $sort++]);
                }
            }
        }
        setFlash('success', 'Requirement saved.');
    } catch (Exception $e) {
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    redirect('loan-products.php?tab=requirements');
}

if (isset($_GET['toggle_req']) && hasPermission('edit_loans')) {
    $id = (int)$_GET['toggle_req'];
    $current = $db->prepare("SELECT is_active FROM loan_requirements WHERE id=?");
    $current->execute([$id]);
    $val = $current->fetchColumn();
    if ($val !== false) {
        $db->prepare("UPDATE loan_requirements SET is_active=? WHERE id=?")->execute([$val ? 0 : 1, $id]);
        setFlash('success', 'Requirement ' . ($val ? 'disabled' : 'enabled') . '.');
    }
    redirect('loan-products.php?tab=requirements');
}

if (isset($_GET['delete_req']) && hasPermission('delete_loans')) {
    $token = $_GET['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        setFlash('danger', 'Invalid security token.');
        redirect('loan-products.php?tab=requirements');
    }
    $id = (int)$_GET['delete_req'];
    try {
        $db->prepare("DELETE FROM loan_requirements WHERE id=?")->execute([$id]);
        setFlash('success', 'Requirement deleted.');
    } catch (Exception $e) {
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    redirect('loan-products.php?tab=requirements');
}

// ─── Product toggle / delete ───
if (isset($_GET['toggle']) && hasPermission('edit_loans')) {
    $id = (int)$_GET['toggle'];
    $current = $db->prepare("SELECT status FROM loan_products WHERE id=?");
    $current->execute([$id]);
    $val = $current->fetchColumn();
    if ($val === false) { setFlash('danger', 'Product not found.'); }
    else {
        $new = $val === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE loan_products SET status=? WHERE id=?")->execute([$new, $id]);
        setFlash('success', 'Product ' . ($new === 'active' ? 'activated' : 'deactivated') . '.');
    }
    redirect('loan-products.php');
}

if (isset($_GET['delete']) && hasPermission('delete_loans')) {
    $token = $_GET['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        setFlash('danger', 'Invalid security token.');
        redirect('loan-products.php');
    }
    $id = (int)$_GET['delete'];
    try {
        $used = $db->prepare("SELECT COUNT(*) FROM loans WHERE product_id=?");
        $used->execute([$id]);
        if ((int)$used->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete: this product has active loans.');
        } else {
            $db->prepare("DELETE FROM loan_products WHERE id=?")->execute([$id]);
            setFlash('success', 'Product deleted.');
        }
    } catch (Exception $e) {
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    redirect('loan-products.php');
}

// ─── Data ───
$products = $db->query("SELECT * FROM loan_products ORDER BY created_at DESC")->fetchAll();
$categories = $db->query("SELECT * FROM loan_requirement_categories ORDER BY sort_order")->fetchAll();
$allRequirements = $db->query("SELECT r.*, c.name as category_name, c.slug as category_slug FROM loan_requirements r LEFT JOIN loan_requirement_categories c ON r.category_id=c.id ORDER BY c.sort_order, r.sort_order, r.label")->fetchAll();
$allReqOptions = $db->query("SELECT * FROM loan_requirement_options ORDER BY sort_order")->fetchAll();
$reqOptionsByReq = [];
foreach ($allReqOptions as $o) { $reqOptionsByReq[$o['requirement_id']][] = $o; }
$productRequirements = $db->query("SELECT * FROM loan_product_requirements ORDER BY sort_order")->fetchAll();
$prodReqsByProduct = [];
foreach ($productRequirements as $pr) { $prodReqsByProduct[$pr['product_id']][] = $pr; }
$productRuleSettings = $db->query("SELECT * FROM loan_product_rule_settings")->fetchAll();
$ruleSettingsByProduct = [];
foreach ($productRuleSettings as $rs) { $ruleSettingsByProduct[$rs['product_id']][$rs['requirement_id']] = $rs; }
$reqsByCategory = [];
foreach ($allRequirements as $r) { $reqsByCategory[$r['category_id'] ?? 0][] = $r; }

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Loan Products</h4><p>Define loan products, terms, and dynamic requirements</p></div>
        <div class="d-flex gap-2">
            <?php if ($tab === 'products'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal"><i class="fas fa-plus me-1"></i>Add Product</button>
            <?php endif; ?>
            <?php if ($tab === 'requirements'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requirementModal"><i class="fas fa-plus me-1"></i>Add Requirement</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab === 'products' ? 'active' : '' ?>" href="?tab=products">Products</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'requirements' ? 'active' : '' ?>" href="?tab=requirements">Requirements</a></li>
    </ul>

    <?php displayFlash(); ?>

    <?php if ($tab === 'products'): ?>
    <!-- ─── PRODUCTS TAB ─── -->
    <div class="row g-3">
        <?php foreach ($products as $p):
            $pReqCount = count($prodReqsByProduct[$p['id']] ?? []);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 <?= $p['status'] === 'active' ? 'border-primary' : 'opacity-75' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="mb-0"><?= e($p['name']) ?></h5>
                        <?= statusBadge($p['status']) ?>
                    </div>
                    <p class="text-muted small mb-2"><?= e(truncate($p['description'] ?? '', 100)) ?></p>
                    <div class="row g-1 small">
                        <div class="col-6"><span class="text-muted">Interest:</span> <strong><?= e($p['interest_rate']) ?>%</strong> (<?= e($p['interest_type']) ?>)</div>
                        <div class="col-6"><span class="text-muted">Range:</span> <strong><?= formatCurrency($p['min_amount']) ?> - <?= $p['max_amount'] ? formatCurrency($p['max_amount']) : 'Unlimited' ?></strong></div>
                        <div class="col-6"><span class="text-muted">Tenure:</span> <strong><?= (int)$p['min_tenure'] ?>-<?= (int)$p['max_tenure'] ?> mo</strong></div>
                        <div class="col-6"><span class="text-muted">Penalty:</span> <strong><?= e($p['late_penalty']) ?>%</strong></div>
                        <div class="col-12">
                            <span class="text-muted">Requirements:</span> <strong><?= $pReqCount ?> assigned</strong>
                            <?php if ($pReqCount > 0):
                                $catCounts = [];
                                foreach ($prodReqsByProduct[$p['id']] as $pr) {
                                    foreach ($allRequirements as $r) {
                                        if ($r['id'] == $pr['requirement_id'] && $r['category_name']) {
                                            $catCounts[$r['category_name']] = ($catCounts[$r['category_name']] ?? 0) + 1;
                                            break;
                                        }
                                    }
                                }
                            ?>
                            <div class="small text-muted mt-1"><?= implode(' | ', array_map(function($k,$v){return "$k: $v";}, array_keys($catCounts), $catCounts)) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-1">
                        <button class="btn btn-sm btn-outline-warning edit-product-btn"
                                data-id="<?= (int)$p['id'] ?>"
                                data-name="<?= e($p['name']) ?>"
                                data-rate="<?= e($p['interest_rate']) ?>"
                                data-type="<?= e($p['interest_type']) ?>"
                                data-max-amt="<?= e($p['max_amount']) ?>"
                                data-min-amt="<?= e($p['min_amount']) ?>"
                                data-max-ten="<?= (int)$p['max_tenure'] ?>"
                                data-min-ten="<?= (int)$p['min_tenure'] ?>"
                                data-penalty="<?= e($p['late_penalty']) ?>"
                                data-grace="<?= (int)$p['grace_period'] ?>"
                                data-fee="<?= e($p['processing_fee']) ?>"
                                data-desc="<?= e($p['description'] ?? '') ?>"
                                title="Edit Product">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?toggle=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-<?= $p['status'] === 'active' ? 'danger' : 'success' ?>" title="<?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                            <i class="fas fa-<?= $p['status'] === 'active' ? 'times' : 'check' ?>"></i>
                        </a>
                        <?php if (hasPermission('delete_loans')): ?>
                        <button class="btn btn-sm btn-outline-danger delete-product-btn"
                                data-id="<?= (int)$p['id'] ?>"
                                data-name="<?= e($p['name']) ?>"
                                title="Delete Product">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
        <div class="col-12">
            <div class="text-center py-5 text-muted"><i class="fas fa-box-open fa-3x mb-3"></i><p>No loan products defined yet.</p></div>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'requirements'): ?>
    <!-- ─── REQUIREMENTS TAB ─── -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Sort</th>
                            <th>Category</th>
                            <th>Label</th>
                            <th>Input Type</th>
                            <th>Required</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $lastCat = null;
                        foreach ($allRequirements as $r):
                            $hasOptions = in_array($r['input_type'], ['dropdown', 'multi_select']);
                            $options = $reqOptionsByReq[$r['id']] ?? [];
                            $showCat = $r['category_name'] !== $lastCat;
                            $lastCat = $r['category_name'];
                        ?>
                        <tr>
                            <td><?= $r['sort_order'] ?></td>
                            <td><?php if ($showCat): ?><span class="badge bg-secondary"><?= e($r['category_name'] ?? 'Uncategorized') ?></span><?php endif; ?></td>
                            <td class="fw-medium"><?= e($r['label']) ?>
                                <?php if ($r['rule_key']): ?><small class="d-block text-muted">key: <?= e($r['rule_key']) ?></small><?php endif; ?>
                            </td>
                            <td><span class="badge bg-info"><?= e($r['input_type']) ?></span>
                                <?php if ($hasOptions && !empty($options)): ?>
                                <small class="d-block text-muted"><?= implode(', ', array_column($options, 'option_value')) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['is_required'] ? '<span class="badge bg-danger">Required</span>' : '<span class="badge bg-secondary">Optional</span>' ?></td>
                            <td><?= $r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>' ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning btn-icon edit-req-btn"
                                    data-id="<?= $r['id'] ?>"
                                    data-label="<?= e($r['label']) ?>"
                                    data-type="<?= $r['input_type'] ?>"
                                    data-required="<?= $r['is_required'] ?>"
                                    data-sort="<?= $r['sort_order'] ?>"
                                    data-category="<?= (int)($r['category_id'] ?? 0) ?>"
                                    data-rule-key="<?= e($r['rule_key'] ?? '') ?>"
                                    data-desc="<?= e($r['description'] ?? '') ?>"
                                    title="Edit"><i class="fas fa-edit"></i></button>
                                <a href="?tab=requirements&toggle_req=<?= $r['id'] ?>" class="btn btn-sm btn-outline-<?= $r['is_active'] ? 'danger' : 'success' ?> btn-icon" title="<?= $r['is_active'] ? 'Disable' : 'Enable' ?>">
                                    <i class="fas fa-<?= $r['is_active'] ? 'times' : 'check' ?>"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger btn-icon delete-req-btn"
                                    data-id="<?= $r['id'] ?>"
                                    data-label="<?= e($r['label']) ?>"
                                    title="Delete"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allRequirements)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No requirements defined. Click "Add Requirement" to create one.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ─── PRODUCT MODAL ─── -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">Add Loan Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" id="productForm">
                <?= csrfField() ?>
                <input type="hidden" name="product_id" id="productId" value="0">
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="productFormTabs">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#prodBasic">Basic</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#prodRequirements">Requirements</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="prodBasic">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="prodName" class="form-control" required maxlength="200">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Interest Type</label>
                                    <select name="interest_type" id="intType" class="form-select">
                                        <option value="reducing">Reducing Balance</option>
                                        <option value="flat">Flat Rate</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Interest Rate (%) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0.01" max="100" name="interest_rate" id="intRate" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Min Amount</label>
                                    <input type="number" step="0.01" min="0" name="min_amount" id="minAmt" class="form-control" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Max Amount</label>
                                    <input type="number" step="0.01" min="0" name="max_amount" id="maxAmt" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Processing Fee (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="processing_fee" id="procFee" class="form-control" value="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Min Tenure (months)</label>
                                    <input type="number" min="1" name="min_tenure" id="minTen" class="form-control" value="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Max Tenure <span class="text-danger">*</span></label>
                                    <input type="number" min="1" name="max_tenure" id="maxTen" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Late Penalty (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="late_penalty" id="latePen" class="form-control" value="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Grace Period (days)</label>
                                    <input type="number" min="0" name="grace_period" id="gracePer" class="form-control" value="0">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="prodDesc" class="form-control" rows="2" maxlength="2000"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="prodRequirements">
                            <p class="text-muted small">Select requirements grouped by category. Check to enable, then configure values.</p>
                            <div id="reqChecklist" class="accordion accordion-flush" id="reqCatAccordion">
                                <?php
                                $catIdx = 0;
                                foreach ($categories as $cat):
                                    $catReqs = $reqsByCategory[$cat['id']] ?? [];
                                    if (empty($catReqs)) continue;
                                    $catIdx++;
                                ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cat<?= $cat['id'] ?>"><?= e($cat['name']) ?> (<?= count($catReqs) ?>)</button></h2>
                                    <div id="cat<?= $cat['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#reqCatAccordion">
                                        <div class="accordion-body">
                                            <?php foreach ($catReqs as $r):
                                                $hasOptions = in_array($r['input_type'], ['dropdown', 'multi_select']);
                                            ?>
                                            <div class="border rounded p-2 mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <input class="form-check-input req-checkbox" type="checkbox" name="requirement_ids[]" value="<?= $r['id'] ?>" id="prodReq<?= $r['id'] ?>">
                                                    <label class="form-check-label fw-medium flex-grow-1" for="prodReq<?= $r['id'] ?>"><?= e($r['label'])?>
                                                        <small class="d-block text-muted"><?= e($r['input_type']) ?><?= $r['rule_key'] ? ' · ' . e($r['rule_key']) : '' ?></small>
                                                    </label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input req-required" type="checkbox" name="req_required[]" value="<?= $r['id'] ?>" id="prodReqReq<?= $r['id'] ?>" checked>
                                                        <label class="form-check-label small" for="prodReqReq<?= $r['id'] ?>">Required</label>
                                                    </div>
                                                </div>
                                                <?php if (in_array($r['input_type'], ['number', 'text'])): ?>
                                                <div class="ms-4 mt-1 rule-value-container" id="ruleValContainer<?= $r['id'] ?>" style="display:none;">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">Value</span>
                                                        <input type="text" class="form-control rule-setting-input" name="rule_value[<?= $r['id'] ?>]" id="ruleVal<?= $r['id'] ?>" placeholder="Configure value for <?= e($r['label']) ?>" disabled>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php
                                $uncatReqs = $reqsByCategory[0] ?? [];
                                if (!empty($uncatReqs)): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cat0">Uncategorized (<?= count($uncatReqs) ?>)</button></h2>
                                    <div id="cat0" class="accordion-collapse collapse" data-bs-parent="#reqCatAccordion">
                                        <div class="accordion-body">
                                            <?php foreach ($uncatReqs as $r): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input req-checkbox" type="checkbox" name="requirement_ids[]" value="<?= $r['id'] ?>" id="prodReq<?= $r['id'] ?>">
                                                <label class="form-check-label" for="prodReq<?= $r['id'] ?>"><?= e($r['label'])?></label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($allRequirements)): ?>
                            <p class="text-muted">No requirements defined. Switch to the Requirements tab to create some.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_product" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─── REQUIREMENT MODAL ─── -->
<div class="modal fade" id="requirementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="reqModalTitle">Add Requirement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="req_id" id="reqId" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="reqCategory" class="form-select">
                            <option value="">-- No Category --</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Label <span class="text-danger">*</span></label>
                        <input type="text" name="label" id="reqLabel" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Input Type</label>
                        <select name="input_type" id="reqInputType" class="form-select">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="textarea">Textarea</option>
                            <option value="checkbox">Checkbox (Yes/No)</option>
                            <option value="file">File Upload</option>
                            <option value="dropdown">Dropdown</option>
                            <option value="multi_select">Multi-Select</option>
                        </select>
                    </div>
                    <div class="mb-3" id="optionsContainer" style="display:none;">
                        <label class="form-label">Options (one per line)</label>
                        <textarea name="options" id="reqOptions" class="form-control" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="reqSort" class="form-control" value="0" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">System Rule Key <small class="text-muted">(leave empty for custom requirements)</small></label>
                        <input type="text" name="rule_key" id="reqRuleKey" class="form-control" placeholder="e.g. min_membership_period" maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="reqDesc" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_required" id="reqIsRequired" value="1" checked>
                        <label class="form-check-label" for="reqIsRequired">This requirement is mandatory</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_requirement" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Requirement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─── PRODUCT DELETE MODAL ─── -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteProductName"></strong>?</p>
                <p class="text-danger small mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- ─── REQUIREMENT DELETE MODAL ─── -->
<div class="modal fade" id="deleteReqModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Are you sure you want to delete requirement <strong id="deleteReqName"></strong>?</p>
                <p class="text-danger small mb-0">This will also remove it from all products.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteReqConfirmBtn" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</a>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
$(document).ready(function() {
    // ─── Product Edit ───
    $('.edit-product-btn').on('click', function() {
        var btn = $(this);
        $('#modalTitle').text('Edit Loan Product');
        $('#productId').val(btn.data('id'));
        $('#prodName').val(btn.data('name'));
        $('#intRate').val(btn.data('rate'));
        $('#intType').val(btn.data('type'));
        $('#maxAmt').val(btn.data('max-amt') || '');
        $('#minAmt').val(btn.data('min-amt'));
        $('#maxTen').val(btn.data('max-ten'));
        $('#minTen').val(btn.data('min-ten'));
        $('#latePen').val(btn.data('penalty'));
        $('#gracePer').val(btn.data('grace'));
        $('#procFee').val(btn.data('fee'));
        $('#prodDesc').val(btn.data('desc'));
        // Load assigned requirements + rule settings
        var pid = btn.data('id');
        $('#reqChecklist .req-checkbox').prop('checked', false);
        $('#reqChecklist .req-required').prop('checked', true);
        $('.rule-value-container').hide().find('.rule-setting-input').prop('disabled', true).val('');
        if (pid) {
            $.get('ajax/loan-requirements.php?action=product_reqs&product_id=' + pid, function(data) {
                if (data.requirements) {
                    data.requirements.forEach(function(r) {
                        $('#prodReq' + r.requirement_id).prop('checked', true);
                        $('#ruleValContainer' + r.requirement_id).show().find('.rule-setting-input').prop('disabled', false);
                        if (r.rule_value) $('#ruleVal' + r.requirement_id).val(r.rule_value);
                        if (!r.is_required) $('#prodReqReq' + r.requirement_id).prop('checked', false);
                    });
                }
            });
        }
        new bootstrap.Modal(document.getElementById('productModal')).show();
    });

    // Show/hide rule value container when a requirement checkbox is toggled
    $(document).on('change', '.req-checkbox', function() {
        var id = $(this).val();
        var container = $('#ruleValContainer' + id);
        if (container.length) {
            if ($(this).is(':checked')) {
                container.show().find('.rule-setting-input').prop('disabled', false);
            } else {
                container.hide().find('.rule-setting-input').prop('disabled', true).val('');
            }
        }
    });

    $('#productModal').on('hidden.bs.modal', function() {
        if ($('#productId').val() === '0') $('#modalTitle').text('Add Loan Product');
    });

    // ─── Product Delete ───
    $('.delete-product-btn').on('click', function() {
        var btn = $(this);
        $('#deleteProductName').text(btn.data('name'));
        $('#deleteConfirmBtn').attr('href', '?delete=' + btn.data('id') + '&csrf_token=' + $('#productForm input[name="csrf_token"]').val());
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });

    // ─── Product Form Validation ───
    $('#productForm').on('submit', function(e) {
        var minAmt = parseFloat($('#minAmt').val()) || 0;
        var maxAmt = $('#maxAmt').val() ? parseFloat($('#maxAmt').val()) : null;
        var minTen = parseInt($('#minTen').val()) || 1;
        var maxTen = parseInt($('#maxTen').val()) || 0;
        var rate = parseFloat($('#intRate').val()) || 0;
        if (rate <= 0) { alert('Interest rate must be greater than 0.'); e.preventDefault(); return; }
        if (rate > 100) { alert('Interest rate cannot exceed 100%.'); e.preventDefault(); return; }
        if (maxTen <= 0) { alert('Max tenure is required.'); e.preventDefault(); return; }
        if (minTen > maxTen) { alert('Min tenure cannot exceed max tenure.'); e.preventDefault(); return; }
        if (maxAmt !== null && maxAmt <= 0) { alert('Max amount must be greater than 0.'); e.preventDefault(); return; }
        if (minAmt > (maxAmt || Infinity)) { alert('Min amount cannot exceed max amount.'); e.preventDefault(); return; }
    });

    // ─── Requirement Edit ───
    $('.edit-req-btn').on('click', function() {
        var btn = $(this);
        $('#reqModalTitle').text('Edit Requirement');
        $('#reqId').val(btn.data('id'));
        $('#reqLabel').val(btn.data('label'));
        $('#reqInputType').val(btn.data('type')).trigger('change');
        $('#reqSort').val(btn.data('sort'));
        $('#reqIsRequired').prop('checked', btn.data('required') == 1);
        $('#reqCategory').val(btn.data('category') || '');
        $('#reqRuleKey').val(btn.data('rule-key') || '');
        $('#reqDesc').val(btn.data('desc') || '');
        // Load options
        var rid = btn.data('id');
        $('#reqOptions').val('');
        if (rid) {
            $.get('ajax/loan-requirements.php?action=req_options&requirement_id=' + rid, function(data) {
                if (data.options) $('#reqOptions').val(data.options.join('\\n'));
            });
        }
        new bootstrap.Modal(document.getElementById('requirementModal')).show();
    });

    // ─── Requirement type toggle ───
    $('#reqInputType').on('change', function() {
        var val = $(this).val();
        $('#optionsContainer').toggle(val === 'dropdown' || val === 'multi_select');
    });

    $('#requirementModal').on('hidden.bs.modal', function() {
        if ($('#reqId').val() === '0') {
            $('#reqModalTitle').text('Add Requirement');
            $('#reqLabel').val('');
            $('#reqInputType').val('text').trigger('change');
            $('#reqSort').val(0);
            $('#reqIsRequired').prop('checked', true);
            $('#reqCategory').val('');
            $('#reqRuleKey').val('');
            $('#reqDesc').val('');
            $('#reqOptions').val('');
        }
    });

    // ─── Requirement Delete ───
    $('.delete-req-btn').on('click', function() {
        var btn = $(this);
        $('#deleteReqName').text(btn.data('label'));
        $('#deleteReqConfirmBtn').attr('href', '?tab=requirements&delete_req=' + btn.data('id') + '&csrf_token=' + $('#productForm input[name="csrf_token"]').val());
        new bootstrap.Modal(document.getElementById('deleteReqModal')).show();
    });

});
</script>
JS;
include __DIR__ . '/views/layouts/footer.php'; ?>

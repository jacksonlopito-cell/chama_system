<?php
/**
 * Loan Applications Management
 */
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_loans');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Loan Applications';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Save new application
    if (isset($_POST['save_application'])) {
        requirePermission('create_loans');

        $memberId = (int)$_POST['member_id'];
        $productId = (int)$_POST['product_id'];
        $amount = (float)$_POST['amount'];
        $tenureMonths = (int)$_POST['tenure_months'];
        $purpose = trim($_POST['purpose'] ?? '');
        $guarantors = $_POST['guarantors'] ?? [];
        $collateralNames = $_POST['collateral_name'] ?? [];
        $collateralValues = $_POST['collateral_value'] ?? [];
        $collateralDesc = $_POST['collateral_description'] ?? [];

        $errors = [];
        if (!$memberId) $errors[] = 'Member is required';
        if (!$productId) $errors[] = 'Loan product is required';
        if ($amount <= 0) $errors[] = 'Amount must be greater than zero';
        if ($tenureMonths <= 0) $errors[] = 'Tenure is required';

        // Validate required requirements
        $reqLabels = $_POST['req_label'] ?? [];
        $reqValues = $_POST['req_value'] ?? [];
        if ($productId && !empty($reqLabels)) {
            $prodReqStmt = $db->prepare("SELECT r.id, r.label, r.rule_key FROM loan_requirements r JOIN loan_product_requirements pr ON pr.requirement_id = r.id WHERE pr.product_id = ? AND pr.is_required = 1 AND r.is_active = 1");
            $prodReqStmt->execute([$productId]);
            foreach ($prodReqStmt->fetchAll() as $req) {
                $val = $reqValues[$req['id']] ?? '';
                if (is_array($val)) $val = implode('', $val);
                if (trim($val) === '') {
                    $errors[] = 'Requirement "' . $req['label'] . '" is mandatory.';
                }
            }
        }

        // Validate system rules server-side
        if ($productId && $memberId && empty($errors)) {
            $sysReqs = $db->prepare("SELECT r.rule_key, rs.rule_value FROM loan_requirements r JOIN loan_product_requirements pr ON pr.requirement_id=r.id LEFT JOIN loan_product_rule_settings rs ON rs.product_id=pr.product_id AND rs.requirement_id=r.id WHERE pr.product_id=? AND r.rule_key IS NOT NULL AND r.is_active=1 AND pr.is_required=1");
            $sysReqs->execute([$productId]);
            foreach ($sysReqs->fetchAll() as $sr) {
                $ruleVal = $sr['rule_value'];
                switch ($sr['rule_key']) {
                    case 'active_membership':
                        $mem = $db->prepare("SELECT status FROM members WHERE id=?");
                        $mem->execute([$memberId]);
                        if ($mem->fetchColumn() !== 'active') $errors[] = 'Active membership is required to apply for a loan.';
                        break;

                    case 'min_membership_period':
                        $mem = $db->prepare("SELECT DATEDIFF(CURDATE(), date_joined)/30 AS months FROM members WHERE id=?");
                        $mem->execute([$memberId]);
                        $months = (float)$mem->fetchColumn();
                        if ($months < (float)$ruleVal) $errors[] = "You must be a member for at least $ruleVal months (currently " . round($months,1) . " months).";
                        break;

                    case 'min_shares':
                        $shr = $db->prepare("SELECT COALESCE(SUM(shares_count),0) FROM share_purchases WHERE member_id=? AND status='active'");
                        $shr->execute([$memberId]);
                        $totalShares = (float)$shr->fetchColumn();
                        if ($totalShares < (float)$ruleVal) $errors[] = "Minimum $ruleVal shares required (you have $totalShares).";
                        break;

                    case 'min_contributions':
                        $con = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE member_id=?");
                        $con->execute([$memberId]);
                        $totalCon = (float)$con->fetchColumn();
                        if ($totalCon < (float)$ruleVal) $errors[] = "Minimum KES " . number_format($ruleVal,0) . " in contributions required (you have KES " . number_format($totalCon,0) . ").";
                        break;

                    case 'prev_loan_clearance':
                        $ln = $db->prepare("SELECT COUNT(*) FROM loans WHERE member_id=? AND status IN ('active','disbursed') AND balance > 0");
                        $ln->execute([$memberId]);
                        if ((int)$ln->fetchColumn() > 0) $errors[] = 'You must clear all outstanding loans before applying for a new one.';
                        break;

                    case 'collateral_required':
                        $hasCollateral = false;
                        foreach (($_POST['collateral_name'] ?? []) as $cn) {
                            if (trim($cn) !== '') { $hasCollateral = true; break; }
                        }
                        if (!$hasCollateral) $errors[] = 'Collateral is required for this product.';
                        break;

                    case 'min_guarantors':
                        $gCount = 0;
                        foreach (($_POST['guarantors'] ?? []) as $g) {
                            if ((int)$g > 0) $gCount++;
                        }
                        $minG = (int)$ruleVal;
                        if ($gCount < $minG) $errors[] = "Minimum $minG guarantor(s) required (you provided $gCount).";
                        break;
                }
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Fetch product details
                $prodStmt = $db->prepare("SELECT * FROM loan_products WHERE id = ? AND status = 'active'");
                $prodStmt->execute([$productId]);
                $product = $prodStmt->fetch();

                if (!$product) {
                    throw new Exception('Invalid loan product selected');
                }

                $loanNo = generateLoanNo();
                $interestRate = $product['interest_rate'];
                $interestType = $product['interest_type'];
                $processingFee = $product['processing_fee'];
                $feeAmount = ($processingFee / 100) * $amount;

                $stmt = $db->prepare("
                    INSERT INTO loans (member_id, product_id, loan_no, amount, interest_rate, interest_type, tenure_months, processing_fee, purpose, application_date, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                ");
                $stmt->execute([
                    $memberId, $productId, $loanNo, $amount, $interestRate, $interestType,
                    $tenureMonths, $feeAmount, $purpose, date('Y-m-d'), $_SESSION['user_id']
                ]);
                $loanId = $db->lastInsertId();

                // Insert guarantors
                if (!empty($guarantors)) {
                    $gStmt = $db->prepare("INSERT INTO loan_guarantors (loan_id, member_id, amount, status) VALUES (?, ?, ?, 'pending')");
                    foreach ($guarantors as $gIndex => $gMemberId) {
                        $gAmount = (float)($_POST['guarantor_amount'][$gIndex] ?? 0);
                        if ($gMemberId > 0) {
                            $gStmt->execute([$loanId, (int)$gMemberId, $gAmount > 0 ? $gAmount : $amount]);
                        }
                    }
                }

                // Insert collateral
                if (!empty($collateralNames)) {
                    $cStmt = $db->prepare("INSERT INTO loan_collateral (loan_id, name, description, estimated_value) VALUES (?, ?, ?, ?)");
                    foreach ($collateralNames as $cIndex => $cName) {
                        $cName = trim($cName);
                        if (!empty($cName)) {
                            $cValue = (float)($collateralValues[$cIndex] ?? 0);
                            $cDesc = trim($collateralDesc[$cIndex] ?? '');
                            $cStmt->execute([$loanId, $cName, $cDesc, $cValue]);
                        }
                    }
                }

                // Insert requirement values
                $reqLabels = $_POST['req_label'] ?? [];
                $reqValues = $_POST['req_value'] ?? [];
                if (!empty($reqLabels)) {
                    $rStmt = $db->prepare("INSERT INTO loan_application_requirements (loan_id, requirement_id, label, value) VALUES (?, ?, ?, ?)");
                    foreach ($reqLabels as $reqId => $label) {
                        $value = $reqValues[$reqId] ?? '';
                        if (is_array($value)) $value = implode(', ', $value);
                        $value = trim($value);
                        if ($value !== '') {
                            $rStmt->execute([$loanId, $reqId, $label, $value]);
                        }
                    }
                }

                $db->commit();

                logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'loans', $loanId, null, ['loan_no' => $loanNo, 'amount' => $amount], 'Created loan application');
                setFlash('success', "Loan application created. Loan No: $loanNo");

            } catch (Exception $e) {
                $db->rollBack();
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        } else {
            setFlash('danger', implode('<br>', $errors));
        }
        redirect('loan-applications.php');
    }

    // Approve loan (step-wise workflow)
    if (isset($_POST['approve_loan'])) {
        requirePermission('approve_loans');

        $loanId = (int)$_POST['loan_id'];
        $stmt = $db->prepare("SELECT * FROM loans WHERE id = ?");
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();

        if (!$loan) {
            setFlash('danger', 'Loan not found');
            redirect('loan-applications.php');
        }

        $roleSlug = $_SESSION['role_slug'];
        $newStatus = null;

        switch ($loan['status']) {
            case 'pending':
                if ($roleSlug === 'secretary') {
                    $newStatus = 'secretary_approved';
                }
                break;
            case 'secretary_approved':
                if ($roleSlug === 'treasurer') {
                    $newStatus = 'treasurer_approved';
                }
                break;
            case 'treasurer_approved':
                if ($roleSlug === 'chairperson') {
                    $newStatus = 'chairperson_approved';
                }
                break;
            default:
                setFlash('warning', 'Loan cannot be approved in its current status');
                redirect('loan-applications.php?id=' . $loanId);
        }

        if (!$newStatus) {
            setFlash('danger', 'You do not have permission to approve at this stage');
            redirect('loan-applications.php?id=' . $loanId);
        }

        try {
            $db->beginTransaction();

            // Generate schedule on chairperson approval
            if ($newStatus === 'chairperson_approved') {
                $calcFn = $loan['interest_type'] === 'flat' ? 'calculateLoanFlatRate' : 'calculateLoanReducingBalance';
                $calcResult = $calcFn($loan['amount'], $loan['interest_rate'], $loan['tenure_months']);

                $firstPayDate = date('Y-m-d', strtotime('+1 month'));
                $maturityDate = date('Y-m-d', strtotime('+' . $loan['tenure_months'] . ' months'));

                $schedStmt = $db->prepare("
                    INSERT INTO loan_schedules (loan_id, installment_no, due_date, principal, interest, total, balance, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                foreach ($calcResult['schedule'] as $entry) {
                    $dueDate = date('Y-m-d', strtotime($firstPayDate . ' +' . ($entry['installment'] - 1) . ' months'));
                    $schedStmt->execute([
                        $loanId, $entry['installment'], $dueDate,
                        $entry['principal'], $entry['interest'], $entry['total'], $entry['balance']
                    ]);
                }

                $updateData = [
                    'total_interest'    => $calcResult['totalInterest'],
                    'total_amount'      => $calcResult['totalAmount'],
                    'first_payment_date' => $firstPayDate,
                    'maturity_date'     => $maturityDate,
                    'balance'           => $calcResult['totalAmount']
                ];
            } else {
                $updateData = [];
            }

            $sets = ['status = ?', 'approved_by = ?', 'approved_date = NOW()'];
            $params = [$newStatus, $_SESSION['user_id']];

            foreach ($updateData as $key => $value) {
                $sets[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $loanId;

            $db->prepare("UPDATE loans SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
            $db->commit();

            logAudit($_SESSION['user_id'], $_SESSION['username'], 'approve', 'loans', $loanId, ['status' => $loan['status']], ['status' => $newStatus], 'Loan approved - ' . $newStatus);
            setFlash('success', 'Loan approved successfully');
            createNotification($loan['created_by'], 'success', 'Loan Approved', 'Your application ' . $loan['loan_no'] . ' has been approved.', 'loan-applications.php?id=' . $loanId);

        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        redirect('loan-applications.php?id=' . $loanId);
    }

    // Disburse loan
    if (isset($_POST['disburse_loan'])) {
        requirePermission('disburse_loans');

        $loanId = (int)$_POST['loan_id'];
        $stmt = $db->prepare("SELECT * FROM loans WHERE id = ?");
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();

        if (!$loan || $loan['status'] !== 'chairperson_approved') {
            setFlash('danger', 'Loan cannot be disbursed in its current status');
            redirect('loan-applications.php?id=' . $loanId);
        }

        try {
            $db->prepare("UPDATE loans SET status = 'disbursed', disbursed_by = ?, disbursed_date = NOW(), disbursement_date = CURDATE() WHERE id = ?")
               ->execute([$_SESSION['user_id'], $loanId]);

            logAudit($_SESSION['user_id'], $_SESSION['username'], 'disburse', 'loans', $loanId, ['status' => $loan['status']], ['status' => 'disbursed'], 'Loan disbursed');
            setFlash('success', 'Loan disbursed successfully');
            createNotification($loan['created_by'], 'success', 'Loan Disbursed', 'Your loan ' . $loan['loan_no'] . ' has been disbursed.', 'loan-applications.php?id=' . $loanId);

        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        redirect('loan-applications.php?id=' . $loanId);
    }

    // Reject loan
    if (isset($_POST['reject_loan'])) {
        requirePermission('approve_loans');

        $loanId = (int)$_POST['loan_id'];
        $reason = trim($_POST['rejection_reason'] ?? '');

        if (empty($reason)) {
            setFlash('danger', 'Rejection reason is required');
            redirect('loan-applications.php?id=' . $loanId);
        }

        $stmt = $db->prepare("SELECT * FROM loans WHERE id = ?");
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();

        if (!$loan || in_array($loan['status'], ['disbursed', 'active', 'paid', 'rejected'])) {
            setFlash('danger', 'Loan cannot be rejected in its current status');
            redirect('loan-applications.php?id=' . $loanId);
        }

        try {
            $db->prepare("UPDATE loans SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_date = NOW() WHERE id = ?")
               ->execute([$reason, $_SESSION['user_id'], $loanId]);

            logAudit($_SESSION['user_id'], $_SESSION['username'], 'reject', 'loans', $loanId, ['status' => $loan['status']], ['status' => 'rejected', 'reason' => $reason], 'Loan rejected');
            setFlash('warning', 'Loan has been rejected');
            createNotification($loan['created_by'], 'danger', 'Loan Rejected', 'Your application ' . $loan['loan_no'] . ' has been rejected. Reason: ' . $reason, 'loan-applications.php?id=' . $loanId);

        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        redirect('loan-applications.php?id=' . $loanId);
    }
}

// Fetch single loan for view action
$loan = null;
$schedule = [];
$payments = [];
$guarantors = [];
$collateral = [];

if ($id && ($action === 'view' || isset($_POST['approve_loan']) || isset($_POST['disburse_loan']) || isset($_POST['reject_loan']))) {
    $stmt = $db->prepare("
        SELECT l.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.member_no, m.phone as member_phone,
               lp.name as product_name, lp.interest_type as product_interest_type,
               u.username as created_by_name
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN loan_products lp ON l.product_id = lp.id
        LEFT JOIN users u ON l.created_by = u.id
        WHERE l.id = ?
    ");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();

    if (!$loan) {
        setFlash('danger', 'Loan not found');
        redirect('loan-applications.php');
    }

    // Schedule
    $schedStmt = $db->prepare("SELECT * FROM loan_schedules WHERE loan_id = ? ORDER BY installment_no");
    $schedStmt->execute([$id]);
    $schedule = $schedStmt->fetchAll();

    // Payments
    $payStmt = $db->prepare("
        SELECT lp.*, u.username as recorded_by_name
        FROM loan_payments lp
        LEFT JOIN users u ON lp.recorded_by = u.id
        WHERE lp.loan_id = ? ORDER BY lp.payment_date DESC
    ");
    $payStmt->execute([$id]);
    $payments = $payStmt->fetchAll();

    // Guarantors
    $gStmt = $db->prepare("
        SELECT lg.*, CONCAT(m.first_name, ' ', m.last_name) as guarantor_name, m.member_no as guarantor_no
        FROM loan_guarantors lg
        JOIN members m ON lg.member_id = m.id
        WHERE lg.loan_id = ?
    ");
    $gStmt->execute([$id]);
    $guarantors = $gStmt->fetchAll();

    // Collateral
    $cStmt = $db->prepare("SELECT * FROM loan_collateral WHERE loan_id = ?");
    $cStmt->execute([$id]);
    $collateral = $cStmt->fetchAll();

    // Requirements
    $reqStmt = $db->prepare("SELECT * FROM loan_application_requirements WHERE loan_id = ? ORDER BY id");
    $reqStmt->execute([$id]);
    $loanRequirements = $reqStmt->fetchAll();
}

// List view data
$page = max(1, (int)($_GET['page'] ?? 1));
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = "WHERE m.group_code=?";
$params = [$_SESSION['group_code']];

if ($statusFilter) {
    $where .= " AND l.status = ?";
    $params[] = $statusFilter;
}
if ($search) {
    $where .= " AND (l.loan_no LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
}

$total = $db->prepare("SELECT COUNT(*) FROM loans l JOIN members m ON l.member_id = m.id $where");
$total->execute($params);
$pagination = paginate($total->fetchColumn(), $page);

$applications = $db->prepare("
    SELECT l.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.member_no,
           lp.name as product_name
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN loan_products lp ON l.product_id = lp.id
    $where ORDER BY l.created_at DESC LIMIT ? OFFSET ?
");
$applications->execute(array_merge($params, [$pagination['perPage'], $pagination['offset']]));
$applicationsList = $applications->fetchAll();

// Data for add form
$roleSlug = $_SESSION['role_slug'] ?? '';
if ($roleSlug === 'member') {
    $stmt = $db->prepare("SELECT id, first_name, last_name, member_no FROM members WHERE id=? AND status='active'");
    $stmt->execute([$_SESSION['member_id'] ?? 0]);
    $members = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, first_name, last_name, member_no FROM members WHERE group_code=? AND status='active' ORDER BY first_name");
    $stmt->execute([$_SESSION['group_code']]);
    $members = $stmt->fetchAll();
}
$products = $db->query("SELECT * FROM loan_products WHERE status = 'active' ORDER BY name")->fetchAll();

$roleSlug = $_SESSION['role_slug'];

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4>
                <?php if ($action === 'add'): ?>New Loan Application
                <?php elseif ($action === 'view'): ?>Loan Details
                <?php else: ?>Loan Applications
                <?php endif; ?>
            </h4>
            <p>
                <?php if ($action === 'list'): ?>Review and manage loan applications
                <?php elseif ($loan): ?><?= e($loan['loan_no']) ?> - <?= e($loan['member_name']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($action === 'list'): ?>
                <?php if (hasPermission('create_loans')): ?>
                    <a href="loan-applications.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>New Application
                    </a>
                <?php endif; ?>
            <?php elseif ($action === 'view'): ?>
                <a href="loan-applications.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php displayFlash(); ?>

    <?php if ($action === 'list'): ?>
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by loan no or member..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="secretary_approved" <?= $statusFilter === 'secretary_approved' ? 'selected' : '' ?>>Secretary Approved</option>
                        <option value="treasurer_approved" <?= $statusFilter === 'treasurer_approved' ? 'selected' : '' ?>>Treasurer Approved</option>
                        <option value="chairperson_approved" <?= $statusFilter === 'chairperson_approved' ? 'selected' : '' ?>>Chairperson Approved</option>
                        <option value="disbursed" <?= $statusFilter === 'disbursed' ? 'selected' : '' ?>>Disbursed</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="loan-applications.php" class="btn btn-outline-secondary w-100"><i class="fas fa-redo me-1"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Loan Applications Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Loan No</th>
                            <th>Member</th>
                            <th>Product</th>
                            <th>Amount</th>
                            <th>Tenure</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applicationsList)): ?>
                            <tr><td colspan="8" class="text-center py-4" style="color: var(--gray-500);">No loan applications found</td></tr>
                        <?php else: ?>
                            <?php foreach ($applicationsList as $app): ?>
                                <tr>
                                    <td class="fw-medium"><?= e($app['loan_no']) ?></td>
                                    <td>
                                        <a href="members.php?action=view&id=<?= $app['member_id'] ?>" class="text-decoration-none fw-medium">
                                            <?= e($app['member_name']) ?>
                                        </a>
                                        <br><small style="color: var(--gray-500);"><?= e($app['member_no']) ?></small>
                                    </td>
                                    <td><?= e($app['product_name']) ?></td>
                                    <td class="fw-semibold"><?= formatCurrency($app['amount']) ?></td>
                                    <td><?= $app['tenure_months'] ?> mo</td>
                                    <td style="font-size: 0.8125rem;"><?= formatDate($app['application_date']) ?></td>
                                    <td><?= statusBadge($app['status']) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="loan-applications.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="card-footer">
                <?= renderPagination($pagination, 'loan-applications.php?' . http_build_query(array_filter(['search' => $search, 'status' => $statusFilter])) . '&') ?>
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($action === 'add'): ?>
    <!-- New Application Form -->
    <div class="card">
        <div class="card-body">
            <form action="loan-applications.php?action=add" method="POST" enctype="multipart/form-data" data-validate>
                <?= csrfField() ?>

                <div class="row g-3">
                    <div class="col-12"><h6 class="border-bottom pb-2">Loan Details</h6></div>

                    <div class="col-md-4">
                        <label class="form-label">Member <span class="text-danger">*</span></label>
                        <select name="member_id" class="form-select searchable-select" required>
                            <option value="">Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['member_no']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Loan Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="productSelect" class="form-select searchable-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"
                                    data-min="<?= $p['min_amount'] ?>"
                                    data-max="<?= $p['max_amount'] ?>"
                                    data-min-tenure="<?= $p['min_tenure'] ?>"
                                    data-max-tenure="<?= $p['max_tenure'] ?>"
                                    data-rate="<?= $p['interest_rate'] ?>"
                                    data-type="<?= $p['interest_type'] ?>">
                                    <?= e($p['name']) ?> (<?= $p['interest_rate'] ?>% <?= e($p['interest_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="amountInput" class="form-control" required min="0.01">
                        <small id="amountHelp" class="text-muted"></small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tenure (Months) <span class="text-danger">*</span></label>
                        <input type="number" name="tenure_months" id="tenureInput" class="form-control" required min="1">
                        <small id="tenureHelp" class="text-muted"></small>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Purpose / Description</label>
                        <textarea name="purpose" class="form-control" rows="2" placeholder="State the purpose of this loan..."></textarea>
                    </div>

                    <!-- Guarantors -->
                    <div class="col-12">
                        <h6 class="border-bottom pb-2">Guarantors</h6>
                        <div id="guarantorsContainer">
                            <div class="row g-2 guarantor-row mb-2">
                                <div class="col-md-5">
                                    <select name="guarantors[]" class="form-select">
                                        <option value="">Select Guarantor</option>
                                        <?php foreach ($members as $m): ?>
                                            <option value="<?= $m['id'] ?>"><?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['member_no']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" step="0.01" name="guarantor_amount[]" class="form-control" placeholder="Guarantee amount" min="0">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-guarantor-btn" style="display:none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="addGuarantorBtn" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus me-1"></i>Add Guarantor
                        </button>
                    </div>

                    <!-- Collateral -->
                    <div class="col-12">
                        <h6 class="border-bottom pb-2">Collateral</h6>
                        <div id="collateralContainer">
                            <div class="row g-2 collateral-row mb-2">
                                <div class="col-md-4">
                                    <input type="text" name="collateral_name[]" class="form-control" placeholder="Collateral name">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" step="0.01" name="collateral_value[]" class="form-control" placeholder="Estimated value" min="0">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="collateral_description[]" class="form-control" placeholder="Description">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-collateral-btn" style="display:none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="addCollateralBtn" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus me-1"></i>Add Collateral
                        </button>
                    </div>
                </div>

                    <!-- Requirements (dynamically loaded via AJAX on product change) -->
                    <div class="col-12" id="requirementsSection" style="display:none;">
                        <h6 class="border-bottom pb-2">Loan Requirements</h6>
                        <div id="requirementsContainer"></div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" name="save_application" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Submit Application
                    </button>
                    <a href="loan-applications.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <?php elseif ($action === 'view' && $loan): ?>
    <!-- Loan Detail View -->
    <div class="row g-3">
        <!-- Loan Summary -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="mb-1"><?= e($loan['loan_no']) ?></h5>
                    <p class="text-muted small mb-2"><?= e($loan['product_name']) ?></p>
                    <?= statusBadge($loan['status']) ?>
                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Amount:</span>
                            <span class="fw-semibold"><?= formatCurrency($loan['amount']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Interest:</span>
                            <span><?= $loan['interest_rate'] ?>% (<?= e($loan['interest_type']) ?>)</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Tenure:</span>
                            <span><?= $loan['tenure_months'] ?> months</span>
                        </div>
                        <?php if ($loan['total_amount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Total Payable:</span>
                            <span class="fw-semibold"><?= formatCurrency($loan['total_amount']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Total Interest:</span>
                            <span class="fw-semibold"><?= formatCurrency($loan['total_interest']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($loan['balance'] > 0): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Balance:</span>
                            <span class="fw-semibold text-danger"><?= formatCurrency($loan['balance']) ?></span>
                        </div>
                        <?php endif; ?>
                        <hr>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Applied:</span>
                            <span><?= formatDate($loan['application_date']) ?></span>
                        </div>
                        <?php if ($loan['disbursement_date']): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Disbursed:</span>
                            <span><?= formatDate($loan['disbursement_date']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($loan['maturity_date']): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Matures:</span>
                            <span><?= formatDate($loan['maturity_date']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($loan['rejection_reason']): ?>
                        <hr>
                        <div class="mb-1">
                            <span class="text-muted">Rejection Reason:</span>
                            <p class="text-danger mb-0"><?= e($loan['rejection_reason']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Member Info -->
            <div class="card mt-3">
                <div class="card-header">Applicant</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Name:</span>
                        <a href="members.php?action=view&id=<?= $loan['member_id'] ?>" class="text-decoration-none fw-medium">
                            <?= e($loan['member_name']) ?>
                        </a>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Member No:</span>
                        <span><?= e($loan['member_no']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Phone:</span>
                        <span><?= e($loan['member_phone']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <?php if (in_array($loan['status'], ['pending', 'secretary_approved', 'treasurer_approved', 'chairperson_approved'])): ?>
            <div class="card mt-3">
                <div class="card-header">Actions</div>
                <div class="card-body d-flex flex-column gap-2">
                    <?php
                    $canApprove = false;
                    $canDisburse = false;
                    $canReject = false;

                    if ($loan['status'] === 'pending' && $roleSlug === 'secretary') {
                        $canApprove = true;
                        $canReject = true;
                    } elseif ($loan['status'] === 'secretary_approved' && $roleSlug === 'treasurer') {
                        $canApprove = true;
                        $canReject = true;
                    } elseif ($loan['status'] === 'treasurer_approved' && $roleSlug === 'chairperson') {
                        $canApprove = true;
                        $canReject = true;
                    }

                    if ($loan['status'] === 'chairperson_approved' && hasPermission('disburse_loans')) {
                        $canDisburse = true;
                    }
                    ?>

                    <?php if ($canApprove && hasPermission('approve_loans')): ?>
                        <form action="loan-applications.php?id=<?= $id ?>" method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="loan_id" value="<?= $id ?>">
                            <button type="submit" name="approve_loan" class="btn btn-success w-100"
                                onclick="return confirm('Approve this loan application?')">
                                <i class="fas fa-check me-1"></i>Approve
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canDisburse): ?>
                        <form action="loan-applications.php?id=<?= $id ?>" method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="loan_id" value="<?= $id ?>">
                            <button type="submit" name="disburse_loan" class="btn btn-primary w-100"
                                onclick="return confirm('Disburse this loan?')">
                                <i class="fas fa-hand-holding-usd me-1"></i>Disburse Loan
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canReject): ?>
                        <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="fas fa-times me-1"></i>Reject
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <!-- Loan Purpose -->
            <?php if (!empty($loan['purpose'])): ?>
            <div class="card mb-3">
                <div class="card-header">Purpose</div>
                <div class="card-body">
                    <p class="mb-0"><?= e($loan['purpose']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Requirements Submitted -->
            <?php if (!empty($loanRequirements)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-1"></i>Submitted Requirements
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($loanRequirements as $lr): ?>
                            <div class="col-md-6">
                                <strong><?= e($lr['label']) ?>:</strong>
                                <span><?= e($lr['value']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Guarantors -->
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-users me-1"></i>Guarantors
                </div>
                <div class="card-body p-0">
                    <?php if (empty($guarantors)): ?>
                        <div class="text-center py-3" style="color: var(--gray-500); font-size: 0.875rem;">No guarantors</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Member No</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($guarantors as $g): ?>
                                        <tr>
                                            <td><a href="members.php?action=view&id=<?= $g['member_id'] ?>" class="text-decoration-none"><?= e($g['guarantor_name']) ?></a></td>
                                            <td><?= e($g['guarantor_no']) ?></td>
                                            <td><?= formatCurrency($g['amount']) ?></td>
                                            <td><?= statusBadge($g['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Collateral -->
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-gavel me-1"></i>Collateral
                </div>
                <div class="card-body p-0">
                    <?php if (empty($collateral)): ?>
                        <div class="text-center py-3" style="color: var(--gray-500); font-size: 0.875rem;">No collateral</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Estimated Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($collateral as $c): ?>
                                        <tr>
                                            <td class="fw-medium"><?= e($c['name']) ?></td>
                                            <td><?= e($c['description'] ?? '-') ?></td>
                                            <td><?= formatCurrency($c['estimated_value']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Loan Schedule -->
            <?php if (!empty($schedule)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-calendar-alt me-1"></i>Repayment Schedule
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Total</th>
                                    <th>Balance</th>
                                    <th>Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedule as $s): ?>
                                    <tr>
                                        <td><?= $s['installment_no'] ?></td>
                                        <td><?= formatDate($s['due_date']) ?></td>
                                        <td><?= formatCurrency($s['principal']) ?></td>
                                        <td><?= formatCurrency($s['interest']) ?></td>
                                        <td class="fw-medium"><?= formatCurrency($s['total']) ?></td>
                                        <td><?= formatCurrency($s['balance']) ?></td>
                                        <td><?= formatCurrency($s['paid_amount']) ?></td>
                                        <td><?= statusBadge($s['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment History -->
            <?php if (!empty($payments)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-coins me-1"></i>Payment History
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Receipt</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Method</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td class="fw-medium"><?= e($p['receipt_no'] ?? '-') ?></td>
                                        <td><?= formatDate($p['payment_date']) ?></td>
                                        <td class="fw-semibold"><?= formatCurrency($p['amount']) ?></td>
                                        <td><?= formatCurrency($p['principal_amount']) ?></td>
                                        <td><?= formatCurrency($p['interest_amount']) ?></td>
                                        <td><?= e(ucfirst($p['payment_method'])) ?></td>
                                        <td style="font-size: 0.8125rem;"><?= e($p['recorded_by_name'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Loan Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="loan-applications.php?id=<?= $id ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="loan_id" value="<?= $id ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="Provide a clear reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reject_loan" class="btn btn-danger" onclick="return confirm('Reject this loan application?')">
                            <i class="fas fa-times me-1"></i>Reject Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$extraScripts = <<<EOT
<script>
(function() {
    // Product constraints
    const productSelect = document.getElementById('productSelect');
    const amountInput = document.getElementById('amountInput');
    const tenureInput = document.getElementById('tenureInput');
    const amountHelp = document.getElementById('amountHelp');
    const tenureHelp = document.getElementById('tenureHelp');

    if (productSelect) {
        productSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const reqSection = document.getElementById('requirementsSection');
            const reqContainer = document.getElementById('requirementsContainer');

            if (opt && opt.value) {
                const min = parseFloat(opt.dataset.min) || 0;
                const max = opt.dataset.max ? parseFloat(opt.dataset.max) : 0;
                const minTenure = parseInt(opt.dataset.minTenure) || 1;
                const maxTenure = parseInt(opt.dataset.maxTenure) || 12;

                amountInput.min = min;
                if (max > 0) amountInput.max = max;
                tenureInput.min = minTenure;
                tenureInput.max = maxTenure;

                let help = '';
                if (min > 0) help += 'Min: ' + min.toLocaleString();
                if (max > 0) help += (help ? ' | ' : '') + 'Max: ' + max.toLocaleString();
                amountHelp.textContent = help;
                tenureHelp.textContent = 'Min: ' + minTenure + ' | Max: ' + maxTenure + ' months';

                // Load requirements via AJAX
                fetch('ajax/loan-requirements.php?action=product_requirements_for_form&product_id=' + opt.value)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        reqContainer.innerHTML = '';
                        if (data.requirements && data.requirements.length) {
                            reqSection.style.display = '';

                            // Group by category
                            var groups = {};
                            data.requirements.forEach(function(req) {
                                var cat = req.category_name || 'Other';
                                if (!groups[cat]) groups[cat] = [];
                                groups[cat].push(req);
                            });

                            var catOrder = ['Eligibility', 'Documents', 'Financial Rules', 'Guarantors', 'Fees', 'Repayment', 'Custom Rules', 'Other'];
                            catOrder.forEach(function(catName) {
                                var catReqs = groups[catName];
                                if (!catReqs) return;
                                delete groups[catName];

                                var section = document.createElement('div');
                                section.className = 'mb-3';
                                var heading = document.createElement('h6');
                                heading.className = 'border-bottom pb-1 text-muted';
                                heading.textContent = catName;
                                section.appendChild(heading);

                                catReqs.forEach(function(req) {
                                    var required = req.product_required == 1;
                                    var requiredAttr = required ? 'required' : '';
                                    var requiredMark = required ? ' <span class="text-danger">*</span>' : '';
                                    var label = '<label class="form-label">' + escHtml(req.label) + requiredMark + '</label>';
                                    // Show rule key note for system rules
                                    if (req.rule_key) label += '<small class="d-block text-muted mb-1">' + escHtml(req.description || '') + '</small>';
                                    var input = '';
                                    var valueName = 'req_value[' + req.id + ']';
                                    var labelName = 'req_label[' + req.id + ']';

                                    switch (req.input_type) {
                                        case 'checkbox':
                                            input = '<div class="form-check"><input type="checkbox" class="form-check-input" name="' + valueName + '" value="1" ' + requiredAttr + ' id="req_' + req.id + '"><label class="form-check-label" for="req_' + req.id + '">Yes</label></div>';
                                            break;
                                        case 'number':
                                            input = '<input type="number" step="0.01" class="form-control" name="' + valueName + '" ' + requiredAttr + '>';
                                            break;
                                        case 'textarea':
                                            input = '<textarea class="form-control" name="' + valueName + '" rows="2" ' + requiredAttr + '></textarea>';
                                            break;
                                        case 'date':
                                            input = '<input type="date" class="form-control" name="' + valueName + '" ' + requiredAttr + '>';
                                            break;
                                        case 'file':
                                            input = '<input type="file" class="form-control" name="' + valueName + '" ' + requiredAttr + '>';
                                            break;
                                        case 'dropdown':
                                            input = '<select class="form-select" name="' + valueName + '" ' + requiredAttr + '>';
                                            input += '<option value="">-- Select --</option>';
                                            (req.options || []).forEach(function(o) {
                                                input += '<option value="' + escHtml(o) + '">' + escHtml(o) + '</option>';
                                            });
                                            input += '</select>';
                                            break;
                                        case 'multi_select':
                                            input = '<div class="border rounded p-2" style="max-height:180px;overflow-y:auto;">';
                                            (req.options || []).forEach(function(o) {
                                                input += '<div class="form-check"><input type="checkbox" class="form-check-input" name="' + valueName + '[]" value="' + escHtml(o) + '" id="req_' + req.id + '_' + o.replace(/\\s+/g, '_') + '"><label class="form-check-label" for="req_' + req.id + '_' + o.replace(/\\s+/g, '_') + '">' + escHtml(o) + '</label></div>';
                                            });
                                            input += '</div>';
                                            break;
                                        default: // text
                                            input = '<input type="text" class="form-control" name="' + valueName + '" ' + requiredAttr + '>';
                                    }

                                    var wrapper = document.createElement('div');
                                    wrapper.className = 'mb-3 ps-2';
                                    wrapper.innerHTML = '<input type="hidden" name="' + labelName + '" value="' + escHtml(req.label) + '">' + label + input;
                                    section.appendChild(wrapper);
                                });

                                reqContainer.appendChild(section);
                            });

                            // Any remaining categories not in the order list
                            for (var catName in groups) {
                                // skip
                            }
                        } else {
                            reqSection.style.display = 'none';
                        }
                    })
                    .catch(function() {
                        reqContainer.innerHTML = '<p class="text-danger small">Failed to load requirements.</p>';
                    });
            } else {
                amountHelp.textContent = '';
                tenureHelp.textContent = '';
                reqSection.style.display = 'none';
                reqContainer.innerHTML = '';
            }
        });
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Guarantors dynamic add
    const guarantorsContainer = document.getElementById('guarantorsContainer');
    const addGuarantorBtn = document.getElementById('addGuarantorBtn');

    if (addGuarantorBtn) {
        addGuarantorBtn.addEventListener('click', function() {
            const rows = guarantorsContainer.querySelectorAll('.guarantor-row');
            const firstRow = rows[0];
            if (!firstRow) return;

            const newRow = firstRow.cloneNode(true);
            newRow.querySelectorAll('select, input').forEach(el => el.value = '');
            const removeBtn = newRow.querySelector('.remove-guarantor-btn');
            if (removeBtn) {
                removeBtn.style.display = 'inline-block';
                removeBtn.addEventListener('click', function() {
                    newRow.remove();
                });
            }
            guarantorsContainer.appendChild(newRow);
        });

        guarantorsContainer.querySelectorAll('.guarantor-row').forEach(function(row, index) {
            const removeBtn = row.querySelector('.remove-guarantor-btn');
            if (removeBtn && index > 0) {
                removeBtn.style.display = 'inline-block';
                removeBtn.addEventListener('click', function() {
                    row.remove();
                });
            }
        });
    }

    // Collateral dynamic add
    const collateralContainer = document.getElementById('collateralContainer');
    const addCollateralBtn = document.getElementById('addCollateralBtn');

    if (addCollateralBtn) {
        addCollateralBtn.addEventListener('click', function() {
            const rows = collateralContainer.querySelectorAll('.collateral-row');
            const firstRow = rows[0];
            if (!firstRow) return;

            const newRow = firstRow.cloneNode(true);
            newRow.querySelectorAll('input').forEach(el => el.value = '');
            const removeBtn = newRow.querySelector('.remove-collateral-btn');
            if (removeBtn) {
                removeBtn.style.display = 'inline-block';
                removeBtn.addEventListener('click', function() {
                    newRow.remove();
                });
            }
            collateralContainer.appendChild(newRow);
        });

        collateralContainer.querySelectorAll('.collateral-row').forEach(function(row, index) {
            const removeBtn = row.querySelector('.remove-collateral-btn');
            if (removeBtn && index > 0) {
                removeBtn.style.display = 'inline-block';
                removeBtn.addEventListener('click', function() {
                    row.remove();
                });
            }
        });
    }
})();
</script>
EOT;

include __DIR__ . '/views/layouts/footer.php';
?>

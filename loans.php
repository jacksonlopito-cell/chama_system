<?php
/**
 * Active Loans Management
 */
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_loans');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Active Loans';

$page = max(1, (int)($_GET['page'] ?? 1));
$status = $_GET['status'] ?? 'active';

$currentRole = $_SESSION['role_slug'] ?? '';
$where = "WHERE l.status = ? AND m.group_code = ?";
$params = [$status, $_SESSION['group_code']];
if ($currentRole === 'member' && !empty($_SESSION['member_id'])) {
    $where .= " AND l.member_id = ?";
    $params[] = $_SESSION['member_id'];
}

$total = $db->prepare("SELECT COUNT(*) FROM loans l JOIN members m ON l.member_id = m.id $where");
$total->execute($params);
$pagination = paginate($total->fetchColumn(), $page);

$loans = $db->prepare("
    SELECT l.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.member_no, lp.name as product_name
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN loan_products lp ON l.product_id = lp.id
    $where ORDER BY l.created_at DESC LIMIT ? OFFSET ?
");
$loans->execute(array_merge($params, [$pagination['perPage'], $pagination['offset']]));
$loansList = $loans->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4>Active Loans</h4>
            <p>Manage loan portfolio and track repayments</p>
        </div>
        <div class="d-flex gap-2">
            <a href="loan-applications.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>New Loan Application
            </a>
        </div>
    </div>

    <?php displayFlash(); ?>

    <!-- Status Tabs -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <ul class="nav nav-pills">
                <li class="nav-item"><a class="nav-link <?= $status === 'active' ? 'active' : '' ?>" href="?status=active">Active</a></li>
                <li class="nav-item"><a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>" href="?status=pending">Pending</a></li>
                <li class="nav-item"><a class="nav-link <?= $status === 'disbursed' ? 'active' : '' ?>" href="?status=disbursed">Disbursed</a></li>
                <li class="nav-item"><a class="nav-link <?= $status === 'paid' ? 'active' : '' ?>" href="?status=paid">Paid</a></li>
                <li class="nav-item"><a class="nav-link <?= $status === 'defaulted' ? 'active' : '' ?>" href="?status=defaulted">Defaulted</a></li>
            </ul>
        </div>
    </div>

    <!-- Loans Table -->
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
                            <th>Balance</th>
                            <th>Interest</th>
                            <th>Tenure</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loansList)): ?>
                            <tr><td colspan="9" class="text-center py-4" style="color: var(--gray-500);">No loans found</td></tr>
                        <?php else: ?>
                            <?php foreach ($loansList as $loan): ?>
                                <tr>
                                    <td class="fw-medium"><?= e($loan['loan_no']) ?></td>
                                    <td>
                                        <a href="members.php?action=view&id=<?= $loan['member_id'] ?>" class="text-decoration-none">
                                            <?= e($loan['member_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($loan['product_name']) ?></td>
                                    <td class="fw-semibold"><?= formatCurrency($loan['amount']) ?></td>
                                    <td class="fw-semibold"><?= formatCurrency($loan['balance']) ?></td>
                                    <td><?= $loan['interest_rate'] ?>% (<?= e($loan['interest_type']) ?>)</td>
                                    <td><?= $loan['tenure_months'] ?> mo</td>
                                    <td><?= statusBadge($loan['status']) ?></td>
                                    <td>
                                        <a href="loan-applications.php?id=<?= $loan['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="loan-repayments.php?loan_id=<?= $loan['id'] ?>" class="btn btn-sm btn-outline-success btn-icon" title="Repayments">
                                            <i class="fas fa-coins"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="card-footer"><?= renderPagination($pagination, "loans.php?status=$status&") ?></div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>

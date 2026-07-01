<?php
/**
 * Contributions Management
 */
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_contributions');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Contributions';
$action = $_GET['action'] ?? 'list';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_contribution'])) {
    verifyCsrf();
    requirePermission('create_contributions');

    $memberId = (int)$_POST['member_id'];
    $typeId = (int)$_POST['type_id'];
    $amount = (float)$_POST['amount'];
    $date = $_POST['contribution_date'] ?? date('Y-m-d');
    $method = $_POST['payment_method'] ?? 'cash';
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($memberId && $typeId && $amount > 0) {
        try {
            $receiptNo = generateReceiptNo();
            $gid = $db->prepare("SELECT id FROM groups_table WHERE group_code=?");
$gid->execute([$_SESSION['group_code']]);
$groupId = $gid->fetchColumn();
$stmt = $db->prepare("INSERT INTO contributions (member_id, type_id, amount, contribution_date, payment_method, reference, receipt_no, notes, group_id, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$memberId, $typeId, $amount, $date, $method, $reference, $receiptNo, $notes, $groupId, $_SESSION['user_id']]);

            // Update balance
            $bal = $db->prepare("INSERT INTO contribution_balances (member_id, type_id, total_contributed, last_contribution_date) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_contributed = total_contributed + ?, last_contribution_date = ?");
            $bal->execute([$memberId, $typeId, $amount, $date, $amount, $date]);

            logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'contributions', $db->lastInsertId(), null, ['member_id' => $memberId, 'amount' => $amount], 'Recorded contribution');
            setFlash('success', "Contribution recorded. Receipt: $receiptNo");
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    } else {
        setFlash('danger', 'Please fill all required fields');
    }
    redirect('contributions.php');
}

// Get contributions
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$typeFilter = (int)($_GET['type'] ?? 0);

$currentRole = $_SESSION['role_slug'] ?? '';
$where = "WHERE m.group_code = ?";
$params = [$_SESSION['group_code']];
if ($currentRole === 'member' && !empty($_SESSION['member_id'])) {
    $where .= " AND c.member_id = ?";
    $params[] = $_SESSION['member_id'];
}
if ($search) {
    $where .= " AND (CONCAT(m.first_name, ' ', m.last_name) LIKE ? OR c.receipt_no LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s;
}
if ($typeFilter) {
    $where .= " AND c.type_id = ?";
    $params[] = $typeFilter;
}

$total = $db->prepare("SELECT COUNT(*) FROM contributions c JOIN members m ON c.member_id = m.id $where");
$total->execute($params);
$totalRecords = $total->fetchColumn();
$pagination = paginate($totalRecords, $page);

$contributions = $db->prepare("
    SELECT c.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.member_no, ct.name as type_name
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    JOIN contribution_types ct ON c.type_id = ct.id
    $where ORDER BY c.created_at DESC LIMIT ? OFFSET ?
");
$contributions->execute(array_merge($params, [$pagination['perPage'], $pagination['offset']]));
$contribs = $contributions->fetchAll();

// Contribution types and members for dropdowns
$types = $db->query("SELECT * FROM contribution_types WHERE status = 'active'")->fetchAll();
$members = $db->prepare("SELECT id, first_name, last_name, member_no FROM members WHERE status = 'active' AND group_code = ? ORDER BY first_name");
$members->execute([$_SESSION['group_code']]);
$members = $members->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4>Contributions</h4>
            <p>Manage member contributions and savings</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($action === 'list' && hasPermission('create_contributions')): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContributionModal">
                <i class="fas fa-plus me-1"></i>Record Contribution
            </button>
            <?php endif; ?>
            <a href="reports/contributions.php" class="btn btn-outline-secondary">
                <i class="fas fa-file-alt me-1"></i>Reports
            </a>
        </div>
    </div>

    <?php displayFlash(); ?>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by member or receipt..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $typeFilter === $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="contributions.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Contributions Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0" data-table-name="contributions">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Member</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php foreach ($contribs as $c): ?>
                                <tr>
                                    <td><span class="fw-medium"><?= e($c['receipt_no']) ?></span></td>
                                    <td>
                                        <a href="members.php?action=view&id=<?= $c['member_id'] ?>" class="text-decoration-none fw-medium">
                                            <?= e($c['member_name']) ?>
                                        </a>
                                        <br><small style="color: var(--gray-500);"><?= e($c['member_no']) ?></small>
                                    </td>
                                    <td><?= e($c['type_name']) ?></td>
                                    <td class="fw-semibold"><?= formatCurrency($c['amount']) ?></td>
                                    <td><?= formatDate($c['contribution_date']) ?></td>
                                    <td><?= e(ucfirst($c['payment_method'])) ?></td>
                                    <td><?= e($c['reference'] ?? '-') ?></td>
                                    <td style="font-size: 0.8125rem;"><?= timeAgo($c['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="card-footer"><?= renderPagination($pagination, 'contributions.php?' . http_build_query(array_filter(['search' => $search, 'type' => $typeFilter])) . '&') ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Contribution Modal -->
<div class="modal fade" id="addContributionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Contribution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="contributions.php" method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Member <span class="text-danger">*</span></label>
                            <select name="member_id" class="form-select searchable-select" required>
                                <option value="">Select Member</option>
                                <?php if ($currentRole === 'member' && !empty($_SESSION['member_id'])): ?>
                                    <?php foreach ($members as $m): ?>
                                        <?php if ($m['id'] === $_SESSION['member_id']): ?>
                                            <option value="<?= $m['id'] ?>" selected><?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['member_no']) ?>)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['member_no']) ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contribution Type <span class="text-danger">*</span></label>
                            <select name="type_id" class="form-select searchable-select" required>
                                <option value="">Select Type</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= formatCurrency($t['amount']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" required min="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="contribution_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference No.</label>
                            <input type="text" name="reference" class="form-control" placeholder="Transaction reference">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Optional notes">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_contribution" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Contribution
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>

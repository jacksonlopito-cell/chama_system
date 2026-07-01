<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_accounting');
$pageTitle = 'Expenses';

$db = getConnection();
$currentUser = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$expCatList = $db->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {
    verifyCsrf();
    try {
        $receiptNo = 'EXP-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $data = ['group_code' => $_SESSION['group_code'], 'category_id' => (int)$_POST['category_id'], 'amount' => $_POST['amount'], 'description' => $_POST['description'], 'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'), 'payment_method' => $_POST['payment_method'] ?? 'cash', 'reference' => $_POST['reference'] ?? '', 'receipt_no' => $receiptNo, 'notes' => trim($_POST['notes'] ?? ''), 'recorded_by' => $userId];
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            unset($data['receipt_no'], $data['recorded_by']);
            $data['approved_by'] = $userId;
            $sets = []; $params = [];
            foreach ($data as $k => $v) { $sets[] = "$k=?"; $params[] = $v; }
            $params[] = $_SESSION['group_code'];
            $params[] = $id;
            $db->prepare("UPDATE expenses SET " . implode(',', $sets) . " WHERE group_code=? AND id=?")->execute($params);
            $msg = 'updated';
        } else {
            $cols = implode(',', array_keys($data));
            $vals = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO expenses ($cols) VALUES ($vals)")->execute(array_values($data));
            $id = $db->lastInsertId(); $msg = 'created';
        }
        logAudit($userId, $_SESSION['username'], 'create', 'expenses', $id, null, $data, "$msg expense");
        setFlash('success', 'Expense ' . $msg);
    } catch (Exception $e) { setFlash('danger', $e->getMessage()); }
    redirect('expenses.php');
}

$where = ' AND e.group_code=?'; $params = [$_SESSION['group_code']];
if (!empty($_GET['category_id'])) { $where .= " AND e.category_id=?"; $params[] = (int)$_GET['category_id']; }
if (!empty($_GET['date_from'])) { $where .= " AND e.expense_date>=?"; $params[] = $_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where .= " AND e.expense_date<=?"; $params[] = $_GET['date_to']; }

$perPage = 30;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = $db->prepare("SELECT COUNT(*) FROM expenses e WHERE 1=1 $where");
$total->execute($params);
$totalPages = ceil($total->fetchColumn() / $perPage);

$expenses = $db->prepare("SELECT e.*, u.username, ec.name as category_name FROM expenses e LEFT JOIN users u ON e.recorded_by=u.id LEFT JOIN expense_categories ec ON e.category_id=ec.id WHERE 1=1 $where ORDER BY e.created_at DESC LIMIT $perPage OFFSET $offset");
$expenses->execute($params);
$rows = $expenses->fetchAll();

$totalAmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses e WHERE 1=1 $where");
$totalAmt->execute($params);
$grandTotal = $totalAmt->fetchColumn();

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Expenses</h4><p>Total: <?= formatCurrency($grandTotal) ?></p></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="fas fa-plus me-1"></i>Add Expense</button>
    </div>
    <?php displayFlash(); ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($expCatList as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($_GET['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><input type="date" name="date_from" class="form-control" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
                <div class="col-md-3"><input type="date" name="date_to" class="form-control" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0" data-table-name="expenses">
                <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Amount</th><th>Payment</th><th>By</th><th class="no-sort">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= formatDate($r['expense_date']) ?></td>
                        <td><?= e(truncate($r['description'], 40)) ?></td>
                        <td><span class="badge bg-secondary"><?= e($r['category_name'] ?? '-') ?></span></td>
                        <td><strong><?= formatCurrency($r['amount']) ?></strong></td>
                        <td><?= e(ucfirst($r['payment_method'] ?? 'cash')) ?></td>
                        <td><small><?= e($r['username'] ?? '') ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-warning btn-icon" onclick="editExpense(<?= $r['id'] ?>, '<?= e(addslashes($r['description'])) ?>', <?= $r['amount'] ?>, <?= (int)($r['category_id'] ?? 0) ?>, '<?= e($r['payment_method'] ?? 'cash') ?>', '<?= $r['expense_date'] ?>', '<?= e(addslashes($r['notes'] ?? '')) ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3"><ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>

<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="expenseModalTitle">Add Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?><input type="hidden" name="id" id="expId" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" id="expDesc" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" id="expAmt" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="expCat" class="form-select searchable-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($expCatList as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" id="expPay" class="form-select">
                                <option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="bank">Bank</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="expense_date" id="expDate" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="expNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_expense" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function editExpense(id, desc, amt, cat, pay, date, notes) {
    $('#expenseModalTitle').text('Edit Expense');
    $('#expId').val(id);
    $('#expDesc').val(desc);
    $('#expAmt').val(amt);
    $('#expCat').val(cat);
    $('#expPay').val(pay);
    $('#expDate').val(date);
    $('#expNotes').val(notes);
    new bootstrap.Modal(document.getElementById('expenseModal')).show();
}
$('#expenseModal').on('hidden.bs.modal', function() {
    if ($('#expId').val() === '0') $('#expenseModalTitle').text('Add Expense');
});
</script>
JS;
include __DIR__ . '/../views/layouts/footer.php'; ?>

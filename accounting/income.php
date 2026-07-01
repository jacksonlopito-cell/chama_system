<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_accounting');
$pageTitle = 'Income';

$db = getConnection();
$currentUser = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$incCatList = $db->query("SELECT * FROM income_categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_income'])) {
    verifyCsrf();
    try {
        $receiptNo = 'INC-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $data = ['group_code' => $_SESSION['group_code'], 'category_id' => (int)$_POST['category_id'], 'description' => $_POST['description'], 'amount' => $_POST['amount'], 'payment_method' => $_POST['payment_method'] ?? 'cash', 'income_date' => $_POST['income_date'] ?? date('Y-m-d'), 'reference' => $_POST['reference'] ?? '', 'receipt_no' => $receiptNo, 'notes' => trim($_POST['notes'] ?? ''), 'recorded_by' => $userId];
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            unset($data['receipt_no'], $data['recorded_by']);
            $sets = []; $params = [];
            foreach ($data as $k => $v) { $sets[] = "$k=?"; $params[] = $v; }
            $params[] = $_SESSION['group_code'];
            $params[] = $id;
            $db->prepare("UPDATE income SET " . implode(',', $sets) . " WHERE group_code=? AND id=?")->execute($params);
            $m = 'updated';
        } else {
            $cols = implode(',', array_keys($data));
            $vals = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO income ($cols) VALUES ($vals)")->execute(array_values($data));
            $id = $db->lastInsertId(); $m = 'created';
        }
        logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'income', $id, null, $data, "$m income");
        setFlash('success', 'Income ' . $m);
    } catch (Exception $e) { setFlash('danger', $e->getMessage()); }
    redirect('income.php');
}

$where = ' AND i.group_code=?'; $params = [$_SESSION['group_code']];
if (!empty($_GET['category_id'])) { $where .= " AND i.category_id=?"; $params[] = (int)$_GET['category_id']; }
if (!empty($_GET['date_from'])) { $where .= " AND i.income_date>=?"; $params[] = $_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where .= " AND i.income_date<=?"; $params[] = $_GET['date_to']; }

$perPage = 30;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = $db->prepare("SELECT COUNT(*) FROM income i WHERE 1=1 $where");
$total->execute($params);
$totalPages = ceil($total->fetchColumn() / $perPage);

$income = $db->prepare("SELECT i.*, ic.name as category_name FROM income i LEFT JOIN income_categories ic ON i.category_id=ic.id WHERE 1=1 $where ORDER BY i.created_at DESC LIMIT $perPage OFFSET $offset");
$income->execute($params);
$rows = $income->fetchAll();

$grandTotal = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM income i WHERE 1=1 $where");
$grandTotal->execute($params);
$totalIncome = $grandTotal->fetchColumn();

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Income</h4><p>Total: <?= formatCurrency($totalIncome) ?></p></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#incomeModal"><i class="fas fa-plus me-1"></i>Add Income</button>
    </div>
    <?php displayFlash(); ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($incCatList as $c): ?>
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
            <table class="table table-hover datatable mb-0" data-table-name="income">
                <thead><tr><th>Date</th><th>Source</th><th>Description</th><th>Amount</th><th>Payment</th><th>Reference</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= formatDate($r['income_date']) ?></td>
                        <td><span class="badge bg-success"><?= e($r['category_name'] ?? '-') ?></span></td>
                        <td><?= e(truncate($r['description'], 40)) ?></td>
                        <td><strong><?= formatCurrency($r['amount']) ?></strong></td>
                        <td><?= e(ucfirst($r['payment_method'] ?? 'cash')) ?></td>
                        <td><small><?= e($r['reference'] ?? '-') ?></small></td>
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

<div class="modal fade" id="incomeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5>Add Income</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select searchable-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($incCatList as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="bank">Bank</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="income_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" placeholder="Receipt/Ref #">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" rows="2" class="form-control" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_income" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../views/layouts/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_accounting');
$pageTitle = 'General Ledger';

$db = getConnection();
$currentUser = getCurrentUser();
$accounts = $db->query("SELECT * FROM accounts ORDER BY code")->fetchAll();
$ledger = [];

if (!empty($_GET['account'])) {
    $stmt = $db->prepare("SELECT ji.*, je.entry_date, je.description as entry_desc, je.entry_no FROM journal_items ji JOIN journal_entries je ON ji.entry_id=je.id WHERE ji.account_id=? ORDER BY je.entry_date ASC");
    $stmt->execute([(int)$_GET['account']]);
    $ledger = $stmt->fetchAll();
    $balance = 0;
    foreach ($ledger as &$l) {
        $balance += $l['debit'] - $l['credit'];
        $l['running_balance'] = $balance;
    }
}

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>General Ledger</h4></div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-6">
                    <select name="account" class="form-select searchable-select" required>
                        <option value="">Select Account...</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= ($_GET['account'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= e($a['code']) ?> - <?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>View Ledger</button></div>
                <div class="col-md-3"><button type="button" class="btn btn-outline-secondary w-100" onclick="$('#account').val('')"><i class="fas fa-undo me-1"></i>Reset</button></div>
            </form>
        </div>
    </div>
    <?php if (!empty($_GET['account'])): ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm datatable mb-0" data-table-name="ledger">
                <thead><tr><th>Date</th><th>Description</th><th>Reference</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
                <tbody>
                    <?php foreach ($ledger as $l): ?>
                    <tr>
                        <td><?= formatDate($l['entry_date']) ?></td>
                        <td><small><?= e($l['entry_desc']) ?><br><?= e($l['description'] ?? '') ?></small></td>
                        <td><?= e($l['entry_no'] ?? '-') ?></td>
                        <td><?= $l['debit'] > 0 ? formatCurrency($l['debit']) : '-' ?></td>
                        <td><?= $l['credit'] > 0 ? formatCurrency($l['credit']) : '-' ?></td>
                        <td><strong><?= formatCurrency($l['running_balance']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../views/layouts/footer.php'; ?>

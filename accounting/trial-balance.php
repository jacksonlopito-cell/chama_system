<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_accounting');
$pageTitle = 'Trial Balance';

$db = getConnection();
$currentUser = getCurrentUser();
$asOf = $_GET['as_of'] ?? date('Y-m-d');

$accounts = $db->query("SELECT * FROM accounts ORDER BY code")->fetchAll();
$data = [];
$totalDr = 0; $totalCr = 0;

foreach ($accounts as $a) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(ji.debit),0) as total_dr, COALESCE(SUM(ji.credit),0) as total_cr FROM journal_items ji JOIN journal_entries je ON ji.entry_id=je.id WHERE ji.account_id=? AND je.entry_date<=?");
    $stmt->execute([$a['id'], $asOf]);
    $r = $stmt->fetch();
    $bal = $r['total_dr'] - $r['total_cr'];
    $dr = $bal > 0 ? $bal : 0;
    $cr = $bal < 0 ? abs($bal) : 0;
    if ($bal != 0) {
        $data[] = ['code' => $a['code'], 'name' => $a['name'], 'debit' => $dr, 'credit' => $cr];
        $totalDr += $dr; $totalCr += $cr;
    }
}

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Trial Balance</h4><p>As of <?= formatDate($asOf) ?></p></div>
    </div>
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="date" name="as_of" class="form-control" value="<?= $asOf ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sync me-1"></i>Refresh</button>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm datatable mb-0" data-table-name="trial-balance">
                <thead><tr><th>Account</th><th>Name</th><th>Debit (DR)</th><th>Credit (CR)</th></tr></thead>
                <tbody>
                    <?php foreach ($data as $d): ?>
                    <tr>
                        <td><?= e($d['code']) ?></td>
                        <td><?= e($d['name']) ?></td>
                        <td class="text-end"><?= $d['debit'] > 0 ? formatCurrency($d['debit']) : '-' ?></td>
                        <td class="text-end"><?= $d['credit'] > 0 ? formatCurrency($d['credit']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="2" class="text-end">Total</td>
                        <td class="text-end"><?= formatCurrency($totalDr) ?></td>
                        <td class="text-end"><?= formatCurrency($totalCr) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../views/layouts/footer.php'; ?>

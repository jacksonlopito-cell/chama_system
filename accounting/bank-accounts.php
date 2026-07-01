<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_accounting');
$pageTitle = 'Bank Accounts';

$db = getConnection();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_account'])) {
    verifyCsrf();
    try {
        $data = ['group_code' => $_SESSION['group_code'], 'bank_name' => $_POST['bank_name'], 'account_name' => $_POST['account_name'], 'account_no' => $_POST['account_no'], 'branch' => $_POST['branch'] ?? '', 'account_type' => $_POST['account_type'] ?? 'savings', 'currency' => $_POST['currency'] ?? 'KES', 'balance' => $_POST['balance'] ?? 0];
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $sets = []; $params = [];
            foreach ($data as $k => $v) { $sets[] = "$k=?"; $params[] = $v; }
            $params[] = $_SESSION['group_code'];
            $params[] = $id;
            $db->prepare("UPDATE bank_accounts SET " . implode(',', $sets) . " WHERE group_code=? AND id=?")->execute($params);
            $m = 'updated';
        } else {
            $cols = implode(',', array_keys($data));
            $vals = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO bank_accounts ($cols) VALUES ($vals)")->execute(array_values($data));
            $m = 'created';
        }
        setFlash('success', "Bank account $m");
    } catch (Exception $e) { setFlash('danger', $e->getMessage()); }
    redirect('bank-accounts.php');
}

if (isset($_POST['delete_account'])) {
    verifyCsrf();
    $db->prepare("DELETE FROM bank_accounts WHERE id=? AND group_code=?")->execute([(int)$_POST['id'], $_SESSION['group_code']]);
    setFlash('info', 'Account deleted');
    redirect('bank-accounts.php');
}

$accounts = $db->prepare("SELECT * FROM bank_accounts WHERE group_code=? ORDER BY bank_name");
$accounts->execute([$_SESSION['group_code']]);
$accounts = $accounts->fetchAll();
$totalBalance = array_sum(array_column($accounts, 'balance'));

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Bank Accounts</h4><p>Total Balance: <?= formatCurrency($totalBalance) ?></p></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bankModal"><i class="fas fa-plus me-1"></i>Add Account</button>
    </div>
    <?php displayFlash(); ?>
    <div class="row g-3">
        <?php foreach ($accounts as $a): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <h5 class="mb-0"><?= e($a['bank_name']) ?></h5>
                        <span class="badge bg-<?= $a['status'] ?? 'success' ?>"><?= e($a['currency'] ?? 'KES') ?></span>
                    </div>
                    <p class="text-muted small mb-1"><?= e($a['account_name']) ?></p>
                    <p class="mb-2"><?= e($a['account_no']) ?></p>
                    <h4 class="text-primary mb-0"><?= formatCurrency($a['balance']) ?></h4>
                    <small class="text-muted"><?= e($a['branch'] ? "$a[branch] branch" : '') ?></small>
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-sm btn-outline-warning" onclick="editBank(<?= $a['id'] ?>, '<?= htmlspecialchars($a['bank_name'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($a['account_name'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($a['account_no'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($a['branch'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($a['account_type'] ?? 'savings', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($a['currency'] ?? 'KES', ENT_QUOTES, 'UTF-8') ?>', <?= (float)$a['balance'] ?>)"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this account?')">
                            <?= csrfField() ?><input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" name="delete_account" class="btn btn-sm btn-outline-danger btn-icon"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="bankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="bankModalTitle">Add Bank Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?><input type="hidden" name="id" id="bankId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                            <input type="text" name="bank_name" id="bankName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Branch</label>
                            <input type="text" name="branch" id="bankBranch" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="account_name" id="bankAcctName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Number <span class="text-danger">*</span></label>
                            <input type="text" name="account_no" id="bankAcctNo" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Type</label>
                            <select name="account_type" id="bankType" class="form-select">
                                <option value="savings">Savings</option><option value="current">Current</option><option value="fixed_deposit">Fixed Deposit</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Currency</label>
                            <select name="currency" id="bankCurr" class="form-select">
                                <option value="KES">KES</option><option value="USD">USD</option><option value="EUR">EUR</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" step="0.01" name="balance" id="bankBal" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_account" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function editBank(id, bank, acctName, acctNo, branch, type, curr, bal) {
    $('#bankModalTitle').text('Edit Bank Account');
    $('#bankId').val(id);
    $('#bankName').val(bank);
    $('#bankBranch').val(branch);
    $('#bankAcctName').val(acctName);
    $('#bankAcctNo').val(acctNo);
    $('#bankType').val(type);
    $('#bankCurr').val(curr);
    $('#bankBal').val(bal);
    new bootstrap.Modal(document.getElementById('bankModal')).show();
}
$('#bankModal').on('hidden.bs.modal', function() {
    if ($('#bankId').val() === '0') $('#bankModalTitle').text('Add Bank Account');
});
</script>
JS;
include __DIR__ . '/../views/layouts/footer.php'; ?>

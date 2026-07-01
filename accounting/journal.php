<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_accounting');
$pageTitle = 'Journal Entries';

$db = getConnection();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_journal'])) {
    verifyCsrf();
    try {
        $db->beginTransaction();
        $entryNo = 'JN' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("INSERT INTO journal_entries (entry_no, description, entry_date, reference_type, created_by, status) VALUES (?,?,?,?,?,'posted')");
        $stmt->execute([$entryNo, $_POST['description'], $_POST['entry_date'], $_POST['reference'] ?? null, $_SESSION['user_id']]);
        $entryId = $db->lastInsertId();
        $debits = $_POST['debit_account'] ?? [];
        $credits = $_POST['credit_account'] ?? [];
        $debitAmts = $_POST['debit_amount'] ?? [];
        $creditAmts = $_POST['credit_amount'] ?? [];
        $totalDebit = array_sum(array_map('floatval', $debitAmts));
        $totalCredit = array_sum(array_map('floatval', $creditAmts));
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new Exception('Debits (' . number_format($totalDebit, 2) . ') must equal Credits (' . number_format($totalCredit, 2) . ')');
        }
        $lineStmt = $db->prepare("INSERT INTO journal_items (entry_id, account_id, description, debit, credit) VALUES (?,?,?,?,?)");
        foreach ($debits as $i => $acct) {
            if (!empty($acct) && !empty($debitAmts[$i])) {
                $lineStmt->execute([$entryId, (int)$acct, $_POST['line_desc'][$i] ?? '', $debitAmts[$i], 0]);
            }
        }
        foreach ($credits as $i => $acct) {
            if (!empty($acct) && !empty($creditAmts[$i])) {
                $lineStmt->execute([$entryId, (int)$acct, $_POST['line_desc_c'][$i] ?? '', 0, $creditAmts[$i]]);
            }
        }
        $db->commit();
        setFlash('success', 'Journal entry posted (Entry #' . $entryNo . ')');
    } catch (Exception $e) { $db->rollBack(); setFlash('danger', $e->getMessage()); }
    redirect('journal.php');
}

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$total = $db->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn();
$totalPages = ceil($total / $perPage);

$entries = $db->query("SELECT je.*, u.username FROM journal_entries je JOIN users u ON je.created_by=u.id ORDER BY je.created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
$accounts = $db->query("SELECT * FROM accounts ORDER BY code")->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Journal Entries</h4></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#journalModal"><i class="fas fa-plus me-1"></i>New Entry</button>
    </div>
    <?php displayFlash(); ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table datatable mb-0" data-table-name="journal">
                <thead><tr><th>Date</th><th>Description</th><th>Reference</th><th>By</th><th class="no-sort">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= formatDate($e['entry_date']) ?></td>
                        <td><?= e(truncate($e['description'], 50)) ?></td>
                        <td><?= e($e['entry_no'] ?? '-') ?></td>
                        <td><?= e($e['username']) ?></td>
                        <td><a href="journal.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-info btn-icon"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="journalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5>New Journal Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Type</label>
                        <input type="text" name="reference" class="form-control" placeholder="e.g., INV-001">
                    </div>
                    <h6>Debits</h6>
                    <div id="debitsContainer">
                        <div class="row g-2 mb-2">
                            <div class="col-md-5">
                                <select name="debit_account[]" class="form-select form-select-sm">
                                    <option value="">Select account</option>
                                    <?php foreach ($accounts as $a): ?>
                                        <option value="<?= $a['id'] ?>"><?= e($a['code']) ?> - <?= e($a['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3"><input type="text" name="line_desc[]" class="form-control form-control-sm" placeholder="Line desc"></div>
                            <div class="col-md-3"><input type="number" step="0.01" name="debit_amount[]" class="form-control form-control-sm" placeholder="Amount"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-success add-debit"><i class="fas fa-plus"></i></button></div>
                        </div>
                    </div>
                    <h6 class="mt-3">Credits</h6>
                    <div id="creditsContainer">
                        <div class="row g-2 mb-2">
                            <div class="col-md-5">
                                <select name="credit_account[]" class="form-select form-select-sm">
                                    <option value="">Select account</option>
                                    <?php foreach ($accounts as $a): ?>
                                        <option value="<?= $a['id'] ?>"><?= e($a['code']) ?> - <?= e($a['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3"><input type="text" name="line_desc_c[]" class="form-control form-control-sm" placeholder="Line desc"></div>
                            <div class="col-md-3"><input type="number" step="0.01" name="credit_amount[]" class="form-control form-control-sm" placeholder="Amount"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-success add-credit"><i class="fas fa-plus"></i></button></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_journal" class="btn btn-primary">Post Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
$(document).on('click', '.add-debit', function() {
    const row = $(this).closest('.row').clone();
    row.find('input').val('');
    row.find('button').removeClass('btn-outline-success add-debit').addClass('btn-outline-danger remove-line').html('<i class="fas fa-times"></i>');
    $('#debitsContainer').append(row);
});
$(document).on('click', '.add-credit', function() {
    const row = $(this).closest('.row').clone();
    row.find('input').val('');
    row.find('button').removeClass('btn-outline-success add-credit').addClass('btn-outline-danger remove-line').html('<i class="fas fa-times"></i>');
    $('#creditsContainer').append(row);
});
$(document).on('click', '.remove-line', function() { $(this).closest('.row').remove(); });
</script>
JS;
include __DIR__ . '/../views/layouts/footer.php'; ?>

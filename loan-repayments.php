<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_loan_payments');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Loan Repayments';
$loanId = (int)($_GET['loan_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    verifyCsrf();
    requirePermission('create_loan_payments');

    $loanId = (int)$_POST['loan_id'];
    $amount = (float)$_POST['amount'];
    $date = $_POST['payment_date'] ?? date('Y-m-d');
    $method = $_POST['payment_method'] ?? 'cash';
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($loanId && $amount > 0) {
        try {
            $db->beginTransaction();

            $loan = $db->prepare("SELECT l.* FROM loans l JOIN members m ON l.member_id = m.id WHERE l.id = ? AND m.group_code = ?");
            $loan->execute([$loanId, $_SESSION['group_code']]);
            $loanData = $loan->fetch();

            if (!$loanData || $loanData['balance'] <= 0) {
                throw new Exception('Loan not found or fully paid');
            }

            $receiptNo = 'RCP-LN-' . date('Y') . '-' . str_pad(($db->query("SELECT COUNT(*) FROM loan_payments")->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);

            // Calculate principal/interest split
            $balance = $loanData['balance'];
            $totalDue = $loanData['total_amount'];
            $totalInterest = $loanData['total_interest'];

            $interestPortion = $totalInterest > 0 ? min($amount * ($totalInterest / $totalDue), $amount) : 0;
            $principalPortion = $amount - $interestPortion;

            $stmt = $db->prepare("INSERT INTO loan_payments (loan_id, receipt_no, amount, principal_amount, interest_amount, payment_date, payment_method, reference, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$loanId, $receiptNo, $amount, $principalPortion, $interestPortion, $date, $method, $reference, $notes, $_SESSION['user_id']]);

            $newBalance = max(0, $balance - $principalPortion);
            $db->prepare("UPDATE loans SET balance = ?, next_payment_date = DATE_ADD(?, INTERVAL 1 MONTH) WHERE id = ?")->execute([$newBalance, $date, $loanId]);

            if ($newBalance <= 0) {
                $db->prepare("UPDATE loans SET status = 'paid' WHERE id = ?")->execute([$loanId]);
            }

            $db->commit();

            logAudit($_SESSION['user_id'], $_SESSION['username'], 'payment', 'loan_payments', $db->lastInsertId(), null, ['loan_id' => $loanId, 'amount' => $amount], 'Loan payment recorded');
            setFlash('success', "Payment recorded. Receipt: $receiptNo");
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    } else {
        setFlash('danger', 'Select a loan and enter amount');
    }
    redirect('loan-repayments.php');
}

// Active loans for dropdown
$currentRole = $_SESSION['role_slug'] ?? '';
$loanWhereExtra = '';
$loanParamsExtra = [];
if ($currentRole === 'member' && !empty($_SESSION['member_id'])) {
    $loanWhereExtra = " AND l.member_id = ?";
    $loanParamsExtra[] = $_SESSION['member_id'];
}

$activeLoans = $db->prepare("SELECT l.id, l.loan_no, CONCAT(m.first_name, ' ', m.last_name) as member_name, l.balance FROM loans l JOIN members m ON l.member_id = m.id WHERE l.status IN ('active', 'disbursed') AND m.group_code = ?$loanWhereExtra ORDER BY l.created_at DESC");
$activeLoans->execute(array_merge([$_SESSION['group_code']], $loanParamsExtra));
$activeLoans = $activeLoans->fetchAll();

// Payments list
$where = "WHERE 1=1";
$params = [];
if ($loanId) { $where .= " AND lp.loan_id = ?"; $params[] = $loanId; }
$where .= " AND m.group_code = ?";
$params[] = $_SESSION['group_code'];
if ($currentRole === 'member' && !empty($_SESSION['member_id'])) {
    $where .= " AND l.member_id = ?";
    $params[] = $_SESSION['member_id'];
}
$payments = $db->prepare("SELECT lp.*, l.loan_no, CONCAT(m.first_name, ' ', m.last_name) as member_name FROM loan_payments lp JOIN loans l ON lp.loan_id = l.id JOIN members m ON l.member_id = m.id $where ORDER BY lp.created_at DESC LIMIT 50");
$payments->execute($params);
$pmts = $payments->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Loan Repayments</h4><p>Record and track loan payments</p></div>
        <?php if (hasPermission('create_loan_payments')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="fas fa-plus me-1"></i>Record Payment</button>
        <?php endif; ?>
    </div>
    <?php displayFlash(); ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Receipt</th><th>Loan</th><th>Member</th><th>Amount</th><th>Principal</th><th>Interest</th><th>Date</th><th>Method</th></tr></thead>
                    <tbody>
                        <?php if (empty($pmts)): ?><tr><td colspan="8" class="text-center py-4" style="color:var(--gray-500);">No payments recorded</td></tr>
                        <?php else: foreach ($pmts as $p): ?>
                            <tr>
                                <td class="fw-medium"><?= e($p['receipt_no'] ?? '-') ?></td>
                                <td><?= e($p['loan_no']) ?></td>
                                <td><?= e($p['member_name']) ?></td>
                                <td class="fw-semibold"><?= formatCurrency($p['amount']) ?></td>
                                <td><?= formatCurrency($p['principal_amount']) ?></td>
                                <td><?= formatCurrency($p['interest_amount']) ?></td>
                                <td><?= formatDate($p['payment_date']) ?></td>
                                <td><?= ucfirst(e($p['payment_method'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Record Loan Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form action="loan-repayments.php" method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Loan <span class="text-danger">*</span></label>
                        <select name="loan_id" class="form-select searchable-select" required>
                            <option value="">Select Loan</option>
                            <?php foreach ($activeLoans as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= e($l['loan_no']) ?> - <?= e($l['member_name']) ?> (Balance: <?= formatCurrency($l['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" class="form-control" required min="0.01">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="bank">Bank</option><option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="Transaction ref">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_payment" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/views/layouts/footer.php'; ?>

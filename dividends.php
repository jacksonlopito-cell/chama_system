<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_shares');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Dividends';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Declare new dividend
    if (isset($_POST['declare_dividend'])) {
        requirePermission('edit_shares');
        $productId = (int)$_POST['share_product_id'];
        $perShare = (float)($_POST['dividend_per_share'] ?? 0);
        $financialYear = trim($_POST['financial_year'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$productId || $perShare <= 0 || empty($financialYear)) {
            setFlash('danger', 'Product, per-share amount, and financial year are required.');
            redirect('dividends.php');
        }

        // Calculate total active shares for this product
        $totalShares = $db->prepare("SELECT COALESCE(SUM(shares_count),0) FROM share_purchases WHERE product_id=? AND status='active' AND group_code=?");
        $totalShares->execute([$productId, $_SESSION['group_code']]);
        $totalActiveShares = (int)$totalShares->fetchColumn();
        $totalAmount = $perShare * $totalActiveShares;

        if ($totalActiveShares <= 0) {
            setFlash('danger', 'No active shares found for this product.');
            redirect('dividends.php');
        }

        $stmt = $db->prepare("INSERT INTO dividends (share_product_id, dividend_per_share, total_amount, financial_year, declaration_date, notes, created_by, group_code) VALUES (?,?,?,?,CURDATE(),?,?,?)");
        $stmt->execute([$productId, $perShare, $totalAmount, $financialYear, $notes, $_SESSION['user_id'], $_SESSION['group_code']]);
        $dividendId = (int)$db->lastInsertId();

        logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'dividends', $dividendId, null, ['product_id' => $productId, 'per_share' => $perShare], 'Dividend declared');

        // Create payment records for each shareholding member
        $holders = $db->prepare("SELECT sp.member_id, m.first_name, m.last_name, m.email, SUM(sp.shares_count) as total_shares
            FROM share_purchases sp
            JOIN members m ON sp.member_id = m.id
            WHERE sp.product_id=? AND sp.status='active' AND sp.group_code=?
            GROUP BY sp.member_id");
        $holders->execute([$productId, $_SESSION['group_code']]);
        $insertPay = $db->prepare("INSERT INTO dividend_payments (dividend_id, member_id, shares_count, amount, status) VALUES (?,?,?,?,?)");
        $receiptNo = 'DIV-' . $dividendId . '-';
        $i = 1;
        foreach ($holders as $h) {
            $amount = $perShare * (int)$h['total_shares'];
            $insertPay->execute([$dividendId, $h['member_id'], $h['total_shares'], $amount, 'pending']);
            $i++;
        }

        setFlash('success', "Dividend declared: KSh " . number_format($perShare, 2) . " per share for " . count($holders) . " members. Total: " . formatCurrency($totalAmount));
        redirect('dividends.php');
    }

    // Mark dividend as paid (batch)
    if (isset($_POST['pay_dividend'])) {
        requirePermission('edit_shares');
        $id = (int)$_POST['id'];
        $div = $db->prepare("SELECT * FROM dividends WHERE id=? AND group_code=?");
        $div->execute([$id, $_SESSION['group_code']]);
        $dividend = $div->fetch();
        if (!$dividend) {
            setFlash('danger', 'Dividend not found.');
            redirect('dividends.php');
        }

        $db->beginTransaction();
        $updatePay = $db->prepare("UPDATE dividend_payments SET status='paid', paid_date=CURDATE(), receipt_no=? WHERE dividend_id=? AND status='pending'");
        $receiptBase = 'REC-DIV-' . $id . '-';
        $countPay = $db->prepare("SELECT COUNT(*) FROM dividend_payments WHERE dividend_id=? AND status='paid'");
        $countPay->execute([$id]);
        $receiptNo = $receiptBase . ((int)$countPay->fetchColumn() + 1);
        $updatePay->execute([$receiptNo, $id]);

        $db->prepare("UPDATE dividends SET status='paid', payment_date=CURDATE() WHERE id=?")->execute([$id]);
        $db->commit();

        logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'dividends', $id, ['status' => 'declared'], ['status' => 'paid'], 'Dividend paid out');
        setFlash('success', 'Dividend marked as paid.');
        redirect('dividends.php');
    }

    // Cancel dividend
    if (isset($_POST['cancel_dividend'])) {
        requirePermission('edit_shares');
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE dividends SET status='cancelled' WHERE id=? AND group_code=? AND status='declared'")->execute([$id, $_SESSION['group_code']]);
        $db->prepare("UPDATE dividend_payments SET status='cancelled' WHERE dividend_id=? AND status='pending'")->execute([$id]);
        setFlash('warning', 'Dividend cancelled.');
        redirect('dividends.php');
    }
}

// Get data
$products = $db->prepare("SELECT id, name, price_per_share FROM share_products WHERE status='active' AND group_code=? ORDER BY name");
$products->execute([$_SESSION['group_code']]);
$products = $products->fetchAll();

$dividends = $db->prepare("SELECT d.*, sp.name as product_name,
    (SELECT COUNT(*) FROM dividend_payments dp WHERE dp.dividend_id = d.id) as member_count,
    (SELECT COUNT(*) FROM dividend_payments dp WHERE dp.dividend_id = d.id AND dp.status='paid') as paid_count
    FROM dividends d JOIN share_products sp ON d.share_product_id = sp.id
    WHERE d.group_code=? ORDER BY d.created_at DESC");
$dividends->execute([$_SESSION['group_code']]);
$dividends = $dividends->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Dividends</h4><p>Declare and manage dividend payouts</p></div>
        <?php if (hasPermission('edit_shares')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dividendModal"><i class="fas fa-plus me-1"></i>Declare Dividend</button>
        <?php endif; ?>
    </div>
    <?php displayFlash(); ?>

    <?php if (empty($dividends)): ?>
    <div class="alert alert-info">No dividends declared yet. Click "Declare Dividend" to create one.</div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($dividends as $d): ?>
        <div class="col-md-6">
            <div class="card h-100 border-<?= $d['status'] === 'paid' ? 'success' : ($d['status'] === 'cancelled' ? 'secondary' : 'warning') ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-medium"><?= e($d['product_name']) ?></span>
                    <?php if ($d['status'] === 'declared'): ?>
                        <span class="badge bg-warning">Declared</span>
                    <?php elseif ($d['status'] === 'paid'): ?>
                        <span class="badge bg-success">Paid</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Cancelled</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted d-block">Per Share</small>
                            <strong><?= formatCurrency($d['dividend_per_share']) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Total Amount</small>
                            <strong><?= formatCurrency($d['total_amount']) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Financial Year</small>
                            <strong><?= e($d['financial_year']) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Members</small>
                            <strong><?= (int)$d['paid_count'] ?>/<?= (int)$d['member_count'] ?> paid</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Declared</small>
                            <strong><?= formatDate($d['declaration_date']) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Paid</small>
                            <strong><?= $d['payment_date'] ? formatDate($d['payment_date']) : '-' ?></strong>
                        </div>
                    </div>
                    <?php if ($d['notes']): ?>
                    <div class="mt-2 small text-muted"><?= e($d['notes']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($d['status'] === 'declared'): ?>
                <div class="card-footer d-flex gap-2">
                    <form method="POST" class="d-inline" onsubmit="return confirm('Mark this dividend as paid? This will create payment records.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                        <button type="submit" name="pay_dividend" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Mark as Paid</button>
                    </form>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this dividend? Pending payments will be cancelled.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                        <button type="submit" name="cancel_dividend" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban me-1"></i>Cancel</button>
                    </form>
                </div>
                <?php endif; ?>
                <?php if ($d['status'] === 'paid' || $d['status'] === 'cancelled'): ?>
                <div class="card-footer">
                    <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#payments-<?= $d['id'] ?>">
                        <i class="fas fa-list me-1"></i>View Payments
                    </button>
                </div>
                <?php endif; ?>
                <!-- Payment details collapse -->
                <div class="collapse" id="payments-<?= $d['id'] ?>">
                    <?php
                    $payments = $db->prepare("SELECT dp.*, CONCAT(m.first_name,' ',m.last_name) as member_name, m.member_no FROM dividend_payments dp JOIN members m ON dp.member_id = m.id WHERE dp.dividend_id=? ORDER BY dp.amount DESC");
                    $payments->execute([$d['id']]);
                    $payments = $payments->fetchAll();
                    ?>
                    <div class="card-body border-top bg-light">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Member</th><th>Shares</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= e($p['member_name']) ?></td>
                                    <td><?= number_format($p['shares_count']) ?></td>
                                    <td class="text-end"><?= formatCurrency($p['amount']) ?></td>
                                    <td><?= $p['status'] === 'paid' ? '<span class="badge bg-success">Paid</span>' : ($p['status'] === 'cancelled' ? '<span class="badge bg-secondary">Cancelled</span>' : '<span class="badge bg-warning">Pending</span>') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Declare Dividend Modal -->
<div class="modal fade" id="dividendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5><i class="fas fa-hand-holding-usd me-1"></i>Declare Dividend</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">A dividend distributes profits to members based on their shareholdings.</p>
                    <div class="mb-3">
                        <label class="form-label">Share Product <span class="text-danger">*</span></label>
                        <select name="share_product_id" class="form-select searchable-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= formatCurrency($p['price_per_share']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount per Share (KES) <span class="text-danger">*</span></label>
                            <input type="number" name="dividend_per_share" class="form-control" required min="0.01" step="0.01" placeholder="e.g. 10.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Financial Year <span class="text-danger">*</span></label>
                            <input type="text" name="financial_year" class="form-control" required placeholder="e.g. 2025/2026" maxlength="20">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes about this dividend"></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Payment records will be created for all active shareholding members. Total is calculated from all active shares.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="declare_dividend" class="btn btn-primary"><i class="fas fa-check me-1"></i>Declare</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>

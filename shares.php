<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_shares');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Shares';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['purchase_shares'])) {
        verifyCsrf();
        requirePermission('create_shares');
        $memberId = (int)$_POST['member_id'];
        $productId = (int)$_POST['product_id'];
        $count = (int)$_POST['shares_count'];
        $product = $db->prepare("SELECT * FROM share_products WHERE id = ?");
        $product->execute([$productId]);
        $prod = $product->fetch();

        if ($memberId && $prod && $count > 0) {
            // Check max_shares
            if ($prod['max_shares'] && $count > (int)$prod['max_shares']) {
                setFlash('danger', "Maximum {$prod['max_shares']} shares allowed for this product.");
                redirect('shares.php');
            }
            // Get count of existing active shares for this member + product
            $existing = $db->prepare("SELECT COALESCE(SUM(shares_count),0) FROM share_purchases WHERE member_id=? AND product_id=? AND status='active' AND group_code=?");
            $existing->execute([$memberId, $productId, $_SESSION['group_code']]);
            $totalShares = (int)$existing->fetchColumn() + $count;
            if ($prod['max_shares'] && $totalShares > (int)$prod['max_shares']) {
                setFlash('danger', "This member already holds shares. Total would exceed the {$prod['max_shares']} share limit.");
                redirect('shares.php');
            }

            $total = $prod['price_per_share'] * $count;
            $certCount = $db->prepare("SELECT COUNT(*) FROM share_purchases WHERE group_code=?");
            $certCount->execute([$_SESSION['group_code']]);
            $certNo = 'CERT-' . date('Y') . '-' . str_pad(($certCount->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO share_purchases (member_id, group_code, product_id, shares_count, total_amount, purchase_date, certificate_no, payment_method, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$memberId, $_SESSION['group_code'], $productId, $count, $total, $_POST['purchase_date'] ?? date('Y-m-d'), $certNo, $_POST['payment_method'] ?? 'cash', $_SESSION['user_id']]);
            logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'share_purchases', $db->lastInsertId(), null, ['member_id' => $memberId, 'shares' => $count], 'Share purchase recorded');
            setFlash('success', "Shares purchased. Certificate: $certNo");
        } else {
            setFlash('danger', 'Invalid input');
        }
        redirect('shares.php');
    }

    if (isset($_POST['cancel_shares'])) {
        requirePermission('edit_shares');
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("SELECT id, certificate_no, member_id FROM share_purchases WHERE id=? AND group_code=?");
        $stmt->execute([$id, $_SESSION['group_code']]);
        $p = $stmt->fetch();
        if ($p) {
            $db->prepare("UPDATE share_purchases SET status='cancelled' WHERE id=?")->execute([$id]);
            logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'share_purchases', $id, ['status' => 'active'], ['status' => 'cancelled'], 'Share purchase cancelled');
            setFlash('success', 'Share purchase cancelled.');
        }
        redirect('shares.php');
    }
}

$products = $db->prepare("SELECT * FROM share_products WHERE status = 'active' AND group_code=?");
$products->execute([$_SESSION['group_code']]);
$products = $products->fetchAll();

$currentRole = $_SESSION['role_slug'] ?? '';
$shareWhere = "sp.group_code=?";
$shareParams = [$_SESSION['group_code']];
if ($currentRole === 'member' && !empty($_SESSION['member_id'])) {
    $shareWhere .= " AND sp.member_id = ?";
    $shareParams[] = $_SESSION['member_id'];
}
$purchases = $db->prepare("SELECT sp.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.member_no, sh.name as product_name FROM share_purchases sp JOIN members m ON sp.member_id = m.id JOIN share_products sh ON sp.product_id = sh.id WHERE $shareWhere ORDER BY sp.created_at DESC");
$purchases->execute($shareParams);
$purchases = $purchases->fetchAll();
$members = $db->prepare("SELECT id, first_name, last_name, member_no FROM members WHERE status = 'active' AND group_code=? ORDER BY first_name");
$members->execute([$_SESSION['group_code']]);
$members = $members->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Shares</h4><p>Share purchases and certificates management</p></div>
        <?php if (hasPermission('create_shares')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shareModal"><i class="fas fa-plus me-1"></i>Purchase Shares</button>
        <?php endif; ?>
    </div>
    <?php displayFlash(); ?>

    <div class="row g-3 mb-4">
        <?php if (empty($products)): ?>
        <div class="col-12">
            <div class="alert alert-info mb-0">No active share products available. <a href="share-products.php" class="alert-link">Create one</a> first.</div>
        </div>
        <?php else: foreach ($products as $p): ?>
        <div class="col-md-4">
            <div class="card border-primary border h-100">
                <div class="card-body text-center">
                    <i class="fas fa-chart-pie" style="font-size:2rem;color:var(--secondary);"></i>
                    <h5 class="mt-2"><?= e($p['name']) ?></h5>
                    <h3 style="color:var(--primary);"><?= formatCurrency($p['price_per_share']) ?></h3>
                    <p class="text-muted small">per share</p>
                    <small>Min: <?= (int)$p['min_shares'] ?> | Max: <?= $p['max_shares'] ? (int)$p['max_shares'] : 'Unlimited' ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Purchase History</span>
            <div>
                <a href="share-products.php" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-cog me-1"></i>Manage Products</a>
                <a href="dividends.php" class="btn btn-sm btn-outline-success"><i class="fas fa-hand-holding-usd me-1"></i>Dividends</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="sharesTable">
                    <thead><tr><th>Certificate</th><th>Member</th><th>Product</th><th>Shares</th><th>Total</th><th>Date</th><th>Status</th><th class="text-end no-sort">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($purchases)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No share purchases yet.</td></tr>
                        <?php else: foreach ($purchases as $s): ?>
                        <tr>
                            <td class="fw-medium"><?= e($s['certificate_no']) ?></td>
                            <td><?= e($s['member_name']) ?><br><small style="color:var(--gray-500);"><?= e($s['member_no']) ?></small></td>
                            <td><?= e($s['product_name']) ?></td>
                            <td><?= number_format($s['shares_count']) ?></td>
                            <td class="fw-semibold"><?= formatCurrency($s['total_amount']) ?></td>
                            <td><?= formatDate($s['purchase_date']) ?></td>
                            <td><?= statusBadge($s['status']) ?></td>
                            <td class="text-end">
                                <?php if (hasPermission('edit_shares') && $s['status'] === 'active'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this share purchase? The member will lose these shares.')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" name="cancel_shares" class="btn btn-sm btn-outline-danger" title="Cancel Shares"><i class="fas fa-ban"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5>Purchase Shares</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="mb-3">
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
                    <div class="mb-3">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select searchable-select" required id="shareProduct">
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-price="<?= $p['price_per_share'] ?>" data-max="<?= $p['max_shares'] ?? 999999 ?>"><?= e($p['name']) ?> (<?= formatCurrency($p['price_per_share']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Shares <span class="text-danger">*</span></label>
                        <input type="number" name="shares_count" id="sharesCount" class="form-control" required min="1">
                        <small class="text-muted" id="maxHint"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="text" id="totalAmount" class="form-control" readonly>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="bank">Bank</option><option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="purchase_shares" class="btn btn-primary"><i class="fas fa-shopping-cart me-1"></i>Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
$(document).ready(function() {
    dtHelper.init('#sharesTable');
});

$('#shareProduct, #sharesCount').on('change input', function() {
    const sel = $('#shareProduct option:selected');
    const price = parseFloat(sel.data('price')) || 0;
    const max = parseInt(sel.data('max')) || 0;
    const count = parseInt($('#sharesCount').val()) || 0;
    $('#totalAmount').val('KSh ' + (price * count).toLocaleString(undefined, {minimumFractionDigits: 2}));
    if (max && max < 999999) {
        $('#maxHint').text('Max ' + max + ' shares');
        if (count > max) $('#sharesCount').addClass('is-invalid');
        else $('#sharesCount').removeClass('is-invalid');
    } else {
        $('#maxHint').text('');
    }
});
</script>
JS;
include __DIR__ . '/views/layouts/footer.php';

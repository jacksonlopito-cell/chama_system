<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_reports');
$pageTitle = 'Loans Report';

$db = getConnection();
$currentUser = getCurrentUser();
$where = ''; $params = [];

if (!empty($_GET['status'])) { $where .= " AND l.status=?"; $params[] = $_GET['status']; }
if (!empty($_GET['member_id'])) { $where .= " AND l.member_id=?"; $params[] = (int)$_GET['member_id']; }
if (!empty($_GET['date_from'])) { $where .= " AND l.created_at>=?"; $params[] = $_GET['date_from'] . ' 00:00:00'; }
if (!empty($_GET['date_to'])) { $where .= " AND l.created_at<=?"; $params[] = $_GET['date_to'] . ' 23:59:59'; }
$where .= " AND m.group_code=?"; $params[] = $_SESSION['group_code'];

$data = $db->prepare("SELECT l.*, m.member_no as member_number, m.first_name, m.last_name, lp.name as product_name FROM loans l JOIN members m ON l.member_id=m.id JOIN loan_products lp ON l.product_id=lp.id WHERE 1=1 $where ORDER BY l.created_at DESC");
$data->execute($params);
$rows = $data->fetchAll();

$totalApproved = array_sum(array_column(array_filter($rows, fn($r) => $r['status'] === 'active'), 'amount'));
$totalPending = array_sum(array_column(array_filter($rows, fn($r) => $r['status'] === 'pending' || strpos($r['status'], 'approved') !== false), 'amount'));
$members = $db->prepare("SELECT id, member_no as member_number, first_name, last_name FROM members WHERE group_code=? ORDER BY first_name");
$members->execute([$_SESSION['group_code']]);
$members = $members->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Loans Report</h4></div>
        <a href="../export.php?type=loans&<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-success"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10"><div class="card-body text-center"><h5><?= formatCurrency($totalPending) ?></h5><small class="text-muted">Pending / Approved</small></div></div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10"><div class="card-body text-center"><h5><?= formatCurrency($totalApproved) ?></h5><small class="text-muted">Active Loans</small></div></div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10"><div class="card-body text-center"><h5><?= count($rows) ?></h5><small class="text-muted">Total Applications</small></div></div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach (['pending','secretary_approved','treasurer_approved','chairperson_approved','disbursed','active','rejected','defaulted','paid'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="member_id" class="form-select searchable-select">
                        <option value="">All Members</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($_GET['member_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= e($m['first_name'] . ' ' . $m['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
                <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0" data-table-name="report-loans">
                <thead><tr><th>Date</th><th>Member</th><th>Product</th><th>Amount</th><th>Status</th><th>Balance</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= formatDate($r['created_at']) ?></td>
                        <td><?= e($r['member_number']) ?> - <?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                        <td><?= e($r['product_name']) ?></td>
                        <td><strong><?= formatCurrency($r['amount']) ?></strong></td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td><?= formatCurrency($r['balance'] ?? $r['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../views/layouts/footer.php'; ?>

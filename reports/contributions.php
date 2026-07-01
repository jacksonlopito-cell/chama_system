<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_reports');
$pageTitle = 'Contributions Report';

$db = getConnection();
$currentUser = getCurrentUser();
$where = ''; $params = [];

if (!empty($_GET['type'])) { $where .= " AND ct.id=?"; $params[] = (int)$_GET['type']; }
if (!empty($_GET['member_id'])) { $where .= " AND c.member_id=?"; $params[] = (int)$_GET['member_id']; }
if (!empty($_GET['date_from'])) { $where .= " AND c.created_at>=?"; $params[] = $_GET['date_from'] . ' 00:00:00'; }
if (!empty($_GET['date_to'])) { $where .= " AND c.created_at<=?"; $params[] = $_GET['date_to'] . ' 23:59:59'; }
$where .= " AND m.group_code=?"; $params[] = $_SESSION['group_code'];

$data = $db->prepare("SELECT c.*, m.member_no as member_number, m.first_name, m.last_name, ct.name as type_name FROM contributions c JOIN members m ON c.member_id=m.id JOIN contribution_types ct ON c.type_id=ct.id WHERE 1=1 $where ORDER BY c.created_at DESC");
$data->execute($params);
$rows = $data->fetchAll();

$total = array_sum(array_column($rows, 'amount'));
$members = $db->prepare("SELECT id, member_no as member_number, first_name, last_name FROM members WHERE group_code=? ORDER BY first_name");
$members->execute([$_SESSION['group_code']]);
$members = $members->fetchAll();
$types = $db->query("SELECT * FROM contribution_types ORDER BY name")->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Contributions Report</h4><p>Total: <?= formatCurrency($total) ?></p></div>
        <a href="../export.php?type=contributions&<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-success"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
    </div>
    <?php displayFlash(); ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($_GET['type'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
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
            <table class="table table-hover datatable mb-0" data-table-name="report-contributions">
                <thead><tr><th>Date</th><th>Member</th><th>Type</th><th>Amount</th><th>Method</th><th>Ref</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= formatDate($r['created_at']) ?></td>
                        <td><?= e($r['member_number']) ?> - <?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                        <td><span class="badge bg-info"><?= e($r['type_name']) ?></span></td>
                        <td><strong><?= formatCurrency($r['amount']) ?></strong></td>
                        <td><?= e($r['payment_method'] ?? 'cash') ?></td>
                        <td><?= e($r['reference'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td><?= formatCurrency($total) ?></td><td colspan="2"></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../views/layouts/footer.php'; ?>

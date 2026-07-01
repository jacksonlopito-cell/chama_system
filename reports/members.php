<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_reports');
$pageTitle = 'Members Report';

$db = getConnection();
$currentUser = getCurrentUser();
$where = ''; $params = [];

if (!empty($_GET['status'])) { $where .= " AND status=?"; $params[] = $_GET['status']; }
if (!empty($_GET['gender'])) { $where .= " AND gender=?"; $params[] = $_GET['gender']; }
$where .= " AND group_code=?"; $params[] = $_SESSION['group_code'];

$data = $db->prepare("SELECT *, member_no as member_number FROM members WHERE 1=1 $where ORDER BY created_at DESC");
$data->execute($params);
$rows = $data->fetchAll();

$totalMembers = count($rows);
$activeMembers = count(array_filter($rows, fn($r) => $r['status'] === 'active'));

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Members Report</h4></div>
        <a href="../export.php?type=members&<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-success"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card bg-primary bg-opacity-10"><div class="card-body text-center"><h5><?= $totalMembers ?></h5><small>Total Members</small></div></div></div>
        <div class="col-md-3"><div class="card bg-success bg-opacity-10"><div class="card-body text-center"><h5><?= $activeMembers ?></h5><small>Active</small></div></div></div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($_GET['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= ($_GET['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="gender" class="form-select">
                        <option value="">All Genders</option>
                        <option value="male" <?= ($_GET['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($_GET['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0" data-table-name="report-members">
                <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Gender</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= e($r['member_number']) ?></td>
                        <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                        <td><?= e($r['phone'] ?? '-') ?></td>
                        <td><?= e($r['email'] ?? '-') ?></td>
                        <td><?= e(ucfirst($r['gender'] ?? '-')) ?></td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td><?= formatDate($r['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../views/layouts/footer.php'; ?>

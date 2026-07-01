<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_reports');
$pageTitle = 'Meetings Report';

$db = getConnection();
$currentUser = getCurrentUser();
$where = ' AND group_code=?'; $params = [$_SESSION['group_code']];

if (!empty($_GET['status'])) { $where .= " AND status=?"; $params[] = $_GET['status']; }
if (!empty($_GET['date_from'])) { $where .= " AND meeting_date>=?"; $params[] = $_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where .= " AND meeting_date<=?"; $params[] = $_GET['date_to']; }

$data = $db->prepare("SELECT *, (SELECT COUNT(*) FROM attendance a WHERE a.meeting_id=meetings.id) as attendance_count FROM meetings WHERE 1=1 $where ORDER BY meeting_date DESC");
$data->execute($params);
$rows = $data->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Meetings Report</h4></div>
        <a href="../export.php?type=meetings&<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-success"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="scheduled" <?= ($_GET['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="ongoing" <?= ($_GET['status'] ?? '') === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                        <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3"><input type="date" name="date_from" class="form-control" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
                <div class="col-md-3"><input type="date" name="date_to" class="form-control" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0" data-table-name="report-meetings">
                <thead><tr><th>Date</th><th>Title</th><th>Location</th><th>Attendance</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= formatDate($r['meeting_date']) ?></td>
                        <td><?= e($r['title']) ?></td>
                        <td><?= e($r['venue'] ?? '-') ?></td>
                        <td><?= $r['attendance_count'] ?? '-' ?></td>
                        <td><?= statusBadge($r['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../views/layouts/footer.php'; ?>

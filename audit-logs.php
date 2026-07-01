<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_audit_logs');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Audit Logs';

$where = ' AND u.group_code=?'; $params = [$_SESSION['group_code']];
if (!empty($_GET['action'])) { $where .= " AND a.action=?"; $params[] = $_GET['action']; }
if (!empty($_GET['table_name'])) { $where .= " AND a.table_name=?"; $params[] = $_GET['table_name']; }
if (!empty($_GET['user_id'])) { $where .= " AND a.user_id=?"; $params[] = (int)$_GET['user_id']; }
if (!empty($_GET['date_from'])) { $where .= " AND a.created_at>=?"; $params[] = $_GET['date_from'] . ' 00:00:00'; }
if (!empty($_GET['date_to'])) { $where .= " AND a.created_at<=?"; $params[] = $_GET['date_to'] . ' 23:59:59'; }

$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$countQ = $db->prepare("SELECT COUNT(*) FROM audit_logs a JOIN users u ON a.user_id=u.id WHERE 1=1 $where");
$countQ->execute($params);
$totalPages = ceil($countQ->fetchColumn() / $perPage);

$logs = $db->prepare("SELECT a.*, u.username FROM audit_logs a JOIN users u ON a.user_id=u.id WHERE 1=1 $where ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset");
$logs->execute($params);
$rows = $logs->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Audit Logs</h4><p>System activity trail</p></div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-select">
                        <option value="">All</option>
                        <?php foreach (['create','update','delete','login','logout','export'] as $a): ?>
                            <option value="<?= $a ?>" <?= ($_GET['action'] ?? '') === $a ? 'selected' : '' ?>><?= ucfirst($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Entity</label>
                    <input type="text" name="table_name" class="form-control" placeholder="e.g., members" value="<?= e($_GET['table_name'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">User ID</label>
                    <input type="number" name="user_id" class="form-control" placeholder="User ID" value="<?= e($_GET['user_id'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= e($_GET['date_from'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= e($_GET['date_to'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm datatable mb-0" data-table-name="audit-logs">
                <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>Details</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><small><?= formatDate($r['created_at']) ?></small></td>
                        <td><small><?= e($r['username'] ?? 'System') ?> (<?= $r['user_id'] ?: '-'; ?>)</small></td>
                        <td><span class="badge bg-<?= $r['action'] === 'create' ? 'success' : ($r['action'] === 'update' ? 'warning' : ($r['action'] === 'delete' ? 'danger' : 'secondary')) ?>"><?= e($r['action']) ?></span></td>
                        <td><?= e($r['table_name'] ?? '-') ?></td>
                        <td><?= $r['record_id'] ?: '-' ?></td>
                        <td><small class="text-muted"><?= e(truncate($r['description'] ?? '', 80)) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3"><ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/views/layouts/footer.php'; ?>

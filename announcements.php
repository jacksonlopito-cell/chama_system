<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_announcements');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    verifyCsrf();
    requirePermission('create_announcements');
    try {
        $groupId = $db->prepare("SELECT id FROM groups_table WHERE group_code=?");
$groupId->execute([$_SESSION['group_code']]);
$gid = $groupId->fetchColumn();
$data = ['title' => $_POST['title'], 'body' => $_POST['body'], 'target_role' => $_POST['target_role'] ?: null, 'target_group_id' => $gid ?: null, 'priority' => $_POST['priority'] ?? 'normal', 'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0, 'expires_at' => $_POST['expires_at'] ?: null, 'created_by' => $_SESSION['user_id']];
        $cols = implode(',', array_keys($data));
        $vals = implode(',', array_fill(0, count($data), '?'));
        $db->prepare("INSERT INTO announcements ($cols) VALUES ($vals)")->execute(array_values($data));
        logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'announcements', $db->lastInsertId(), null, $data, 'Created announcement');
        setFlash('success', 'Announcement published');
    } catch (Exception $e) { setFlash('danger', $e->getMessage()); }
    redirect('announcements.php');
}

if (isset($_POST['delete_announcement']) && hasPermission('delete_announcements')) {
    verifyCsrf();
    $db->prepare("DELETE FROM announcements WHERE id=? AND target_group_id=(SELECT id FROM groups_table WHERE group_code=?)")->execute([(int)$_POST['id'], $_SESSION['group_code']]);
    setFlash('info', 'Announcement deleted');
    redirect('announcements.php');
}

$announcements = $db->prepare("SELECT a.*, u.username FROM announcements a JOIN users u ON a.created_by = u.id WHERE a.target_group_id=(SELECT id FROM groups_table WHERE group_code=?) OR a.target_group_id IS NULL ORDER BY a.is_pinned DESC, a.created_at DESC");
$announcements->execute([$_SESSION['group_code']]);
$announcements = $announcements->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Announcements</h4><p>Manage group announcements</p></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#announceModal"><i class="fas fa-plus me-1"></i>New Announcement</button>
    </div>
    <?php displayFlash(); ?>
    <?php foreach ($announcements as $a): ?>
    <div class="card mb-3 <?= $a['is_pinned'] ? 'border-warning' : '' ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <?php if ($a['is_pinned']): ?><span class="badge bg-warning text-dark"><i class="fas fa-thumbtack me-1"></i>Pinned</span><?php endif; ?>
                        <span class="badge bg-<?= $a['priority'] === 'urgent' ? 'danger' : ($a['priority'] === 'high' ? 'warning' : ($a['priority'] === 'low' ? 'secondary' : 'info')) ?>"><?= ucfirst(e($a['priority'])) ?></span>
                    </div>
                    <h5 class="mb-1"><?= e($a['title']) ?></h5>
                    <p class="text-muted small mb-2">By <?= e($a['username']) ?> &middot; <?= timeAgo($a['created_at']) ?></p>
                    <div><?= nl2br(e($a['body'])) ?></div>
                    <?php if ($a['expires_at']): ?><small class="text-muted d-block mt-1">Expires: <?= formatDate($a['expires_at']) ?></small><?php endif; ?>
                </div>
                <?php if (hasPermission('delete_announcements')): ?>
                <form method="POST" class="ms-2" onsubmit="return confirm('Delete this announcement?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <button type="submit" name="delete_announcement" class="btn btn-sm btn-outline-danger btn-icon"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="announceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5>New Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea name="body" rows="5" class="form-control" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Target Role</label>
                            <select name="target_role" class="form-select">
                                <option value="">All Members</option>
                                <?php $roles = $db->query("SELECT * FROM roles")->fetchAll(); foreach ($roles as $r): ?>
                                    <option value="<?= $r['slug'] ?>"><?= e($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expires On</label>
                            <input type="date" name="expires_at" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3 form-check">
                        <input type="checkbox" name="is_pinned" class="form-check-input" id="pinCheck">
                        <label class="form-check-label" for="pinCheck">Pin this announcement</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_announcement" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Publish</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/views/layouts/footer.php'; ?>

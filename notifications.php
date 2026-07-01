<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
$pageTitle = 'Notifications';

$db = getConnection();
$currentUser = getCurrentUser();
$userId = (int)$_SESSION['user_id'];

if (isset($_POST['mark_read'])) {
    $id = (int)$_POST['id'];
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $userId]);
    exit;
}
if (isset($_POST['mark_all_read'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    setFlash('success', 'All notifications marked as read');
    redirect('notifications.php');
}
if (isset($_POST['delete_all'])) {
    $db->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$userId]);
    setFlash('info', 'All notifications cleared');
    redirect('notifications.php');
}

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$total = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?");
$total->execute([$userId]);
$totalPages = ceil($total->fetchColumn() / $perPage);

$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$notifs->execute([$userId]);
$notifications = $notifs->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Notifications</h4></div>
        <div class="d-flex gap-2">
            <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm"><i class="fas fa-check-double me-1"></i>Mark All Read</button>
            </form>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete all notifications?')">
                <?= csrfField() ?>
                <button type="submit" name="delete_all" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Clear All</button>
            </form>
        </div>
    </div>
    <?php displayFlash(); ?>
    <?php if (empty($notifications)): ?>
        <div class="text-center py-5"><i class="fas fa-bell text-muted" style="font-size:3rem;"></i><p class="mt-2 text-muted">No notifications</p></div>
    <?php endif; ?>
    <?php foreach ($notifications as $n): ?>
        <div class="card mb-2 notif-card <?= $n['is_read'] ? '' : 'border-start border-primary border-3' ?>" data-id="<?= $n['id'] ?>">
            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-<?= $n['type'] === 'urgent' ? 'danger' : ($n['type'] === 'warning' ? 'warning' : 'primary') ?> me-2"><?= ucfirst(e($n['type'] ?? 'info')) ?></span>
                    <?= e($n['message']) ?>
                    <small class="text-muted d-block"><?= timeAgo($n['created_at']) ?></small>
                </div>
                <?php if (!$n['is_read']): ?>
                    <button class="btn btn-sm btn-link mark-read" data-id="<?= $n['id'] ?>">Mark read</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($totalPages > 1): ?>
    <nav><ul class="pagination pagination-sm justify-content-center mt-3">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php
$extraScripts = <<<JS
<script>
$(document).on('click', '.mark-read', function() {
    const btn = $(this); const id = btn.data('id');
    $.post('notifications.php', { mark_read: 1, id: id }).done(function() {
        btn.closest('.notif-card').removeClass('border-start border-primary border-3');
        btn.remove();
    });
});
</script>
JS;
include __DIR__ . '/views/layouts/footer.php'; ?>

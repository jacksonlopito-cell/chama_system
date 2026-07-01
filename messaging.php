<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_messages');
$pageTitle = 'Messages';

$db = getConnection();
$currentUser = getCurrentUser();
$userId = (int)$_SESSION['user_id'];

if (isset($_POST['send_message'])) {
    verifyCsrf();
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO messages (sender_id, subject, body, sent_at) VALUES (?,?,?, NOW())");
        $stmt->execute([$userId, $_POST['subject'], $_POST['body']]);
        $msgId = $db->lastInsertId();
        $recipients = is_array($_POST['receiver_id'] ?? null) ? $_POST['receiver_id'] : [(int)$_POST['receiver_id']];
        $rStmt = $db->prepare("INSERT INTO message_recipients (message_id, recipient_id) VALUES (?,?)");
        foreach ($recipients as $rid) {
            $rStmt->execute([$msgId, (int)$rid]);
        }
        $db->commit();
        logAudit($userId, $_SESSION['username'], 'create', 'messages', $msgId, null, null, 'Sent message');
        setFlash('success', 'Message sent');
    } catch (Exception $e) { $db->rollBack(); setFlash('danger', $e->getMessage()); }
    redirect('messaging.php');
}

$tab = $_GET['tab'] ?? 'inbox';

$users = $db->prepare("SELECT id, username FROM users WHERE id != ? AND status = 'active' AND group_code = ?");
$users->execute([$userId, $_SESSION['group_code']]);
$userList = $users->fetchAll();

if ($tab === 'inbox') {
    $msgs = $db->prepare("SELECT m.*, u.username as sender_name, mr.is_read, mr.read_at FROM messages m JOIN message_recipients mr ON m.id = mr.message_id JOIN users u ON m.sender_id = u.id WHERE mr.recipient_id = ? ORDER BY m.sent_at DESC");
    $msgs->execute([$userId]);
    $messages = $msgs->fetchAll();
} else {
    $msgs = $db->prepare("SELECT m.*, GROUP_CONCAT(u.username SEPARATOR ', ') as receiver_names FROM messages m LEFT JOIN message_recipients mr ON m.id = mr.message_id LEFT JOIN users u ON mr.recipient_id = u.id WHERE m.sender_id = ? GROUP BY m.id ORDER BY m.sent_at DESC");
    $msgs->execute([$userId]);
    $messages = $msgs->fetchAll();
}

if (isset($_GET['read']) && $tab === 'inbox') {
    $db->prepare("UPDATE message_recipients SET is_read=1, read_at=NOW() WHERE message_id=? AND recipient_id=?")->execute([(int)$_GET['read'], $userId]);
}

$unreadCount = $db->prepare("SELECT COUNT(*) FROM message_recipients WHERE recipient_id=? AND is_read=0");
$unreadCount->execute([$userId]);
$uc = $unreadCount->fetchColumn();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Messages</h4></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#msgModal"><i class="fas fa-plus me-1"></i>Compose</button>
    </div>
    <?php displayFlash(); ?>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab === 'inbox' ? 'active' : '' ?>" href="?tab=inbox">Inbox <?php if ($uc > 0): ?><span class="badge bg-danger ms-1"><?= $uc ?></span><?php endif; ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'sent' ? 'active' : '' ?>" href="?tab=sent">Sent</a></li>
    </ul>
    <div class="card">
        <div class="list-group list-group-flush">
            <?php if (empty($messages)): ?>
                <div class="list-group-item text-center text-muted py-4">No messages</div>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <a href="?tab=<?= $tab ?>&read=<?= $m['id'] ?>#msg-<?= $m['id'] ?>" class="list-group-item list-group-item-action <?= ($tab === 'inbox' && !$m['is_read']) ? 'fw-bold' : '' ?>">
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-<?= $tab === 'inbox' ? 'user' : 'users' ?> me-2"></i><?= e($tab === 'inbox' ? $m['sender_name'] : ($m['receiver_names'] ?? 'Unknown')) ?></span>
                        <small class="text-muted"><?= timeAgo($m['sent_at'] ?? $m['created_at']) ?></small>
                    </div>
                    <div class="mt-1"><strong><?= e($m['subject'] ?? '(No subject)') ?></strong></div>
                    <small class="text-muted"><?= e(truncate($m['body'], 120)) ?></small>
                </a>
                <div id="msg-<?= $m['id'] ?>">
                <?php if (isset($_GET['read']) && (int)$_GET['read'] === (int)$m['id']): ?>
                    <div class="p-3 bg-light border-bottom"><?= nl2br(e($m['body'])) ?></div>
                <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="msgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5>Compose Message</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">To <span class="text-danger">*</span></label>
                        <select name="receiver_id" class="form-select searchable-select" required>
                            <option value="">Select recipient...</option>
                            <?php foreach ($userList as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Optional">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea name="body" rows="5" class="form-control" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_message" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/views/layouts/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('manage_homepage');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Contact Messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['delete_message'])) {
        if ($_SESSION['role_id'] != 1) {
            setFlash('danger', 'Only Super Administrators can delete messages.');
            redirect('contact-messages.php');
        }
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
        logAudit($_SESSION['user_id'], $_SESSION['username'], 'delete', 'contact_messages', $id, null, null, 'Deleted contact message');
        setFlash('success', 'Message deleted');
        redirect('contact-messages.php');
    }
}

// Mark as read when viewing details
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
}

// Get messages
$messages = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
$unreadCount = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0 OR is_read IS NULL")->fetchColumn();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div>
            <h4>Contact Messages</h4>
            <p>Messages submitted through the homepage contact form</p>
        </div>
        <span class="badge bg-<?= $unreadCount > 0 ? 'danger' : 'secondary' ?> fs-6">
            <?= $unreadCount ?> unread
        </span>
    </div>
    <?php displayFlash(); ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0" data-table-name="contact-messages">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $m): ?>
                        <tr class="<?= empty($m['is_read']) ? 'fw-bold' : '' ?>">
                            <td>
                                <?php if (empty($m['is_read'])): ?>
                                    <span class="badge bg-danger" title="Unread">New</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary" title="Read">Read</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($m['name']) ?></td>
                            <td><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></td>
                            <td><?= e($m['subject'] ?: '-') ?></td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($m['message']) ?></td>
                            <td class="small"><?= timeAgo($m['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info btn-icon view-message-btn"
                                    data-id="<?= $m['id'] ?>"
                                    data-name="<?= e($m['name']) ?>"
                                    data-email="<?= e($m['email']) ?>"
                                    data-subject="<?= e($m['subject'] ?? '') ?>"
                                    data-date="<?= e($m['created_at']) ?>"
                                    title="View"><i class="fas fa-eye"></i></button>
                                <textarea class="d-none msg-content" id="msg-<?= $m['id'] ?>"><?= e($m['message']) ?></textarea>
                                <?php if ((int)$_SESSION['role_id'] === 1): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this message?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" name="delete_message" class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($messages)): ?>
                        <tr><td colspan="7" class="text-center py-4" style="color: var(--gray-500);">No messages yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>



<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <strong>From:</strong> <span id="vmName"></span>
                </div>
                <div class="mb-2">
                    <strong>Email:</strong> <span id="vmEmail"></span>
                </div>
                <div class="mb-2">
                    <strong>Subject:</strong> <span id="vmSubject"></span>
                </div>
                <div class="mb-2">
                    <strong>Date:</strong> <span id="vmDate"></span>
                </div>
                <hr>
                <div>
                    <strong>Message:</strong>
                    <p id="vmMessage" class="mt-1 mb-0" style="white-space: pre-wrap;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="vmReplyBtn" class="btn btn-primary" target="_blank"><i class="fas fa-reply me-1"></i>Reply via Gmail</a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).on("click", ".view-message-btn", function() {
    var b = $(this);
    $("#vmName").text(b.data("name"));
    $("#vmEmail").text(b.data("email"));
    $("#vmSubject").text(b.data("subject") || "(none)");
    $("#vmDate").text(b.data("date"));
    var msgId = b.data("id");
    var msgText = $("#msg-" + msgId).text();
    $("#vmMessage").text(msgText);
    var gmailSubject = "Re: " + (b.data("subject") || "Contact Form Message");
    var gmailBody = "On " + b.data("date") + ", " + b.data("name") + " wrote:\n\n" + msgText + "\n\n---\n";
    $("#vmReplyBtn").attr("href", "https://mail.google.com/mail/?view=cm&fs=1&to=" + encodeURIComponent(b.data("email")) + "&su=" + encodeURIComponent(gmailSubject) + "&body=" + encodeURIComponent(gmailBody));
    new bootstrap.Modal(document.getElementById("viewMessageModal")).show();
});
</script>
<?php include __DIR__ . '/views/layouts/footer.php'; ?>

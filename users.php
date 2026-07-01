<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('manage_users');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'User Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['save_user'])) {
        try {
            $memberId = (int)($_POST['member_id'] ?? 0);
            $data = [
                'username'   => $_POST['username'],
                'email'      => $_POST['email'],
                'role_id'    => (int)$_POST['role_id'],
                'group_code' => $_POST['group_code'] ?? $_SESSION['group_code'],
                'phone'      => $_POST['phone'] ?? '',
                'member_id'  => $memberId ?: null
            ];
            $id = (int)($_POST['user_id'] ?? 0);
            if ($id) {
                $sets = []; $params = [];
                foreach ($data as $k => $v) { $sets[] = "$k=?"; $params[] = $v; }
                if (!empty($_POST['password'])) { $sets[] = "password=?"; $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT); }
                $params[] = $id;
                $params[] = $_SESSION['group_code'];
                $db->prepare("UPDATE users SET " . implode(',', $sets) . " WHERE id=? AND group_code=?")->execute($params);
                setFlash('success', 'User updated');
            } else {
                $data['password'] = password_hash($_POST['password'] ?? bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
                $cols = implode(',', array_keys($data));
                $vals = implode(',', array_fill(0, count($data), '?'));
                $db->prepare("INSERT INTO users ($cols) VALUES ($vals)")->execute(array_values($data));
                setFlash('success', 'User created');
            }
        } catch (Exception $e) { setFlash('danger', $e->getMessage()); }
        redirect('users.php');
    }
    if (isset($_POST['toggle_status'])) {
        $id = (int)$_POST['id'];
        $u = $db->prepare("SELECT status FROM users WHERE id=? AND group_code=?");
        $u->execute([$id, $_SESSION['group_code']]);
        $currentStatus = $u->fetchColumn();
        if ($currentStatus === false) { setFlash('danger', 'User not found'); redirect('users.php'); }
        $new = $currentStatus === 'active' ? 'suspended' : 'active';
        $db->prepare("UPDATE users SET status=? WHERE id=? AND group_code=?")->execute([$new, $id, $_SESSION['group_code']]);
        logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'users', $id, ['status' => $currentStatus], ['status' => $new], 'User status toggled');
        setFlash('info', 'User status changed');
        redirect('users.php');
    }
    if (isset($_POST['delete_user'])) {
        if ($_SESSION['role_id'] != 1) {
            setFlash('danger', 'Only Super Administrators can delete users.');
            redirect('users.php');
        }
        $id = (int)$_POST['id'];
        if ($id === (int)$_SESSION['user_id']) {
            setFlash('danger', 'You cannot delete your own account.');
            redirect('users.php');
        }
        $stmt = $db->prepare("SELECT id, username, role_id, status FROM users WHERE id=? AND group_code=?");
        $stmt->execute([$id, $_SESSION['group_code']]);
        $target = $stmt->fetch();
        if (!$target) { setFlash('danger', 'User not found.'); redirect('users.php'); }
        if ($target['role_id'] == 1 && $target['status'] === 'active') {
            $saCount = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id=1 AND status='active' AND group_code=? AND id!=?");
            $saCount->execute([$_SESSION['group_code'], $id]);
            if ($saCount->fetchColumn() == 0) {
                setFlash('danger', 'Cannot delete the last active Super Administrator.');
                redirect('users.php');
            }
        }
        $fkChecks = [
            'documents' => ['table' => 'documents', 'column' => 'uploaded_by', 'group' => false],
            'expenses' => ['table' => 'expenses', 'column' => 'recorded_by', 'group' => true],
            'income' => ['table' => 'income', 'column' => 'recorded_by', 'group' => true],
            'journal_entries' => ['table' => 'journal_entries', 'column' => 'created_by', 'group' => false],
        ];
        $blocking = [];
        foreach ($fkChecks as $label => $fk) {
            $sql = "SELECT COUNT(*) FROM {$fk['table']} WHERE {$fk['column']}=?";
            $params = [$id];
            if ($fk['group']) { $sql .= " AND group_code=?"; $params[] = $_SESSION['group_code']; }
            $c = $db->prepare($sql);
            $c->execute($params);
            if ($c->fetchColumn() > 0) $blocking[] = $label;
        }
        if ($blocking) {
            setFlash('danger', 'Cannot delete: user has related records in ' . implode(', ', $blocking) . '. Suspend the user instead.');
            redirect('users.php');
        }
        $db->prepare("DELETE FROM users WHERE id=? AND group_code=?")->execute([$id, $_SESSION['group_code']]);
        logAudit($_SESSION['user_id'], $_SESSION['username'], 'delete', 'users', $id, ['username' => $target['username']], null, 'User deleted');
        setFlash('success', 'User deleted successfully.');
        redirect('users.php');
    }
}

$users = $db->prepare("
    SELECT u.id, u.username, u.email, u.phone, u.role_id, u.group_code, u.status, u.last_login, u.created_at, u.member_id,
           r.name as role_name,
           m.first_name, m.last_name, m.member_no, m.photo as member_photo
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN members m ON u.member_id = m.id
    WHERE u.group_code = ?
    ORDER BY u.created_at DESC
");
$users->execute([$_SESSION['group_code']]);
$users = $users->fetchAll();
$roles = $db->query("SELECT * FROM roles ORDER BY name")->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Users</h4><p>Manage system users and accounts</p></div>
        <button class="btn btn-primary" id="addUserBtn"><i class="fas fa-plus me-1"></i>Add User</button>
    </div>
    <?php displayFlash(); ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0" data-table-name="users">
                    <thead><tr><th>User</th><th>Email</th><th>Member</th><th>Role</th><th>Group</th><th>Last Login</th><th>Status</th><th class="no-sort">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar user-avatar-sm me-2" style="background:var(--primary);color:#fff;">
                                        <?php if (!empty($u['member_photo'])): ?>
                                            <img src="<?= BASE_URL ?>uploads/members/<?= e($u['member_photo']) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:50%;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div><div class="fw-medium"><?= e($u['username']) ?></div><small style="color:var(--gray-500);"><?= e($u['phone'] ?? '') ?></small></div>
                                </div>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <?php if ($u['member_id']): ?>
                                    <small><?= e($u['first_name'] . ' ' . $u['last_name']) ?><br><span class="text-muted"><?= e($u['member_no']) ?></span></small>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-primary"><?= e($u['role_name']) ?></span></td>
                            <td><?= e($u['group_code']) ?></td>
                            <td><?= $u['last_login'] ? timeAgo($u['last_login']) : 'Never' ?></td>
                            <td><?= statusBadge($u['status']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning btn-icon edit-user-btn"
                                    data-id="<?= $u['id'] ?>"
                                    data-username="<?= e($u['username']) ?>"
                                    data-email="<?= e($u['email']) ?>"
                                    data-role-id="<?= $u['role_id'] ?>"
                                    data-phone="<?= e($u['phone'] ?? '') ?>"
                                    data-group-code="<?= e($u['group_code']) ?>"
                                    data-member-id="<?= $u['member_id'] ?? '' ?>"
                                    title="Edit"><i class="fas fa-edit"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Toggle user status?')">
                                    <?= csrfField() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-<?= $u['status'] === 'active' ? 'danger' : 'success' ?> btn-icon" title="<?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>"><i class="fas fa-<?= $u['status'] === 'active' ? 'ban' : 'check' ?>"></i></button>
                                </form>
                                <?php if ((int)$_SESSION['role_id'] === 1 && (int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                <button class="btn btn-sm btn-outline-danger btn-icon delete-user-btn"
                                    data-id="<?= $u['id'] ?>"
                                    data-username="<?= e($u['username']) ?>"
                                    title="Delete"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="userModalTitle">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <?= csrfField() ?><input type="hidden" name="user_id" id="userId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Select Member <span class="text-danger">*</span></label>
                            <select name="member_id" id="memberSearch" class="form-select" style="width:100%;">
                                <option value="">Search for an active member...</option>
                            </select>
                            <small class="text-muted">Only active members without a linked user account are shown.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" id="memberFirstName" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" id="memberLastName" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="uEmail" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="uPhone" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="uName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="uRole" class="form-select searchable-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Group Code</label>
                            <input type="text" name="group_code" id="uGroup" class="form-control" value="<?= e($_SESSION['group_code']) ?>">
                        </div>
                        <div class="col-md-6" id="passwordField">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                            <small class="text-muted">Min 8 characters with uppercase, lowercase, number, special char</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_user" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete <strong id="deleteUserName"></strong>?</p>
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-circle me-1"></i>This action cannot be undone. All access for this user will be revoked immediately.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline" id="deleteUserForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" id="deleteUserId">
                    <button type="submit" name="delete_user" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$groupCode = $_SESSION['group_code'];
$extraScripts = <<<JS
<script>
$(document).ready(function() {
    // Initialize Select2 for member search
    $('#memberSearch').select2({
        dropdownParent: $('#userModal'),
        ajax: {
            url: BASE_URL + 'ajax/search-members.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return data; },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: 'Search for an active member...'
    });

    // Auto-populate fields when member is selected
    $('#memberSearch').on('change', function() {
        var data = $(this).select2('data')[0];
        if (data && data.id) {
            $('#memberFirstName').val(data.first_name || '');
            $('#memberLastName').val(data.last_name || '');
            $('#uEmail').val(data.email || '');
            $('#uPhone').val(data.phone || '');
            if (!data.email) $('#uEmail').val(data.first_name.toLowerCase() + '.' + data.last_name.toLowerCase() + '@example.com');
        }
    });

    $('#addUserBtn').on('click', function() {
        $('#userModalTitle').text('Add User');
        $('#userId').val(0);
        $('#userModal form')[0].reset();
        $('#memberSearch').val(null).trigger('change');
        $('#memberFirstName').val('');
        $('#memberLastName').val('');
        $('#uRole').val('');
        $('#uGroup').val('$groupCode');
        $('#passwordField input').prop('required', true).val('');
        $('#passwordField label').html('Password <span class="text-danger">*</span>');
        $('#passwordField input').attr('placeholder', 'Leave blank to keep current password');
        $('#memberSearch').prop('disabled', false);
        new bootstrap.Modal(document.getElementById('userModal')).show();
    });

    $(document).on('click', '.edit-user-btn', function() {
        var \$btn = $(this);
        $('#userModalTitle').text('Edit User');
        $('#userId').val(\$btn.data('id'));
        $('#uName').val(\$btn.data('username'));
        $('#uEmail').val(\$btn.data('email'));
        $('#uRole').val(\$btn.data('role-id'));
        $('#uPhone').val(\$btn.data('phone'));
        $('#uGroup').val(\$btn.data('group-code'));
        $('#passwordField input').prop('required', false).val('');
        $('#passwordField label').html('Password');
        $('#passwordField input').attr('placeholder', 'Leave blank to keep current');

        // Disable member search in edit mode & show current member info
        var mid = \$btn.data('member-id');
        if (mid) {
            // Create a static option for the existing member
            var text = \$btn.closest('tr').find('td:eq(2)').text().trim();
            var option = new Option(text, mid, true, true);
            $('#memberSearch').append(option).trigger('change');
        } else {
            $('#memberSearch').val(null).trigger('change');
        }
        $('#memberSearch').prop('disabled', true);

        new bootstrap.Modal(document.getElementById('userModal')).show();
    });

    $('#userModal').on('hidden.bs.modal', function() {
        $('#memberSearch').prop('disabled', false);
        if ($('#userId').val() === '0') { $('#userModalTitle').text('Add User'); }
    });

    $(document).on('click', '.delete-user-btn', function() {
        var \$btn = $(this);
        $('#deleteUserId').val(\$btn.data('id'));
        $('#deleteUserName').text(\$btn.data('username'));
        new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
    });
});
</script>
JS;
include __DIR__ . '/views/layouts/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'My Profile';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $errors = [];

        if (empty($username)) $errors[] = 'Username is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!validateEmail($email)) $errors[] = 'Invalid email format';

        if (empty($errors)) {
            try {
                $check = $db->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $check->execute([$username, $email, $userId]);
                if ($check->fetchColumn() > 0) {
                    $errors[] = 'Username or email already taken';
                } else {
                    $photo = $currentUser['photo'];
                    if (!empty($_FILES['photo']['name'])) {
                        $uploaded = uploadFile($_FILES['photo'], ROOT_PATH . 'uploads/members', ['jpg', 'jpeg', 'png', 'gif']);
                        if ($uploaded) $photo = $uploaded;
                    }

                    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, phone = ?, photo = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $phone, $photo, $userId]);

                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $_SESSION['photo'] = $photo;

                    logAudit($userId, $username, 'update', 'users', $userId, null, ['username' => $username], 'Updated profile');
                    setFlash('success', 'Profile updated successfully');
                    redirect('profile.php');
                }
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            setFlash('danger', implode('<br>', $errors));
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $errors = [];

        if (empty($currentPassword)) $errors[] = 'Current password is required';
        if (!password_verify($currentPassword, $currentUser['password'])) $errors[] = 'Current password is incorrect';

        $strength = isPasswordStrong($newPassword);
        if (!$strength['valid']) {
            $errors = array_merge($errors, $strength['errors']);
        }
        if ($newPassword !== $confirmPassword) $errors[] = 'Passwords do not match';

        if (empty($errors)) {
            try {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $userId]);

                logAudit($userId, $currentUser['username'], 'update', 'users', $userId, null, null, 'Changed password');
                setFlash('success', 'Password changed successfully');
                redirect('profile.php');
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            setFlash('danger', implode('<br>', $errors));
        }
    }
}

$currentUser = getCurrentUser();

// Fetch linked member info
$memberInfo = $_SESSION['member_name'] ? ['first_name' => explode(' ', $_SESSION['member_name'], 2)[0], 'last_name' => explode(' ', $_SESSION['member_name'], 2)[1] ?? '', 'member_no' => $_SESSION['member_no'] ?? '', 'photo' => $_SESSION['photo'] ?? ''] : null;
if ($currentUser['member_id']) {
    $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$currentUser['member_id']]);
    $dbMember = $stmt->fetch();
    if ($dbMember) $memberInfo = $dbMember;
}

$stmt = $db->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$userId]);
$activities = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM sessions WHERE user_id = ? ORDER BY last_activity DESC LIMIT 10");
$stmt->execute([$userId]);
$sessions = $stmt->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4>My Profile</h4>
            <p>Manage your account details and security</p>
        </div>
    </div>

    <?php displayFlash(); ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="user-avatar user-avatar-lg mx-auto mb-3" style="width: 100px; height: 100px;">
                        <?php
                        $avatarSrc = $memberInfo['photo'] ?? $currentUser['photo'] ?? '';
                        if (!empty($avatarSrc)): ?>
                            <img src="<?= BASE_URL ?>uploads/members/<?= e($avatarSrc) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?= strtoupper(substr($currentUser['username'] ?? 'U', 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <h5><?= e($currentUser['username']) ?></h5>
                    <?php if ($memberInfo): ?>
                        <p class="mb-0"><?= e($memberInfo['first_name'] ?? '') . ' ' . e($memberInfo['last_name'] ?? '') ?></p>
                    <?php endif; ?>
                    <p class="text-muted small"><?= e($currentUser['role_name'] ?? '') ?></p>
                    <hr>
                    <div class="text-start small">
                        <?php if ($memberInfo): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Member No:</span>
                            <span><?= e($memberInfo['member_no'] ?? '-') ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Email:</span>
                            <span><?= e($currentUser['email']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Phone:</span>
                            <span><?= e($currentUser['phone'] ?? '-') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Status:</span>
                            <span><?= statusBadge($currentUser['status']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Last Login:</span>
                            <span><?= $currentUser['last_login'] ? formatDateTime($currentUser['last_login']) : '-' ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Group:</span>
                            <span><?= e($currentUser['group_code']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($memberInfo): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="user-avatar user-avatar-sm" style="width:28px;height:28px;font-size:12px;">
                            <?php if (!empty($memberInfo['photo'])): ?>
                                <img src="<?= BASE_URL ?>uploads/members/<?= e($memberInfo['photo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                            <?php else: ?>
                                <?= strtoupper(substr($memberInfo['first_name'] ?? 'M', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <span>Linked Member</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-start small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Name:</span>
                            <span><?= e($memberInfo['first_name'] . ' ' . $memberInfo['last_name']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Member No:</span>
                            <span><?= e($memberInfo['member_no']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Phone:</span>
                            <span><?= e($memberInfo['phone']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Status:</span>
                            <span><?= statusBadge($memberInfo['status'] ?? 'active') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-user-edit me-2" style="color: var(--secondary);"></i>Edit Profile</div>
                <div class="card-body">
                    <form action="profile.php" method="POST" enctype="multipart/form-data" data-validate>
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" value="<?= e($currentUser['username']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" value="<?= e($currentUser['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= e($currentUser['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Photo</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
                                <?php if ($currentUser['photo']): ?>
                                    <small class="d-block mt-1">
                                        <img src="<?= BASE_URL ?>uploads/members/<?= e($currentUser['photo']) ?>" style="height: 40px; width: 40px; object-fit: cover; border-radius: 50%;">
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-lock me-2" style="color: var(--secondary);"></i>Change Password</div>
                <div class="card-body">
                    <form action="profile.php" method="POST" data-validate>
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-2 small text-muted">
                            <i class="fas fa-info-circle me-1"></i>Password must be at least 8 characters with uppercase, lowercase, number, and special character.
                        </div>
                        <div class="mt-3">
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key me-1"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-clock me-2" style="color: var(--secondary);"></i>Activity History</div>
                <div class="card-body p-0">
                    <?php if (empty($activities)): ?>
                        <div class="text-center py-4" style="color: var(--gray-500);">
                            <i class="fas fa-inbox mb-2" style="font-size: 2rem;"></i>
                            <p class="small mb-0">No activity recorded</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>IP</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $act): ?>
                                        <tr>
                                            <td><span class="badge bg-info text-dark"><?= e($act['activity_type']) ?></span></td>
                                            <td style="font-size: 0.8125rem;"><?= e($act['description']) ?></td>
                                            <td style="font-size: 0.75rem;"><?= e($act['ip_address'] ?? '-') ?></td>
                                            <td style="font-size: 0.75rem;"><?= timeAgo($act['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-history me-2" style="color: var(--secondary);"></i>Login History</div>
                <div class="card-body p-0">
                    <?php if (empty($sessions)): ?>
                        <div class="text-center py-4" style="color: var(--gray-500);">
                            <i class="fas fa-history mb-2" style="font-size: 2rem;"></i>
                            <p class="small mb-0">No login sessions recorded</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>IP Address</th>
                                        <th>Browser</th>
                                        <th>Last Activity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $s): ?>
                                        <tr>
                                            <td style="font-size: 0.8125rem;"><?= e($s['ip_address'] ?? '-') ?></td>
                                            <td style="font-size: 0.75rem;">
                                                <?php
                                                $ua = $s['user_agent'] ?? '';
                                                if (strpos($ua, 'Chrome') !== false) echo '<i class="fab fa-chrome me-1"></i>Chrome';
                                                elseif (strpos($ua, 'Firefox') !== false) echo '<i class="fab fa-firefox me-1"></i>Firefox';
                                                elseif (strpos($ua, 'Safari') !== false) echo '<i class="fab fa-safari me-1"></i>Safari';
                                                elseif (strpos($ua, 'Edge') !== false) echo '<i class="fab fa-edge me-1"></i>Edge';
                                                else echo e(substr($ua, 0, 50));
                                                ?>
                                            </td>
                                            <td style="font-size: 0.75rem;"><?= timeAgo($s['last_activity']) ?></td>
                                            <td><?= $s['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Expired</span>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/views/layouts/footer.php';
?>

<?php
require_once __DIR__ . '/includes/config.php';

$db = getConnection();
$pageTitle = 'Reset Password';
$token = $_GET['token'] ?? '';
$valid = false; $email = '';

if (!empty($token)) {
    $stmt = $db->prepare("SELECT email FROM password_resets WHERE token=? AND expires_at>NOW() AND used=0");
    $stmt->execute([$token]);
    $email = $stmt->fetchColumn();
    $valid = !empty($email);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $db->prepare("SELECT email FROM password_resets WHERE token=? AND expires_at>NOW() AND used=0");
    $stmt->execute([$token]);
    $email = $stmt->fetchColumn();

    if (empty($email)) {
        setFlash('danger', 'Invalid or expired reset token');
    } elseif (strlen($password) < 8) {
        setFlash('danger', 'Password must be at least 8 characters');
    } elseif ($password !== $confirm) {
        setFlash('danger', 'Passwords do not match');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=? WHERE email=?")->execute([$hash, $email]);
        $db->prepare("UPDATE password_resets SET used=1 WHERE token=?")->execute([$token]);
        setFlash('success', 'Password reset successful. Please login.');
        redirect('login.php');
    }
}

include __DIR__ . '/views/layouts/header.php';
?>
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="card" style="width:100%;max-width:480px;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="mb-2" style="width:48px;height:48px;background:var(--primary);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;">
                    <i class="fas fa-lock text-white"></i>
                </div>
                <h4>Reset Password</h4>
            </div>
            <?php displayFlash(); ?>
            <?php if (!$valid && empty($_POST['token'])): ?>
                <div class="alert alert-danger">Invalid or expired reset link.</div>
                <a href="forgot-password.php" class="btn btn-primary w-100">Request New Link</a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Min 8 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Reset Password</button>
                </form>
            <?php endif; ?>
            <div class="text-center mt-3"><a href="login.php" class="text-decoration-none small">Back to Login</a></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/views/layouts/footer.php'; ?>

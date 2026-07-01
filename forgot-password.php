<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

$pageTitle = 'Forgot Password | Chama Portal';
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db = getConnection();

            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_NUM);

            if ($user) {
                $userId = $user[0];
                $userName = $user[1];

                $stmt = $db->prepare("SELECT COUNT(*) FROM password_resets WHERE email=? AND used=0 AND expires_at>NOW()");
                $stmt->execute([$email]);
                $activeTokens = (int)$stmt->fetchColumn();

                if ($activeTokens >= 3) {
                    error_log("Password reset rate limit hit for $email ($activeTokens active tokens)");
                } else {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600);

                    $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$email, $token, $expires]);

                    $resetLink = BASE_URL . "reset-password.php?token=$token";

                    $htmlBody = '
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;padding:0;background:#f5f7fa}
.container{max-width:520px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.header{background:#0d6efd;padding:32px 40px;text-align:center}
.header h1{margin:0;color:#fff;font-size:20px;font-weight:700}
.body{padding:32px 40px;color:#333;font-size:15px;line-height:1.6}
.btn{display:inline-block;background:#0d6efd;color:#fff !important;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:600;font-size:15px;margin:16px 0}
.footer{text-align:center;padding:24px 40px;font-size:12px;color:#999;border-top:1px solid #eee}
</style></head><body>
<div class="container">
<div class="header"><h1>Password Reset</h1></div>
<div class="body">
<p>Hi ' . e($userName) . ',</p>
<p>We received a request to reset your Chama System password. Click below to set a new one. This link expires in 1 hour.</p>
<p style="text-align:center"><a href="' . $resetLink . '" class="btn">Reset Password</a></p>
<p style="font-size:13px;color:#888">If you didn\'t request this, ignore this email. Your password will stay the same.<br>
Or paste this link: ' . $resetLink . '</p>
</div>
<div class="footer">Chama System &bull; Secure Password Recovery</div>
</div>
</body></html>';

                $textBody = "Hi $userName,\n\nWe received a request to reset your Chama System password. Click the link below to set a new one. This link expires in 1 hour.\n\n$resetLink\n\nIf you didn't request this, ignore this email.\n\nChama System";

                $result = sendEmail($email, 'Password Reset – Chama System', $htmlBody, $textBody);
                if (!$result['success']) {
                    error_log("Password reset email FAILED to $email: " . $result['error']);
                }
            }

            $message = 'If the email exists in our system, a reset link has been sent.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="preloader">
        <div class="spinner"></div>
    </div>

    <div class="login-page">
        <div class="login-form-section">
            <div class="login-form-container">
                <div class="mb-4 text-center text-lg-start">
                    <a href="index.php" class="text-decoration-none">
                        <h4 style="color: var(--primary); font-weight: 800;">
                            <i class="fas fa-hand-holding-usd me-2"></i>Chama System
                        </h4>
                    </a>
                </div>

                <div class="mb-4">
                    <h1 class="text-title-sm mb-1" style="color: var(--gray-800);">Forgot Password</h1>
                    <p style="color: var(--gray-500); font-size: 0.9375rem;">Enter your email and we'll send you a reset link.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <div><?= e($message) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!$message): ?>
                <form action="forgot-password.php" method="POST" data-validate>
                    <?= csrfField() ?>

                    <div class="mb-4">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                    </button>

                    <p class="text-center mb-0" style="font-size: 0.875rem; color: var(--gray-500);">
                        <a href="login.php" style="color: var(--secondary);">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </p>
                </form>
                <?php else: ?>
                    <p class="text-center">
                        <a href="login.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="login-brand-section">
            <div class="login-brand-content">
                <div class="mb-4">
                    <h1>CHAMA PORTAL</h1>
                    <p>Secure password recovery</p>
                </div>
            </div>
        </div>
    </div>

    <button id="darkModeToggle" class="dark-mode-toggle" title="Toggle Dark Mode">
        <i class="fas fa-moon"></i>
    </button>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

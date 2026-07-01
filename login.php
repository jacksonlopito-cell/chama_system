<?php
/**
 * Login Page
 */
require_once __DIR__ . '/includes/config.php';

// Check remember me cookie
checkRememberMe();

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

$pageTitle = 'Sign In | Chama Portal';
$allSettings = getAllSettings();
$siteLogo = $allSettings['site_logo'] ?? null;
$siteName = $allSettings['site_name'] ?? 'Chama System';

// Handle login form submission
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $groupCode = trim($_POST['group_code'] ?? '');

    if (empty($email) || empty($password) || empty($groupCode)) {
        $error = 'Please fill in all fields.';
    } else {
        $result = authenticateUser($email, $password, $groupCode);
        if ($result['success']) {
            if (isset($_POST['remember'])) {
                $db = getConnection();
                $token = bin2hex(random_bytes(32));
                $db->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$token, $_SESSION['user_id']]);
                setcookie('remember_me', $_SESSION['user_id'] . ':' . $token, time() + 2592000, '/', '', false, true);
            }
            logActivity($_SESSION['user_id'], 'login', 'User logged in successfully');
            redirect(BASE_URL . 'dashboard.php');
        } else {
            $error = $result['message'];
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

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Main Stylesheet -->
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        body {
            background: #fff;
            min-height: 100vh;
        }
    </style>
</head>
<body>

    <!-- ===== Preloader ===== -->
    <div class="preloader">
        <div class="spinner"></div>
    </div>

    <!-- ===== Login Page ===== -->
    <div class="login-page">

        <!-- Login Form Section -->
        <div class="login-form-section">
            <div class="login-form-container">
                <div class="mb-4 text-center text-lg-start">
                    <a href="index.php" class="text-decoration-none d-inline-block">
                        <?php if ($siteLogo): ?>
                            <img src="<?= BASE_URL ?>uploads/settings/<?= e($siteLogo) ?>" alt="<?= e($siteName) ?>" style="max-height: 50px;">
                        <?php else: ?>
                            <h4 style="color: var(--primary); font-weight: 800;">
                                <i class="fas fa-hand-holding-usd me-2"></i><?= e($siteName) ?>
                            </h4>
                        <?php endif; ?>
                    </a>
                    <div class="mt-2">
                        <a href="index.php" style="font-size: 0.8125rem; color: var(--gray-500);">
                            <i class="fas fa-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                </div>

                <div class="mb-4">
                    <h1 class="text-title-sm mb-1" style="color: var(--gray-800);">Sign In</h1>
                    <p style="color: var(--gray-500); font-size: 0.9375rem;">Welcome back! Please enter your details.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php displayFlash(); ?>

                <form action="login.php" method="POST" data-validate>
                    <?= csrfField() ?>

                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email"
                                   name="email"
                                   class="form-control"
                                   placeholder="info@gmail.com"
                                   value="<?= e($_POST['email'] ?? '') ?>"
                                   required
                                   autocomplete="email">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label class="form-label">
                            Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="form-control"
                                   placeholder="Enter your password"
                                   required
                                   autocomplete="current-password">
                            <button class="btn btn-outline-secondary password-toggle" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Group Code -->
                    <div class="mb-3">
                        <label class="form-label">
                            Group Code <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-users"></i></span>
                            <input type="text"
                                   name="group_code"
                                   class="form-control"
                                   placeholder="Enter your Group Code"
                                   value="<?= e($_POST['group_code'] ?? '') ?>"
                                   required>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember" style="font-size: 0.875rem;">Remember me</label>
                        </div>
                        <a href="forgot-password.php" style="font-size: 0.875rem; color: var(--secondary);">
                            Forgot password?
                        </a>
                    </div>

                    <!-- Submit -->
                    <button type="submit" name="login" class="btn btn-primary w-100 py-2.5 mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>

                    <p class="text-center mb-0" style="font-size: 0.875rem; color: var(--gray-500);">
                        Don't have an account?
                        <a href="register.php" style="color: var(--secondary); font-weight: 500;">Register here</a>
                        &middot;
                        <a href="index.php#contact" style="color: var(--gray-500);">Contact us</a>
                    </p>
                </form>

                <!-- Demo Credentials (visible only on localhost, remove in production) -->
                <?php if ($_SERVER['HTTP_HOST'] === 'localhost' || str_contains($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1')): ?>
                <div class="mt-4 p-3" style="background: var(--gray-50); border-radius: var(--border-radius-sm);">
                    <p class="mb-2" style="font-size: 0.8125rem; font-weight: 600; color: var(--gray-600);">
                        <i class="fas fa-info-circle me-1"></i>Local Development
                    </p>
                    <div style="font-size: 0.75rem; color: var(--gray-500);">
                        <div><strong>Group Code:</strong> CHAMA001</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Brand Section -->
        <div class="login-brand-section">
            <div class="login-brand-content">
                <div class="mb-4">
                    <h1>CHAMA PORTAL</h1>
                    <p>Manage your basic group affairs with this portal</p>
                </div>
                <div class="mt-5">
                    <i class="fas fa-chart-line" style="font-size: 6rem; opacity: 0.15; color: #fff;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Dark Mode Toggle ===== -->
    <button id="darkModeToggle" class="dark-mode-toggle" title="Toggle Dark Mode">
        <i class="fas fa-moon"></i>
    </button>

    <!-- ===== Scripts ===== -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

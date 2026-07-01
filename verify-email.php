<?php
/**
 * Email Verification Handler
 *
 * Handles verification link clicks from email.
 * Validates token, updates member status, and redirects.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/member_workflow.php';

$pageTitle = 'Email Verification';
$message = '';
$success = false;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = 'Invalid verification link. No token provided.';
} else {
    $result = verifyEmailToken($token);

    if ($result['success']) {
        $success = true;
        if (!empty($result['auto_activated'])) {
            $message = 'Email verified successfully! Your account is now active.';
        } else {
            $message = 'Email verified successfully! Your registration is now pending administrator approval. You will be notified once approved.';
        }
    } else {
        $message = $result['error'] ?? 'Verification failed. Please try again.';
    }
}

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<div class="main-content">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card text-center p-5">
                <div style="font-size: 4rem; color: <?= $success ? '#198754' : '#dc3545' ?>; margin-bottom: 1rem;">
                    <i class="fas <?= $success ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                </div>
                <h3><?= $success ? 'Verification Successful' : 'Verification Failed' ?></h3>
                <p class="text-muted"><?= e($message) ?></p>

                <?php if ($success): ?>
                    <p class="mt-3">
                        <?php if (!empty($result['auto_activated'])): ?>
                            <a href="login.php" class="btn btn-primary">Proceed to Login</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-outline-primary">Go to Login</a>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="mt-3">
                        <a href="login.php" class="btn btn-outline-secondary">Back to Login</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>

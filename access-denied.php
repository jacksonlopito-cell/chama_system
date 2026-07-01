<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$pageTitle = 'Access Denied';
include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="d-flex flex-column align-items-center justify-content-center" style="min-height: 60vh;">
        <div class="text-center">
            <i class="fas fa-shield-alt" style="font-size: 4rem; color: var(--danger);"></i>
            <h3 class="mt-3">Access Denied</h3>
            <p class="text-muted">You do not have permission to access this page.</p>
            <p class="text-muted small">If you believe this is an error, please contact your administrator.</p>
            <a href="dashboard.php" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/views/layouts/footer.php'; ?>

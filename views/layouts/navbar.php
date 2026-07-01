<?php
/**
 * Top Navigation Bar
 * @var array $currentUser - from dashboard.php
 */
$unreadCount = countUnreadNotifications();
$recentNotifications = getRecentNotifications();
?>
<!-- ===== Top Navbar ===== -->
<nav class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <button class="sidebar-toggle d-lg-none me-2" id="mobileSidebarToggle" title="Menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" id="globalSearch" placeholder="Search members, loans, contributions..." autocomplete="off">
        </div>
    </div>

    <div class="topbar-right">
        <!-- Dark Mode Toggle -->
        <button class="nav-link" id="darkModeToggleTop" title="Toggle Dark Mode">
            <i class="fas fa-moon"></i>
        </button>

        <!-- Notifications -->
        <div class="nav-item dropdown">
            <button class="nav-link" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge-dot"></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <div class="dropdown-header d-flex justify-content-between align-items-center">
                    <span>Notifications</span>
                    <?php if ($unreadCount > 0): ?>
                        <a href="#" class="text-decoration-none small" style="color: var(--secondary);">Mark all read</a>
                    <?php endif; ?>
                </div>
                <?php if (empty($recentNotifications)): ?>
                    <div class="text-center py-3" style="color: var(--gray-500); font-size: 0.8125rem;">
                        <i class="fas fa-bell-slash mb-1 d-block" style="font-size: 1.5rem;"></i>
                        No notifications
                    </div>
                <?php else: ?>
                    <?php foreach ($recentNotifications as $notif): ?>
                        <a href="<?= e($notif['link'] ?? '#') ?>" class="dropdown-item <?= $notif['is_read'] ? '' : 'fw-bold' ?>">
                            <div class="d-flex">
                                <i class="fas fa-circle me-2 mt-1" style="font-size: 0.5rem; color: <?= $notif['is_read'] ? 'var(--gray-300)' : 'var(--secondary)' ?>;"></i>
                                <div>
                                    <div style="font-size: 0.8125rem;"><?= e($notif['title']) ?></div>
                                    <small style="color: var(--gray-500);"><?= timeAgo($notif['created_at']) ?></small>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?= BASE_URL ?>notifications.php" class="dropdown-item text-center small" style="color: var(--secondary);">
                    View All Notifications
                </a>
            </div>
        </div>

        <!-- Messages -->
        <div class="nav-item d-none d-sm-block">
            <a href="<?= BASE_URL ?>messaging.php" class="nav-link" title="Messages">
                <i class="fas fa-envelope"></i>
            </a>
        </div>

        <!-- Fullscreen -->
        <button class="nav-link d-none d-md-block" id="fullscreenToggle" title="Toggle Fullscreen">
            <i class="fas fa-expand"></i>
        </button>

        <!-- Profile -->
        <div class="nav-item dropdown ms-2">
            <button class="nav-link d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar me-2">
                    <?php if (!empty($currentUser['photo'])): ?>
                        <img src="<?= BASE_URL ?>uploads/members/<?= e($currentUser['photo']) ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?= strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="d-none d-md-block text-start">
                    <div style="font-size: 0.8125rem; font-weight: 600; line-height: 1.2;"><?= e($currentUser['username'] ?? 'User') ?></div>
                    <small style="font-size: 0.6875rem; color: var(--gray-500);"><?= e($currentUser['role_name'] ?? '') ?></small>
                </div>
                <i class="fas fa-chevron-down ms-2" style="font-size: 0.75rem; color: var(--gray-400);"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
                <div class="dropdown-header py-2">
                    <div class="fw-bold" style="font-size: 0.875rem;"><?= e($currentUser['username'] ?? '') ?></div>
                    <small style="color: var(--gray-500);"><?= e($currentUser['email'] ?? '') ?></small>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?= BASE_URL ?>profile.php" class="dropdown-item"><i class="fas fa-user me-2" style="width: 16px;"></i>My Profile</a>
                <?php if (hasPermission('view_settings')): ?>
                <a href="<?= BASE_URL ?>settings.php" class="dropdown-item"><i class="fas fa-cog me-2" style="width: 16px;"></i>Settings</a>
                <?php endif; ?>
                <?php if (hasPermission('view_audit_logs')): ?>
                <a href="<?= BASE_URL ?>audit-logs.php" class="dropdown-item"><i class="fas fa-history me-2" style="width: 16px;"></i>Activity Log</a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?= BASE_URL ?>logout.php" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt me-2" style="width: 16px;"></i>Sign Out
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay"></div>

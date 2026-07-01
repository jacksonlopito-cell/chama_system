<?php
/**
 * Sidebar Navigation
 */
$roleSlug = $_SESSION['role_slug'] ?? 'member';
$u = BASE_URL;
?>
<!-- ===== Sidebar ===== -->
<?php
$siteLogo = null;
$siteName = 'Chama System';
if (function_exists('getAllSettings')) {
    $allS = getAllSettings();
    $siteLogo = $allS['site_logo'] ?? null;
    $siteName = $allS['site_name'] ?? 'Chama System';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php if ($siteLogo): ?>
            <img src="<?= BASE_URL ?>uploads/settings/<?= e($siteLogo) ?>" alt="<?= e($siteName) ?>" style="max-height: 36px; max-width: 140px; object-fit: contain;">
        <?php else: ?>
            <i class="fas fa-hand-holding-usd" style="font-size: 1.5rem; color: var(--secondary);"></i>
            <span class="ms-2"><?= e($siteName) ?></span>
        <?php endif; ?>
    </div>

    <div class="sidebar-menu">
        <div class="menu-label">Main Menu</div>

        <a href="<?= $u ?>dashboard.php" class="menu-item <?= basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span class="menu-text">Dashboard</span>
        </a>

        <?php if (hasPermission('view_members')): ?>
        <a href="<?= $u ?>members.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'members') !== false ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span class="menu-text">Members</span>
        </a>
        <?php endif; ?>

        <div class="menu-label">Finance</div>

        <?php if (hasPermission('view_contributions')): ?>
        <a href="<?= $u ?>contributions.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'contributions') !== false ? 'active' : '' ?>">
            <i class="fas fa-piggy-bank"></i>
            <span class="menu-text">Contributions</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('view_shares')): ?>
        <a href="javascript:void(0)" class="menu-item has-submenu <?= strpos($_SERVER['SCRIPT_NAME'], 'shares') !== false || strpos($_SERVER['SCRIPT_NAME'], 'share-products') !== false || strpos($_SERVER['SCRIPT_NAME'], 'dividends') !== false ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i>
            <span class="menu-text">Shares</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
        <div class="submenu">
            <a href="<?= $u ?>share-products.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'share-products') !== false ? 'active' : '' ?>">Products</a>
            <a href="<?= $u ?>shares.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'shares') !== false && strpos($_SERVER['SCRIPT_NAME'], 'share-products') === false ? 'active' : '' ?>">Purchases</a>
            <a href="<?= $u ?>dividends.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'dividends') !== false ? 'active' : '' ?>">Dividends</a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('view_loans')): ?>
        <a href="javascript:void(0)" class="menu-item has-submenu">
            <i class="fas fa-hand-holding-usd"></i>
            <span class="menu-text">Loans</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
        <div class="submenu">
            <?php if (hasPermission('edit_loans')): ?>
            <a href="<?= $u ?>loan-products.php" class="menu-item">Loan Products</a>
            <?php endif; ?>
            <a href="<?= $u ?>loan-applications.php" class="menu-item">Applications</a>
            <a href="<?= $u ?>loans.php" class="menu-item">Active Loans</a>
            <a href="<?= $u ?>loan-repayments.php" class="menu-item">Repayments</a>
        </div>
        <?php endif; ?>

        <div class="menu-label">Organization</div>

        <?php if (hasPermission('view_meetings')): ?>
        <a href="javascript:void(0)" class="menu-item has-submenu">
            <i class="fas fa-calendar-check"></i>
            <span class="menu-text">Meetings</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
        <div class="submenu">
            <a href="<?= $u ?>meetings.php" class="menu-item">All Meetings</a>
            <?php if (hasPermission('create_meetings')): ?>
            <a href="<?= $u ?>meetings.php?action=create" class="menu-item">Schedule Meeting</a>
            <?php endif; ?>
            <a href="<?= $u ?>meetings.php" class="menu-item">Attendance</a>
        </div>
        <?php endif; ?>

        <div class="menu-label">Communication</div>

        <?php if (hasPermission('view_messages')): ?>
        <a href="<?= $u ?>messaging.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'messaging') !== false ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i>
            <span class="menu-text">Messaging</span>
            <span class="menu-badge">New</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('view_announcements')): ?>
        <a href="<?= $u ?>announcements.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'announcements') !== false ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i>
            <span class="menu-text">Announcements</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('manage_homepage')): ?>
        <a href="<?= $u ?>contact-messages.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'contact-messages') !== false ? 'active' : '' ?>">
            <i class="fas fa-inbox"></i>
            <span class="menu-text">Contact Inbox</span>
        </a>
        <?php endif; ?>

        <div class="menu-label">Management</div>

        <?php if (hasPermission('view_accounting')): ?>
        <a href="javascript:void(0)" class="menu-item has-submenu">
            <i class="fas fa-book"></i>
            <span class="menu-text">Accounting</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
        <div class="submenu">
            <a href="<?= $u ?>accounting/expenses.php" class="menu-item">Expenses</a>
            <a href="<?= $u ?>accounting/income.php" class="menu-item">Income</a>
            <a href="<?= $u ?>accounting/journal.php" class="menu-item">Journal</a>
            <a href="<?= $u ?>accounting/ledger.php" class="menu-item">Ledger</a>
            <a href="<?= $u ?>accounting/trial-balance.php" class="menu-item">Trial Balance</a>
            <a href="<?= $u ?>reports/financial.php" class="menu-item">Financial Reports</a>
            <a href="<?= $u ?>accounting/bank-accounts.php" class="menu-item">Bank Accounts</a>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('view_reports')): ?>
        <a href="javascript:void(0)" class="menu-item has-submenu">
            <i class="fas fa-file-alt"></i>
            <span class="menu-text">Reports</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
        <div class="submenu">
            <a href="<?= $u ?>reports/contributions.php" class="menu-item">Contribution Report</a>
            <a href="<?= $u ?>reports/loans.php" class="menu-item">Loan Report</a>
            <a href="<?= $u ?>reports/members.php" class="menu-item">Member Report</a>
            <a href="<?= $u ?>reports/meetings.php" class="menu-item">Meeting Report</a>
            <a href="<?= $u ?>reports/financial.php" class="menu-item">Financial Report</a>
        </div>
        <?php endif; ?>

        <div class="menu-label">System</div>

        <?php if (hasPermission('view_settings')): ?>
        <a href="<?= $u ?>settings.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'settings') !== false ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span class="menu-text">Settings</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('manage_users')): ?>
        <a href="<?= $u ?>users.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'users') !== false ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i>
            <span class="menu-text">Users</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('view_audit_logs')): ?>
        <a href="<?= $u ?>audit-logs.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'audit-logs') !== false ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span class="menu-text">Audit Logs</span>
        </a>
        <?php endif; ?>

        <a href="<?= $u ?>notifications.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'notifications') !== false ? 'active' : '' ?>">
            <i class="fas fa-bell"></i>
            <span class="menu-text">Notifications</span>
        </a>

        <div class="menu-label">Account</div>

        <a href="<?= $u ?>profile.php" class="menu-item <?= strpos($_SERVER['SCRIPT_NAME'], 'profile') !== false ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span class="menu-text">Profile</span>
        </a>

        <a href="<?= $u ?>logout.php" class="menu-item text-danger">
            <i class="fas fa-sign-out-alt"></i>
            <span class="menu-text">Sign Out</span>
        </a>
    </div>
</aside>

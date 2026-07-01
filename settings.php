<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_settings');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Settings';

$activeTab = $_GET['tab'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['save_settings'])) {
        $allowedKeys = [
            'general' => ['site_name', 'site_tagline', 'currency', 'currency_symbol', 'timezone', 'date_format'],
            'security' => ['max_login_attempts', 'session_timeout', 'password_min_length'],
            'email' => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name'],
            'registration' => ['allow_public_registration', 'require_admin_approval', 'min_registration_age', 'default_group_code', 'registration_terms_url', 'max_verification_attempts', 'verification_token_expiry', 'require_national_id', 'require_passport_photo', 'require_signature', 'require_emergency_contact'],
        ];

        $tab = $_POST['setting_tab'] ?? 'general';
        $keys = $allowedKeys[$tab] ?? [];

        try {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)");

            foreach ($keys as $key) {
                $value = $_POST[$key] ?? '';
                if ($key === 'smtp_password' && empty($value)) continue;
                $stmt->execute([$key, $value, $tab]);
            }

            logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'settings', null, null, ['tab' => $tab], 'Updated system settings');
            setFlash('success', 'Settings saved successfully');
            redirect('settings.php?tab=' . $tab);
        } catch (Exception $e) {
            setFlash('danger', 'Error saving settings: ' . $e->getMessage());
        }
    }

    if (isset($_POST['save_branding'])) {
        try {
            $primaryColor = trim($_POST['primary_color'] ?? '#465fff');
            $secondaryColor = trim($_POST['secondary_color'] ?? '#12b76a');

            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'branding') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute(['primary_color', $primaryColor]);
            $stmt->execute(['secondary_color', $secondaryColor]);

            $uploadsDir = ROOT_PATH . 'uploads/settings/';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

            if (!empty($_FILES['logo']['name'])) {
                $logo = uploadFile($_FILES['logo'], $uploadsDir, ['jpg', 'jpeg', 'png', 'gif', 'svg']);
                if ($logo) {
                    $stmt->execute(['site_logo', $logo]);
                }
            }

            if (!empty($_FILES['favicon']['name'])) {
                $favicon = uploadFile($_FILES['favicon'], $uploadsDir, ['ico', 'png', 'jpg', 'jpeg']);
                if ($favicon) {
                    $stmt->execute(['site_favicon', $favicon]);
                }
            }

            logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'settings', null, null, null, 'Updated branding settings');
            setFlash('success', 'Branding settings saved successfully');
            redirect('settings.php?tab=branding');
        } catch (Exception $e) {
            setFlash('danger', 'Error saving branding: ' . $e->getMessage());
        }
    }

    if (isset($_POST['save_backup'])) {
        $autoBackup = $_POST['auto_backup'] ?? '0';
        $backupFrequency = $_POST['backup_frequency'] ?? 'daily';

        try {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'backup') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute(['auto_backup', $autoBackup]);
            $stmt->execute(['backup_frequency', $backupFrequency]);

            setFlash('success', 'Backup settings saved successfully');
            redirect('settings.php?tab=backup');
        } catch (Exception $e) {
            setFlash('danger', 'Error saving backup settings: ' . $e->getMessage());
        }
    }

    if (isset($_POST['manual_backup'])) {
        $backupDir = ROOT_PATH . 'backups/';
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;

        try {
            $possiblePaths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                PHP_BINDIR . DIRECTORY_SEPARATOR . 'mysqldump',
                'mysqldump',
            ];
            $mysqldump = 'mysqldump';
            foreach ($possiblePaths as $p) {
                if (is_executable($p)) { $mysqldump = $p; break; }
            }
            $command = sprintf(
                '"%s" --host=%s --user=%s --password=%s %s > "%s" 2>&1',
                $mysqldump, DB_HOST, DB_USER, DB_PASS, DB_NAME, $filepath
            );
            exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'settings', null, null, null, 'Manual database backup created: ' . $filename);
                setFlash('success', 'Database backup created successfully: ' . $filename);
            } else {
                setFlash('danger', 'Backup failed. Ensure mysqldump is available.');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Backup error: ' . $e->getMessage());
        }
        redirect('settings.php?tab=backup');
    }
}

$allSettings = getAllSettings();

$siteLogo = $allSettings['site_logo'] ?? null;
$siteFavicon = $allSettings['site_favicon'] ?? null;

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4>System Settings</h4>
            <p>Configure your chama system preferences</p>
        </div>
    </div>

    <?php displayFlash(); ?>

    <div class="card">
        <div class="card-header p-0">
            <ul class="nav nav-tabs card-header-tabs m-0 px-3 pt-2" role="tablist">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" href="?tab=general">
                        <i class="fas fa-sliders-h me-1"></i>General
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'branding' ? 'active' : '' ?>" href="?tab=branding">
                        <i class="fas fa-palette me-1"></i>Branding
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'security' ? 'active' : '' ?>" href="?tab=security">
                        <i class="fas fa-shield-alt me-1"></i>Security
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'email' ? 'active' : '' ?>" href="?tab=email">
                        <i class="fas fa-envelope me-1"></i>Email
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'backup' ? 'active' : '' ?>" href="?tab=backup">
                        <i class="fas fa-database me-1"></i>Backup
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'registration' ? 'active' : '' ?>" href="?tab=registration">
                        <i class="fas fa-user-plus me-1"></i>Registration
                    </a>
                </li>
                <?php if (hasPermission('manage_homepage')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'homepage' ? 'active' : '' ?>" href="?tab=homepage">
                        <i class="fas fa-home me-1"></i>Home Page
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card-body">
            <?php if ($activeTab === 'general'): ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="setting_tab" value="general">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?= e($allSettings['site_name'] ?? 'Chama System') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Site Tagline</label>
                        <input type="text" name="site_tagline" class="form-control" value="<?= e($allSettings['site_tagline'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-select">
                            <option value="KES" <?= ($allSettings['currency'] ?? 'KES') === 'KES' ? 'selected' : '' ?>>KES - Kenyan Shilling</option>
                            <option value="USD" <?= ($allSettings['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                            <option value="EUR" <?= ($allSettings['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                            <option value="GBP" <?= ($allSettings['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                            <option value="TZS" <?= ($allSettings['currency'] ?? '') === 'TZS' ? 'selected' : '' ?>>TZS - Tanzanian Shilling</option>
                            <option value="UGX" <?= ($allSettings['currency'] ?? '') === 'UGX' ? 'selected' : '' ?>>UGX - Ugandan Shilling</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" name="currency_symbol" class="form-control" value="<?= e($allSettings['currency_symbol'] ?? 'KSh ') ?>" maxlength="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-select">
                            <option value="Africa/Nairobi" <?= ($allSettings['timezone'] ?? 'Africa/Nairobi') === 'Africa/Nairobi' ? 'selected' : '' ?>>Africa/Nairobi (UTC+3)</option>
                            <option value="Africa/Dar_es_Salaam" <?= ($allSettings['timezone'] ?? '') === 'Africa/Dar_es_Salaam' ? 'selected' : '' ?>>Africa/Dar es Salaam</option>
                            <option value="Africa/Kampala" <?= ($allSettings['timezone'] ?? '') === 'Africa/Kampala' ? 'selected' : '' ?>>Africa/Kampala</option>
                            <option value="Africa/Lagos" <?= ($allSettings['timezone'] ?? '') === 'Africa/Lagos' ? 'selected' : '' ?>>Africa/Lagos</option>
                            <option value="Africa/Johannesburg" <?= ($allSettings['timezone'] ?? '') === 'Africa/Johannesburg' ? 'selected' : '' ?>>Africa/Johannesburg</option>
                            <option value="UTC" <?= ($allSettings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                            <option value="America/New_York" <?= ($allSettings['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>America/New York</option>
                            <option value="Europe/London" <?= ($allSettings['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>Europe/London</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date Format</label>
                        <select name="date_format" class="form-select">
                            <option value="d/m/Y" <?= ($allSettings['date_format'] ?? 'd/m/Y') === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (31/12/2026)</option>
                            <option value="m/d/Y" <?= ($allSettings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (12/31/2026)</option>
                            <option value="Y-m-d" <?= ($allSettings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2026-12-31)</option>
                            <option value="d.m.Y" <?= ($allSettings['date_format'] ?? '') === 'd.m.Y' ? 'selected' : '' ?>>DD.MM.YYYY (31.12.2026)</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" name="save_settings" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save General Settings
                    </button>
                </div>
            </form>

            <?php elseif ($activeTab === 'branding'): ?>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Primary Color</label>
                        <div class="input-group">
                            <input type="color" name="primary_color" class="form-control form-control-color" value="<?= e($allSettings['primary_color'] ?? '#465fff') ?>" style="max-width: 60px;">
                            <input type="text" class="form-control" value="<?= e($allSettings['primary_color'] ?? '#465fff') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Secondary Color</label>
                        <div class="input-group">
                            <input type="color" name="secondary_color" class="form-control form-control-color" value="<?= e($allSettings['secondary_color'] ?? '#12b76a') ?>" style="max-width: 60px;">
                            <input type="text" class="form-control" value="<?= e($allSettings['secondary_color'] ?? '#12b76a') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Site Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <?php if ($siteLogo): ?>
                            <div class="mt-2 p-2 border rounded d-inline-block">
                                <img src="<?= BASE_URL ?>uploads/settings/<?= e($siteLogo) ?>" style="height: 50px;">
                                <small class="d-block text-muted">Current logo</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Favicon</label>
                        <input type="file" name="favicon" class="form-control" accept=".ico,.png,.jpg,.jpeg">
                        <?php if ($siteFavicon): ?>
                            <div class="mt-2">
                                <img src="<?= BASE_URL ?>uploads/settings/<?= e($siteFavicon) ?>" style="height: 32px; width: 32px;">
                                <small class="d-block text-muted">Current favicon</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" name="save_branding" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Branding Settings
                    </button>
                </div>
            </form>

            <?php elseif ($activeTab === 'security'): ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="setting_tab" value="security">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Max Login Attempts</label>
                        <input type="number" name="max_login_attempts" class="form-control" value="<?= e($allSettings['max_login_attempts'] ?? MAX_LOGIN_ATTEMPTS) ?>" min="1" max="20">
                        <small class="text-muted">Number of failed attempts before lockout</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Session Timeout (seconds)</label>
                        <input type="number" name="session_timeout" class="form-control" value="<?= e($allSettings['session_timeout'] ?? SESSION_TIMEOUT) ?>" min="60" max="86400">
                        <small class="text-muted">Default: <?= SESSION_TIMEOUT ?>s (<?= SESSION_TIMEOUT / 60 ?> min)</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Password Min Length</label>
                        <input type="number" name="password_min_length" class="form-control" value="<?= e($allSettings['password_min_length'] ?? '8') ?>" min="6" max="32">
                        <small class="text-muted">Minimum characters for user passwords</small>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" name="save_settings" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Security Settings
                    </button>
                </div>
            </form>

            <?php elseif ($activeTab === 'email'): ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="setting_tab" value="email">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= e($allSettings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= e($allSettings['smtp_port'] ?? '587') ?>" placeholder="587">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= e($allSettings['smtp_username'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SMTP Password</label>
                        <input type="password" name="smtp_password" class="form-control" placeholder="<?= !empty($allSettings['smtp_password']) ? '•••••••• (leave blank to keep current)' : 'Enter password' ?>">
                        <?php if (!empty($allSettings['smtp_password'])): ?>
                            <small class="text-muted"><i class="fas fa-check-circle text-success me-1"></i>Password is set</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From Email</label>
                        <input type="email" name="smtp_from_email" class="form-control" value="<?= e($allSettings['smtp_from_email'] ?? '') ?>" placeholder="noreply@chama.com">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From Name</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?= e($allSettings['smtp_from_name'] ?? '') ?>" placeholder="Chama System">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" name="save_settings" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Email Settings
                    </button>
                </div>
            </form>

            <?php elseif ($activeTab === 'backup'): ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Auto Backup Settings</div>
                        <div class="card-body">
                            <form method="POST">
                                <?= csrfField() ?>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="auto_backup" value="1" id="autoBackup" <?= !empty($allSettings['auto_backup']) && $allSettings['auto_backup'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="autoBackup">Enable Automatic Backups</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Backup Frequency</label>
                                    <select name="backup_frequency" class="form-select">
                                        <option value="daily" <?= ($allSettings['backup_frequency'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                        <option value="weekly" <?= ($allSettings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        <option value="monthly" <?= ($allSettings['backup_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                    </select>
                                </div>
                                <button type="submit" name="save_backup" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Backup Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Manual Backup</div>
                        <div class="card-body text-center py-4">
                            <i class="fas fa-database mb-3" style="font-size: 3rem; color: var(--secondary);"></i>
                            <p>Create a manual backup of the entire database. The backup file will be saved in the <code>backups/</code> directory.</p>
                            <form method="POST">
                                <?= csrfField() ?>
                                <button type="submit" name="manual_backup" class="btn btn-primary btn-lg" onclick="return confirm('Start database backup? This may take a moment.')">
                                    <i class="fas fa-download me-1"></i>Create Backup Now
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">Existing Backups</div>
                        <div class="card-body p-0">
                            <?php
                            $backupDir = ROOT_PATH . 'backups/';
                            $backups = [];
                            if (is_dir($backupDir)) {
                                $files = glob($backupDir . '*.sql');
                                rsort($files);
                                foreach ($files as $file) {
                                    $backups[] = [
                                        'name' => basename($file),
                                        'size' => filesize($file),
                                        'date' => date('d/m/Y H:i', filemtime($file))
                                    ];
                                }
                            }
                            ?>
                            <?php if (empty($backups)): ?>
                                <div class="text-center py-4" style="color: var(--gray-500);">
                                    <i class="fas fa-folder-open mb-2" style="font-size: 2rem;"></i>
                                    <p class="small mb-0">No backups found</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>File</th>
                                                <th>Size</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backups as $bk): ?>
                                                <tr>
                                                    <td style="font-size: 0.8125rem;"><?= e($bk['name']) ?></td>
                                                    <td style="font-size: 0.8125rem;"><?= number_format($bk['size'] / 1024, 1) ?> KB</td>
                                                    <td style="font-size: 0.8125rem;"><?= e($bk['date']) ?></td>
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
            <?php elseif ($activeTab === 'registration'): ?>
            <?php require_once __DIR__ . '/includes/member_workflow.php'; ?>
            <?php $regSettings = getRegistrationSettings(); ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="save_settings" value="1">
                <input type="hidden" name="setting_tab" value="registration">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-plus me-1"></i> Registration Settings</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="allow_public_registration" value="1" id="allowPublicReg" <?= ($regSettings['allow_public_registration'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allowPublicReg">Allow Public Registration</label>
                                    <small class="d-block text-muted">When enabled, new members can register via register.php</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_admin_approval" value="1" id="requireAdminApproval" <?= ($regSettings['require_admin_approval'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="requireAdminApproval">Require Admin Approval</label>
                                    <small class="d-block text-muted">New members must be approved by an administrator before becoming active</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Minimum Registration Age</label>
                                <input type="number" name="min_registration_age" class="form-control" value="<?= e($regSettings['min_registration_age'] ?? '18') ?>" min="1" max="120">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Default Group Code</label>
                                <input type="text" name="default_group_code" class="form-control" value="<?= e($regSettings['default_group_code'] ?? 'CHAMA001') ?>" maxlength="20">
                                <small class="text-muted">Group code assigned to self-registered members</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Verification Token Expiry (hours)</label>
                                <input type="number" name="verification_token_expiry" class="form-control" value="<?= e($regSettings['verification_token_expiry'] ?? '24') ?>" min="1" max="168">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Verification Attempts</label>
                                <input type="number" name="max_verification_attempts" class="form-control" value="<?= e($regSettings['max_verification_attempts'] ?? '5') ?>" min="1" max="20">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Terms & Conditions URL</label>
                                <input type="url" name="registration_terms_url" class="form-control" value="<?= e($regSettings['registration_terms_url'] ?? '') ?>" placeholder="https://example.com/terms">
                                <small class="text-muted">Optional link to terms page shown during registration</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-check-circle me-1"></i> Required Fields</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_national_id" value="1" id="requireNationalId" <?= ($regSettings['require_national_id'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="requireNationalId">Require National ID</label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_passport_photo" value="1" id="requirePassportPhoto" <?= ($regSettings['require_passport_photo'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="requirePassportPhoto">Require Passport Photo</label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_signature" value="1" id="requireSignature" <?= ($regSettings['require_signature'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="requireSignature">Require Signature</label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_emergency_contact" value="1" id="requireEmergencyContact" <?= ($regSettings['require_emergency_contact'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="requireEmergencyContact">Require Emergency Contact</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Registration Settings</button>
                </div>
            </form>
            <?php elseif ($activeTab === 'homepage'): ?>
            <?php requirePermission('manage_homepage'); ?>
            <?php require_once __DIR__ . '/includes/homepage_cms.php'; ?>
            <?php $hpSections = hp_get_sections(); $hpTab = $_GET['hp_tab'] ?? 'general'; ?>
            <style>
                .hp-sub-tabs { display: flex; gap: 2px; overflow-x: auto; flex-wrap: nowrap; border-bottom: 2px solid #e9ecef; margin-bottom: 1.5rem; padding-bottom: 0; }
                .hp-sub-tab { padding: 0.5rem 0.9rem; font-size: 0.8125rem; font-weight: 500; color: #6b7280; text-decoration: none; white-space: nowrap; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s; }
                .hp-sub-tab:hover { color: var(--secondary); border-bottom-color: var(--secondary); }
                .hp-sub-tab.active { color: var(--secondary); border-bottom-color: var(--secondary); }
                .hp-section-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
                .hp-form-label { font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem; }
                .hp-help-text { font-size: 0.75rem; color: #9ca3af; margin-top: 0.15rem; }
                .hp-image-preview { max-height: 80px; border-radius: 6px; border: 1px solid #e5e7eb; }
                .hp-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem; }
                .hp-item-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 0.75rem; position: relative; }
                .hp-item-card .item-drag { cursor: grab; color: #9ca3af; }
                .hp-item-card .item-remove { position: absolute; top: 0.35rem; right: 0.35rem; cursor: pointer; color: #ef4444; font-size: 0.875rem; }
                .hp-action-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e5e7eb; padding: 0.75rem 0; margin-top: 1.5rem; z-index: 10; }
                .hp-revision-list { max-height: 300px; overflow-y: auto; }
                .hp-revision-item { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f3f4f6; font-size: 0.8125rem; }
                .hp-color-input { width: 40px; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 4px; cursor: pointer; }
                .hp-toggle-lg { transform: scale(1.2); margin-right: 0.5rem; }
                .tox-tinymce { border-radius: 6px !important; }
            </style>

            <div id="hpCms">
                <!-- Action Bar -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0" style="font-weight: 700;">Home Page Manager</h5>
                        <p class="text-muted small mb-0">Configure every section of your landing page using simple forms.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>index.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>Preview</a>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="hpRestoreDefaults"><i class="fas fa-undo me-1"></i>Restore Defaults</button>
                        <button type="button" class="btn btn-outline-info btn-sm" id="hpUndoBtn"><i class="fas fa-history me-1"></i>Undo</button>
                    </div>
                </div>

                <!-- Sub-tab Navigation -->
                <div class="hp-sub-tabs">
                    <a href="?tab=homepage&hp_tab=general" class="hp-sub-tab <?= $hpTab === 'general' ? 'active' : '' ?>">General</a>
                    <a href="?tab=homepage&hp_tab=hero" class="hp-sub-tab <?= $hpTab === 'hero' ? 'active' : '' ?>">Hero</a>
                    <a href="?tab=homepage&hp_tab=about" class="hp-sub-tab <?= $hpTab === 'about' ? 'active' : '' ?>">About</a>
                    <a href="?tab=homepage&hp_tab=services" class="hp-sub-tab <?= $hpTab === 'services' ? 'active' : '' ?>">Services</a>
                    <a href="?tab=homepage&hp_tab=stats" class="hp-sub-tab <?= $hpTab === 'stats' ? 'active' : '' ?>">Statistics</a>
                    <a href="?tab=homepage&hp_tab=gallery" class="hp-sub-tab <?= $hpTab === 'gallery' ? 'active' : '' ?>">Gallery</a>
                    <a href="?tab=homepage&hp_tab=testimonials" class="hp-sub-tab <?= $hpTab === 'testimonials' ? 'active' : '' ?>">Testimonials</a>
                    <a href="?tab=homepage&hp_tab=faqs" class="hp-sub-tab <?= $hpTab === 'faqs' ? 'active' : '' ?>">FAQs</a>
                    <a href="?tab=homepage&hp_tab=partners" class="hp-sub-tab <?= $hpTab === 'partners' ? 'active' : '' ?>">Partners</a>
                    <a href="?tab=homepage&hp_tab=contact" class="hp-sub-tab <?= $hpTab === 'contact' ? 'active' : '' ?>">Contact</a>
                    <a href="?tab=homepage&hp_tab=footer" class="hp-sub-tab <?= $hpTab === 'footer' ? 'active' : '' ?>">Footer</a>
                </div>

                <?php function hp_section_toggle(array $sec): void { ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input hp-toggle-lg hp-toggle" type="checkbox" data-key="<?= e($sec['section_key']) ?>" id="hpVis_<?= e($sec['section_key']) ?>" <?= !empty($sec['is_visible']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="hpVis_<?= e($sec['section_key']) ?>">Show <?= e($sec['title'] ?? $sec['section_key']) ?> on Homepage</label>
                        </div>
                    </div>
                <?php } ?>

                <?php function hp_text_field(string $label, string $name, string $value, string $help = ''): void { ?>
                    <div class="mb-3">
                        <label class="hp-form-label"><?= e($label) ?></label>
                        <input type="text" name="<?= e($name) ?>" class="form-control form-control-sm" value="<?= e($value) ?>">
                        <?php if ($help): ?><div class="hp-help-text"><?= e($help) ?></div><?php endif; ?>
                    </div>
                <?php } ?>

                <?php function hp_textarea(string $label, string $name, string $value, int $rows = 3, string $help = ''): void { ?>
                    <div class="mb-3">
                        <label class="hp-form-label"><?= e($label) ?></label>
                        <textarea name="<?= e($name) ?>" class="form-control form-control-sm" rows="<?= $rows ?>"><?= e($value) ?></textarea>
                        <?php if ($help): ?><div class="hp-help-text"><?= e($help) ?></div><?php endif; ?>
                    </div>
                <?php } ?>

                <?php function hp_rich_text(string $label, string $name, string $value, string $help = ''): void { ?>
                    <div class="mb-3">
                        <label class="hp-form-label"><?= e($label) ?></label>
                        <textarea name="<?= e($name) ?>" class="form-control form-control-sm hp-richtext" rows="5"><?= e($value) ?></textarea>
                        <?php if ($help): ?><div class="hp-help-text"><?= e($help) ?></div><?php endif; ?>
                    </div>
                <?php } ?>

                <?php function hp_color_field(string $label, string $name, string $value): void { ?>
                    <div class="mb-3">
                        <label class="hp-form-label"><?= e($label) ?></label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="<?= e($name) ?>" class="hp-color-input" value="<?= e($value) ?>">
                            <span class="small text-muted hp-color-val"><?= e($value) ?></span>
                        </div>
                    </div>
                <?php } ?>

                <?php function hp_image_upload(string $label, string $fieldKey, string $currentValue = ''): void {
                    // fieldKey is the bare key (e.g. 'hero_image'), NOT wrapped in fields[...]
                    $inputName = 'fields[' . $fieldKey . ']';
                    ?>
                    <div class="mb-3">
                        <label class="hp-form-label"><?= e($label) ?></label>
                        <input type="file" name="<?= e($inputName) ?>" class="form-control form-control-sm hp-file-upload" accept="image/*">
                        <input type="hidden" name="<?= e($inputName) ?>_current" value="<?= e($currentValue) ?>">
                        <?php if ($currentValue): ?>
                        <div class="mt-1 d-flex align-items-center gap-2">
                            <img src="<?= BASE_URL . e($currentValue) ?>" class="hp-image-preview" onerror="this.style.display='none'">
                            <small class="text-muted"><?= e(basename($currentValue)) ?></small>
                            <button type="button" class="btn btn-sm btn-outline-danger hp-remove-image" data-field="<?= e($fieldKey) ?>"><i class="fas fa-times"></i></button>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php } ?>

                <?php function hp_url_field(string $label, string $name, string $value, string $help = ''): void { ?>
                    <div class="mb-3">
                        <label class="hp-form-label"><?= e($label) ?></label>
                        <input type="text" name="<?= e($name) ?>" class="form-control form-control-sm" value="<?= e($value) ?>" placeholder="https://...">
                        <?php if ($help): ?><div class="hp-help-text"><?= e($help) ?></div><?php endif; ?>
                    </div>
                <?php } ?>

                <?php function hp_section_fields(array $sec): void {
                    $f = $sec['fields']; $sk = $sec['section_key'];
                    echo '<input type="hidden" name="section_key" value="' . e($sk) . '">';
                    echo '<input type="hidden" name="action" value="save_fields">';
                    echo csrfField();
                    hp_section_toggle($sec);
                } ?>

                <?php function hp_items_manager(array $sec, array $fieldDefs, string $itemLabel = 'Item'): void { ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="hp-form-label mb-0"><?= e($itemLabel) ?>s</label>
                            <button type="button" class="btn btn-sm btn-outline-primary hp-add-item" data-section="<?= e($sec['section_key']) ?>"><i class="fas fa-plus me-1"></i>Add <?= e($itemLabel) ?></button>
                        </div>
                        <div class="hp-items-grid" data-section="<?= e($sec['section_key']) ?>">
                            <?php foreach ($sec['items'] ?? [] as $item): $fi = $item['fields']; ?>
                            <div class="hp-item-card" data-item-id="<?= (int)$item['id'] ?>">
                                <span class="item-drag"><i class="fas fa-grip-vertical"></i></span>
                                <span class="item-remove" title="Remove"><i class="fas fa-times-circle"></i></span>
                                <div class="small fw-semibold mb-1">#<?= (int)$item['id'] ?></div>
                                <?php foreach ($fieldDefs as $fd): $fk = $fd[0]; $ft = $fd[1]; $fl = $fd[2] ?? $fk; ?>
                                    <?php if ($ft === 'text'): ?>
                                        <input type="text" class="form-control form-control-sm mb-1 hp-item-field" data-field="<?= e($fk) ?>" value="<?= e($fi[$fk] ?? '') ?>" placeholder="<?= e($fl) ?>">
                                    <?php elseif ($ft === 'number'): ?>
                                        <input type="number" class="form-control form-control-sm mb-1 hp-item-field" data-field="<?= e($fk) ?>" value="<?= e($fi[$fk] ?? '') ?>" placeholder="<?= e($fl) ?>">
                                    <?php elseif ($ft === 'textarea'): ?>
                                        <textarea class="form-control form-control-sm mb-1 hp-item-field" data-field="<?= e($fk) ?>" rows="2" placeholder="<?= e($fl) ?>"><?= e($fi[$fk] ?? '') ?></textarea>
                                    <?php elseif ($ft === 'color'): ?>
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <input type="color" class="hp-item-field" data-field="<?= e($fk) ?>" value="<?= e($fi[$fk] ?? '#12b76a') ?>" style="width: 30px; height: 26px; padding: 1px; border: 1px solid #d1d5db; border-radius: 3px;">
                                            <small class="text-muted"><?= e($fl) ?></small>
                                        </div>
                                    <?php elseif ($ft === 'stars'): ?>
                                        <select class="form-select form-select-sm mb-1 hp-item-field" data-field="<?= e($fk) ?>">
                                            <?php for ($s = 1; $s <= 5; $s += 0.5): ?>
                                            <option value="<?= $s ?>" <?= ($fi[$fk] ?? '') == $s ? 'selected' : '' ?>><?= $s ?> stars</option>
                                            <?php endfor; ?>
                                        </select>
                                    <?php elseif ($ft === 'icon'): ?>
                                        <input type="text" class="form-control form-control-sm mb-1 hp-item-field" data-field="<?= e($fk) ?>" value="<?= e($fi[$fk] ?? 'fa-check') ?>" placeholder="fa-icon-name">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php } ?>

                <!-- ===== SUB-TAB CONTENT ===== -->
                <?php switch ($hpTab):
                    case 'general': ?>
                    <form class="hp-form" data-auto-save="true">
                        <?php $sec = $hpSections['navbar'] ?? null; if ($sec): ?>
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Navigation Bar</h6>
                            <?php hp_section_fields($sec); ?>
                            <?php hp_text_field('Brand Text', 'fields[brand_text]', $sec['fields']['brand_text'] ?? ''); ?>
                            <?php hp_text_field('Sign In Button Text', 'fields[sign_in_text]', $sec['fields']['sign_in_text'] ?? ''); ?>
                            <?php hp_url_field('Sign In Link', 'fields[sign_in_link]', $sec['fields']['sign_in_link'] ?? ''); ?>
                            <?php $navItems = $sec['items'] ?? []; if (!empty($navItems)): ?>
                            <div class="mb-3">
                                <label class="hp-form-label">Navigation Menu Items</label>
                                <div class="hp-items-grid" data-section="navbar">
                                    <?php foreach ($navItems as $item): $fi = $item['fields']; ?>
                                    <div class="hp-item-card" data-item-id="<?= (int)$item['id'] ?>">
                                        <span class="item-drag"><i class="fas fa-grip-vertical"></i></span>
                                        <span class="item-remove"><i class="fas fa-times-circle"></i></span>
                                        <input type="text" class="form-control form-control-sm mb-1 hp-item-field" data-field="label" value="<?= e($fi['label'] ?? '') ?>" placeholder="Label">
                                        <input type="text" class="form-control form-control-sm hp-item-field" data-field="href" value="<?= e($fi['href'] ?? '#') ?>" placeholder="#section-id">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-1 hp-add-item" data-section="navbar"><i class="fas fa-plus me-1"></i>Add Menu Item</button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php foreach (['preloader', 'dark_mode_toggle', 'scroll_top'] as $sk): $sec = $hpSections[$sk] ?? null; if ($sec): ?>
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3"><?= e($sec['title'] ?? $sk) ?></h6>
                            <?php hp_section_fields($sec); ?>
                            <p class="text-muted small mb-0">This section has no configurable content. Use the toggle above to show or hide it.</p>
                        </div>
                        <?php endif; endforeach; ?>

                        <?php $sec = $hpSections['copyright'] ?? null; if ($sec): ?>
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Copyright Bar</h6>
                            <?php hp_section_fields($sec); ?>
                            <?php hp_text_field('Copyright Text', 'fields[copyright_text]', $sec['fields']['copyright_text'] ?? ''); ?>
                        </div>
                        <?php endif; ?>

                        <div class="hp-action-bar text-end">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                        </div>
                    </form>
                    <?php break; ?>

                    <?php case 'hero': $sec = $hpSections['hero'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Hero Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_color_field('Background Color', 'fields[bg_color]', $f['bg_color'] ?? '#0f0e3a'); ?></div>
                            </div>
                            <?php hp_textarea('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? '', 3, 'A brief description below the main heading.'); ?>
                            <div class="row">
                                <div class="col-md-3"><?php hp_text_field('Primary Button Text', 'fields[primary_btn_text]', $f['primary_btn_text'] ?? ''); ?></div>
                                <div class="col-md-3"><?php hp_url_field('Primary Button Link', 'fields[primary_btn_link]', $f['primary_btn_link'] ?? ''); ?></div>
                                <div class="col-md-3"><?php hp_text_field('Secondary Button Text', 'fields[secondary_btn_text]', $f['secondary_btn_text'] ?? ''); ?></div>
                                <div class="col-md-3"><?php hp_url_field('Secondary Button Link', 'fields[secondary_btn_link]', $f['secondary_btn_link'] ?? ''); ?></div>
                            </div>
                            <?php hp_image_upload('Hero Image', 'hero_image', $f['hero_image'] ?? ''); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'about': $sec = $hpSections['about'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">About Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_color_field('Background Color', 'fields[bg_color]', $f['bg_color'] ?? '#ffffff'); ?></div>
                            </div>
                            <?php hp_textarea('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? '', 3); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Badge Text', 'fields[badge_text]', $f['badge_text'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_text_field('Badge Icon (Font Awesome)', 'fields[badge_icon]', $f['badge_icon'] ?? 'fa-users', 'e.g. fa-users, fa-handshake'); ?></div>
                            </div>
                            <?php hp_image_upload('About Image', 'about_image', $f['about_image'] ?? ''); ?>
                            <?php hp_items_manager($sec, [['icon','icon','Icon'],['color','color','Color'],['title','text','Title'],['description','textarea','Description']], 'Feature'); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'services': $sec = $hpSections['services'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Services Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_color_field('Background Color', 'fields[bg_color]', $f['bg_color'] ?? '#f8f9fc'); ?></div>
                            </div>
                            <?php hp_textarea('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? '', 3); ?>
                            <?php hp_items_manager($sec, [['icon','icon','Icon'],['color','color','Color'],['title','text','Title'],['description','textarea','Description']], 'Service'); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'stats': $sec = $hpSections['stats'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Statistics Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_color_field('Background Color', 'fields[bg_color]', $f['bg_color'] ?? '#0f0e3a'); ?></div>
                            </div>
                            <?php hp_textarea('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? '', 3); ?>
                            <?php hp_items_manager($sec, [['number','number','Number'],['prefix','text','Prefix'],['suffix','text','Suffix'],['label','text','Label']], 'Stat'); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'gallery': $sec = $hpSections['gallery'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Gallery Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_text_field('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? ''); ?></div>
                            </div>
                            <?php hp_items_manager($sec, [['icon','icon','Icon'],['color','color','Color'],['label','text','Label']], 'Gallery Item'); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'testimonials': $sec = $hpSections['testimonials'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Testimonials Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_text_field('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? ''); ?></div>
                            </div>
                            <?php hp_items_manager($sec, [['name','text','Name'],['role','text','Role'],['text','textarea','Testimonial Text'],['initials','text','Initials'],['stars','stars','Stars']], 'Testimonial'); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'faqs': $sec = $hpSections['faqs'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">FAQs Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_text_field('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? ''); ?></div>
                            </div>
                            <?php hp_items_manager($sec, [['question','text','Question'],['answer','textarea','Answer']], 'FAQ'); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'partners': $sec = $hpSections['partners'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Partners Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_text_field('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? ''); ?></div>
                            </div>
                            <?php hp_items_manager($sec, [['name','text','Partner Name']], 'Partner'); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'contact': $sec = $hpSections['contact'] ?? null; if ($sec): $f = $sec['fields']; ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Contact Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Headline', 'fields[headline]', $f['headline'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_text_field('Subheadline', 'fields[subheadline]', $f['subheadline'] ?? ''); ?></div>
                            </div>
                            <?php hp_url_field('Form Action URL', 'fields[form_action]', $f['form_action'] ?? 'ajax/contact.php'); ?>
                            <?php hp_items_manager($sec, [['icon','icon','Icon'],['color','color','Color'],['title','text','Title'],['value','text','Value']], 'Contact Info'); ?>
                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                    <?php case 'footer': $sec = $hpSections['footer'] ?? null; if ($sec): $f = $sec['fields'];
                        $socialItems = []; $quickItems = []; $serviceItems = [];
                        foreach ($sec['items'] as $item) {
                            $fi = $item['fields'];
                            $t = $fi['type'] ?? '';
                            if ($t === 'social') $socialItems[] = $item;
                            elseif ($t === 'quick_link') $quickItems[] = $item;
                            elseif ($t === 'service_link') $serviceItems[] = $item;
                        }
                    ?>
                    <form class="hp-form">
                        <div class="hp-section-card">
                            <h6 class="fw-bold mb-3">Footer Section</h6>
                            <?php hp_section_fields($sec); ?>
                            <?php hp_text_field('Brand Text', 'fields[brand_text]', $f['brand_text'] ?? ''); ?>
                            <?php hp_textarea('Description', 'fields[description]', $f['description'] ?? '', 3); ?>
                            <div class="row">
                                <div class="col-md-6"><?php hp_text_field('Newsletter Title', 'fields[newsletter_title]', $f['newsletter_title'] ?? ''); ?></div>
                                <div class="col-md-6"><?php hp_textarea('Newsletter Description', 'fields[newsletter_text]', $f['newsletter_text'] ?? '', 3); ?></div>
                            </div>

                            <!-- Social Links -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="hp-form-label mb-0">Social Links</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary hp-add-item" data-section="footer" data-item-type="social"><i class="fas fa-plus me-1"></i>Add Social Link</button>
                                </div>
                                <div class="hp-items-grid" data-section="footer" data-filter="social">
                                    <?php foreach ($socialItems as $item): $fi = $item['fields']; ?>
                                    <div class="hp-item-card" data-item-id="<?= (int)$item['id'] ?>">
                                        <span class="item-drag"><i class="fas fa-grip-vertical"></i></span>
                                        <span class="item-remove"><i class="fas fa-times-circle"></i></span>
                                        <input type="text" class="form-control form-control-sm mb-1 hp-item-field" data-field="icon" value="<?= e($fi['icon'] ?? '') ?>" placeholder="fa-facebook-f">
                                        <input type="text" class="form-control form-control-sm hp-item-field" data-field="url" value="<?= e($fi['url'] ?? '#') ?>" placeholder="URL">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Quick Links -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="hp-form-label mb-0">Quick Links</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary hp-add-item" data-section="footer" data-item-type="quick_link"><i class="fas fa-plus me-1"></i>Add Quick Link</button>
                                </div>
                                <div class="hp-items-grid" data-section="footer" data-filter="quick_link">
                                    <?php foreach ($quickItems as $item): $fi = $item['fields']; ?>
                                    <div class="hp-item-card" data-item-id="<?= (int)$item['id'] ?>">
                                        <span class="item-drag"><i class="fas fa-grip-vertical"></i></span>
                                        <span class="item-remove"><i class="fas fa-times-circle"></i></span>
                                        <input type="text" class="form-control form-control-sm mb-1 hp-item-field" data-field="label" value="<?= e($fi['label'] ?? '') ?>" placeholder="Label">
                                        <input type="text" class="form-control form-control-sm hp-item-field" data-field="href" value="<?= e($fi['href'] ?? '#') ?>" placeholder="#section-id">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Service Links -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="hp-form-label mb-0">Service Links</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary hp-add-item" data-section="footer" data-item-type="service_link"><i class="fas fa-plus me-1"></i>Add Service Link</button>
                                </div>
                                <div class="hp-items-grid" data-section="footer" data-filter="service_link">
                                    <?php foreach ($serviceItems as $item): $fi = $item['fields']; ?>
                                    <div class="hp-item-card" data-item-id="<?= (int)$item['id'] ?>">
                                        <span class="item-drag"><i class="fas fa-grip-vertical"></i></span>
                                        <span class="item-remove"><i class="fas fa-times-circle"></i></span>
                                        <input type="text" class="form-control form-control-sm mb-1 hp-item-field" data-field="label" value="<?= e($fi['label'] ?? '') ?>" placeholder="Label">
                                        <input type="text" class="form-control form-control-sm hp-item-field" data-field="href" value="<?= e($fi['href'] ?? '#') ?>" placeholder="#section-id">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="hp-action-bar text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; break; ?>

                <?php endswitch; ?>

                <!-- Undo Modal -->
                <div class="modal fade" id="hpUndoModal" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header"><h6 class="modal-title">Restore Previous Version</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <p class="small text-muted">Select a revision to restore:</p>
                                <div class="hp-revision-list">
                                    <?php $revisions = hp_get_revisions(10); foreach ($revisions as $rev): ?>
                                    <div class="hp-revision-item d-flex justify-content-between align-items-center">
                                        <span><strong><?= e($rev['action']) ?></strong> <span class="text-muted ms-1"><?= e($rev['created_at']) ?></span></span>
                                        <button class="btn btn-sm btn-outline-primary hp-restore-revision" data-rev-id="<?= (int)$rev['id'] ?>">Restore</button>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($revisions)): ?><p class="text-muted small mb-0">No revisions yet.</p><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
            <script>
            (function($) {
                'use strict';

                // ===== Refresh CSRF tokens from AJAX responses =====
                $(document).ajaxSuccess(function(event, xhr, settings, data) {
                    if (data && data.csrf_token && data.csrf_token.length > 10) {
                        $('[name="csrf_token"]').val(data.csrf_token);
                    }
                });
                $(document).ajaxError(function(event, xhr, settings, error) {
                    // If CSRF failed, try to extract new token from error response
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data && data.csrf_token && data.csrf_token.length > 10) {
                            $('[name="csrf_token"]').val(data.csrf_token);
                        }
                    } catch(e) {}
                });

                // ===== TinyMCE Rich Text =====
                if (typeof tinymce !== 'undefined') {
                    tinymce.init({
                        selector: '.hp-richtext',
                        menubar: false,
                        toolbar: 'undo redo | bold italic underline strikethrough | bullist numlist | alignleft aligncenter alignright | link image | removeformat',
                        plugins: 'lists link image',
                        statusbar: false,
                        height: 200,
                        branding: false,
                        promotion: false,
                        setup: function(editor) {
                            editor.on('change', function() {
                                editor.save();
                            });
                        }
                    });
                }

                // ===== Auto-Save on File Selection =====
                $(document).on('change', '.hp-file-upload', function() {
                    $(this).closest('form.hp-form').find('[type="submit"]').trigger('click');
                });

                // ===== Save Section Form(s) =====
                $(document).on('submit', '.hp-form', function(e) {
                    e.preventDefault();
                    var $form = $(this);
                    if (typeof tinymce !== 'undefined') {
                        tinymce.triggerSave();
                    }
                    var $btn = $form.find('[type="submit"]');
                    var origHtml = $btn.html();
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

                    // For multi-section forms (General tab), save each section card separately
                    var $cards = $form.find('.hp-section-card');
                    var sections = [];
                    var csrfToken = $form.find('[name="csrf_token"]').first().val();

                    $cards.each(function() {
                        var $card = $(this);
                        var sectionKey = $card.find('[name="section_key"]').val();
                        if (!sectionKey) return;

                        var fields = {};
                        $card.find('[name^="fields["]').each(function() {
                            var name = $(this).attr('name');
                            var match = name.match(/fields\[([^\]]+)\]/);
                            if (match && $(this).attr('type') !== 'file') {
                                fields[match[1]] = $(this).val();
                            }
                        });

                        // Collect item-level fields from the items manager inside this card
                        var items = [];
                        $card.find('.hp-item-card').each(function() {
                            var itemId = $(this).data('item-id');
                            if (!itemId) return;
                            var itemData = { item_id: itemId, fields: {} };
                            $(this).find('.hp-item-field').each(function() {
                                itemData.fields[$(this).data('field')] = $(this).val();
                            });
                            items.push(itemData);
                        });

                        var formData = new FormData();
                        formData.append('action', 'save_fields');
                        formData.append('section_key', sectionKey);
                        formData.append('fields', JSON.stringify(fields));
                        if (items.length) {
                            formData.append('items', JSON.stringify(items));
                        }

                        $card.find('input[type="file"]').each(function() {
                            if (this.files && this.files[0]) {
                                var fname = $(this).attr('name');
                                var fmatch = fname.match(/fields\[([^\]]+)\]/);
                                var fkey = fmatch ? 'upload_' + fmatch[1] : 'upload_' + fname;
                                formData.append(fkey, this.files[0]);
                            }
                        });

                        $card.find('.hp-remove-image').each(function() {
                            var field = $(this).data('field');
                            if ($(this).data('removed')) {
                                formData.append('remove_' + field, '1');
                            }
                        });

                        sections.push({ key: sectionKey, data: formData });
                    });

                    if (sections.length === 0) {
                        $btn.prop('disabled', false).html(origHtml);
                        return;
                    }

                    // Save sections sequentially (CSRF token regenerates after each save)
                    var saved = 0, errors = 0;
                    function saveNext(i) {
                        if (i >= sections.length) {
                            if (errors === 0) {
                                showToast('success', saved + ' section' + (saved > 1 ? 's' : '') + ' saved successfully');
                            } else {
                                showToast('error', errors + ' section' + (errors > 1 ? 's' : '') + ' failed');
                            }
                            $btn.prop('disabled', false).html(origHtml);
                            return;
                        }
                        sections[i].data.append('csrf_token', csrfToken);
                        $.ajax({
                            url: BASE_URL + 'ajax/homepage-cms.php',
                            method: 'POST',
                            data: sections[i].data,
                            processData: false,
                            contentType: false,
                            dataType: 'json'
                        }).done(function(resp) {
                            if (resp.success) {
                                saved++;
                                if (resp.csrf_token) csrfToken = resp.csrf_token;
                            } else {
                                errors++;
                            }
                        }).fail(function() {
                            errors++;
                        }).always(function() {
                            saveNext(i + 1);
                        });
                    }
                    saveNext(0);
                });

                // ===== Toggle Visibility =====
                $(document).on('change', '.hp-toggle', function() {
                    var $cb = $(this);
                    var key = $cb.data('key');
                    var visible = $cb.is(':checked') ? 1 : 0;
                    $.ajax({
                        url: BASE_URL + 'ajax/homepage-cms.php',
                        method: 'POST',
                        data: {
                            action: 'toggle',
                            section_key: key,
                            is_visible: visible,
                            csrf_token: $('[name="csrf_token"]').first().val()
                        },
                        dataType: 'json',
                        success: function(resp) {
                            if (resp.success) {
                                showToast('success', (visible ? 'Shown' : 'Hidden') + ' on homepage');
                            } else {
                                $cb.prop('checked', !visible);
                                showToast('error', resp.error || 'Toggle failed');
                            }
                        },
                        error: function() {
                            $cb.prop('checked', !visible);
                            showToast('error', 'Toggle failed');
                        }
                    });
                });

                // ===== Color Picker Sync =====
                $(document).on('input', '.hp-color-input', function() {
                    $(this).closest('.d-flex').find('.hp-color-val').text($(this).val());
                });

                // ===== Remove Image =====
                $(document).on('click', '.hp-remove-image', function() {
                    var $btn = $(this);
                    $btn.data('removed', true);
                    $btn.closest('.d-flex').find('.hp-image-preview').hide();
                    $btn.closest('.d-flex').find('small').text('(will be removed on save)');
                    $btn.prop('disabled', true);
                });

                // ===== Add Item =====
                $(document).on('click', '.hp-add-item', function() {
                    var sectionKey = $(this).data('section');
                    var itemType = $(this).data('item-type') || '';
                    var $btn = $(this);
                    var $grid = $(this).closest('.mb-3').find('.hp-items-grid');
                    if (!$grid.length) $grid = $('.hp-items-grid[data-section="' + sectionKey + '"]');
                    var csrfToken = $('[name="csrf_token"]').first().val();
                    $btn.prop('disabled', true);
                    $.ajax({
                        url: BASE_URL + 'ajax/homepage-cms.php',
                        method: 'POST',
                        data: { action: 'add_item', section_key: sectionKey, item_type: itemType, csrf_token: csrfToken },
                        dataType: 'json',
                        success: function(resp) {
                            if (resp.success) {
                                var $template = $grid.find('.hp-item-card').first();
                                if ($template.length) {
                                    var clone = $template.clone();
                                    clone.find('input, textarea, select').val('');
                                    clone.find('.hp-item-field[data-field="color"]').val('#12b76a');
                                    clone.find('.hp-item-field[data-field="stars"]').val('5');
                                    clone.find('.mt-1.text-muted.small').remove();
                                    clone.attr('data-item-id', resp.item_id);
                                    $grid.append(clone);
                                    showToast('success', 'New item added');
                                } else {
                                    showToast('success', 'Item created. Reloading...');
                                    setTimeout(function() { location.reload(); }, 800);
                                }
                            } else {
                                showToast('error', resp.error || 'Failed to add item');
                            }
                        },
                        error: function() {
                            showToast('error', 'Failed to add item');
                        },
                        complete: function() {
                            $btn.prop('disabled', false);
                        }
                    });
                });

                // ===== Remove Item =====
                $(document).on('click', '.item-remove', function() {
                    var $card = $(this).closest('.hp-item-card');
                    var itemId = $card.data('item-id');
                    if (itemId) {
                        if (!confirm('Delete this item?')) return;
                        $.ajax({
                            url: BASE_URL + 'ajax/homepage-cms.php',
                            method: 'POST',
                            data: {
                                action: 'delete_item',
                                item_id: itemId,
                                csrf_token: $('[name="csrf_token"]').first().val()
                            },
                            dataType: 'json',
                            success: function(r) {
                                if (r.success) {
                                    $card.fadeOut(300, function() { $(this).remove(); });
                                    showToast('success', 'Item deleted');
                                } else {
                                    showToast('error', r.error || 'Delete failed');
                                }
                            }
                        });
                    } else {
                        $card.remove();
                    }
                });

                // ===== Save Items (auto-save on field change) =====
                var itemSaveTimer = null;
                $(document).on('change input', '.hp-item-field', function() {
                    clearTimeout(itemSaveTimer);
                    var $field = $(this);
                    itemSaveTimer = setTimeout(function() {
                        var $card = $field.closest('.hp-item-card');
                        var itemId = $card.data('item-id');
                        if (!itemId) return;
                        var data = { action: 'update_item', item_id: itemId, csrf_token: $('[name="csrf_token"]').first().val() };
                        $card.find('.hp-item-field').each(function() {
                            data['fields[' + $(this).data('field') + ']'] = $(this).val();
                        });
                        $.ajax({
                            url: BASE_URL + 'ajax/homepage-cms.php',
                            method: 'POST',
                            data: data,
                            dataType: 'json',
                            success: function(r) {
                                if (!r.success) {
                                    showToast('error', r.error || 'Failed to save item #' + itemId);
                                }
                            },
                            error: function(xhr) {
                                var msg = 'Failed to save item #' + itemId;
                                try {
                                    var d = JSON.parse(xhr.responseText);
                                    if (d.error) msg = d.error;
                                } catch(e) {}
                                showToast('error', msg);
                            }
                        });
                    }, 500);
                });

                // ===== Restore Defaults =====
                $('#hpRestoreDefaults').on('click', function() {
                    if (!confirm('Restore ALL homepage sections to factory defaults? This cannot be undone.')) return;
                    var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Restoring...');
                    $.ajax({
                        url: BASE_URL + 'ajax/homepage-cms.php',
                        method: 'POST',
                        data: { action: 'restore_defaults', csrf_token: $('[name="csrf_token"]').first().val() },
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                showToast('success', 'Defaults restored. Reloading...');
                                setTimeout(function() { location.reload(); }, 1000);
                            } else {
                                showToast('error', r.error || 'Restore failed');
                                $btn.prop('disabled', false).html('<i class="fas fa-undo me-1"></i>Restore Defaults');
                            }
                        }
                    });
                });

                // ===== Undo =====
                $('#hpUndoBtn').on('click', function() {
                    $('#hpUndoModal').modal('show');
                });

                $(document).on('click', '.hp-restore-revision', function() {
                    if (!confirm('Restore this revision? Current data will be replaced.')) return;
                    var $btn = $(this).prop('disabled', true).html('Restoring...');
                    var revId = $btn.data('rev-id');
                    $.ajax({
                        url: BASE_URL + 'ajax/homepage-cms.php',
                        method: 'POST',
                        data: { action: 'restore_revision', revision_id: revId, csrf_token: $('[name="csrf_token"]').first().val() },
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                showToast('success', 'Revision restored. Reloading...');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                showToast('error', r.error || 'Restore failed');
                                $btn.prop('disabled', false).html('Restore');
                            }
                        }
                    });
                });

            })(jQuery);
            </script>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/views/layouts/footer.php';
?>

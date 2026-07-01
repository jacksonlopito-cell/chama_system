<?php
/**
 * Main Dashboard
 */
require_once __DIR__ . '/includes/config.php';
requireAuth();

$currentUser = getCurrentUser();
$pageTitle = 'Dashboard';

// Get stats
$db = getConnection();
$gc = $_SESSION['group_code'];

// Member count
$totalMembers = $db->prepare("SELECT COUNT(*) FROM members WHERE status = 'active' AND group_code=?");
$totalMembers->execute([$gc]);
$totalMembers = $totalMembers->fetchColumn();
$pendingMembers = $db->prepare("SELECT COUNT(*) FROM members WHERE status = 'pending' AND group_code=?");
$pendingMembers->execute([$gc]);
$pendingMembers = $pendingMembers->fetchColumn();

// Contribution stats
$totalContributions = $db->prepare("SELECT COALESCE(SUM(c.amount), 0) FROM contributions c JOIN members m ON c.member_id=m.id WHERE m.group_code=?");
$totalContributions->execute([$gc]);
$totalContributions = $totalContributions->fetchColumn();
$monthContributions = $db->prepare("SELECT COALESCE(SUM(c.amount), 0) FROM contributions c JOIN members m ON c.member_id=m.id WHERE m.group_code=? AND MONTH(c.contribution_date)=MONTH(CURRENT_DATE) AND YEAR(c.contribution_date)=YEAR(CURRENT_DATE)");
$monthContributions->execute([$gc]);
$monthContributions = $monthContributions->fetchColumn();

// Loan stats
$totalLoans = $db->prepare("SELECT COUNT(*) FROM loans l JOIN members m ON l.member_id=m.id WHERE l.status IN ('active','disbursed') AND m.group_code=?");
$totalLoans->execute([$gc]);
$totalLoans = $totalLoans->fetchColumn();
$pendingLoans = $db->prepare("SELECT COUNT(*) FROM loans l JOIN members m ON l.member_id=m.id WHERE l.status='pending' AND m.group_code=?");
$pendingLoans->execute([$gc]);
$pendingLoans = $pendingLoans->fetchColumn();
$totalDisbursed = $db->prepare("SELECT COALESCE(SUM(l.amount), 0) FROM loans l JOIN members m ON l.member_id=m.id WHERE l.status IN ('active','disbursed','paid') AND m.group_code=?");
$totalDisbursed->execute([$gc]);
$totalDisbursed = $totalDisbursed->fetchColumn();
$totalOutstanding = $db->prepare("SELECT COALESCE(SUM(l.balance), 0) FROM loans l JOIN members m ON l.member_id=m.id WHERE l.status='active' AND m.group_code=?");
$totalOutstanding->execute([$gc]);
$totalOutstanding = $totalOutstanding->fetchColumn();

// Meeting stats
$upcomingMeetings = $db->prepare("SELECT COUNT(*) FROM meetings WHERE meeting_date>=CURRENT_DATE AND status!='cancelled' AND group_code=?");
$upcomingMeetings->execute([$gc]);
$upcomingMeetings = $upcomingMeetings->fetchColumn();
$totalMeetings = $db->prepare("SELECT COUNT(*) FROM meetings WHERE group_code=?");
$totalMeetings->execute([$gc]);
$totalMeetings = $totalMeetings->fetchColumn();

// Recent members
$recentMembers = $db->prepare("SELECT id, member_no, first_name, last_name, phone, date_joined, status, photo FROM members WHERE group_code=? ORDER BY created_at DESC LIMIT 5");
$recentMembers->execute([$gc]);
$recentMembers = $recentMembers->fetchAll();

// Recent contributions
$recentContributions = $db->prepare("
    SELECT c.*, CONCAT(m.first_name, ' ', m.last_name) as member_name
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    WHERE m.group_code=?
    ORDER BY c.created_at DESC LIMIT 5
");
$recentContributions->execute([$gc]);
$recentContributions = $recentContributions->fetchAll();

$currentRole = $_SESSION['role_slug'] ?? '';

// Recent activities
$activityWhere = "u.group_code = ?";
$activityParams = [$gc];
if ($currentRole === 'member') {
    $activityWhere .= " AND a.user_id = ?";
    $activityParams[] = $_SESSION['user_id'];
}
$recentActivities = $db->prepare("
    SELECT a.*, u.username
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $activityWhere
    ORDER BY a.created_at DESC LIMIT 8
");
$recentActivities->execute($activityParams);
$recentActivities = $recentActivities->fetchAll();

// Pending loan applications
$pendingLoanWhere = "l.status IN ('pending','secretary_approved','treasurer_approved') AND m.group_code=?";
$pendingLoanParams = [$gc];
if ($currentRole === 'member' && !empty($_SESSION['member_id'])) {
    $pendingLoanWhere .= " AND l.member_id=?";
    $pendingLoanParams[] = $_SESSION['member_id'];
}
$pendingLoanApps = $db->prepare("
    SELECT l.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, lp.name as product_name
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN loan_products lp ON l.product_id = lp.id
    WHERE $pendingLoanWhere
    ORDER BY l.created_at DESC LIMIT 5
");
$pendingLoanApps->execute($pendingLoanParams);
$pendingLoanApps = $pendingLoanApps->fetchAll();

// Chart data - monthly contributions
$chartData = $db->prepare("
    SELECT DATE_FORMAT(c.contribution_date, '%b') as month_name,
           MONTH(c.contribution_date) as month_num,
           SUM(c.amount) as total
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    WHERE c.contribution_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH) AND m.group_code=?
    GROUP BY YEAR(c.contribution_date), MONTH(c.contribution_date), DATE_FORMAT(c.contribution_date, '%b')
    ORDER BY YEAR(c.contribution_date), MONTH(c.contribution_date)
");
$chartData->execute([$gc]);
$chartData = $chartData->fetchAll();

$chartLabels = [];
$chartValues = [];
foreach ($chartData as $row) {
    $chartLabels[] = $row['month_name'];
    $chartValues[] = (float)$row['total'];
}
$chartLabelsJson = json_encode($chartLabels);
$chartValuesJson = json_encode($chartValues);

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<!-- ===== Main Content ===== -->
<div class="main-content">
    <div class="page-header">
        <div>
            <h4>Dashboard</h4>
            <p>Welcome back, <?= e($currentUser['username'] ?? 'User') ?>! Here's your overview.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="window.location.reload()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
            <button class="btn btn-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print
            </button>
        </div>
    </div>

    <?php displayFlash(); ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card-stat bg-primary">
                <i class="fas fa-users icon"></i>
                <div class="number"><?= number_format($totalMembers) ?></div>
                <div class="label">Active Members</div>
                <?php if ($pendingMembers > 0): ?>
                    <small class="d-block mt-1 opacity-75"><?= $pendingMembers ?> pending approval</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card-stat bg-success">
                <i class="fas fa-piggy-bank icon"></i>
                <div class="number"><?= formatCurrency($totalContributions) ?></div>
                <div class="label">Total Contributions</div>
                <small class="d-block mt-1 opacity-75"><?= formatCurrency($monthContributions) ?> this month</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card-stat bg-warning">
                <i class="fas fa-hand-holding-usd icon"></i>
                <div class="number"><?= formatCurrency($totalDisbursed) ?></div>
                <div class="label">Loans Disbursed</div>
                <small class="d-block mt-1 opacity-75"><?= $totalLoans ?> active loans</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card-stat bg-info">
                <i class="fas fa-calendar-check icon"></i>
                <div class="number"><?= $totalMeetings ?></div>
                <div class="label">Total Meetings</div>
                <small class="d-block mt-1 opacity-75"><?= $upcomingMeetings ?> upcoming</small>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Chart -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-chart-bar me-2" style="color: var(--secondary);"></i>Contributions Overview</span>
                    <select class="form-select form-select-sm" style="width: auto;" id="chartPeriod">
                        <option value="6">Last 6 months</option>
                        <option value="12">Last 12 months</option>
                    </select>
                </div>
                <div class="card-body">
                    <canvas id="contributionChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-clock me-2" style="color: var(--secondary);"></i>Recent Activities
                </div>
                <div class="card-body p-0" style="max-height: 370px; overflow-y: auto;">
                    <?php if (empty($recentActivities)): ?>
                        <div class="text-center py-4" style="color: var(--gray-500);">
                            <i class="fas fa-inbox mb-2" style="font-size: 2rem;"></i>
                            <p class="small mb-0">No recent activities</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="list-group-item px-3 py-2 border-0 border-bottom" style="border-color: var(--gray-100) !important;">
                                    <div class="d-flex align-items-start">
                                        <div class="user-avatar user-avatar-sm me-2 flex-shrink-0">
                                            <?= strtoupper(substr($activity['username'] ?? 'S', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-size: 0.8125rem;"><?= e($activity['description']) ?></div>
                                            <small style="color: var(--gray-500); font-size: 0.6875rem;"><?= timeAgo($activity['created_at']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($currentRole !== 'member' && hasPermission('view_members')): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-users me-2" style="color: var(--secondary);"></i>Recent Members</span>
                    <a href="members.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Phone</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentMembers)): ?>
                                    <tr><td colspan="4" class="text-center py-3" style="color: var(--gray-500);">No members yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentMembers as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar user-avatar-sm me-2">
                                                        <?php if (!empty($member['photo'])): ?>
                                                            <img src="<?= BASE_URL ?>uploads/members/<?= e($member['photo']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                                        <?php else: ?>
                                                            <?= strtoupper(substr($member['first_name'], 0, 1)) . strtoupper(substr($member['last_name'], 0, 1)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 0.8125rem; font-weight: 500;">
                                                            <a href="members.php?id=<?= $member['id'] ?>" class="text-decoration-none"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></a>
                                                        </div>
                                                        <small style="color: var(--gray-500);"><?= e($member['member_no']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-size: 0.8125rem;"><?= e($member['phone']) ?></td>
                                            <td style="font-size: 0.8125rem;"><?= formatDate($member['date_joined']) ?></td>
                                            <td><?= statusBadge($member['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pending Loan Applications -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-clock me-2" style="color: var(--warning);"></i>Pending Approvals</span>
                    <a href="loan-applications.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Product</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingLoanApps)): ?>
                                    <tr><td colspan="5" class="text-center py-3" style="color: var(--gray-500);">No pending applications</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pendingLoanApps as $loan): ?>
                                        <tr>
                                            <td>
                                                <div style="font-size: 0.8125rem; font-weight: 500;"><?= e($loan['member_name']) ?></div>
                                                <small style="color: var(--gray-500);"><?= e($loan['loan_no']) ?></small>
                                            </td>
                                            <td style="font-size: 0.8125rem;"><?= e($loan['product_name']) ?></td>
                                            <td style="font-size: 0.8125rem; font-weight: 600;"><?= formatCurrency($loan['amount']) ?></td>
                                            <td style="font-size: 0.8125rem;"><?= formatDate($loan['application_date']) ?></td>
                                            <td>
                                                <a href="loan-applications.php?id=<?= $loan['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-bolt me-2" style="color: var(--secondary);"></i>Quick Actions
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php if (hasPermission('create_members')): ?>
                        <div class="col-6 col-md-3 col-lg-2">
                            <a href="members.php?action=add" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-user-plus d-block mb-1" style="font-size: 1.25rem;"></i>
                                <small>Add Member</small>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if (hasPermission('create_contributions')): ?>
                        <div class="col-6 col-md-3 col-lg-2">
                            <a href="contributions.php" class="btn btn-outline-success w-100 py-3">
                                <i class="fas fa-coins d-block mb-1" style="font-size: 1.25rem;"></i>
                                <small>Record Contribution</small>
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="col-6 col-md-3 col-lg-2">
                            <a href="loan-applications.php?action=add" class="btn btn-outline-warning w-100 py-3">
                                <i class="fas fa-hand-holding-usd d-block mb-1" style="font-size: 1.25rem;"></i>
                                <small>New Loan</small>
                            </a>
                        </div>
                        <?php if (hasPermission('create_meetings')): ?>
                        <div class="col-6 col-md-3 col-lg-2">
                            <a href="meetings.php?action=create" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-calendar-plus d-block mb-1" style="font-size: 1.25rem;"></i>
                                <small>Schedule Meeting</small>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if (hasPermission('view_reports')): ?>
                        <div class="col-6 col-md-3 col-lg-2">
                            <a href="reports/financial.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="fas fa-file-invoice d-block mb-1" style="font-size: 1.25rem;"></i>
                                <small>View Reports</small>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if (hasPermission('create_announcements')): ?>
                        <div class="col-6 col-md-3 col-lg-2">
                            <a href="announcements.php" class="btn btn-outline-danger w-100 py-3">
                                <i class="fas fa-bullhorn d-block mb-1" style="font-size: 1.25rem;"></i>
                                <small>Announcement</small>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<EOT
<script>
$(document).ready(function() {
    // Contribution Chart
    const ctx = document.getElementById('contributionChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {$chartLabelsJson},
            datasets: [{
                label: 'Contributions',
                data: {$chartValuesJson},
                backgroundColor: 'rgba(70, 95, 255, 0.2)',
                borderColor: '#465fff',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return 'KSh ' + v.toLocaleString(); } }
                }
            }
        }
    });
});
</script>
EOT;

include __DIR__ . '/views/layouts/footer.php';
?>

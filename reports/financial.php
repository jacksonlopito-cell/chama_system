<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();
requirePermission('view_reports');
$pageTitle = 'Financial Report';

$db = getConnection();
$currentUser = getCurrentUser();
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

$gc = $_SESSION['group_code'];

$totalContributions = $db->prepare("SELECT COALESCE(SUM(c.amount),0) FROM contributions c JOIN members m ON c.member_id=m.id WHERE m.group_code=? AND c.created_at>=? AND c.created_at<=?");
$totalContributions->execute([$gc, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
$contrib = $totalContributions->fetchColumn();

$totalExpenses = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_code=? AND expense_date>=? AND expense_date<=?");
$totalExpenses->execute([$gc, $dateFrom, $dateTo]);
$exp = $totalExpenses->fetchColumn();

$totalLoans = $db->prepare("SELECT COALESCE(SUM(l.amount),0) FROM loans l JOIN members m ON l.member_id=m.id WHERE m.group_code=? AND l.created_at>=? AND l.created_at<=? AND l.status IN ('active','disbursed','paid')");
$totalLoans->execute([$gc, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
$loans = $totalLoans->fetchColumn();

$totalRepayments = $db->prepare("SELECT COALESCE(SUM(lp.amount),0) FROM loan_payments lp JOIN loans l ON lp.loan_id=l.id JOIN members m ON l.member_id=m.id WHERE m.group_code=? AND lp.created_at>=? AND lp.created_at<=?");
$totalRepayments->execute([$gc, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
$repay = $totalRepayments->fetchColumn();

$totalIncome = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE group_code=? AND income_date>=? AND income_date<=?");
$totalIncome->execute([$gc, $dateFrom, $dateTo]);
$inc = $totalIncome->fetchColumn();

include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/layouts/sidebar.php';
include __DIR__ . '/../views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Financial Report</h4></div>
        <a href="../export.php?type=financial&<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-success"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
    </div>
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4"><input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>"></div>
        <div class="col-md-4"><input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>"></div>
        <div class="col-md-4"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-sync me-1"></i>Generate</button></div>
    </form>
    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <div class="card bg-success bg-opacity-10"><div class="card-body text-center"><h4><?= formatCurrency($contrib) ?></h4><small>Total Contributions</small></div></div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card bg-primary bg-opacity-10"><div class="card-body text-center"><h4><?= formatCurrency($inc) ?></h4><small>Other Income</small></div></div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card bg-danger bg-opacity-10"><div class="card-body text-center"><h4><?= formatCurrency($exp) ?></h4><small>Expenses</small></div></div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card bg-dark bg-opacity-10"><div class="card-body text-center"><h4><?= formatCurrency($contrib + $inc - $exp) ?></h4><small>Operating Result</small></div></div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card bg-warning bg-opacity-10"><div class="card-body text-center"><h4><?= formatCurrency($loans) ?></h4><small>Loans Disbursed</small></div></div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card bg-info bg-opacity-10"><div class="card-body text-center"><h4><?= formatCurrency($repay) ?></h4><small>Loan Repayments</small></div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../views/layouts/footer.php'; ?>

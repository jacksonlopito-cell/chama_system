<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('export_reports');

require_once __DIR__ . '/assets/fpdf/fpdf.php';

$type = $_GET['type'] ?? 'contributions';
$db = getConnection();

class ChamaPDF extends FPDF {
    protected $groupName = 'CHAMA System';

    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(22, 25, 80);
        $this->Cell(0, 10, $this->groupName, 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100);
        $this->Cell(0, 6, 'Report: ' . ucfirst(str_replace('_', ' ', htmlspecialchars($_GET['type'] ?? '', ENT_QUOTES, 'UTF-8'))), 0, 1, 'C');
        $this->Cell(0, 6, 'Generated: ' . date('d M Y H:i'), 0, 1, 'C');
        $this->Ln(5);
        $this->SetDrawColor(70, 95, 255);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new ChamaPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

switch ($type) {
    case 'contributions':
        $where = '';
        $params = [];
        $where .= " AND m.group_code=?"; $params[] = $_SESSION['group_code'];
        if (!empty($_GET['type'])) { $where .= " AND ct.id=?"; $params[] = (int)$_GET['type']; }
        if (!empty($_GET['member_id'])) { $where .= " AND c.member_id=?"; $params[] = (int)$_GET['member_id']; }
        if (!empty($_GET['date_from'])) { $where .= " AND c.created_at>=?"; $params[] = $_GET['date_from'] . ' 00:00:00'; }
        if (!empty($_GET['date_to'])) { $where .= " AND c.created_at<=?"; $params[] = $_GET['date_to'] . ' 23:59:59'; }
        $stmt = $db->prepare("SELECT c.*, m.member_no as member_number, m.first_name, m.last_name, ct.name as type_name FROM contributions c JOIN members m ON c.member_id=m.id JOIN contribution_types ct ON c.type_id=ct.id WHERE 1=1 $where ORDER BY c.created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 7, 'Date', 1);
        $pdf->Cell(25, 7, 'Member #', 1);
        $pdf->Cell(40, 7, 'Name', 1);
        $pdf->Cell(30, 7, 'Type', 1);
        $pdf->Cell(30, 7, 'Amount', 1, 1);
        $pdf->SetFont('Arial', '', 9);
        $total = 0;
        foreach ($rows as $r) {
            $pdf->Cell(25, 6, date('d/m/Y', strtotime($r['created_at'])), 1);
            $pdf->Cell(25, 6, $r['member_number'], 1);
            $pdf->Cell(40, 6, substr($r['first_name'] . ' ' . $r['last_name'], 0, 25), 1);
            $pdf->Cell(30, 6, $r['type_name'], 1);
            $pdf->Cell(30, 6, number_format($r['amount'], 2), 1, 1);
            $total += $r['amount'];
        }
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(120, 7, 'Total', 1);
        $pdf->Cell(30, 7, number_format($total, 2), 1, 1);
        break;

    case 'loans':
        $where = '';
        $params = [];
        $where .= " AND m.group_code=?"; $params[] = $_SESSION['group_code'];
        if (!empty($_GET['status'])) { $where .= " AND l.status=?"; $params[] = $_GET['status']; }
        if (!empty($_GET['member_id'])) { $where .= " AND l.member_id=?"; $params[] = (int)$_GET['member_id']; }
        $stmt = $db->prepare("SELECT l.*, m.member_no as member_number, m.first_name, m.last_name, lp.name as product_name FROM loans l JOIN members m ON l.member_id=m.id JOIN loan_products lp ON l.product_id=lp.id WHERE 1=1 $where ORDER BY l.created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(20, 7, 'Date', 1);
        $pdf->Cell(25, 7, 'Member #', 1);
        $pdf->Cell(35, 7, 'Name', 1);
        $pdf->Cell(25, 7, 'Product', 1);
        $pdf->Cell(25, 7, 'Amount', 1);
        $pdf->Cell(20, 7, 'Status', 1, 1);
        $pdf->SetFont('Arial', '', 9);
        foreach ($rows as $r) {
            $pdf->Cell(20, 6, date('d/m/Y', strtotime($r['created_at'])), 1);
            $pdf->Cell(25, 6, $r['member_number'], 1);
            $pdf->Cell(35, 6, substr($r['first_name'] . ' ' . $r['last_name'], 0, 20), 1);
            $pdf->Cell(25, 6, substr($r['product_name'], 0, 15), 1);
            $pdf->Cell(25, 6, number_format($r['amount'], 2), 1);
            $pdf->Cell(20, 6, ucfirst(str_replace('_', ' ', $r['status'])), 1, 1);
        }
        break;

    case 'members':
        $where = '';
        $params = [];
        $where .= " AND group_code=?"; $params[] = $_SESSION['group_code'];
        if (!empty($_GET['status'])) { $where .= " AND status=?"; $params[] = $_GET['status']; }
        $stmt = $db->prepare("SELECT * FROM members WHERE 1=1 $where ORDER BY first_name");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 7, 'Member #', 1);
        $pdf->Cell(45, 7, 'Name', 1);
        $pdf->Cell(40, 7, 'Phone', 1);
        $pdf->Cell(20, 7, 'Gender', 1);
        $pdf->Cell(20, 7, 'Status', 1, 1);
        $pdf->SetFont('Arial', '', 9);
        foreach ($rows as $r) {
            $pdf->Cell(25, 6, $r['member_no'], 1);
            $pdf->Cell(45, 6, substr(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''), 0, 30), 1);
            $pdf->Cell(40, 6, $r['phone'] ?? '-', 1);
            $pdf->Cell(20, 6, ucfirst($r['gender'] ?? '-'), 1);
            $pdf->Cell(20, 6, ucfirst($r['status']), 1, 1);
        }
        break;

    case 'financial':
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-t');
        $contribStmt = $db->prepare("SELECT COALESCE(SUM(c.amount),0) FROM contributions c JOIN members m ON c.member_id=m.id WHERE m.group_code=? AND c.created_at>=? AND c.created_at<=?");
        $contribStmt->execute([$_SESSION['group_code'], $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        $contrib = $contribStmt->fetchColumn();
        $expStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_code=? AND expense_date>=? AND expense_date<=?");
        $expStmt->execute([$_SESSION['group_code'], $dateFrom, $dateTo]);
        $exp = $expStmt->fetchColumn();
        $incomeStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE group_code=? AND income_date>=? AND income_date<=?");
        $incomeStmt->execute([$_SESSION['group_code'], $dateFrom, $dateTo]);
        $inc = $incomeStmt->fetchColumn();
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, "Period: " . date('d M Y', strtotime($dateFrom)) . " - " . date('d M Y', strtotime($dateTo)), 0, 1);
        $pdf->Ln(5);
        $items = [
            ['Total Contributions', $contrib],
            ['Other Income', $inc],
            ['Total Expenses', $exp],
            ['Net Position', $contrib + $inc - $exp],
        ];
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(60, 7, 'Category', 1);
        $pdf->Cell(40, 7, 'Amount (KES)', 1, 1);
        $pdf->SetFont('Arial', '', 9);
        foreach ($items as $item) {
            $pdf->Cell(60, 6, $item[0], 1);
            $pdf->Cell(40, 6, number_format($item[1], 2), 1, 1);
        }
        break;

    default:
        $pdf->Cell(0, 10, 'Unknown report type', 0, 1);
}

$pdf->Output('D', ucfirst($type) . '_Report_' . date('Ymd') . '.pdf');
exit;

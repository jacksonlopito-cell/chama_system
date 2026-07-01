<?php
/**
 * PDF Documentation Generator
 * Converts Markdown docs to styled PDF using FPDF
 */

/**
 * PDF Documentation Generator
 * Converts Markdown docs to styled PDF using FPDF
 */

// Determine path relative to project root
$projectRoot = __DIR__ . '/..';

// Skip auth when running from CLI
if (php_sapi_name() === 'cli') {
    // Just load FPDF directly
    require_once $projectRoot . '/assets/fpdf/fpdf.php';
} else {
    require_once $projectRoot . '/includes/config.php';
    requireAuth();
    require_once $projectRoot . '/assets/fpdf/fpdf.php';
}

require_once $projectRoot . '/assets/fpdf/fpdf.php';

// Simple Markdown to PDF converter
class DocPDF extends FPDF {
    protected $title;
    protected $docFile;
    protected $inCodeBlock = false;
    protected $codeBlock = [];
    protected $inTable = false;
    protected $tableHeaders = [];
    protected $tableRows = [];
    protected $tableColWidths = [];
    protected $listDepth = 0;
    protected $sectionNumbers = [];
    protected $inBlockquote = false;
    protected $tempY = 0;

    function __construct($title, $docFile = '') {
        parent::__construct();
        $this->title = $title;
        $this->docFile = $docFile;
        $this->SetAutoPageBreak(true, 20);
    }

    function Header() {
        if ($this->PageNo() > 1) {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(100);
            $this->Cell(0, 6, $this->title, 0, 0, 'L');
            $this->Cell(0, 6, 'Page ' . $this->PageNo() . '/{nb}', 0, 1, 'R');
            $this->SetDrawColor(200, 200, 200);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(4);
        }
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, $this->title . ' | Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function renderMd($filePath) {
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->addErrorPage("Cannot read file: $filePath");
            return;
        }
        $lines = explode("\n", $content);

        // Cover page
        $this->addCoverPage();

        // Table of contents
        $this->addTocPage($lines);

        // Render content
        $this->renderLines($lines);
    }

    protected function addCoverPage() {
        $this->AddPage();
        $this->Ln(50);
        $this->SetFont('Arial', 'B', 28);
        $this->SetTextColor(22, 25, 80);
        $this->Cell(0, 15, 'Advanced Chama', 0, 1, 'C');
        $this->Cell(0, 15, 'Management System', 0, 1, 'C');
        $this->Ln(10);
        $this->SetFont('Arial', '', 14);
        $this->SetTextColor(100);
        $this->Cell(0, 10, $this->title, 0, 1, 'C');
        $this->Ln(5);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 8, 'Generated: ' . date('d F Y H:i'), 0, 1, 'C');
        $this->Cell(0, 8, 'Version 1.0', 0, 1, 'C');
        $this->SetDrawColor(70, 95, 255);
        $this->Line(60, $this->GetY() + 5, 150, $this->GetY() + 5);
    }

    protected function addTocPage($lines) {
        $this->AddPage();
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(22, 25, 80);
        $this->Cell(0, 10, 'Table of Contents', 0, 1, 'L');
        $this->Ln(5);

        $toc = [];
        $page = 3; // cover + toc = 2 pages
        $sectionCount = [0];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^(#{2,3})\s+(.+)/', $trimmed, $m)) {
                $level = strlen($m[1]);
                $title = strip_tags(html_entity_decode($m[2]));
                $toc[] = ['level' => $level, 'title' => $title, 'page' => $page];
            }
        }

        $this->SetFont('Arial', '', 11);
        foreach ($toc as $item) {
            $indent = ($item['level'] - 2) * 10;
            $this->SetX(15 + $indent);
            $this->SetTextColor(50);
            if ($item['level'] == 2) {
                $this->SetFont('Arial', 'B', 11);
            } else {
                $this->SetFont('Arial', '', 10);
            }
            $this->Cell(0, 7, $item['title'], 0, 1);
        }
        $this->Ln(10);
    }

    protected function renderLines($lines) {
        $this->AddPage();
        $i = 0;
        $sectionCount = [0];
        $inTable = false;
        $tableBuffer = [];
        $listStack = [];
        $inBlockquote = false;

        while ($i < count($lines)) {
            $line = $lines[$i];
            $trimmed = trim($line);
            $raw = $line;

            // Code block fence
            if (strpos($trimmed, '```') === 0) {
                if ($this->inCodeBlock) {
                    $this->renderCodeBlock(implode("\n", $this->codeBlock));
                    $this->inCodeBlock = false;
                    $this->codeBlock = [];
                } else {
                    $this->inCodeBlock = true;
                    $this->codeBlock = [];
                }
                $i++;
                continue;
            }
            if ($this->inCodeBlock) {
                $this->codeBlock[] = $raw;
                $i++;
                continue;
            }

            // Tables
            if (strpos($trimmed, '|') === 0 && substr_count($trimmed, '|') >= 2) {
                $tableBuffer[] = $trimmed;
                $i++;
                continue;
            }
            if (!empty($tableBuffer)) {
                $this->renderTable($tableBuffer);
                $tableBuffer = [];
                $this->Ln(3);
            }

            // Horizontal rule
            if (preg_match('/^---+/', $trimmed)) {
                if ($this->inBlockquote) { $this->endBlockquote(); $this->inBlockquote = false; }
                $this->SetDrawColor(200);
                $this->Line(15, $this->GetY() + 2, 195, $this->GetY() + 2);
                $this->Ln(6);
                $i++;
                continue;
            }

            // Headings
            if (preg_match('/^(#{1,4})\s+(.+)/', $trimmed, $m)) {
                if ($this->inBlockquote) { $this->endBlockquote(); $this->inBlockquote = false; }
                $level = strlen($m[1]);
                $text = $this->cleanInline($m[2]);
                $this->renderHeading($text, $level, $sectionCount);
                $i++;
                continue;
            }

            // Blockquote
            if (strpos($trimmed, '> ') === 0) {
                if (!$this->inBlockquote) { $this->beginBlockquote(); $this->inBlockquote = true; }
                $text = $this->cleanInline(substr($trimmed, 2));
                $this->SetX(20);
                $this->SetFont('Arial', 'I', 10);
                $this->SetTextColor(80, 90, 120);
                $this->MultiCell(170, 5.5, $text);
                $i++;
                continue;
            }
            if ($this->inBlockquote) { $this->endBlockquote(); $this->inBlockquote = false; }

            // Unordered list
            if (preg_match('/^(\s*)[-*+]\s+(.+)/', $trimmed, $m)) {
                $depth = floor(strlen($m[1]) / 2);
                $text = $this->cleanInline($m[2]);
                $bullet = $depth == 0 ? "\x95" : ($depth == 1 ? "\x97" : '-');
                $x = 18 + ($depth * 8);
                $this->SetX($x);
                $this->SetFont('Arial', '', 10);
                $this->SetTextColor(30);
                $this->Cell(5, 5.5, $bullet, 0, 0);
                $this->SetX($x + 6);
                $this->MultiCell(170 - ($depth * 8) - 6, 5.5, $text);
                $i++;
                continue;
            }

            // Ordered list
            if (preg_match('/^(\s*)(\d+)\.\s+(.+)/', $trimmed, $m)) {
                $depth = floor(strlen($m[1]) / 2);
                $num = $m[2];
                $text = $this->cleanInline($m[3]);
                $x = 18 + ($depth * 8);
                $this->SetX($x);
                $this->SetFont('Arial', '', 10);
                $this->SetTextColor(30);
                $this->Cell(8, 5.5, "$num.", 0, 0);
                $this->SetX($x + 9);
                $this->MultiCell(170 - ($depth * 8) - 9, 5.5, $text);
                $i++;
                continue;
            }

            // Bold headings (plain text paragraphs)
            if (preg_match('/^\*\*(.+?)\*\*[:.]?\s*(.*)/', $trimmed, $m)) {
                $bold = $m[1];
                $rest = $m[2];
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor(30);
                $this->SetX(15);
                $w = $this->GetStringWidth($bold) + 2;
                $this->Cell($w, 5.5, $bold);
                if ($rest) {
                    $this->SetFont('Arial', '', 10);
                    $this->MultiCell(170 - $w, 5.5, $rest);
                } else {
                    $this->Ln();
                }
                $i++;
                continue;
            }

            // Blank line
            if (empty($trimmed)) {
                $this->Ln(2);
                $i++;
                continue;
            }

            // Normal paragraph
            $text = $this->cleanInline($trimmed);
            if (!empty($text)) {
                $this->SetFont('Arial', '', 10);
                $this->SetTextColor(30);
                $this->SetX(15);
                $this->MultiCell(180, 5.5, $text);
                $i++;
                continue;
            }

            $i++;
        }

        if (!empty($tableBuffer)) {
            $this->renderTable($tableBuffer);
        }
        if ($this->inCodeBlock) {
            $this->renderCodeBlock(implode("\n", $this->codeBlock));
        }
        if ($this->inBlockquote) {
            $this->endBlockquote();
        }
    }

    protected function renderHeading($text, $level, &$sectionCount) {
        if ($level <= 2) {
            // New section
            $this->Ln(6);
        }
        switch ($level) {
            case 1:
                $this->SetFont('Arial', 'B', 16);
                $this->SetTextColor(22, 25, 80);
                $this->SetX(15);
                $this->Cell(0, 10, $text, 0, 1);
                $this->SetDrawColor(70, 95, 255);
                $this->Line(15, $this->GetY(), 195, $this->GetY());
                $this->Ln(4);
                break;
            case 2:
                $this->SetFont('Arial', 'B', 13);
                $this->SetTextColor(22, 25, 80);
                $this->SetX(15);
                $this->Cell(0, 8, $text, 0, 1);
                $this->Ln(2);
                break;
            case 3:
                $this->SetFont('Arial', 'B', 11);
                $this->SetTextColor(60);
                $this->SetX(15);
                $this->Cell(0, 7, $text, 0, 1);
                $this->Ln(1);
                break;
            case 4:
                $this->SetFont('Arial', 'BI', 10);
                $this->SetTextColor(80);
                $this->SetX(15);
                $this->Cell(0, 6, $text, 0, 1);
                $this->Ln(1);
                break;
        }
    }

    protected function renderCodeBlock($code) {
        $this->Ln(3);
        $this->SetFillColor(245, 247, 250);
        $this->SetDrawColor(220, 225, 235);
        $y = $this->GetY();
        $lines = explode("\n", $code);
        $numLines = count($lines);
        $height = $numLines * 5.5 + 6;

        // Check page break
        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage();
        }

        $this->Rect(15, $this->GetY(), 180, $height, 'DF');
        $this->Ln(3);
        $this->SetFont('Courier', '', 8);
        $this->SetTextColor(40, 50, 80);
        foreach ($lines as $cl) {
            $this->SetX(18);
            $this->Cell(0, 5, html_entity_decode($cl), 0, 1);
        }
        $this->Ln(3);
    }

    protected function renderTable($rows) {
        if (count($rows) < 2) return;

        // Parse header and data
        $header = $this->parseTableRow($rows[0]);
        $alignRow = isset($rows[1]) && preg_match('/^[\s:|:-]+$/', trim($rows[1])) ? $rows[1] : null;
        $dataStart = $alignRow ? 2 : 1;

        $numCols = count($header);
        if ($numCols == 0) return;

        $colWidth = min(30, floor(170 / $numCols));
        $colWidth = max(20, $colWidth);
        $tableWidth = $colWidth * $numCols;
        $xStart = 10 + (180 - $tableWidth) / 2;

        // Check page break
        $rowHeight = 6;
        $headerHeight = 7;
        $totalHeight = $headerHeight + (count($rows) - $dataStart) * $rowHeight + 6;
        if ($this->GetY() + $totalHeight > $this->PageBreakTrigger) {
            $this->AddPage();
        }

        // Header
        $this->SetFillColor(22, 25, 80);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 8);
        $this->SetX($xStart);
        foreach ($header as $h) {
            $this->Cell($colWidth, $headerHeight, trim($h), 1, 0, 'C', true);
        }
        $this->Ln();

        // Data rows
        $this->SetFillColor(248, 249, 251);
        $fill = false;
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(30);

        for ($i = $dataStart; $i < count($rows); $i++) {
            $row = $this->parseTableRow($rows[$i]);
            if (count($row) != $numCols) continue;

            $this->SetX($xStart);
            foreach ($row as $j => $cell) {
                $cell = trim($cell);
                // Handle inline bold in table cells
                $cell = preg_replace('/\*\*(.+?)\*\*/', '$1', $cell);
                $this->Cell($colWidth, $rowHeight, html_entity_decode($cell), 1, 0, 'L', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
        $this->Ln(3);
    }

    protected function parseTableRow($row) {
        $row = trim($row);
        if (strpos($row, '|') !== 0) $row = '|' . $row;
        if (substr($row, -1) !== '|') $row .= '|';
        $parts = explode('|', $row);
        array_shift($parts);
        array_pop($parts);
        return array_map('trim', $parts);
    }

    protected function cleanInline($text) {
        // Remove bold markers
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        // Remove italic markers
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        // Remove inline code
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        // Remove links but keep text
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        // Remove images
        $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', $text);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return $text;
    }

    protected function beginBlockquote() {
        $this->SetFillColor(235, 240, 255);
        $y = $this->GetY();
        $this->Rect(15, $y, 3, 10, 'F'); // temporary, will be extended
        $this->tempY = $y;
    }

    protected function endBlockquote() {
        // Extend the left bar to current position
        $y1 = $this->tempY;
        $y2 = $this->GetY();
        $this->SetFillColor(70, 95, 255);
        $this->Rect(15, $y1, 3, $y2 - $y1, 'F');
        $this->SetTextColor(30);
        $this->Ln(2);
    }

    protected function addErrorPage($msg) {
        $this->AddPage();
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(200, 0, 0);
        $this->Cell(0, 10, 'Error: ' . $msg, 0, 1);
    }
}

// Main dispatch
$docName = $_GET['doc'] ?? ($argv[1] ?? 'user-manual');

$documents = [
    'user-manual' => [
        'file' => 'USER_MANUAL.md',
        'title' => 'User Manual',
    ],
    'admin-guide' => [
        'file' => 'ADMINISTRATOR_GUIDE.md',
        'title' => 'Administrator Guide',
    ],
    'member-guide' => [
        'file' => 'MEMBER_GUIDE.md',
        'title' => 'Member Guide',
    ],
    'quick-start' => [
        'file' => 'QUICK_START_GUIDE.md',
        'title' => 'Quick Start Guide',
    ],
    'faq' => [
        'file' => 'FAQ.md',
        'title' => 'Frequently Asked Questions',
    ],
    'glossary' => [
        'file' => 'GLOSSARY.md',
        'title' => 'Glossary of Terms',
    ],
    'troubleshooting' => [
        'file' => 'TROUBLESHOOTING.md',
        'title' => 'Troubleshooting Guide',
    ],
];

if (!isset($documents[$docName])) {
    header('Content-Type: text/html');
    echo "<h1>Document Not Found</h1>";
    echo "<p>Available documents:</p><ul>";
    foreach ($documents as $key => $doc) {
        echo "<li><a href='?doc=$key'>{$doc['title']}</a></li>";
    }
    echo "</ul>";
    exit;
}

$doc = $documents[$docName];
$filePath = __DIR__ . '/' . $doc['file'];

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $docName . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');

$pdf = new DocPDF($doc['title'], $filePath);
$pdf->AliasNbPages();
$pdf->renderMd($filePath);

if (php_sapi_name() === 'cli') {
    $pdf->Output('F', $projectRoot . '/docs/' . $docName . '.pdf');
    echo 'PDF saved to: docs/' . $docName . '.pdf' . "\n";
} else {
    $pdf->Output('D', $docName . '.pdf');
}
if (php_sapi_name() !== 'cli') {
    exit;
}

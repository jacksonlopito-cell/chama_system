<?php
/**
 * Chama System — Manual Generator
 * 
 * Generates a complete HTML manual from the Markdown docs.
 * Open in browser and use Ctrl+P → "Save as PDF" for PDF output.
 * 
 * Usage: php generate-manual.php > manual.html
 *        (then open manual.html and print to PDF)
 */

$docsDir = __DIR__;

function readDoc($path) {
    return file_exists($path) ? file_get_contents($path) : "# Missing\n\n*Document not found: $path*";
}

function mdToHtml($md) {
    // Basic Markdown to HTML conversion for our specific format
    $html = $md;
    
    // Code blocks
    $html = preg_replace('/```(\w*)\n(.*?)```/s', '<pre><code>$2</code></pre>', $html);
    
    // Tables
    $html = preg_replace_callback('/\|(.+)\|\n\|[-| :]+\|\n((?:\|.+\|\n)*)/', function($m) {
        $rows = explode("\n", trim($m[0]));
        $out = "<table>\n<thead>\n<tr>\n";
        $headers = explode('|', trim($rows[0], '|'));
        foreach ($headers as $h) {
            $out .= "<th>" . trim($h) . "</th>\n";
        }
        $out .= "</tr>\n</thead>\n<tbody>\n";
        for ($i = 2; $i < count($rows); $i++) {
            $cells = explode('|', trim($rows[$i], '|'));
            $out .= "<tr>\n";
            foreach ($cells as $c) {
                $out .= "<td>" . trim($c) . "</td>\n";
            }
            $out .= "</tr>\n";
        }
        $out .= "</tbody>\n</table>\n";
        return $out;
    }, $html);
    
    // Bold
    $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
    
    // Inline code
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    
    // Headers
    $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    
    // Lists
    $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*\n)+/m', '<ul>$0</ul>', $html);
    
    // Paragraphs (double newlines)
    $html = preg_replace('/\n\n+/', "</p><p>", $html);
    $html = "<p>" . $html . "</p>";
    
    // Clean up nested paragraphs
    $html = str_replace("<p><h", "<h", $html);
    $html = str_replace("</h", "</h", $html);
    $html = preg_replace('/<\/h[1-4]><p>/', '</h1>', $html);
    $html = str_replace("</p><ul>", "<ul>", $html);
    $html = str_replace("</ul><p>", "</ul>", $html);
    $html = str_replace("<p><ul>", "<ul>", $html);
    $html = str_replace("<p><li>", "<li>", $html);
    $html = str_replace("</li><p>", "</li>", $html);
    $html = str_replace("<p><table>", "<table>", $html);
    $html = str_replace("</table><p>", "</table>", $html);
    $html = str_replace("<p><pre>", "<pre>", $html);
    $html = str_replace("</pre><p>", "</pre>", $html);
    $html = str_replace("<p><code>", "<code>", $html);
    $html = str_replace("</code><p>", "</code>", $html);
    $html = str_replace("<p><strong>", "<strong>", $html);
    $html = str_replace("</strong><p>", "</strong>", $html);
    
    return $html;
}

$moduleFiles = [
    'Dashboard' => 'modules/dashboard.md',
    'Members' => 'modules/members.md',
    'Users' => 'modules/users.md',
    'Meetings' => 'modules/meetings.md',
    'Contributions' => 'modules/contributions.md',
    'Loans' => 'modules/loans.md',
    'Share Products' => 'modules/share-products.md',
    'Share Purchases' => 'modules/shares.md',
    'Dividends' => 'modules/dividends.md',
    'Accounting - Income' => 'modules/income.md',
    'Accounting - Expenses' => 'modules/expenses.md',
    'Accounting - Journal' => 'modules/journal.md',
    'Accounting - Ledger' => 'modules/ledger.md',
    'Accounting - Trial Balance' => 'modules/trial-balance.md',
    'Reports' => 'modules/reports.md',
    'Settings' => 'modules/settings.md',
    'Audit Logs' => 'modules/audit-logs.md',
    'Contact Messages' => 'modules/contact-messages.md',
    'Profile' => 'modules/profile.md',
];

$indexMd = file_exists("$docsDir/index.md") ? file_get_contents("$docsDir/index.md") : '';
$schemaMd = file_exists("$docsDir/database-schema.md") ? file_get_contents("$docsDir/database-schema.md") : '';
$securityMd = file_exists("$docsDir/security.md") ? file_get_contents("$docsDir/security.md") : '';
$troubleshootingMd = file_exists("$docsDir/troubleshooting.md") ? file_get_contents("$docsDir/troubleshooting.md") : '';
$quickStartMd = file_exists("$docsDir/quick-start.md") ? file_get_contents("$docsDir/quick-start.md") : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chama System — Complete User Manual</title>
<style>
  @media print {
    @page { margin: 1.5cm; size: A4; }
    body { font-size: 10pt; }
    .page-break { page-break-before: always; }
    nav { display: none; }
    h1 { page-break-before: always; }
    h1:first-of-type { page-break-before: avoid; }
    h2 { page-break-after: avoid; }
    h3, h4 { page-break-after: avoid; }
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; }
    pre { page-break-inside: avoid; }
  }
  @media screen {
    body { max-width: 900px; margin: 2em auto; padding: 0 2em; }
  }
  body {
    font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
    line-height: 1.6;
    color: #222;
  }
  h1 { font-size: 2em; color: #1a365d; border-bottom: 2px solid #2b6cb0; padding-bottom: 0.3em; margin-top: 1.5em; }
  h2 { font-size: 1.5em; color: #2b6cb0; margin-top: 1.2em; }
  h3 { font-size: 1.2em; color: #2d3748; margin-top: 1em; }
  h4 { font-size: 1.1em; color: #4a5568; }
  table { border-collapse: collapse; width: 100%; margin: 1em 0; font-size: 0.95em; }
  th, td { border: 1px solid #cbd5e0; padding: 0.4em 0.6em; text-align: left; }
  th { background: #edf2f7; font-weight: 600; }
  tr:nth-child(even) td { background: #f7fafc; }
  code { background: #edf2f7; padding: 0.1em 0.3em; border-radius: 3px; font-size: 0.9em; }
  pre { background: #2d3748; color: #e2e8f0; padding: 1em; border-radius: 5px; overflow-x: auto; }
  pre code { background: transparent; color: inherit; }
  ul { padding-left: 1.5em; }
  li { margin: 0.3em 0; }
  strong { color: #1a202c; }
  .cover {
    text-align: center;
    padding: 4em 0 2em;
    border-bottom: 3px solid #2b6cb0;
    margin-bottom: 2em;
  }
  .cover h1 { font-size: 2.5em; border: none; margin: 0.5em 0; }
  .cover .meta { color: #718096; font-size: 1.1em; }
  .toc { columns: 2; column-gap: 2em; margin: 1em 0; }
  .toc a { display: block; padding: 0.2em 0; color: #2b6cb0; text-decoration: none; }
  .toc a:hover { text-decoration: underline; }
  .header-link { float: right; font-size: 0.6em; color: #cbd5e0; text-decoration: none; }
  .header-link:hover { color: #2b6cb0; }
  .badge { display: inline-block; padding: 0.1em 0.4em; border-radius: 3px; font-size: 0.85em; font-weight: 600; }
</style>
</head>
<body>

<div class="cover">
  <h1>Chama System</h1>
  <p class="meta">Complete User Manual</p>
  <p class="meta">Version 1.0 &mdash; July 2026</p>
  <p class="meta"><code>http://localhost/chama-system/</code></p>
</div>

<nav>
  <h2>Table of Contents</h2>
  <div class="toc">
    <a href="#system-overview">System Overview</a>
    <a href="#quick-start">Quick Start Guide</a>
    <a href="#role-based-access">Role-Based Access</a>
    <a href="#security-model">Security Model</a>
    <a href="#database-schema">Database Schema</a>
    <strong>Modules:</strong>
<?php foreach ($moduleFiles as $name => $file): ?>
    <a href="#module-<?php echo strtolower(str_replace([' ', '-', '/'], '-', $name)); ?>"><?php echo $name; ?></a>
<?php endforeach; ?>
    <a href="#troubleshooting">Troubleshooting</a>
  </div>
</nav>

<!-- System Overview -->
<div class="page-break"></div>
<?php
// Extract sections from index.md
$sections = preg_split('/^## /m', $indexMd);
foreach ($sections as $section) {
    if (preg_match('/^1\. System Overview/', $section)) {
        echo mdToHtml("## System Overview\n" . $section);
    }
}
?>

<!-- Quick Start -->
<div class="page-break"></div>
<?php echo mdToHtml("## Quick Start Guide\n\n" . $quickStartMd); ?>

<!-- Role-Based Access -->
<div class="page-break"></div>
<?php
foreach ($sections as $section) {
    if (preg_match('/^3\. Role-Based Access/', $section)) {
        echo mdToHtml("## Role-Based Access\n" . $section);
    }
}
?>

<!-- Security Model -->
<div class="page-break"></div>
<?php echo mdToHtml("## Security Model\n\n" . $securityMd); ?>

<!-- Database Schema -->
<div class="page-break"></div>
<?php echo mdToHtml("## Database Schema\n\n" . $schemaMd); ?>

<!-- Modules -->
<div class="page-break"></div>
<h1>Module Reference</h1>

<?php foreach ($moduleFiles as $name => $file):
    $fullPath = "$docsDir/$file";
    $content = file_get_contents($fullPath);
    // Remove the first h1 (already in the title) and add anchor
    $content = preg_replace('/^# .+\n/', '', $content);
    $anchor = 'module-' . strtolower(str_replace([' ', '-', '/'], '-', $name));
?>
<div id="<?php echo $anchor; ?>">
  <?php echo mdToHtml("## $name\n" . $content); ?>
</div>
<div class="page-break"></div>
<?php endforeach; ?>

<!-- Troubleshooting -->
<?php echo mdToHtml("## Troubleshooting\n\n" . $troubleshootingMd); ?>

<hr>
<p style="text-align:center;color:#718096;font-size:0.9em;">
  Generated <?php echo date('Y-m-d H:i'); ?> &mdash; Chama System Documentation
</p>

</body>
</html>

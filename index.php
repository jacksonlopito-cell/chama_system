<?php
/**
 * Landing Page - Advanced Chama Management System
 * Fully dynamic — all sections rendered from the homepage CMS database.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/homepage_cms.php';

$pageTitle = getSetting('site_name', 'Advanced Chama Management System');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="A modern Chama management system for savings, loans, shares, meetings, and member management.">
    <link rel="icon" href="assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="landing-page">

<?php hp_render_all(); ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>

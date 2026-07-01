<?php
/**
 * Migration 006: Homepage CMS Normalized Schema
 * 
 * Drops the old JSON-based page_sections table and creates
 * proper relational tables for a visual homepage CMS.
 * 
 * Usage: php migrations/run_006.php
 */

require_once __DIR__ . '/../includes/config.php';

$db = getConnection();

echo "=== Homepage CMS Normalized Schema (006) ===\n\n";

// ---- Step 1: Create tables from SQL file ----
echo "Creating tables... ";
$sql = file_get_contents(__DIR__ . '/006_homepage_cms.sql');
$statements = explode(';', $sql);
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (!empty($stmt)) {
        try {
            $db->exec($stmt);
        } catch (Exception $e) {
            echo "\n  SQL warning: " . $e->getMessage() . "\n";
        }
    }
}
echo "done.\n";

// ---- Step 2: Seed default sections ----
echo "\nSeeding default sections...\n";
$sections = get_default_sections();
$insertSec = $db->prepare("INSERT IGNORE INTO homepage_sections (section_key, title, is_visible, sort_order) VALUES (?, ?, ?, ?)");
$insertField = $db->prepare("INSERT INTO homepage_fields (section_id, field_key, field_value) VALUES (?, ?, ?)");
$secCount = 0;

foreach ($sections as $sec) {
    $insertSec->execute([$sec['key'], $sec['title'], $sec['visible'], $sec['order']]);
    if ($insertSec->rowCount() > 0) {
        $secId = $db->lastInsertId();
        $secCount++;
        // Insert fields
        foreach ($sec['fields'] as $fk => $fv) {
            $insertField->execute([$secId, $fk, $fv]);
        }
        // Insert items
        if (!empty($sec['items'])) {
            $insertItem = $db->prepare("INSERT INTO homepage_items (section_id, sort_order) VALUES (?, ?)");
            $insertItemField = $db->prepare("INSERT INTO homepage_item_fields (item_id, field_key, field_value) VALUES (?, ?, ?)");
            foreach ($sec['items'] as $order => $item) {
                $insertItem->execute([$secId, $order]);
                $itemId = $db->lastInsertId();
                foreach ($item as $fk => $fv) {
                    $insertItemField->execute([$itemId, $fk, $fv]);
                }
            }
        }
        echo "  [CREATED] {$sec['key']} - {$sec['title']}\n";
    } else {
        echo "  [EXISTS]  {$sec['key']}\n";
    }
}

// ---- Step 3: Verify ----
echo "\n=== VERIFICATION ===\n";
$counts = [
    'homepage_sections' => $db->query("SELECT COUNT(*) FROM homepage_sections")->fetchColumn(),
    'homepage_fields' => $db->query("SELECT COUNT(*) FROM homepage_fields")->fetchColumn(),
    'homepage_items' => $db->query("SELECT COUNT(*) FROM homepage_items")->fetchColumn(),
    'homepage_item_fields' => $db->query("SELECT COUNT(*) FROM homepage_item_fields")->fetchColumn(),
];
foreach ($counts as $t => $c) {
    printf("  %s: %d\n", $t, $c);
}

if ($counts['homepage_sections'] >= 15) {
    echo "\n✅ MIGRATION COMPLETE.\n";
} else {
    echo "\n⚠️  Migration may be incomplete.\n";
}

// ================================================================
function get_default_sections(): array {
    return [
        [
            'key' => 'preloader', 'title' => 'Preloader', 'visible' => 1, 'order' => 0,
            'fields' => [], 'items' => [],
        ],
        [
            'key' => 'navbar', 'title' => 'Navigation Bar', 'visible' => 1, 'order' => 1,
            'fields' => [
                'brand_text' => 'Chama System',
                'sign_in_text' => 'Sign In',
                'sign_in_link' => 'login.php',
            ],
            'items' => [
                ['label' => 'Home', 'href' => '#home'],
                ['label' => 'About', 'href' => '#about'],
                ['label' => 'Services', 'href' => '#services'],
                ['label' => 'Benefits', 'href' => '#benefits'],
                ['label' => 'Testimonials', 'href' => '#testimonials'],
                ['label' => 'FAQs', 'href' => '#faqs'],
                ['label' => 'Contact', 'href' => '#contact'],
            ],
        ],
        [
            'key' => 'hero', 'title' => 'Hero Section', 'visible' => 1, 'order' => 2,
            'fields' => [
                'headline' => 'Smart Chama Management Made Simple',
                'subheadline' => 'Manage your group savings, loans, shares, meetings, and members all in one powerful platform. Secure, fast, and reliable.',
                'primary_btn_text' => 'Get Started',
                'primary_btn_link' => 'login.php',
                'secondary_btn_text' => 'Learn More',
                'secondary_btn_link' => '#about',
                'hero_image' => 'assets/images/hero-illustration.svg',
                'bg_color' => '#0f0e3a',
                'text_color' => '#ffffff',
            ],
            'items' => [],
        ],
        [
            'key' => 'about', 'title' => 'About Section', 'visible' => 1, 'order' => 3,
            'fields' => [
                'headline' => 'About Chama System',
                'subheadline' => 'Empowering groups to manage their finances seamlessly with cutting-edge technology',
                'about_image' => 'assets/images/about.svg',
                'badge_text' => 'Trusted by 100+ Groups',
                'badge_icon' => 'fa-users',
                'bg_color' => '#ffffff',
            ],
            'items' => [
                ['icon' => 'fa-shield-alt', 'color' => 'primary', 'title' => 'Secure & Reliable', 'description' => 'Bank-grade security with encrypted data and secure authentication.'],
                ['icon' => 'fa-chart-bar', 'color' => 'success', 'title' => 'Real-time Reports', 'description' => 'Generate detailed reports on contributions, loans, and financial status.'],
                ['icon' => 'fa-mobile-alt', 'color' => 'warning', 'title' => 'Mobile Friendly', 'description' => 'Access your chama from any device, anywhere, anytime.'],
                ['icon' => 'fa-clock', 'color' => 'info', 'title' => '24/7 Access', 'description' => 'Round-the-clock access to your group\'s financial information.'],
            ],
        ],
        [
            'key' => 'services', 'title' => 'Services Section', 'visible' => 1, 'order' => 4,
            'fields' => [
                'headline' => 'Our Services',
                'subheadline' => 'Comprehensive tools to manage every aspect of your Chama',
                'bg_color' => '#f8f9fc',
            ],
            'items' => [
                ['icon' => 'fa-piggy-bank', 'color' => 'primary', 'title' => 'Savings Management', 'description' => 'Track daily, weekly, monthly, and custom contributions with automatic balance calculations.'],
                ['icon' => 'fa-hand-holding-usd', 'color' => 'success', 'title' => 'Loan Management', 'description' => 'Complete loan lifecycle from application, approval, disbursement to repayment tracking.'],
                ['icon' => 'fa-chart-pie', 'color' => 'warning', 'title' => 'Shares & Dividends', 'description' => 'Manage share purchases, certificates, and automatic dividend calculations.'],
                ['icon' => 'fa-users', 'color' => 'danger', 'title' => 'Member Management', 'description' => 'Comprehensive member profiles with documents, beneficiaries, and contact management.'],
                ['icon' => 'fa-calendar-check', 'color' => 'info', 'title' => 'Meetings & Attendance', 'description' => 'Schedule meetings, manage agendas, record minutes, and track attendance.'],
                ['icon' => 'fa-file-invoice', 'color' => '#7a5af8', 'title' => 'Reports & Analytics', 'description' => 'Generate professional reports with charts, export to PDF, Excel, and CSV formats.'],
            ],
        ],
        [
            'key' => 'stats', 'title' => 'Statistics Section', 'visible' => 1, 'order' => 5,
            'fields' => [
                'headline' => 'Trusted by Numbers',
                'subheadline' => 'Our platform helps groups achieve their financial goals',
                'bg_color' => '#0f0e3a',
                'text_color' => '#ffffff',
            ],
            'items' => [
                ['number' => '150', 'suffix' => '+', 'prefix' => '', 'label' => 'Active Groups'],
                ['number' => '5000', 'suffix' => '+', 'prefix' => '', 'label' => 'Registered Members'],
                ['number' => '50000000', 'suffix' => '+', 'prefix' => 'KSh ', 'label' => 'Total Savings'],
                ['number' => '98', 'suffix' => '%', 'prefix' => '', 'label' => 'Satisfaction Rate'],
            ],
        ],
        [
            'key' => 'testimonials', 'title' => 'Testimonials Section', 'visible' => 1, 'order' => 6,
            'fields' => [
                'headline' => 'What Our Clients Say',
                'subheadline' => 'Hear from group leaders and members who use our platform',
            ],
            'items' => [
                ['name' => 'John Mwangi', 'role' => 'Chairperson, Umoja Group', 'text' => 'This system has transformed how our group manages finances. The loan tracking and contribution reports are incredible!', 'initials' => 'JM', 'stars' => '5'],
                ['name' => 'Mary Wanjiku', 'role' => 'Treasurer, Sisterhood Chama', 'text' => 'The automated savings tracking and receipt generation saves us hours every month. Highly recommended for any Chama.', 'initials' => 'MW', 'stars' => '5'],
                ['name' => 'Peter Kamau', 'role' => 'Secretary, Maendeleo Group', 'text' => 'As secretary, managing meeting minutes and attendance has never been easier. The reporting features are top-notch.', 'initials' => 'PK', 'stars' => '4.5'],
            ],
        ],
        [
            'key' => 'gallery', 'title' => 'Gallery Section', 'visible' => 1, 'order' => 7,
            'fields' => [
                'headline' => 'Our Gallery',
                'subheadline' => 'Moments captured during group meetings and events',
            ],
            'items' => [
                ['type' => 'icon', 'icon' => 'fa-users', 'color' => 'primary', 'label' => 'Group Meeting'],
                ['type' => 'icon', 'icon' => 'fa-handshake', 'color' => 'success', 'label' => 'Partnership'],
                ['type' => 'icon', 'icon' => 'fa-chart-line', 'color' => 'warning', 'label' => 'Growth'],
                ['type' => 'icon', 'icon' => 'fa-graduation-cap', 'color' => 'info', 'label' => 'Training'],
            ],
        ],
        [
            'key' => 'partners', 'title' => 'Partners Section', 'visible' => 1, 'order' => 8,
            'fields' => [
                'headline' => 'Our Partners',
                'subheadline' => 'Trusted by leading organizations',
            ],
            'items' => [
                ['name' => 'Partner 1'],
                ['name' => 'Partner 2'],
                ['name' => 'Partner 3'],
                ['name' => 'Partner 4'],
                ['name' => 'Partner 5'],
            ],
        ],
        [
            'key' => 'faqs', 'title' => 'FAQs Section', 'visible' => 1, 'order' => 9,
            'fields' => [
                'headline' => 'Frequently Asked Questions',
                'subheadline' => 'Find answers to common questions about our platform',
            ],
            'items' => [
                ['question' => 'How do I create a group account?', 'answer' => 'Simply sign up with your group details, and you will receive a unique group code. Share this code with members so they can join your group.'],
                ['question' => 'Is my data secure?', 'answer' => 'Yes, we use industry-standard encryption and security practices. All data is transmitted over secure connections and stored with encryption.'],
                ['question' => 'Can I export reports?', 'answer' => 'Yes, you can export reports in PDF, Excel, and CSV formats. You can also print reports directly from the platform.'],
                ['question' => 'How does the loan system work?', 'answer' => 'Members can apply for loans through the system. Applications go through an approval workflow (Secretary, Treasurer, Chairperson). Once approved, funds are disbursed and repayment schedules are generated automatically.'],
                ['question' => 'Can I access the system on my phone?', 'answer' => 'Absolutely! The system is fully responsive and works on all devices - desktop, tablet, and mobile phones.'],
                ['question' => 'How is the cost structured?', 'answer' => 'Contact us for pricing details. We offer flexible plans tailored to groups of all sizes.'],
            ],
        ],
        [
            'key' => 'contact', 'title' => 'Contact Section', 'visible' => 1, 'order' => 10,
            'fields' => [
                'headline' => 'Get In Touch',
                'subheadline' => 'Have questions? We would love to hear from you',
                'form_action' => 'ajax/contact.php',
            ],
            'items' => [
                ['icon' => 'fa-map-marker-alt', 'color' => 'primary', 'title' => 'Our Location', 'value' => 'Nairobi, Kenya'],
                ['icon' => 'fa-phone', 'color' => 'success', 'title' => 'Phone', 'value' => '+254 700 000 000'],
                ['icon' => 'fa-envelope', 'color' => 'warning', 'title' => 'Email', 'value' => 'info@chamasystem.com'],
                ['icon' => 'fa-clock', 'color' => 'info', 'title' => 'Working Hours', 'value' => 'Mon - Fri: 8:00 AM - 5:00 PM'],
            ],
        ],
        [
            'key' => 'footer', 'title' => 'Footer Section', 'visible' => 1, 'order' => 11,
            'fields' => [
                'brand_text' => 'Chama System',
                'description' => 'A comprehensive platform for managing group savings, loans, shares, and member activities. Empowering groups to achieve financial success together.',
                'newsletter_title' => 'Newsletter',
                'newsletter_text' => 'Subscribe to receive updates and news.',
                'copyright_text' => '© ' . date('Y') . ' Chama System. All rights reserved. | Powered by Advanced Chama Management System',
            ],
            'items' => [
                ['type' => 'social', 'icon' => 'fa-facebook-f', 'url' => '#'],
                ['type' => 'social', 'icon' => 'fa-twitter', 'url' => '#'],
                ['type' => 'social', 'icon' => 'fa-instagram', 'url' => '#'],
                ['type' => 'social', 'icon' => 'fa-linkedin-in', 'url' => '#'],
                ['type' => 'social', 'icon' => 'fa-whatsapp', 'url' => '#'],
                ['type' => 'quick_link', 'label' => 'Home', 'href' => '#home'],
                ['type' => 'quick_link', 'label' => 'About Us', 'href' => '#about'],
                ['type' => 'quick_link', 'label' => 'Services', 'href' => '#services'],
                ['type' => 'quick_link', 'label' => 'Testimonials', 'href' => '#testimonials'],
                ['type' => 'quick_link', 'label' => 'FAQs', 'href' => '#faqs'],
                ['type' => 'quick_link', 'label' => 'Contact', 'href' => '#contact'],
                ['type' => 'service_link', 'label' => 'Savings', 'href' => '#services'],
                ['type' => 'service_link', 'label' => 'Loans', 'href' => '#services'],
                ['type' => 'service_link', 'label' => 'Shares', 'href' => '#services'],
                ['type' => 'service_link', 'label' => 'Meetings', 'href' => '#services'],
                ['type' => 'service_link', 'label' => 'Reports', 'href' => '#services'],
            ],
        ],
        [
            'key' => 'copyright', 'title' => 'Copyright Bar', 'visible' => 1, 'order' => 12,
            'fields' => [
                'copyright_text' => '© ' . date('Y') . ' Chama System. All rights reserved.',
            ],
            'items' => [],
        ],
        [
            'key' => 'dark_mode_toggle', 'title' => 'Dark Mode Toggle', 'visible' => 1, 'order' => 13,
            'fields' => [], 'items' => [],
        ],
        [
            'key' => 'scroll_top', 'title' => 'Scroll to Top Button', 'visible' => 1, 'order' => 14,
            'fields' => [], 'items' => [],
        ],
    ];
}

<?php
/**
 * Migration Runner 005: Homepage Sections CMS
 * 
 * Creates the page_sections table, seeds default content,
 * and adds the manage_homepage permission.
 * 
 * Usage: php migrations/run_005.php
 */

require_once __DIR__ . '/../includes/config.php';

$db = getConnection();

echo "=== Homepage Sections CMS Migration (005) ===\n\n";

// ---- Step 1: Create page_sections table ----
echo "Creating page_sections table... ";
$db->exec("
    CREATE TABLE IF NOT EXISTS page_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(255) DEFAULT NULL,
        content JSON DEFAULT NULL,
        media JSON DEFAULT NULL,
        is_visible TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "done.\n";

// ---- Step 2: Add manage_homepage permission ----
echo "Adding manage_homepage permission... ";
$checkPerm = $db->query("SELECT COUNT(*) FROM permissions WHERE slug = 'manage_homepage'")->fetchColumn();
if (!$checkPerm) {
    $db->exec("INSERT INTO permissions (name, slug, module) VALUES ('Manage Homepage', 'manage_homepage', 'settings')");
    echo "inserted.\n";
} else {
    echo "already exists.\n";
}

// Assign to Super Admin
$permId = $db->query("SELECT id FROM permissions WHERE slug = 'manage_homepage'")->fetchColumn();
$checkRp = $db->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = 1 AND permission_id = $permId")->fetchColumn();
if (!$checkRp && $permId) {
    $db->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, $permId)");
    echo "Assigned to Super Admin.\n";
} else {
    echo "Already assigned to Super Admin.\n";
}

// ---- Step 3: Seed default sections ----
echo "\nSeeding default homepage sections...\n";

$defaultSections = json_decode(file_get_contents(__DIR__ . '/default_sections.json'), true);
if (!$defaultSections) {
    echo "ERROR: Could not load default_sections.json. Falling back to inline defaults.\n";
    $defaultSections = getInlineDefaults();
}

$insertStmt = $db->prepare(
    "INSERT IGNORE INTO page_sections (section_key, title, content, media, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
);

$count = 0;
foreach ($defaultSections as $sec) {
    $insertStmt->execute([
        $sec['section_key'],
        $sec['title'],
        json_encode($sec['content'] ?? '{}'),
        json_encode($sec['media'] ?? '{}'),
        $sec['is_visible'] ?? 1,
        $sec['sort_order'] ?? 0
    ]);
    if ($insertStmt->rowCount() > 0) $count++;
}

echo "  $count new sections inserted (existing ones skipped).\n";

// ---- Step 4: Verify ----
$total = $db->query("SELECT COUNT(*) FROM page_sections")->fetchColumn();
echo "\n=== VERIFICATION ===\n";
echo "  Total page_sections: $total (expected 15)\n";

if ($total >= 15) {
    echo "\n✅ MIGRATION COMPLETE — All checks passed.\n";
} else {
    echo "\n⚠️  Migration completed but fewer sections than expected.\n";
}

/**
 * Inline fallback defaults in case JSON file is missing.
 */
function getInlineDefaults(): array {
    return [
        ['section_key' => 'preloader', 'title' => 'Preloader', 'content' => '{}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 0],
        ['section_key' => 'navbar', 'title' => 'Navigation Bar', 'content' => '{"brand_text":"Chama System","nav_items":[{"label":"Home","href":"#home"},{"label":"About","href":"#about"},{"label":"Services","href":"#services"},{"label":"Benefits","href":"#benefits"},{"label":"Testimonials","href":"#testimonials"},{"label":"FAQs","href":"#faqs"},{"label":"Contact","href":"#contact"}],"sign_in_text":"Sign In","sign_in_link":"login.php"}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 1],
        ['section_key' => 'hero', 'title' => 'Hero Section', 'content' => '{"title":"Smart Chama Management Made Simple","subtitle":"Manage your group savings, loans, shares, meetings, and members all in one powerful platform. Secure, fast, and reliable.","primary_btn_text":"Get Started","primary_btn_link":"login.php","primary_btn_icon":"fa-rocket","secondary_btn_text":"Learn More","secondary_btn_link":"#about","secondary_btn_icon":"fa-play-circle","fallback_icon":"fa-chart-line"}', 'media' => '{"image":"assets/images/hero-illustration.svg"}', 'is_visible' => 1, 'sort_order' => 2],
        ['section_key' => 'about', 'title' => 'About Section', 'content' => '{"title":"About Chama System","subtitle":"Empowering groups to manage their finances seamlessly with cutting-edge technology","features":[{"icon":"fa-shield-alt","color":"primary","title":"Secure & Reliable","description":"Bank-grade security with encrypted data and secure authentication."},{"icon":"fa-chart-bar","color":"success","title":"Real-time Reports","description":"Generate detailed reports on contributions, loans, and financial status."},{"icon":"fa-mobile-alt","color":"warning","title":"Mobile Friendly","description":"Access your chama from any device, anywhere, anytime."},{"icon":"fa-clock","color":"info","title":"24/7 Access","description":"Round-the-clock access to your group\'s financial information."}],"badge_text":"Trusted by 100+ Groups","badge_icon":"fa-users"}', 'media' => '{"image":"assets/images/about.svg"}', 'is_visible' => 1, 'sort_order' => 3],
        ['section_key' => 'services', 'title' => 'Services Section', 'content' => '{"title":"Our Services","subtitle":"Comprehensive tools to manage every aspect of your Chama","items":[{"icon":"fa-piggy-bank","color":"primary","title":"Savings Management","description":"Track daily, weekly, monthly, and custom contributions with automatic balance calculations."},{"icon":"fa-hand-holding-usd","color":"success","title":"Loan Management","description":"Complete loan lifecycle from application, approval, disbursement to repayment tracking."},{"icon":"fa-chart-pie","color":"warning","title":"Shares & Dividends","description":"Manage share purchases, certificates, and automatic dividend calculations."},{"icon":"fa-users","color":"danger","title":"Member Management","description":"Comprehensive member profiles with documents, beneficiaries, and contact management."},{"icon":"fa-calendar-check","color":"info","title":"Meetings & Attendance","description":"Schedule meetings, manage agendas, record minutes, and track attendance."},{"icon":"fa-file-invoice","color":"#7a5af8","title":"Reports & Analytics","description":"Generate professional reports with charts, export to PDF, Excel, and CSV formats."}]}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 4],
        ['section_key' => 'stats', 'title' => 'Statistics / Benefits Section', 'content' => '{"title":"Trusted by Numbers","subtitle":"Our platform helps groups achieve their financial goals","items":[{"number":150,"suffix":"+","prefix":"","label":"Active Groups"},{"number":5000,"suffix":"+","prefix":"","label":"Registered Members"},{"number":50000000,"suffix":"+","prefix":"KSh ","label":"Total Savings"},{"number":98,"suffix":"%","prefix":"","label":"Satisfaction Rate"}]}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 5],
        ['section_key' => 'testimonials', 'title' => 'Testimonials Section', 'content' => '{"title":"What Our Clients Say","subtitle":"Hear from group leaders and members who use our platform","items":[{"name":"John Mwangi","role":"Chairperson, Umoja Group","text":"This system has transformed how our group manages finances. The loan tracking and contribution reports are incredible!","initials":"JM","stars":5},{"name":"Mary Wanjiku","role":"Treasurer, Sisterhood Chama","text":"The automated savings tracking and receipt generation saves us hours every month. Highly recommended for any Chama.","initials":"MW","stars":5},{"name":"Peter Kamau","role":"Secretary, Maendeleo Group","text":"As secretary, managing meeting minutes and attendance has never been easier. The reporting features are top-notch.","initials":"PK","stars":4.5}]}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 6],
        ['section_key' => 'gallery', 'title' => 'Gallery Section', 'content' => '{"title":"Our Gallery","subtitle":"Moments captured during group meetings and events","items":[{"type":"icon","value":"fa-users","color":"primary","label":"Group Meeting"},{"type":"icon","value":"fa-handshake","color":"success","label":"Partnership"},{"type":"icon","value":"fa-chart-line","color":"warning","label":"Growth"},{"type":"icon","value":"fa-graduation-cap","color":"info","label":"Training"}]}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 7],
        ['section_key' => 'partners', 'title' => 'Partners Section', 'content' => '{"title":"Our Partners","subtitle":"Trusted by leading organizations","items":[{"name":"Partner 1"},{"name":"Partner 2"},{"name":"Partner 3"},{"name":"Partner 4"},{"name":"Partner 5"}]}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 8],
        ['section_key' => 'faqs', 'title' => 'FAQs Section', 'content' => '{"title":"Frequently Asked Questions","subtitle":"Find answers to common questions about our platform","items":[{"question":"How do I create a group account?","answer":"Simply sign up with your group details, and you will receive a unique group code. Share this code with members so they can join your group."},{"question":"Is my data secure?","answer":"Yes, we use industry-standard encryption and security practices. All data is transmitted over secure connections and stored with encryption."},{"question":"Can I export reports?","answer":"Yes, you can export reports in PDF, Excel, and CSV formats. You can also print reports directly from the platform."},{"question":"How does the loan system work?","answer":"Members can apply for loans through the system. Applications go through an approval workflow (Secretary, Treasurer, Chairperson). Once approved, funds are disbursed and repayment schedules are generated automatically."},{"question":"Can I access the system on my phone?","answer":"Absolutely! The system is fully responsive and works on all devices - desktop, tablet, and mobile phones."},{"question":"How is the cost structured?","answer":"Contact us for pricing details. We offer flexible plans tailored to groups of all sizes."}]}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 9],
        ['section_key' => 'contact', 'title' => 'Contact Section', 'content' => '{"title":"Get In Touch","subtitle":"Have questions? We would love to hear from you","form_action":"ajax/contact.php","info":[{"icon":"fa-map-marker-alt","color":"primary","title":"Our Location","value":"Nairobi, Kenya"},{"icon":"fa-phone","color":"success","title":"Phone","value":"+254 700 000 000"},{"icon":"fa-envelope","color":"warning","title":"Email","value":"info@chamasystem.com"},{"icon":"fa-clock","color":"info","title":"Working Hours","value":"Mon - Fri: 8:00 AM - 5:00 PM"}]}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 10],
        ['section_key' => 'footer', 'title' => 'Footer Section', 'content' => '{"brand_text":"Chama System","description":"A comprehensive platform for managing group savings, loans, shares, and member activities. Empowering groups to achieve financial success together.","social_links":[{"icon":"fa-facebook-f","url":"#"},{"icon":"fa-twitter","url":"#"},{"icon":"fa-instagram","url":"#"},{"icon":"fa-linkedin-in","url":"#"},{"icon":"fa-whatsapp","url":"#"}],"quick_links":[{"label":"Home","href":"#home"},{"label":"About Us","href":"#about"},{"label":"Services","href":"#services"},{"label":"Testimonials","href":"#testimonials"},{"label":"FAQs","href":"#faqs"},{"label":"Contact","href":"#contact"}],"service_links":[{"label":"Savings","href":"#services"},{"label":"Loans","href":"#services"},{"label":"Shares","href":"#services"},{"label":"Meetings","href":"#services"},{"label":"Reports","href":"#services"}],"newsletter_title":"Newsletter","newsletter_text":"Subscribe to receive updates and news."}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 11],
        ['section_key' => 'copyright', 'title' => 'Copyright Bar', 'content' => '{"text":"© 2026 Chama System. All rights reserved. | Powered by Advanced Chama Management System"}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 12],
        ['section_key' => 'dark_mode_toggle', 'title' => 'Dark Mode Toggle', 'content' => '{}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 13],
        ['section_key' => 'scroll_top', 'title' => 'Scroll to Top Button', 'content' => '{}', 'media' => '{}', 'is_visible' => 1, 'sort_order' => 14],
    ];
}

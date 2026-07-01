<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function getSmtpSettings(): array {
    static $settings = null;
    if ($settings !== null) return $settings;

    $db = getConnection();
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = [
        'host'     => $rows['smtp_host'] ?? 'smtp.gmail.com',
        'port'     => (int)($rows['smtp_port'] ?? 587),
        'username' => $rows['smtp_username'] ?? '',
        'password' => $rows['smtp_password'] ?? '',
        'from'     => $rows['smtp_from_email'] ?? $rows['smtp_username'] ?? 'noreply@localhost',
        'fromName' => $rows['smtp_from_name'] ?? 'Chama System',
    ];
    return $settings;
}

function sendEmail(string $to, string $subject, string $htmlBody, ?string $textBody = null): array {
    $smtp = getSmtpSettings();
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['username'];
        $mail->Password   = $smtp['password'];
        $mail->SMTPSecure = $smtp['port'] === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp['port'];

        $mail->setFrom($smtp['from'], $smtp['fromName']);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?? strip_tags($htmlBody);

        $mail->send();
        error_log("Email sent successfully to $to — Subject: $subject");
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        $errorMsg = $mail->ErrorInfo;
        error_log("Email FAILED to $to — Subject: $subject — Error: $errorMsg");
        return ['success' => false, 'error' => $errorMsg];
    }
}

<?php
/**
 * Member Registration Workflow Engine
 *
 * Handles multi-stage registration: draft → pending_verification → email_verified → pending_approval → active
 */

require_once __DIR__ . '/mailer.php';

/**
 * Generate a cryptographically secure verification token
 */
function generateVerificationToken(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Calculate age from date of birth
 */
function calculateAge(string $dob): int {
    $birth = new DateTime($dob);
    $now = new DateTime();
    return (int)$birth->diff($now)->y;
}

/**
 * Get registration settings
 */
function getRegistrationSettings(): array {
    $keys = [
        'allow_public_registration', 'require_admin_approval', 'min_registration_age',
        'default_group_code', 'registration_terms_url', 'max_verification_attempts',
        'verification_token_expiry', 'require_national_id', 'require_passport_photo',
        'require_signature', 'require_emergency_contact'
    ];
    $settings = [];
    foreach ($keys as $key) {
        $settings[$key] = getSetting($key, $key === 'allow_public_registration' || $key === 'require_admin_approval' || $key === 'require_national_id' ? '1' : (
            $key === 'min_registration_age' ? '18' : (
                $key === 'default_group_code' ? 'CHAMA001' : (
                    $key === 'max_verification_attempts' ? '5' : (
                        $key === 'verification_token_expiry' ? '24' : ''
                    )
                )
            )
        ));
    }
    return $settings;
}

/**
 * Send email verification email
 */
function sendVerificationEmail(int $memberId, string $email, string $firstName): array {
    $db = getConnection();
    $token = generateVerificationToken();
    $expiryHours = (int)getSetting('verification_token_expiry', '24');
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

    $stmt = $db->prepare(
        "UPDATE members SET email_verification_token = ?, email_verification_expires = ?,
         email_verification_sent_at = NOW(), verification_attempts = verification_attempts + 1
         WHERE id = ?"
    );
    $stmt->execute([$token, $expiresAt, $memberId]);

    $stmt = $db->prepare(
        "INSERT INTO email_verifications (member_id, token, email, type, expires_at) VALUES (?, ?, ?, 'email_verification', ?)"
    );
    $stmt->execute([$memberId, $token, $email, $expiresAt]);

    $verifyUrl = BASE_URL . 'verify-email.php?token=' . urlencode($token);
    $siteName = getSetting('site_name', 'Chama System');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">Email Verification</h2>
    </div>
    <div style="padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;">
        <p>Hello <strong>{$firstName}</strong>,</p>
        <p>Thank you for registering with <strong>{$siteName}</strong>.</p>
        <p>Please verify your email address by clicking the button below:</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{$verifyUrl}" style="background: #0d6efd; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">Verify Email Address</a>
        </div>
        <p>This link expires in <strong>{$expiryHours} hours</strong>.</p>
        <p>If you did not create an account, please ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #888; font-size: 12px;">&copy; {$siteName} &mdash; This is an automated message, please do not reply.</p>
    </div>
</body>
</html>
HTML;

    $textBody = "Hello {$firstName},\n\nThank you for registering with {$siteName}.\n\nPlease verify your email by clicking this link:\n{$verifyUrl}\n\nThis link expires in {$expiryHours} hours.\n\nIf you did not create an account, please ignore this email.";

    return sendEmail($email, "Verify Your Email – {$siteName}", $htmlBody, $textBody);
}

/**
 * Resend verification email (with rate limiting)
 */
function resendVerificationEmail(int $memberId): array {
    $db = getConnection();
    $member = $db->prepare("SELECT id, email, first_name, status, verification_attempts FROM members WHERE id = ?");
    $member->execute([$memberId]);
    $m = $member->fetch(PDO::FETCH_ASSOC);
    if (!$m) return ['success' => false, 'error' => 'Member not found'];

    $maxAttempts = (int)getSetting('max_verification_attempts', '5');

    // Check rate limit (max attempts)
    if ((int)$m['verification_attempts'] >= $maxAttempts) {
        return ['success' => false, 'error' => "Maximum verification attempts ({$maxAttempts}) reached. Contact support."];
    }

    // Check last sent time (prevent spam - 60 second cooldown)
    $lastSent = $db->prepare("SELECT MAX(created_at) FROM email_verifications WHERE member_id = ? AND type = 'email_verification'");
    $lastSent->execute([$memberId]);
    $lastTime = $lastSent->fetchColumn();
    if ($lastTime && strtotime($lastTime) > time() - 60) {
        return ['success' => false, 'error' => 'Please wait at least 60 seconds before requesting a new verification email.'];
    }

    return sendVerificationEmail($memberId, $m['email'], $m['first_name']);
}

/**
 * Verify email token
 */
function verifyEmailToken(string $token): array {
    $db = getConnection();

    // Find the verification record
    $stmt = $db->prepare(
        "SELECT ev.id, ev.member_id, ev.email, ev.expires_at, ev.used_at,
                m.status, m.email_verified
         FROM email_verifications ev
         JOIN members m ON ev.member_id = m.id
         WHERE ev.token = ? AND ev.type = 'email_verification'"
    );
    $stmt->execute([$token]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) return ['success' => false, 'error' => 'Invalid verification link.'];

    // Already verified — show success instead of error (idempotent)
    if ($record['email_verified']) {
        $requireApproval = getSetting('require_admin_approval', '1');
        return [
            'success' => true,
            'member_id' => $record['member_id'],
            'status' => $record['status'],
            'auto_activated' => ($requireApproval !== '1')
        ];
    }

    if ($record['used_at']) return ['success' => false, 'error' => 'This verification link has already been used.'];

    if (strtotime($record['expires_at']) < time()) return ['success' => false, 'error' => 'This verification link has expired. Please request a new one.'];

    if ($record['status'] !== 'pending_verification') return ['success' => false, 'error' => 'Invalid member status for verification.'];

    // Mark as used
    $db->prepare("UPDATE email_verifications SET used_at = NOW() WHERE id = ?")->execute([$record['id']]);

    // Update member
    $requireApproval = getSetting('require_admin_approval', '1');
    $newStatus = $requireApproval === '1' ? 'email_verified' : 'active';

    $db->prepare(
        "UPDATE members SET email_verified = 1, email_verified_at = NOW(),
         email_verification_token = NULL, email_verification_expires = NULL, status = ?
         WHERE id = ?"
    )->execute([$newStatus, $record['member_id']]);

    // Audit log
    $auditAction = $requireApproval === '1' ? 'Email verified, awaiting approval' : 'Email verified, member activated';
    logAudit(0, 'system', 'update', 'members', $record['member_id'],
        null, ['status' => $newStatus, 'email_verified' => 1], $auditAction);

    // Send notification
    $member = $db->prepare("SELECT first_name, email FROM members WHERE id = ?");
    $member->execute([$record['member_id']]);
    $m = $member->fetch(PDO::FETCH_ASSOC);

    if ($requireApproval === '1') {
        sendPendingApprovalNotification($record['member_id'], $m['first_name'], $m['email']);
    } else {
        sendActivationNotification($record['member_id'], $m['first_name'], $m['email']);
    }

    return [
        'success' => true,
        'member_id' => $record['member_id'],
        'status' => $newStatus,
        'auto_activated' => ($requireApproval !== '1')
    ];
}

/**
 * Send notification to admin about pending approval
 */
function sendPendingApprovalNotification(int $memberId, string $firstName, string $email): array {
    $adminUrl = BASE_URL . 'members.php?action=view&id=' . $memberId;
    $siteName = getSetting('site_name', 'Chama System');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">New Member Pending Approval</h2>
    </div>
    <div style="padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;">
        <p>A new member registration requires your review:</p>
        <p><strong>Name:</strong> {$firstName}<br>
        <strong>Email:</strong> {$email}</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{$adminUrl}" style="background: #0d6efd; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">Review Application</a>
        </div>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #888; font-size: 12px;">{$siteName}</p>
    </div>
</body>
</html>
HTML;

    // Notify all admins with approve_members permission
    $db = getConnection();
    $admins = $db->query(
        "SELECT u.email FROM users u
         JOIN role_permissions rp ON u.role_id = rp.role_id
         JOIN permissions p ON rp.permission_id = p.id
         WHERE p.slug = 'approve_members' AND u.status = 'active'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $siteName = getSetting('site_name', 'Chama System');
    $textBody = "New member pending approval: {$firstName} ({$email})\nReview at: {$adminUrl}";

    $results = [];
    foreach ($admins as $adminEmail) {
        $results[] = sendEmail($adminEmail, "New Member Pending Approval – {$siteName}", $htmlBody, $textBody);
    }
    return $results ? end($results) : ['success' => true, 'error' => null];
}

/**
 * Send approval notification
 */
function sendApprovalNotification(int $memberId, string $firstName, string $email): array {
    $siteName = getSetting('site_name', 'Chama System');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #198754; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">Registration Approved!</h2>
    </div>
    <div style="padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;">
        <p>Hello <strong>{$firstName}</strong>,</p>
        <p>Congratulations! Your membership registration with <strong>{$siteName}</strong> has been <strong>approved</strong>.</p>
        <p>You are now an <strong>Active Member</strong> of {$siteName}.</p>
        <p>Your login credentials have been sent in a separate email. Check your inbox for your username and password.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #888; font-size: 12px;">{$siteName} &mdash; This is an automated message, please do not reply.</p>
    </div>
</body>
</html>
HTML;

    $textBody = "Hello {$firstName},\n\nCongratulations! Your membership registration with {$siteName} has been approved.\n\nYour login credentials have been sent in a separate email.";

    return sendEmail($email, "Membership Approved – {$siteName}", $htmlBody, $textBody);
}

/**
 * Send rejection notification
 */
function sendRejectionNotification(int $memberId, string $firstName, string $email, string $reason): array {
    $siteName = getSetting('site_name', 'Chama System');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">Registration Update</h2>
    </div>
    <div style="padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;">
        <p>Hello <strong>{$firstName}</strong>,</p>
        <p>Thank you for your interest in <strong>{$siteName}</strong>.</p>
        <p>After reviewing your application, we regret to inform you that your membership registration could not be approved at this time.</p>
        <p><strong>Reason:</strong></p>
        <blockquote style="background: #f8f9fa; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0;">{$reason}</blockquote>
        <p>If you believe this is an error or would like to re-apply, please contact the administrator.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #888; font-size: 12px;">{$siteName} &mdash; This is an automated message, please do not reply.</p>
    </div>
</body>
</html>
HTML;

    $textBody = "Hello {$firstName},\n\nThank you for your interest in {$siteName}.\n\nAfter reviewing your application, we regret to inform you that your membership registration could not be approved at this time.\n\nReason: {$reason}";

    return sendEmail($email, "Membership Registration Update – {$siteName}", $htmlBody, $textBody);
}

/**
 * Send activation notification (when member is activated without needing approval)
 */
function sendActivationNotification(int $memberId, string $firstName, string $email): array {
    $siteName = getSetting('site_name', 'Chama System');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #198754; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">Account Activated!</h2>
    </div>
    <div style="padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;">
        <p>Hello <strong>{$firstName}</strong>,</p>
        <p>Your email has been verified and your account is now <strong>active</strong>!</p>
        <p>Your administrator will create your login credentials shortly.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #888; font-size: 12px;">{$siteName} &mdash; This is an automated message, please do not reply.</p>
    </div>
</body>
</html>
HTML;

    $textBody = "Hello {$firstName},\n\nYour email has been verified and your account is now active!\n\nYour administrator will create your login credentials shortly.";

    return sendEmail($email, "Account Activated – {$siteName}", $htmlBody, $textBody);
}

/**
 * Validate registration data (comprehensive server-side)
 */
function validateRegistrationData(array $data, array $files): array {
    $errors = [];
    $regSettings = getRegistrationSettings();
    $db = getConnection();

    // --- Personal Info ---
    if (empty(trim($data['first_name'] ?? ''))) $errors['first_name'] = 'First name is required.';
    if (empty(trim($data['last_name'] ?? ''))) $errors['last_name'] = 'Last name is required.';
    if (empty($data['gender'] ?? '')) $errors['gender'] = 'Gender is required.';

    // Date of Birth + Age
    if (empty($data['date_of_birth'] ?? '')) {
        $errors['date_of_birth'] = 'Date of birth is required.';
    } elseif (!validateDate($data['date_of_birth'])) {
        $errors['date_of_birth'] = 'Invalid date format.';
    } else {
        $age = calculateAge($data['date_of_birth']);
        $minAge = (int)($regSettings['min_registration_age'] ?? 18);
        if ($age < $minAge) {
            $errors['date_of_birth'] = "You must be at least {$minAge} years old to register.";
        }
    }

    // National ID
    $requireNationalId = $regSettings['require_national_id'] === '1';
    $nationalId = trim($data['national_id'] ?? '');
    if ($requireNationalId && empty($nationalId)) {
        $errors['national_id'] = 'National ID is required.';
    } elseif (!empty($nationalId)) {
        if (!validateNationalId($nationalId)) {
            $errors['national_id'] = 'Invalid National ID format (6-8 digits).';
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE national_id = ?");
            $stmt->execute([$nationalId]);
            if ($stmt->fetchColumn() > 0) $errors['national_id'] = 'This National ID is already registered.';
        }
    }

    // Passport
    $passport = trim($data['passport'] ?? '');
    if (!empty($passport)) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE passport = ?");
        $stmt->execute([$passport]);
        if ($stmt->fetchColumn() > 0) $errors['passport'] = 'This Passport number is already registered.';
    }

    // --- Contact ---
    $email = strtolower(trim($data['email'] ?? ''));
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!validateEmail($email)) {
        $errors['email'] = 'Invalid email format.';
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) $errors['email'] = 'This email is already registered.';
    }

    $phone = trim($data['phone'] ?? '');
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!validatePhone($phone)) {
        $errors['phone'] = 'Invalid phone number. Use Kenyan format (07XX XXX XXX or +2547XX XXX XXX).';
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn() > 0) $errors['phone'] = 'This phone number is already registered.';
    }

    // --- Location ---
    if (empty($data['county_id'] ?? '')) $errors['county_id'] = 'County is required.';
    if (empty($data['sub_county_id'] ?? '')) $errors['sub_county_id'] = 'Sub-county is required.';
    if (empty($data['ward_id'] ?? '')) $errors['ward_id'] = 'Ward is required.';

    // --- Professional ---
    if (empty(trim($data['occupation'] ?? ''))) $errors['occupation'] = 'Occupation is required.';

    // --- Emergency Contact ---
    if ($regSettings['require_emergency_contact'] === '1') {
        if (empty(trim($data['emergency_name'] ?? ''))) $errors['emergency_name'] = 'Emergency contact name is required.';
        if (empty(trim($data['emergency_phone'] ?? ''))) {
            $errors['emergency_phone'] = 'Emergency contact phone is required.';
        } elseif (!validatePhone($data['emergency_phone'])) {
            $errors['emergency_phone'] = 'Invalid emergency contact phone format.';
        }
        if (empty(trim($data['emergency_relation'] ?? ''))) $errors['emergency_relation'] = 'Emergency contact relationship is required.';
    }

    // --- Document Uploads ---
    $docTypes = [
        'national_id_doc' => 'National ID / Passport document',
        'passport_photo' => 'Passport photo',
        'signature' => 'Signature',
    ];

    foreach ($docTypes as $field => $label) {
        $hasFile = isset($files[$field]) && $files[$field]['error'] === UPLOAD_ERR_OK;
        $hasExisting = !empty($data[$field . '_existing'] ?? '');
        if (!$hasFile && !$hasExisting) {
            $errors[$field] = "{$label} is required.";
        }
    }

    return $errors;
}

/**
 * Save uploaded registration document
 */
function saveMemberDocument(int $memberId, string $docType, string $filePath, ?int $fileSize = null, ?string $mimeType = null): void {
    $db = getConnection();
    $stmt = $db->prepare(
        "INSERT INTO member_documents (member_id, document_type, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$memberId, $docType, $filePath, $fileSize, $mimeType]);
}

/**
 * Create member from registration data
 */
function createMemberFromRegistration(array $data, array $uploadedFiles): int {
    $db = getConnection();
    $regSettings = getRegistrationSettings();
    $groupCode = $regSettings['default_group_code'] ?? 'CHAMA001';

    $memberNo = generateMemberNumber($groupCode);
    $dateJoined = date('Y-m-d');

    // Format phone to international
    $phone = $data['phone'];
    if (preg_match('/^0(\d{9})$/', $phone)) {
        $phone = '+254' . substr($phone, 1);
    }

    $stmt = $db->prepare(
        "INSERT INTO members (member_no, group_code, first_name, last_name, national_id, passport,
         phone, email, occupation, employer, address, county_id, sub_county_id, ward_id,
         county, sub_county, ward, gender, date_of_birth, date_joined, status,
         emergency_name, emergency_phone, emergency_relation, notes, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_verification',
         ?, ?, ?, ?, NULL, NOW())"
    );

    // Resolve location names from FKs
    $countyName = '';
    $subCountyName = '';
    $wardName = '';
    if (!empty($data['county_id'])) {
        $c = $db->prepare("SELECT name FROM counties WHERE id = ?");
        $c->execute([$data['county_id']]);
        $countyName = (string)$c->fetchColumn();
    }
    if (!empty($data['sub_county_id'])) {
        $s = $db->prepare("SELECT name FROM sub_counties WHERE id = ?");
        $s->execute([$data['sub_county_id']]);
        $subCountyName = (string)$s->fetchColumn();
    }
    if (!empty($data['ward_id'])) {
        $w = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $w->execute([$data['ward_id']]);
        $wardName = (string)$w->fetchColumn();
    }

    $stmt->execute([
        $memberNo, $groupCode,
        $data['first_name'], $data['last_name'],
        $data['national_id'] ?? null, $data['passport'] ?? null,
        $phone, strtolower(trim($data['email'])),
        $data['occupation'] ?? null, $data['employer'] ?? null,
        $data['address'] ?? null,
        $data['county_id'] ? (int)$data['county_id'] : null,
        $data['sub_county_id'] ? (int)$data['sub_county_id'] : null,
        $data['ward_id'] ? (int)$data['ward_id'] : null,
        $countyName, $subCountyName, $wardName,
        $data['gender'], $data['date_of_birth'], $dateJoined,
        $data['emergency_name'] ?? null, $data['emergency_phone'] ?? null,
        $data['emergency_relation'] ?? null, $data['notes'] ?? null,
    ]);

    $memberId = (int)$db->lastInsertId();

    // Save documents
    foreach ($uploadedFiles as $docType => $filePath) {
        saveMemberDocument($memberId, $docType, $filePath);
    }

    // Audit log
    logAudit(0, 'system', 'create', 'members', $memberId, null, [
        'member_no' => $memberNo,
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'email' => strtolower(trim($data['email'])),
        'status' => 'pending_verification'
    ], 'Member self-registered');

    // Update location suggestions for auto-complete
    $suggestionFields = [
        'occupation' => $data['occupation'] ?? null,
        'employer' => $data['employer'] ?? null,
        'emergency_relation' => $data['emergency_relation'] ?? null,
    ];
    $upsert = $db->prepare(
        "INSERT IGNORE INTO location_suggestions (field_name, value, group_code, usage_count)
         VALUES (?, ?, ?, 1)"
    );
    foreach ($suggestionFields as $field => $value) {
        if (!empty($value)) {
            $upsert->execute([$field, $value, $groupCode]);
        }
    }

    return $memberId;
}

/**
 * Approve member registration
 */
function approveMember(int $memberId, int $adminUserId, string $adminUsername): array {
    $db = getConnection();

    $member = $db->prepare("SELECT id, first_name, email, status FROM members WHERE id = ?");
    $member->execute([$memberId]);
    $m = $member->fetch(PDO::FETCH_ASSOC);
    if (!$m) return ['success' => false, 'error' => 'Member not found.'];
    if ($m['status'] !== 'email_verified' && $m['status'] !== 'pending_approval') {
        return ['success' => false, 'error' => "Member status must be 'Email Verified' or 'Pending Approval' to approve. Current: {$m['status']}"];
    }

    $db->prepare(
        "UPDATE members SET status = 'active', approved_by = ?, approved_at = NOW(), rejection_reason = NULL, rejected_by = NULL, rejected_at = NULL WHERE id = ?"
    )->execute([$adminUserId, $memberId]);

    logAudit($adminUserId, $adminUsername, 'approve', 'members', $memberId,
        ['status' => $m['status']], ['status' => 'active'], 'Member registration approved');

    sendApprovalNotification($memberId, $m['first_name'], $m['email']);

    return ['success' => true, 'message' => "Member {$m['first_name']} has been approved."];
}

/**
 * Reject member registration
 */
function rejectMember(int $memberId, int $adminUserId, string $adminUsername, string $reason): array {
    $db = getConnection();

    $member = $db->prepare("SELECT id, first_name, email, status FROM members WHERE id = ?");
    $member->execute([$memberId]);
    $m = $member->fetch(PDO::FETCH_ASSOC);
    if (!$m) return ['success' => false, 'error' => 'Member not found.'];
    if (!in_array($m['status'], ['pending_verification', 'email_verified', 'pending_approval'])) {
        return ['success' => false, 'error' => "Member cannot be rejected in current status: {$m['status']}"];
    }

    $db->prepare(
        "UPDATE members SET status = 'terminated', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ?"
    )->execute([$adminUserId, $reason, $memberId]);

    logAudit($adminUserId, $adminUsername, 'reject', 'members', $memberId,
        ['status' => $m['status']], ['status' => 'terminated', 'rejection_reason' => $reason], 'Member registration rejected');

    if (!empty($m['email'])) {
        sendRejectionNotification($memberId, $m['first_name'], $m['email'], $reason);
    }

    return ['success' => true, 'message' => "Member {$m['first_name']} has been rejected."];
}

/**
 * Get registration progress information
 */
function getRegistrationProgress(string $status): array {
    $steps = [
        'draft'                => ['step' => 0, 'label' => 'Draft',                'icon' => 'fa-file',          'color' => 'secondary'],
        'pending_verification' => ['step' => 1, 'label' => 'Pending Verification',  'icon' => 'fa-envelope',      'color' => 'warning'],
        'email_verified'       => ['step' => 2, 'label' => 'Email Verified',        'icon' => 'fa-check-circle',  'color' => 'info'],
        'pending_approval'     => ['step' => 2, 'label' => 'Pending Approval',      'icon' => 'fa-clock',         'color' => 'info'],
        'active'               => ['step' => 3, 'label' => 'Active',                'icon' => 'fa-check-double',  'color' => 'success'],
    ];
    return $steps[$status] ?? ['step' => -1, 'label' => ucfirst($status), 'icon' => 'fa-question', 'color' => 'secondary'];
}

/**
 * Create a user account from an approved member
 */
function createUserFromMember(int $memberId, string $password): array {
    $db = getConnection();

    $stmt = $db->prepare("SELECT id, first_name, last_name, email, phone, photo, group_code FROM members WHERE id = ?");
    $stmt->execute([$memberId]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$m) return ['success' => false, 'error' => 'Member not found.'];

    // Check if user already exists for this member
    $existing = $db->prepare("SELECT id, username FROM users WHERE member_id = ?");
    $existing->execute([$memberId]);
    if ($existing->fetch()) {
        return ['success' => false, 'error' => 'A user account already exists for this member.'];
    }

    // Generate username: first letter of first_name + last_name lowercase, unique
    $baseUsername = strtolower(substr($m['first_name'], 0, 1) . preg_replace('/[^a-z0-9]/', '', strtolower($m['last_name'])));
    $username = $baseUsername;
    $suffix = 1;
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    for (;;) {
        $checkStmt->execute([$username]);
        if ((int)$checkStmt->fetchColumn() === 0) break;
        $username = $baseUsername . $suffix++;
    }

    $roleId = 7; // Member role
    $groupCode = $m['group_code'] ?? getSetting('default_group_code', 'CHAMA001');
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $db->prepare(
        "INSERT INTO users (role_id, member_id, username, email, password, group_code, phone, photo, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())"
    );
    $insert->execute([$roleId, $memberId, $username, $m['email'], $hash, $groupCode, $m['phone'], $m['photo']]);
    $userId = (int)$db->lastInsertId();

    logAudit($userId, $username, 'create', 'users', $userId,
        null, ['member_id' => $memberId, 'username' => $username], 'User account auto-created on member approval');

    return ['success' => true, 'user_id' => $userId, 'username' => $username, 'email' => $m['email'], 'first_name' => $m['first_name']];
}

/**
 * Send login credentials email to approved member
 */
function sendCredentialsEmail(string $firstName, string $email, string $username, string $password): array {
    $siteName = getSetting('site_name', 'Chama System');
    $loginUrl = BASE_URL . 'login.php';

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">Welcome to {$siteName}!</h2>
    </div>
    <div style="padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;">
        <p>Hello <strong>{$firstName}</strong>,</p>
        <p>Your membership registration has been <strong>approved</strong> and your account has been created.</p>
        <p>Use the credentials below to log in:</p>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0 0 5px 0;"><strong>Username:</strong> {$username}</p>
            <p style="margin: 0;"><strong>Password:</strong> {$password}</p>
        </div>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{$loginUrl}" style="background: #0d6efd; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">Log In Now</a>
        </div>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #888; font-size: 12px;">{$siteName} &mdash; This is an automated message, please do not reply.</p>
    </div>
</body>
</html>
HTML;

    $textBody = "Hello {$firstName},\n\nYour membership registration has been approved and your account has been created.\n\nUsername: {$username}\nPassword: {$password}\n\nLog in at: {$loginUrl}";

    return sendEmail($email, "Your Login Credentials – {$siteName}", $htmlBody, $textBody);
}

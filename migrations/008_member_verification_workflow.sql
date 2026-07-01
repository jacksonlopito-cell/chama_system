-- Migration 008: Member Registration & Verification Workflow
-- Multi-stage: draft → pending_verification → email_verified → pending_approval → active

-- 1. Change members.status ENUM to support new workflow
ALTER TABLE members MODIFY COLUMN status ENUM(
    'draft',
    'pending_verification',
    'email_verified',
    'pending_approval',
    'active',
    'suspended',
    'terminated'
) NOT NULL DEFAULT 'draft';

-- 2. Add verification and approval columns to members
ALTER TABLE members
    ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email,
    ADD COLUMN email_verified_at DATETIME DEFAULT NULL AFTER email_verified,
    ADD COLUMN email_verification_token VARCHAR(64) DEFAULT NULL AFTER email_verified_at,
    ADD COLUMN email_verification_expires DATETIME DEFAULT NULL AFTER email_verification_token,
    ADD COLUMN email_verification_sent_at DATETIME DEFAULT NULL AFTER email_verification_expires,
    ADD COLUMN verification_attempts TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verification_sent_at,
    ADD COLUMN approved_by INT DEFAULT NULL AFTER ward_id,
    ADD COLUMN approved_at DATETIME DEFAULT NULL AFTER approved_by,
    ADD COLUMN rejection_reason TEXT DEFAULT NULL AFTER approved_at,
    ADD COLUMN rejected_by INT DEFAULT NULL AFTER rejection_reason,
    ADD COLUMN rejected_at DATETIME DEFAULT NULL AFTER rejected_by;

-- 3. Create member_documents table for registration document uploads
CREATE TABLE IF NOT EXISTS member_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL COMMENT 'national_id, passport, passport_photo, signature, additional',
    document_label VARCHAR(200) DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create email_verifications table for tracking verification attempts
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    email VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'email_verification' COMMENT 'email_verification, password_reset',
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_member_type (member_id, type),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Add registration settings
INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES
('allow_public_registration', '1', 'registration'),
('require_admin_approval', '1', 'registration'),
('min_registration_age', '18', 'registration'),
('default_group_code', 'CHAMA001', 'registration'),
('registration_terms_url', '', 'registration'),
('max_verification_attempts', '5', 'registration'),
('verification_token_expiry', '24', 'registration'),
('require_national_id', '1', 'registration'),
('require_passport_photo', '1', 'registration'),
('require_signature', '1', 'registration'),
('require_emergency_contact', '1', 'registration');

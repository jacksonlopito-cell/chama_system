-- ============================================================
-- Advanced Chama Management System - Seed Data
-- ============================================================
USE chama_system;

-- ============================================================
-- 1. ROLES
-- ============================================================
INSERT INTO roles (name, slug, description, is_system) VALUES
('Super Admin', 'super_admin', 'Full system access', 1),
('Admin', 'admin', 'System administrator', 1),
('Chairperson', 'chairperson', 'Group chairperson', 1),
('Secretary', 'secretary', 'Group secretary', 1),
('Treasurer', 'treasurer', 'Group treasurer', 1),
('Loan Officer', 'loan_officer', 'Loan management officer', 1),
('Member', 'member', 'Regular member', 1),
('Auditor', 'auditor', 'Financial auditor', 1),
('Guest', 'guest', 'Limited read-only access', 1);

-- ============================================================
-- 2. PERMISSIONS
-- ============================================================
INSERT INTO permissions (name, slug, module) VALUES
-- Dashboard
('View Dashboard', 'view_dashboard', 'dashboard'),

-- Members
('View Members', 'view_members', 'members'),
('Create Members', 'create_members', 'members'),
('Edit Members', 'edit_members', 'members'),
('Delete Members', 'delete_members', 'members'),
('Suspend Members', 'suspend_members', 'members'),
('Approve Members', 'approve_members', 'members'),

-- Contributions
('View Contributions', 'view_contributions', 'contributions'),
('Create Contributions', 'create_contributions', 'contributions'),
('Edit Contributions', 'edit_contributions', 'contributions'),
('Delete Contributions', 'delete_contributions', 'contributions'),
('Export Contributions', 'export_contributions', 'contributions'),

-- Shares
('View Shares', 'view_shares', 'shares'),
('Create Shares', 'create_shares', 'shares'),
('Edit Shares', 'edit_shares', 'shares'),
('Delete Shares', 'delete_shares', 'shares'),

-- Loans
('View Loans', 'view_loans', 'loans'),
('Create Loans', 'create_loans', 'loans'),
('Edit Loans', 'edit_loans', 'loans'),
('Delete Loans', 'delete_loans', 'loans'),
('Approve Loans', 'approve_loans', 'loans'),
('Disburse Loans', 'disburse_loans', 'loans'),
('Restructure Loans', 'restructure_loans', 'loans'),

-- Loan Payments
('View Loan Payments', 'view_loan_payments', 'loan_payments'),
('Create Loan Payments', 'create_loan_payments', 'loan_payments'),
('Delete Loan Payments', 'delete_loan_payments', 'loan_payments'),

-- Meetings
('View Meetings', 'view_meetings', 'meetings'),
('Create Meetings', 'create_meetings', 'meetings'),
('Edit Meetings', 'edit_meetings', 'meetings'),
('Delete Meetings', 'delete_meetings', 'meetings'),
('Record Attendance', 'record_attendance', 'meetings'),

-- Messaging
('View Messages', 'view_messages', 'messaging'),
('Send Messages', 'send_messages', 'messaging'),
('Delete Messages', 'delete_messages', 'messaging'),

-- Announcements
('View Announcements', 'view_announcements', 'announcements'),
('Create Announcements', 'create_announcements', 'announcements'),
('Edit Announcements', 'edit_announcements', 'announcements'),
('Delete Announcements', 'delete_announcements', 'announcements'),

-- Reports
('View Reports', 'view_reports', 'reports'),
('Export Reports', 'export_reports', 'reports'),

-- Accounting
('View Accounting', 'view_accounting', 'accounting'),
('Create Journal Entries', 'create_journal_entries', 'accounting'),
('Post Journal Entries', 'post_journal_entries', 'accounting'),
('View Bank Accounts', 'view_bank_accounts', 'accounting'),

-- Settings
('View Settings', 'view_settings', 'settings'),
('Edit Settings', 'edit_settings', 'settings'),
('Manage Roles', 'manage_roles', 'settings'),
('Manage Users', 'manage_users', 'settings'),
('View Audit Logs', 'view_audit_logs', 'settings'),
('Backup Database', 'backup_database', 'settings');

-- ============================================================
-- 3. SUPER ADMIN PERMISSIONS (all permissions)
-- ============================================================
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- ============================================================
-- 4. ADMIN PERMISSIONS
-- ============================================================
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE slug NOT IN ('manage_roles', 'backup_database');

-- ============================================================
-- 5. OTHER ROLES (selective permissions)
-- ============================================================
-- Chairperson
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE slug IN ('view_dashboard', 'view_members', 'view_contributions', 'view_shares',
    'view_loans', 'approve_loans', 'view_loan_payments', 'view_meetings',
    'create_meetings', 'record_attendance', 'view_messages', 'send_messages',
    'view_announcements', 'view_reports', 'export_reports', 'view_accounting');

-- Secretary
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE slug IN ('view_dashboard', 'view_members', 'create_members', 'edit_members',
    'view_contributions', 'view_shares', 'view_loans', 'create_loans',
    'view_meetings', 'create_meetings', 'edit_meetings', 'record_attendance',
    'view_messages', 'send_messages', 'view_announcements', 'create_announcements',
    'view_reports');

-- Treasurer
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions
WHERE slug IN ('view_dashboard', 'view_members', 'view_contributions', 'create_contributions',
    'edit_contributions', 'export_contributions', 'view_shares', 'create_shares',
    'view_loans', 'create_loan_payments', 'view_loan_payments', 'view_meetings',
    'view_messages', 'send_messages', 'view_reports', 'export_reports',
    'view_accounting', 'create_journal_entries', 'view_bank_accounts');

-- Loan Officer
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions
WHERE slug IN ('view_dashboard', 'view_members', 'view_loans', 'create_loans',
    'edit_loans', 'disburse_loans', 'view_loan_payments', 'create_loan_payments',
    'view_meetings', 'view_messages', 'send_messages', 'view_reports');

-- Member
INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions
WHERE slug IN ('view_dashboard', 'view_members', 'view_contributions',
    'view_shares', 'view_loans', 'view_loan_payments',
    'view_meetings', 'view_messages', 'send_messages', 'view_announcements');

-- Auditor
INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions
WHERE slug IN ('view_dashboard', 'view_members', 'view_contributions', 'export_contributions',
    'view_shares', 'view_loans', 'view_loan_payments', 'view_meetings',
    'view_reports', 'export_reports', 'view_accounting', 'view_audit_logs');

-- ============================================================
-- 6. DEFAULT USERS
-- ============================================================
-- Password for all: Admin@123 (hashed with PHP password_hash)
INSERT INTO users (role_id, username, email, password, group_code, phone, status, is_verified) VALUES
(1, 'admin', 'admin@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000000', 'active', 1),
(2, 'manager', 'manager@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000001', 'active', 1),
(3, 'chairperson', 'chair@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000002', 'active', 1),
(4, 'secretary', 'secretary@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000003', 'active', 1),
(5, 'treasurer', 'treasurer@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000004', 'active', 1),
(6, 'loanofficer', 'loanofficer@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000005', 'active', 1),
(7, 'member1', 'member1@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000010', 'active', 1),
(7, 'member2', 'member2@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000011', 'active', 1),
(7, 'member3', 'member3@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000012', 'active', 1),
(8, 'auditor', 'auditor@chama.com', '$2y$10$3GB2lWSkchf.syW8gmgT6e7ChtUIGXcQAs.Dk/i1BkhOztdWghVdK', 'CHAMA001', '+254700000006', 'active', 1);

-- ============================================================
-- 7. DEFAULT GROUP
-- ============================================================
INSERT INTO groups_table (group_code, name, description, registration_date, contact_person, contact_phone, contact_email, status) VALUES
('CHAMA001', 'Chama System Demo Group', 'Main demo group for the Chama Management System', '2024-01-01', 'Admin User', '+254700000000', 'admin@chama.com', 'active');

-- ============================================================
-- 8. SAMPLE MEMBERS
-- ============================================================
INSERT INTO members (user_id, member_no, group_code, first_name, last_name, national_id, phone, email, occupation, employer, county, gender, date_joined, status) VALUES
(7, 'CHM-001', 'CHAMA001', 'John', 'Mwangi', 'ID12345678', '+254712345678', 'john@email.com', 'Teacher', 'Ministry of Education', 'Nairobi', 'male', '2024-01-15', 'active'),
(8, 'CHM-002', 'CHAMA001', 'Mary', 'Wanjiku', 'ID23456789', '+254723456789', 'mary@email.com', 'Nurse', 'Kenyatta Hospital', 'Kiambu', 'female', '2024-01-20', 'active'),
(9, 'CHM-003', 'CHAMA001', 'Peter', 'Kamau', 'ID34567890', '+254734567890', 'peter@email.com', 'Engineer', 'Safaricom', 'Nairobi', 'male', '2024-02-01', 'active'),
(NULL, 'CHM-004', 'CHAMA001', 'Grace', 'Akinyi', 'ID45678901', '+254745678901', 'grace@email.com', 'Accountant', 'KPMG', 'Mombasa', 'female', '2024-02-10', 'active'),
(NULL, 'CHM-005', 'CHAMA001', 'David', 'Ochieng', 'ID56789012', '+254756789012', 'david@email.com', 'Lawyer', 'Ochieng Associates', 'Kisumu', 'male', '2024-03-01', 'active'),
(NULL, 'CHM-006', 'CHAMA001', 'Sarah', 'Chebet', 'ID67890123', '+254767890123', 'sarah@email.com', 'Doctor', 'Moi Hospital', 'Nakuru', 'female', '2024-03-15', 'active');

-- Update users with member_id
UPDATE users SET member_id = 1 WHERE id = 7;
UPDATE users SET member_id = 2 WHERE id = 8;
UPDATE users SET member_id = 3 WHERE id = 9;

-- Add users for chairperson, secretary, treasurer
INSERT INTO members (user_id, member_no, group_code, first_name, last_name, national_id, phone, email, occupation, employer, county, gender, date_joined, status) VALUES
(3, 'CHM-007', 'CHAMA001', 'James', 'Kiprop', 'ID78901234', '+254778901234', 'james.chair@chama.com', 'Businessman', 'Self Employed', 'Eldoret', 'male', '2023-06-01', 'active'),
(4, 'CHM-008', 'CHAMA001', 'Esther', 'Nyambura', 'ID89012345', '+254789012345', 'esther.sec@chama.com', 'Administrator', 'County Govt', 'Nairobi', 'female', '2023-06-01', 'active'),
(5, 'CHM-009', 'CHAMA001', 'Francis', 'Omondi', 'ID90123456', '+254790123456', 'francis.tres@chama.com', 'Banker', 'Equity Bank', 'Nairobi', 'male', '2023-06-01', 'active');

-- ============================================================
-- 9. CONTRIBUTION TYPES
-- ============================================================
INSERT INTO contribution_types (name, slug, frequency, amount, description) VALUES
('Monthly Savings', 'monthly_savings', 'monthly', 2000.00, 'Regular monthly contribution'),
('Weekly Savings', 'weekly_savings', 'weekly', 500.00, 'Weekly savings contribution'),
('Quarterly Savings', 'quarterly_savings', 'quarterly', 5000.00, 'Quarterly contribution'),
('Annual Dues', 'annual_dues', 'yearly', 1000.00, 'Annual membership dues'),
('Welfare Fund', 'welfare_fund', 'monthly', 500.00, 'Welfare and emergency fund'),
('Investment Fund', 'investment_fund', 'monthly', 3000.00, 'Group investment contributions');

-- ============================================================
-- 10. SAMPLE CONTRIBUTIONS
-- ============================================================
INSERT INTO contributions (member_id, type_id, amount, contribution_date, payment_method, reference, receipt_no, recorded_by) VALUES
(1, 1, 2000.00, '2024-01-31', 'mpesa', 'MPE123ABC', 'RCP-2024-0001', 1),
(2, 1, 2000.00, '2024-01-31', 'cash', NULL, 'RCP-2024-0002', 1),
(3, 1, 2000.00, '2024-01-31', 'mpesa', 'MPE456DEF', 'RCP-2024-0003', 1),
(4, 1, 2000.00, '2024-01-31', 'bank', 'BANK001', 'RCP-2024-0004', 1),
(5, 1, 2000.00, '2024-01-31', 'cash', NULL, 'RCP-2024-0005', 1),
(6, 1, 2000.00, '2024-01-31', 'mpesa', 'MPE789GHI', 'RCP-2024-0006', 1),
(1, 1, 2000.00, '2024-02-28', 'mpesa', 'MPE321JKL', 'RCP-2024-0007', 1),
(2, 1, 2000.00, '2024-02-28', 'cash', NULL, 'RCP-2024-0008', 1),
(3, 1, 2000.00, '2024-02-28', 'mpesa', 'MPE654MNO', 'RCP-2024-0009', 1),
(4, 1, 2000.00, '2024-02-28', 'bank', 'BANK002', 'RCP-2024-0010', 1);

-- ============================================================
-- 11. SHARE PRODUCTS
-- ============================================================
INSERT INTO share_products (name, price_per_share, min_shares, max_shares, description) VALUES
('Ordinary Shares', 100.00, 1, 1000, 'Regular membership shares'),
('Preference Shares', 200.00, 1, 500, 'Preference shares with priority dividends');

-- ============================================================
-- 12. SAMPLE SHARE PURCHASES
-- ============================================================
INSERT INTO share_purchases (member_id, product_id, shares_count, total_amount, purchase_date, certificate_no, payment_method, recorded_by) VALUES
(1, 1, 10, 1000.00, '2024-01-31', 'CERT-2024-0001', 'cash', 1),
(2, 1, 5, 500.00, '2024-01-31', 'CERT-2024-0002', 'mpesa', 1),
(3, 1, 20, 2000.00, '2024-02-01', 'CERT-2024-0003', 'bank', 1);

-- ============================================================
-- 13. LOAN PRODUCTS
-- ============================================================
INSERT INTO loan_products (name, interest_rate, interest_type, max_amount, min_amount, max_tenure, late_penalty, grace_period, description) VALUES
('Emergency Loan', 5.00, 'flat', 50000.00, 1000.00, 6, 2.00, 7, 'Quick emergency loans with low interest'),
('Development Loan', 8.00, 'reducing', 500000.00, 10000.00, 24, 3.00, 14, 'Long-term development loans'),
('Business Loan', 10.00, 'reducing', 1000000.00, 50000.00, 36, 3.00, 14, 'Business investment loans'),
('School Fees Loan', 6.00, 'flat', 200000.00, 5000.00, 12, 2.00, 7, 'Education support loans');

-- ============================================================
-- 14. SAMPLE LOANS
-- ============================================================
INSERT INTO loans (member_id, product_id, loan_no, amount, interest_rate, interest_type, tenure_months, total_interest, total_amount, purpose, application_date, status, balance) VALUES
(1, 1, 'LN-2024-0001', 20000.00, 5.00, 'flat', 6, 1000.00, 21000.00, 'Medical emergency', '2024-02-01', 'active', 14000.00),
(2, 4, 'LN-2024-0002', 50000.00, 6.00, 'flat', 12, 3000.00, 53000.00, 'School fees for son', '2024-01-15', 'disbursed', 50000.00),
(3, 2, 'LN-2024-0003', 100000.00, 8.00, 'reducing', 24, 10256.00, 110256.00, 'Home improvement', '2024-03-01', 'pending', 0);

-- ============================================================
-- 15. SAMPLE LOAN SCHEDULES
-- ============================================================
INSERT INTO loan_schedules (loan_id, installment_no, due_date, principal, interest, total, balance, status) VALUES
(1, 1, '2024-03-01', 3333.33, 166.67, 3500.00, 17500.00, 'paid'),
(1, 2, '2024-04-01', 3333.33, 166.67, 3500.00, 14000.00, 'paid'),
(1, 3, '2024-05-01', 3333.34, 166.66, 3500.00, 10500.00, 'pending'),
(1, 4, '2024-06-01', 3333.33, 166.67, 3500.00, 7000.00, 'pending'),
(1, 5, '2024-07-01', 3333.34, 166.66, 3500.00, 3500.00, 'pending'),
(1, 6, '2024-08-01', 3333.33, 166.67, 3500.00, 0, 'pending');

-- ============================================================
-- 16. SAMPLE LOAN PAYMENTS
-- ============================================================
INSERT INTO loan_payments (loan_id, receipt_no, amount, principal_amount, interest_amount, payment_date, payment_method, recorded_by) VALUES
(1, 'RCP-LN-0001', 3500.00, 3333.33, 166.67, '2024-03-01', 'mpesa', 1),
(1, 'RCP-LN-0002', 3500.00, 3333.33, 166.67, '2024-04-01', 'cash', 1);

-- ============================================================
-- 17. EXPENSE CATEGORIES
-- ============================================================
INSERT INTO expense_categories (name, description) VALUES
('Transport', 'Travel and transport expenses'),
('Stationery', 'Office stationery and supplies'),
('Utilities', 'Electricity, water, internet bills'),
('Food & Drinks', 'Catering during meetings'),
('Rent', 'Office/meeting venue rent'),
('Miscellaneous', 'Other expenses');

-- ============================================================
-- 18. INCOME CATEGORIES
-- ============================================================
INSERT INTO income_categories (name, description) VALUES
('Member Contributions', 'Regular member contributions'),
('Loan Interest', 'Interest earned from loans'),
('Fines & Penalties', 'Late payment fines and penalties'),
('Investment Income', 'Income from investments'),
('Registration Fees', 'Member registration fees'),
('Other Income', 'Miscellaneous income');

-- ============================================================
-- 19. CHART OF ACCOUNTS
-- ============================================================
INSERT INTO accounts (code, name, type, description) VALUES
('1000', 'Cash at Bank', 'asset', 'Cash held in bank accounts'),
('1010', 'Cash in Hand', 'asset', 'Physical cash held'),
('1020', 'Member Receivables', 'asset', 'Outstanding member contributions'),
('1030', 'Loan Receivables', 'asset', 'Outstanding loan balances'),
('2000', 'Member Shares', 'liability', 'Member share capital'),
('2010', 'Member Savings', 'liability', 'Member savings deposits'),
('2020', 'Welfare Fund', 'liability', 'Welfare fund reserves'),
('3000', 'Retained Earnings', 'equity', 'Accumulated retained earnings'),
('3010', 'Share Capital', 'equity', 'Member share capital'),
('4000', 'Interest Income', 'income', 'Interest earned from loans'),
('4010', 'Contribution Income', 'income', 'Member contributions'),
('4020', 'Fine Income', 'income', 'Penalties and fines'),
('5000', 'Administrative Expenses', 'expense', 'Administrative costs'),
('5010', 'Meeting Expenses', 'expense', 'Meeting-related costs');

-- ============================================================
-- 20. SAMPLE MEETINGS
-- ============================================================
INSERT INTO meetings (title, description, meeting_date, start_time, end_time, venue, type, status, created_by) VALUES
('Annual General Meeting 2024', 'Annual general meeting to review 2024 performance', '2024-03-15', '09:00:00', '13:00:00', 'Nairobi Conference Centre', 'annual', 'completed', 1),
('Monthly Meeting - April 2024', 'Regular monthly meeting', '2024-04-20', '10:00:00', '12:00:00', 'Community Hall', 'regular', 'completed', 1),
('Monthly Meeting - May 2024', 'Regular monthly meeting for May', '2024-05-18', '10:00:00', '12:00:00', 'Community Hall', 'regular', 'scheduled', 1);

-- ============================================================
-- 21. SAMPLE SETTINGS
-- ============================================================
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'Advanced Chama System', 'general'),
('site_tagline', 'Manage your Chama affairs with ease', 'general'),
('site_logo', '', 'general'),
('site_favicon', '', 'general'),
('primary_color', '#161950', 'branding'),
('secondary_color', '#465fff', 'branding'),
('currency', 'KES', 'general'),
('currency_symbol', 'KSh', 'general'),
('currency_position', 'before', 'general'),
('date_format', 'd/m/Y', 'general'),
('timezone', 'Africa/Nairobi', 'general'),
('smtp_host', '', 'email'),
('smtp_port', '587', 'email'),
('smtp_username', '', 'email'),
('smtp_password', '', 'email'),
('smtp_from_email', 'noreply@chama.com', 'email'),
('smtp_from_name', 'Chama System', 'email'),
('sms_api_key', '', 'sms'),
('sms_sender_id', 'CHAMA', 'sms'),
('max_login_attempts', '5', 'security'),
('session_timeout', '3600', 'security'),
('password_min_length', '8', 'security'),
('auto_backup', '0', 'backup'),
('backup_frequency', 'weekly', 'backup'),
('welcome_message', 'Welcome to the Chama Management System', 'general'),
('default_interest_rate', '8', 'loans'),
('late_penalty_rate', '3', 'loans');

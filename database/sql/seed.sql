-- ============================================================
-- Advanced Chama Management System - Seed Data
-- ============================================================

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
('View Dashboard', 'view_dashboard', 'dashboard'),
('View Members', 'view_members', 'members'),
('Create Members', 'create_members', 'members'),
('Edit Members', 'edit_members', 'members'),
('Delete Members', 'delete_members', 'members'),
('Suspend Members', 'suspend_members', 'members'),
('Approve Members', 'approve_members', 'members'),
('View Contributions', 'view_contributions', 'contributions'),
('Create Contributions', 'create_contributions', 'contributions'),
('Edit Contributions', 'edit_contributions', 'contributions'),
('Delete Contributions', 'delete_contributions', 'contributions'),
('Export Contributions', 'export_contributions', 'contributions'),
('View Shares', 'view_shares', 'shares'),
('Create Shares', 'create_shares', 'shares'),
('Edit Shares', 'edit_shares', 'shares'),
('Delete Shares', 'delete_shares', 'shares'),
('View Loans', 'view_loans', 'loans'),
('Create Loans', 'create_loans', 'loans'),
('Edit Loans', 'edit_loans', 'loans'),
('Delete Loans', 'delete_loans', 'loans'),
('Approve Loans', 'approve_loans', 'loans'),
('Disburse Loans', 'disburse_loans', 'loans'),
('View Loan Payments', 'view_loan_payments', 'loan_payments'),
('Create Loan Payments', 'create_loan_payments', 'loan_payments'),
('Delete Loan Payments', 'delete_loan_payments', 'loan_payments'),
('View Meetings', 'view_meetings', 'meetings'),
('Create Meetings', 'create_meetings', 'meetings'),
('Edit Meetings', 'edit_meetings', 'meetings'),
('Delete Meetings', 'delete_meetings', 'meetings'),
('Record Attendance', 'record_attendance', 'meetings'),
('View Messages', 'view_messages', 'messaging'),
('Send Messages', 'send_messages', 'messaging'),
('View Announcements', 'view_announcements', 'announcements'),
('Create Announcements', 'create_announcements', 'announcements'),
('Edit Announcements', 'edit_announcements', 'announcements'),
('Delete Announcements', 'delete_announcements', 'announcements'),
('View Reports', 'view_reports', 'reports'),
('Export Reports', 'export_reports', 'reports'),
('View Accounting', 'view_accounting', 'accounting'),
('Create Journal Entries', 'create_journal_entries', 'accounting'),
('View Bank Accounts', 'view_bank_accounts', 'accounting'),
('View Settings', 'view_settings', 'settings'),
('Edit Settings', 'edit_settings', 'settings'),
('Manage Users', 'manage_users', 'settings'),
('View Audit Logs', 'view_audit_logs', 'settings');

-- ============================================================
-- 3. ROLE PERMISSIONS
-- ============================================================
INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;
INSERT INTO role_permissions (role_id, permission_id) SELECT 2, id FROM permissions WHERE slug NOT IN ('manage_roles', 'view_settings', 'edit_settings');
INSERT INTO role_permissions (role_id, permission_id) SELECT 3, id FROM permissions WHERE slug IN ('view_dashboard','view_members','view_contributions','view_shares','view_loans','approve_loans','view_loan_payments','view_meetings','create_meetings','record_attendance','view_messages','send_messages','view_announcements','view_reports','export_reports','view_accounting');
INSERT INTO role_permissions (role_id, permission_id) SELECT 4, id FROM permissions WHERE slug IN ('view_dashboard','view_members','create_members','edit_members','view_contributions','view_shares','view_loans','create_loans','view_meetings','create_meetings','edit_meetings','record_attendance','view_messages','send_messages','view_announcements','create_announcements','view_reports');
INSERT INTO role_permissions (role_id, permission_id) SELECT 5, id FROM permissions WHERE slug IN ('view_dashboard','view_members','view_contributions','create_contributions','edit_contributions','export_contributions','view_shares','create_shares','view_loans','create_loan_payments','view_loan_payments','view_meetings','view_messages','send_messages','view_reports','export_reports','view_accounting','create_journal_entries','view_bank_accounts');
INSERT INTO role_permissions (role_id, permission_id) SELECT 6, id FROM permissions WHERE slug IN ('view_dashboard','view_members','view_loans','create_loans','edit_loans','disburse_loans','view_loan_payments','create_loan_payments','view_meetings','view_messages','send_messages','view_reports');
INSERT INTO role_permissions (role_id, permission_id) SELECT 7, id FROM permissions WHERE slug IN ('view_dashboard','view_members','view_contributions','view_shares','view_loans','view_loan_payments','view_meetings','view_messages','send_messages','view_announcements');
INSERT INTO role_permissions (role_id, permission_id) SELECT 8, id FROM permissions WHERE slug IN ('view_dashboard','view_members','view_contributions','export_contributions','view_shares','view_loans','view_loan_payments','view_meetings','view_reports','export_reports','view_accounting','view_audit_logs');

-- ============================================================
-- 4. DEFAULT USERS
-- ============================================================
-- Password for all: Admin@123
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
-- 5. SAMPLE MEMBERS
-- ============================================================
INSERT INTO members (member_no, group_code, first_name, last_name, id_number, phone, email, county_id, gender, join_date, status) VALUES
('CHM-001', 'CHAMA001', 'John', 'Mwangi', 'ID12345678', '+254712345678', 'john@email.com', NULL, 'Male', '2024-01-15', 'active'),
('CHM-002', 'CHAMA001', 'Mary', 'Wanjiku', 'ID23456789', '+254723456789', 'mary@email.com', NULL, 'Female', '2024-01-20', 'active'),
('CHM-003', 'CHAMA001', 'Peter', 'Kamau', 'ID34567890', '+254734567890', 'peter@email.com', NULL, 'Male', '2024-02-01', 'active'),
('CHM-004', 'CHAMA001', 'Grace', 'Akinyi', 'ID45678901', '+254745678901', 'grace@email.com', NULL, 'Female', '2024-02-10', 'active'),
('CHM-005', 'CHAMA001', 'David', 'Ochieng', 'ID56789012', '+254756789012', 'david@email.com', NULL, 'Male', '2024-03-01', 'active'),
('CHM-006', 'CHAMA001', 'Sarah', 'Chebet', 'ID67890123', '+254767890123', 'sarah@email.com', NULL, 'Female', '2024-03-15', 'active'),
('CHM-007', 'CHAMA001', 'James', 'Kiprop', 'ID78901234', '+254778901234', 'james.chair@chama.com', NULL, 'Male', '2023-06-01', 'active'),
('CHM-008', 'CHAMA001', 'Esther', 'Nyambura', 'ID89012345', '+254789012345', 'esther.sec@chama.com', NULL, 'Female', '2023-06-01', 'active'),
('CHM-009', 'CHAMA001', 'Francis', 'Omondi', 'ID90123456', '+254790123456', 'francis.tres@chama.com', NULL, 'Male', '2023-06-01', 'active');

-- Link users to members
UPDATE users SET member_id = 1 WHERE id = 7;
UPDATE users SET member_id = 2 WHERE id = 8;
UPDATE users SET member_id = 3 WHERE id = 9;
UPDATE users SET member_id = 7 WHERE id = 3;
UPDATE users SET member_id = 8 WHERE id = 4;
UPDATE users SET member_id = 9 WHERE id = 5;

-- ============================================================
-- 6. SETTINGS
-- ============================================================
INSERT INTO settings (setting_key, setting_value, group_code) VALUES
('site_name', 'Advanced Chama System', 'CHAMA001'),
('currency', 'KES', 'CHAMA001'),
('currency_symbol', 'KSh', 'CHAMA001'),
('date_format', 'd/m/Y', 'CHAMA001'),
('timezone', 'Africa/Nairobi', 'CHAMA001'),
('smtp_host', '', 'CHAMA001'),
('smtp_port', '587', 'CHAMA001'),
('smtp_username', '', 'CHAMA001'),
('smtp_password', '', 'CHAMA001'),
('smtp_from_email', 'noreply@chama.com', 'CHAMA001'),
('max_login_attempts', '5', 'CHAMA001'),
('session_timeout', '3600', 'CHAMA001'),
('password_min_length', '8', 'CHAMA001'),
('default_interest_rate', '8', 'CHAMA001'),
('default_group_code', 'CHAMA001', 'CHAMA001');

-- ============================================================
-- 7. SHARE PRODUCTS
-- ============================================================
INSERT INTO share_products (name, price_per_share, max_per_member, status, group_code) VALUES
('Ordinary Shares', 100.00, 1000, 'active', 'CHAMA001'),
('Preference Shares', 200.00, 500, 'active', 'CHAMA001');

-- ============================================================
-- 8. SHARE PURCHASES
-- ============================================================
INSERT INTO share_purchases (certificate_no, member_id, product_id, quantity, total_amount, purchase_date, payment_method, status, group_code, created_by) VALUES
('CERT-2024-0001', 1, 1, 10, 1000.00, '2024-01-31', 'cash', 'active', 'CHAMA001', 1),
('CERT-2024-0002', 2, 1, 5, 500.00, '2024-01-31', 'mpesa', 'active', 'CHAMA001', 1),
('CERT-2024-0003', 3, 1, 20, 2000.00, '2024-02-01', 'bank', 'active', 'CHAMA001', 1);

-- ============================================================
-- 9. LOAN PRODUCTS
-- ============================================================
INSERT INTO loan_products (name, min_amount, max_amount, interest_rate, min_duration, max_duration, repayment_method, group_code) VALUES
('Emergency Loan', 1000.00, 50000.00, 5.00, 1, 6, 'monthly', 'CHAMA001'),
('Development Loan', 10000.00, 500000.00, 8.00, 6, 24, 'monthly', 'CHAMA001'),
('Business Loan', 50000.00, 1000000.00, 10.00, 6, 36, 'monthly', 'CHAMA001'),
('School Fees Loan', 5000.00, 200000.00, 6.00, 3, 12, 'monthly', 'CHAMA001');

-- ============================================================
-- 10. SAMPLE MEETINGS
-- ============================================================
INSERT INTO meetings (title, meeting_date, meeting_time, venue, status, group_code, created_by) VALUES
('Annual General Meeting 2024', '2024-03-15', '09:00:00', 'Nairobi Conference Centre', 'completed', 'CHAMA001', 1),
('Monthly Meeting - April 2024', '2024-04-20', '10:00:00', 'Community Hall', 'completed', 'CHAMA001', 1),
('Monthly Meeting - May 2024', '2024-05-18', '10:00:00', 'Community Hall', 'scheduled', 'CHAMA001', 1);

-- ============================================================
-- 11. EXPENSE CATEGORIES
-- ============================================================
INSERT INTO expense_categories (name, group_code) VALUES
('Transport', 'CHAMA001'),
('Stationery', 'CHAMA001'),
('Utilities', 'CHAMA001'),
('Food & Drinks', 'CHAMA001'),
('Rent', 'CHAMA001'),
('Miscellaneous', 'CHAMA001');

-- ============================================================
-- 12. INCOME CATEGORIES
-- ============================================================
INSERT INTO income_categories (name, group_code) VALUES
('Member Contributions', 'CHAMA001'),
('Loan Interest', 'CHAMA001'),
('Investment Income', 'CHAMA001'),
('Other Income', 'CHAMA001');

-- ============================================================
-- 13. CHART OF ACCOUNTS
-- ============================================================
INSERT INTO chart_of_accounts (account_code, account_name, account_type, group_code) VALUES
('1000', 'Cash at Bank', 'asset', 'CHAMA001'),
('1010', 'Cash in Hand', 'asset', 'CHAMA001'),
('1020', 'Member Receivables', 'asset', 'CHAMA001'),
('2000', 'Member Savings', 'liability', 'CHAMA001'),
('3000', 'Retained Earnings', 'equity', 'CHAMA001'),
('4000', 'Interest Income', 'income', 'CHAMA001'),
('4010', 'Contribution Income', 'income', 'CHAMA001'),
('5000', 'Administrative Expenses', 'expense', 'CHAMA001');

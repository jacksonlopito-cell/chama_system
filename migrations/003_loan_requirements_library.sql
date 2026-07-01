-- ============================================================
-- Migration 003: Loan Requirements Library
-- Categories, system rule keys, per-product rule values
-- ============================================================

-- 1. Requirement categories
CREATE TABLE IF NOT EXISTS loan_requirement_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(50) NOT NULL UNIQUE,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO loan_requirement_categories (name, slug, sort_order) VALUES
  ('Eligibility', 'eligibility', 1),
  ('Documents', 'documents', 2),
  ('Financial Rules', 'financial_rules', 3),
  ('Guarantors', 'guarantors', 4),
  ('Fees', 'fees', 5),
  ('Repayment', 'repayment', 6),
  ('Custom Rules', 'custom', 7);

-- 2. Add columns to loan_requirements
ALTER TABLE loan_requirements
  ADD COLUMN category_id INT DEFAULT NULL,
  ADD COLUMN rule_key VARCHAR(50) DEFAULT NULL,
  ADD COLUMN description TEXT DEFAULT NULL,
  ADD FOREIGN KEY (category_id) REFERENCES loan_requirement_categories(id) ON DELETE SET NULL;

-- 3. Per-product rule values
CREATE TABLE IF NOT EXISTS loan_product_rule_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  requirement_id INT NOT NULL,
  rule_value TEXT,
  FOREIGN KEY (product_id) REFERENCES loan_products(id) ON DELETE CASCADE,
  FOREIGN KEY (requirement_id) REFERENCES loan_requirements(id) ON DELETE CASCADE,
  UNIQUE KEY uq_product_requirement (product_id, requirement_id)
);

-- 4. Seed system requirements
-- Eligibility
INSERT INTO loan_requirements (label, input_type, is_required, is_active, sort_order, category_id, rule_key, description)
VALUES
('Active Membership Status', 'checkbox', 1, 1, 1, (SELECT id FROM loan_requirement_categories WHERE slug='eligibility'), 'active_membership', 'Member must have active status'),
('Minimum Membership Period (months)', 'number', 1, 1, 2, (SELECT id FROM loan_requirement_categories WHERE slug='eligibility'), 'min_membership_period', 'Minimum months since registration'),
('Minimum Shares Owned', 'number', 1, 1, 3, (SELECT id FROM loan_requirement_categories WHERE slug='eligibility'), 'min_shares', 'Minimum number of shares held'),
('Minimum Contributions Made (KES)', 'number', 1, 1, 4, (SELECT id FROM loan_requirement_categories WHERE slug='eligibility'), 'min_contributions', 'Minimum total contributions'),
('Previous Loan Cleared', 'checkbox', 1, 1, 5, (SELECT id FROM loan_requirement_categories WHERE slug='eligibility'), 'prev_loan_clearance', 'No outstanding loan balance'),
('Credit Score / Check', 'checkbox', 0, 1, 6, (SELECT id FROM loan_requirement_categories WHERE slug='eligibility'), 'credit_score', 'Minimum credit score (optional)'),
('Maximum Loan Multiplier', 'number', 1, 1, 7, (SELECT id FROM loan_requirement_categories WHERE slug='eligibility'), 'max_loan_multiplier', 'Loan amount = multiplier × (shares + contributions)');

-- Documents
INSERT INTO loan_requirements (label, input_type, is_required, is_active, sort_order, category_id, rule_key, description)
VALUES
('National ID / Passport', 'file', 1, 1, 1, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'national_id', 'Upload scanned ID or passport'),
('KRA PIN', 'text', 0, 1, 2, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'kra_pin', 'KRA PIN certificate (optional)'),
('Passport Photo', 'file', 0, 1, 3, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'passport_photo', 'Recent passport-size photo'),
('Signature', 'file', 0, 1, 4, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'signature', 'Scanned signature specimen'),
('Proof of Income', 'file', 0, 1, 5, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'proof_of_income', 'Bank statements or pay slips'),
('Payslip Upload', 'file', 0, 1, 6, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'payslip', 'Latest payslip'),
('Business License Upload', 'file', 0, 1, 7, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'business_license', 'Business permit/license (for business loans)'),
('Employer Details', 'text', 0, 1, 8, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'employer_details', 'Employer name, address, phone'),
('Business Details', 'textarea', 0, 1, 9, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'business_details', 'Business name, type, location'),
('Bank Account / Mobile Money Details', 'text', 0, 1, 10, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'bank_details', 'Bank account or M-Pesa number'),
('Loan Purpose', 'textarea', 1, 1, 11, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'loan_purpose', 'State the purpose of the loan'),
('Supporting Documents', 'file', 0, 1, 12, (SELECT id FROM loan_requirement_categories WHERE slug='documents'), 'supporting_docs', 'Any additional supporting documents');

-- Financial Rules
INSERT INTO loan_requirements (label, input_type, is_required, is_active, sort_order, category_id, rule_key, description)
VALUES
('Collateral Requirement', 'checkbox', 1, 1, 1, (SELECT id FROM loan_requirement_categories WHERE slug='financial_rules'), 'collateral_required', 'Require collateral for this product'),
('Insurance Fee (%)', 'number', 0, 1, 2, (SELECT id FROM loan_requirement_categories WHERE slug='financial_rules'), 'insurance_fee', 'Insurance fee as percentage of loan amount'),
('Interest Type', 'dropdown', 1, 1, 3, (SELECT id FROM loan_requirement_categories WHERE slug='financial_rules'), 'interest_type', 'Fixed per product'),
('Processing Fee (%)', 'number', 1, 1, 4, (SELECT id FROM loan_requirement_categories WHERE slug='financial_rules'), 'processing_fee', 'Processing fee percentage');

-- Guarantors
INSERT INTO loan_requirements (label, input_type, is_required, is_active, sort_order, category_id, rule_key, description)
VALUES
('Minimum Guarantors', 'number', 1, 1, 1, (SELECT id FROM loan_requirement_categories WHERE slug='guarantors'), 'min_guarantors', 'Minimum number of guarantors required'),
('Guarantor Approval Required', 'checkbox', 1, 1, 2, (SELECT id FROM loan_requirement_categories WHERE slug='guarantors'), 'guarantor_approval', 'Guarantors must approve the application');

-- Repayment
INSERT INTO loan_requirements (label, input_type, is_required, is_active, sort_order, category_id, rule_key, description)
VALUES
('Repayment Frequency', 'dropdown', 1, 1, 1, (SELECT id FROM loan_requirement_categories WHERE slug='repayment'), 'repayment_frequency', 'Monthly, bi-weekly, weekly'),
('Grace Period (days)', 'number', 1, 1, 2, (SELECT id FROM loan_requirement_categories WHERE slug='repayment'), 'grace_period', 'Days after due date before penalty'),
('Late Penalty (% per period)', 'number', 1, 1, 3, (SELECT id FROM loan_requirement_categories WHERE slug='repayment'), 'late_penalty', 'Penalty rate per overdue period');

-- Insert options for dropdown-type seeded requirements
INSERT INTO loan_requirement_options (requirement_id, option_value, sort_order)
SELECT r.id, 'Monthly', 0 FROM loan_requirements r WHERE r.rule_key = 'repayment_frequency'
UNION ALL SELECT r.id, 'Bi-Weekly', 1 FROM loan_requirements r WHERE r.rule_key = 'repayment_frequency'
UNION ALL SELECT r.id, 'Weekly', 2 FROM loan_requirements r WHERE r.rule_key = 'repayment_frequency';

INSERT INTO loan_requirement_options (requirement_id, option_value, sort_order)
SELECT r.id, 'Reducing Balance', 0 FROM loan_requirements r WHERE r.rule_key = 'interest_type'
UNION ALL SELECT r.id, 'Flat Rate', 1 FROM loan_requirements r WHERE r.rule_key = 'interest_type';

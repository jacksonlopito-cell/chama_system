-- Fix missing group_code columns
-- If you see "Duplicate column" errors, skip them (column already exists)

ALTER TABLE loans ADD COLUMN group_code VARCHAR(20) NOT NULL DEFAULT 'CHAMA001' AFTER balance;
ALTER TABLE share_products ADD COLUMN group_code VARCHAR(20) NOT NULL DEFAULT 'CHAMA001' AFTER status;
ALTER TABLE dividends ADD COLUMN group_code VARCHAR(20) NOT NULL DEFAULT 'CHAMA001' AFTER status;

-- Indexes
CREATE INDEX idx_loans_group ON loans(group_code);
CREATE INDEX idx_share_products_group ON share_products(group_code);
CREATE INDEX idx_dividends_group ON dividends(group_code);

-- Migration 009: Add group_code to share_products, and dividends
-- Enables multi-group isolation for shares & dividends
-- Note: share_purchases.group_code already exists

ALTER TABLE share_products
  ADD COLUMN group_code VARCHAR(20) NOT NULL DEFAULT 'CHAMA001' AFTER status,
  ADD INDEX idx_share_products_group (group_code);

ALTER TABLE dividends
  ADD COLUMN group_code VARCHAR(20) NOT NULL DEFAULT 'CHAMA001' AFTER created_by,
  ADD INDEX idx_dividends_group (group_code);

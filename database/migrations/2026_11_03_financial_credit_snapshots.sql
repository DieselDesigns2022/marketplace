-- New snapshots default to legacy behavior; no historical amounts rewritten.
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS financial_snapshot_version TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS marketplace_credit_merchandise DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS marketplace_credit_tax DECIMAL(12,2) NOT NULL DEFAULT 0.00;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS marketplace_credit_applied DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS cash_merchandise_amount DECIMAL(12,2) NULL;

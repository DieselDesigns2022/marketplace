-- Phase 10.3: seller opt-in store-level sales tax settings and order tax snapshots.
ALTER TABLE designers ADD COLUMN IF NOT EXISTS sales_tax_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER shopify_url;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS sales_tax_state CHAR(2) NULL AFTER sales_tax_enabled;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS sales_tax_registration_id VARCHAR(120) NULL AFTER sales_tax_state;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS sales_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER sales_tax_registration_id;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS sales_tax_responsibility_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER sales_tax_rate;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS sales_tax_updated_at TIMESTAMP NULL AFTER sales_tax_responsibility_confirmed;
CREATE INDEX IF NOT EXISTS designers_sales_tax_phase_10_3 ON designers (sales_tax_enabled, sales_tax_state);

ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_snapshot JSON NULL AFTER tax_amount;

ALTER TABLE order_items ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_price;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS tax_snapshot JSON NULL AFTER tax_amount;

ALTER TABLE seller_earnings ADD COLUMN IF NOT EXISTS seller_tax_collected DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER marketplace_commission;
ALTER TABLE seller_payouts ADD COLUMN IF NOT EXISTS seller_tax_collected DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER platform_commission_amount;

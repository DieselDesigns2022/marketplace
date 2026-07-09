-- Phase 10.3B Stripe Tax, marketplace tax compliance, and 1099 website integration.
-- Tax is calculated by Stripe Checkout/Stripe Tax, not by seller-entered rates.

ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER subtotal;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_provider VARCHAR(60) DEFAULT 'stripe_tax' AFTER tax_amount;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_status VARCHAR(60) DEFAULT 'pending' AFTER tax_provider;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_liability_owner VARCHAR(60) DEFAULT 'platform' AFTER tax_status;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_snapshot JSON NULL AFTER tax_liability_owner;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_collected_at TIMESTAMP NULL AFTER tax_snapshot;

CREATE INDEX IF NOT EXISTS orders_tax_provider_phase_10_3b ON orders (tax_provider);
CREATE INDEX IF NOT EXISTS orders_tax_status_phase_10_3b ON orders (tax_status);

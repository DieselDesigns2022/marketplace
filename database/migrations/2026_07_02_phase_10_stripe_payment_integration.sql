-- Phase 10: Stripe payment integration, webhook logs, and seller payout foundation.
ALTER TABLE orders MODIFY status ENUM('pending','paid','failed','cancelled','refunded','partially_refunded','partially_fulfilled','fulfilled','completed') DEFAULT 'pending';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_provider VARCHAR(40) DEFAULT NULL AFTER payment_mode;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(40) DEFAULT 'pending' AFTER payment_provider;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(255) NULL AFTER payment_status;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_payment_intent_id VARCHAR(255) NULL AFTER stripe_checkout_session_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255) NULL AFTER stripe_payment_intent_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_charge_id VARCHAR(255) NULL AFTER stripe_customer_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_payment_status VARCHAR(80) NULL AFTER stripe_charge_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_amount_total INT NULL AFTER stripe_payment_status;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_currency VARCHAR(10) DEFAULT 'usd' AFTER stripe_amount_total;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_fee_total DECIMAL(10,2) NULL AFTER stripe_currency;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS platform_commission_total DECIMAL(10,2) DEFAULT 0.00 AFTER stripe_fee_total;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL AFTER platform_commission_total;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS failed_at TIMESTAMP NULL AFTER paid_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS refunded_at TIMESTAMP NULL AFTER failed_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS partially_refunded_at TIMESTAMP NULL AFTER refunded_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS canceled_at TIMESTAMP NULL AFTER partially_refunded_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_error TEXT NULL AFTER canceled_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_retry_count INT NOT NULL DEFAULT 0 AFTER payment_error;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS manual_review_required TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_retry_count;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS manual_review_reason TEXT NULL AFTER manual_review_required;
CREATE INDEX IF NOT EXISTS orders_payment_status_phase10 ON orders (payment_status);
CREATE INDEX IF NOT EXISTS orders_stripe_session_phase10 ON orders (stripe_checkout_session_id);
CREATE INDEX IF NOT EXISTS orders_stripe_intent_phase10 ON orders (stripe_payment_intent_id);

ALTER TABLE order_items ADD COLUMN IF NOT EXISTS platform_commission_amount DECIMAL(10,2) DEFAULT 0.00 AFTER commission_rate;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS seller_payout_amount DECIMAL(10,2) DEFAULT 0.00 AFTER platform_commission_amount;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS seller_payout_status VARCHAR(60) DEFAULT 'pending_payment' AFTER seller_payout_amount;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS stripe_transfer_id VARCHAR(255) NULL AFTER seller_payout_status;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS stripe_transfer_error TEXT NULL AFTER stripe_transfer_id;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL AFTER stripe_transfer_error;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS payout_ready_at TIMESTAMP NULL AFTER paid_at;
CREATE INDEX IF NOT EXISTS order_items_payout_status_phase10 ON order_items (seller_payout_status);

ALTER TABLE designers ADD COLUMN IF NOT EXISTS stripe_connect_account_id VARCHAR(255) NULL AFTER rank_override;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS stripe_charges_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER stripe_connect_account_id;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS stripe_payouts_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER stripe_charges_enabled;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS stripe_details_submitted TINYINT(1) NOT NULL DEFAULT 0 AFTER stripe_payouts_enabled;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS stripe_account_status VARCHAR(80) DEFAULT 'not_connected' AFTER stripe_details_submitted;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS stripe_onboarding_started_at TIMESTAMP NULL AFTER stripe_account_status;
ALTER TABLE designers ADD COLUMN IF NOT EXISTS stripe_onboarding_completed_at TIMESTAMP NULL AFTER stripe_onboarding_started_at;
CREATE INDEX IF NOT EXISTS designers_stripe_account_phase10 ON designers (stripe_connect_account_id);

CREATE TABLE IF NOT EXISTS stripe_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
  event_type VARCHAR(120) NOT NULL,
  processed_at TIMESTAMP NULL,
  processing_status VARCHAR(40) NOT NULL DEFAULT 'received',
  processing_error TEXT NULL,
  payload_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY stripe_events_status_phase10 (processing_status),
  KEY stripe_events_type_phase10 (event_type)
);

CREATE TABLE IF NOT EXISTS payment_transactions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT NOT NULL,
  stripe_event_id VARCHAR(255) NULL,
  transaction_type VARCHAR(80) NOT NULL,
  payment_status VARCHAR(60) NOT NULL,
  amount DECIMAL(10,2) DEFAULT 0.00,
  currency VARCHAR(10) DEFAULT 'usd',
  stripe_checkout_session_id VARCHAR(255) NULL,
  stripe_payment_intent_id VARCHAR(255) NULL,
  stripe_charge_id VARCHAR(255) NULL,
  message TEXT NULL,
  manual_review_required TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY payment_transactions_order_phase10 (order_id),
  KEY payment_transactions_event_phase10 (stripe_event_id)
);

CREATE TABLE IF NOT EXISTS seller_payouts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT NOT NULL,
  designer_id BIGINT NOT NULL,
  gross_amount DECIMAL(10,2) DEFAULT 0.00,
  platform_commission_amount DECIMAL(10,2) DEFAULT 0.00,
  seller_payout_amount DECIMAL(10,2) DEFAULT 0.00,
  currency VARCHAR(10) DEFAULT 'usd',
  payout_status VARCHAR(60) DEFAULT 'pending_payment',
  stripe_transfer_id VARCHAR(255) NULL,
  stripe_transfer_error TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY seller_payouts_order_designer_phase10 (order_id, designer_id),
  KEY seller_payouts_designer_phase10 (designer_id),
  KEY seller_payouts_status_phase10 (payout_status)
);

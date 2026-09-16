-- Phase 12.7: immutable 9% + $0.30-per-seller marketplace fee snapshots.
-- Existing rows remain legacy_percentage and no historical amounts are updated.
ALTER TABLE orders
  ADD COLUMN marketplace_fee_model VARCHAR(40) NOT NULL DEFAULT 'legacy_percentage' AFTER platform_commission_total,
  ADD COLUMN marketplace_fee_basis_points INT NULL AFTER marketplace_fee_model,
  ADD COLUMN marketplace_fixed_fee_cents INT NULL AFTER marketplace_fee_basis_points;

ALTER TABLE order_items
  ADD COLUMN marketplace_percentage_fee_amount DECIMAL(10,2) NULL AFTER platform_commission_amount,
  ADD COLUMN marketplace_fixed_fee_amount DECIMAL(10,2) NULL AFTER marketplace_percentage_fee_amount;

ALTER TABLE seller_payouts
  ADD COLUMN fee_model VARCHAR(40) NOT NULL DEFAULT 'legacy_percentage' AFTER gross_amount,
  ADD COLUMN commission_rate_snapshot DECIMAL(5,4) NULL AFTER fee_model,
  ADD COLUMN fixed_fee_cents_snapshot INT NULL AFTER commission_rate_snapshot,
  ADD COLUMN marketplace_percentage_fee_amount DECIMAL(10,2) NULL AFTER fixed_fee_cents_snapshot,
  ADD COLUMN marketplace_fixed_fee_amount DECIMAL(10,2) NULL AFTER marketplace_percentage_fee_amount,
  ADD COLUMN original_gross_amount DECIMAL(10,2) NULL AFTER marketplace_fixed_fee_amount,
  ADD COLUMN original_seller_payout_amount DECIMAL(10,2) NULL AFTER original_gross_amount;
-- Preserve the existing legacy authority as a stable refund baseline; no historical amount is recalculated.
UPDATE seller_payouts SET original_gross_amount=gross_amount,original_seller_payout_amount=seller_payout_amount
WHERE original_gross_amount IS NULL OR original_seller_payout_amount IS NULL;
ALTER TABLE seller_payouts
  ADD COLUMN recovery_reserved_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER seller_payout_amount, ADD COLUMN recovery_applied_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER recovery_reserved_amount,
  ADD COLUMN transfer_amount_after_recovery DECIMAL(10,2) NULL AFTER recovery_applied_amount,
  ADD COLUMN recovery_claim_status ENUM('unclaimed','reserved','applied') NOT NULL DEFAULT 'unclaimed' AFTER transfer_amount_after_recovery;
ALTER TABLE seller_payouts
  ADD COLUMN payout_execution_status ENUM('unplanned','reserved','attempting','failed','completed') NOT NULL DEFAULT 'unplanned' AFTER recovery_claim_status,
  ADD COLUMN payout_execution_key VARCHAR(190) NULL AFTER payout_execution_status,
  ADD COLUMN payout_execution_claimed_at TIMESTAMP NULL AFTER payout_execution_key,
  ADD COLUMN completed_economic_value_amount DECIMAL(10,2) NULL AFTER payout_execution_claimed_at,
  ADD UNIQUE KEY seller_payout_execution_key_unique(payout_execution_key);

CREATE TABLE seller_financial_adjustments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT NOT NULL,
  designer_id BIGINT NOT NULL,
  adjustment_type ENUM('refund_recovery','refund_allocation_review','fee_cap_warning','manual_recovery','waiver','resolution') NOT NULL,
  amount_cents BIGINT NOT NULL DEFAULT 0,
  original_amount_cents BIGINT NOT NULL DEFAULT 0,
  applied_cents BIGINT NOT NULL DEFAULT 0,
  reserved_cents BIGINT NOT NULL DEFAULT 0,
  balance_cents BIGINT NOT NULL DEFAULT 0,
  event_key VARCHAR(190) NOT NULL,
  status ENUM('open','applied','waived','resolved') NOT NULL DEFAULT 'open',
  note VARCHAR(500) NULL,
  resolved_by BIGINT NULL,
  resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY seller_financial_adjustments_event_unique(event_key),
  KEY seller_financial_adjustments_seller_status(designer_id,status),
  CONSTRAINT seller_financial_adjustments_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT seller_financial_adjustments_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
  CONSTRAINT seller_financial_adjustments_admin_fk FOREIGN KEY(resolved_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE marketplace_refund_observations (
  id BIGINT PRIMARY KEY AUTO_INCREMENT, order_id BIGINT NOT NULL, stripe_event_id VARCHAR(190) NOT NULL,
  cumulative_refund_cents BIGINT NOT NULL, refund_delta_cents BIGINT NOT NULL,
  merchandise_refund_cents BIGINT NOT NULL DEFAULT 0, tax_refund_cents BIGINT NOT NULL DEFAULT 0,
  allocated_cents BIGINT NOT NULL DEFAULT 0,
  allocation_status ENUM('needs_allocation','allocated','reconciled') NOT NULL DEFAULT 'needs_allocation',
  allocated_at TIMESTAMP NULL, reconciled_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY marketplace_refund_observation_event(stripe_event_id),
  KEY marketplace_refund_observation_order_status(order_id,allocation_status),
  CONSTRAINT marketplace_refund_observation_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT
);

CREATE TABLE marketplace_refund_allocations (
  id BIGINT PRIMARY KEY AUTO_INCREMENT, refund_observation_id BIGINT NOT NULL, order_id BIGINT NOT NULL, order_item_id BIGINT NOT NULL,
  merchandise_refund_cents BIGINT NOT NULL, event_key VARCHAR(190) NOT NULL,
  allocation_source ENUM('admin','stripe_full_order') NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY marketplace_refund_allocation_observation_item(refund_observation_id,order_item_id),
  UNIQUE KEY marketplace_refund_allocation_event(event_key), KEY marketplace_refund_allocations_order(order_id),
  CONSTRAINT marketplace_refund_allocations_observation_fk FOREIGN KEY(refund_observation_id) REFERENCES marketplace_refund_observations(id) ON DELETE RESTRICT,
  CONSTRAINT marketplace_refund_allocations_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT marketplace_refund_allocations_item_fk FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT
);

CREATE TABLE seller_recovery_applications (
  id BIGINT PRIMARY KEY AUTO_INCREMENT, seller_financial_adjustment_id BIGINT NOT NULL,
  seller_payout_id BIGINT NOT NULL, order_id BIGINT NOT NULL, designer_id BIGINT NOT NULL,
  amount_cents BIGINT NOT NULL, event_key VARCHAR(190) NOT NULL, application_status ENUM('reserved','applied','released') NOT NULL DEFAULT 'reserved', applied_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY seller_recovery_application_event(event_key),
  UNIQUE KEY seller_recovery_application_adjustment_payout(seller_financial_adjustment_id,seller_payout_id),
  CONSTRAINT seller_recovery_application_adjustment_fk FOREIGN KEY(seller_financial_adjustment_id) REFERENCES seller_financial_adjustments(id) ON DELETE RESTRICT,
  CONSTRAINT seller_recovery_application_payout_fk FOREIGN KEY(seller_payout_id) REFERENCES seller_payouts(id) ON DELETE RESTRICT
);

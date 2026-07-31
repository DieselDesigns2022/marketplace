ALTER TABLE seller_payouts
    ADD COLUMN IF NOT EXISTS admin_resolved_at TIMESTAMP NULL DEFAULT NULL AFTER stripe_transfer_error,
    ADD COLUMN IF NOT EXISTS admin_resolved_by BIGINT NULL DEFAULT NULL AFTER admin_resolved_at,
    ADD COLUMN IF NOT EXISTS admin_resolution_note VARCHAR(500) NULL DEFAULT NULL AFTER admin_resolved_by;

ALTER TABLE stripe_events
    ADD COLUMN IF NOT EXISTS admin_resolved_at TIMESTAMP NULL DEFAULT NULL AFTER processing_error,
    ADD COLUMN IF NOT EXISTS admin_resolved_by BIGINT NULL DEFAULT NULL AFTER admin_resolved_at,
    ADD COLUMN IF NOT EXISTS admin_resolution_note VARCHAR(500) NULL DEFAULT NULL AFTER admin_resolved_by;

CREATE INDEX IF NOT EXISTS seller_payouts_resolution_phase106 ON seller_payouts (admin_resolved_at);
CREATE INDEX IF NOT EXISTS stripe_events_resolution_phase106 ON stripe_events (admin_resolved_at);

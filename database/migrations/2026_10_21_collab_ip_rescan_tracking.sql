ALTER TABLE collab_events
ADD COLUMN IF NOT EXISTS ip_risk_scanned_at DATETIME NULL AFTER ip_content_fingerprint;

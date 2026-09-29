ALTER TABLE collab_file_update_requests
    MODIFY status ENUM(
        'pending',
        'approved',
        'ip_review',
        'applied',
        'denied',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',

    ADD COLUMN IF NOT EXISTS base_sha256 CHAR(64) NULL AFTER collab_file_id,
    ADD COLUMN IF NOT EXISTS candidate_fingerprint CHAR(64) NULL AFTER sha256,
    ADD COLUMN IF NOT EXISTS previous_ip_state VARCHAR(30) NULL AFTER candidate_fingerprint,
    ADD COLUMN IF NOT EXISTS previous_ip_fingerprint CHAR(64) NULL AFTER previous_ip_state,
    ADD COLUMN IF NOT EXISTS previous_storage_path VARCHAR(500) NULL AFTER previous_ip_fingerprint,
    ADD COLUMN IF NOT EXISTS applied_at TIMESTAMP NULL AFTER reviewed_at,
    ADD COLUMN IF NOT EXISTS buyer_notified_at TIMESTAMP NULL AFTER applied_at;

UPDATE collab_file_update_requests r
JOIN collab_files f ON f.id=r.collab_file_id
SET r.base_sha256=f.sha256
WHERE r.base_sha256 IS NULL;

ALTER TABLE collab_file_update_requests
    MODIFY base_sha256 CHAR(64) NOT NULL;

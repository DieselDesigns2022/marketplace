CREATE TABLE IF NOT EXISTS collab_file_update_requests (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,

    collab_id BIGINT NOT NULL,
    collab_file_id BIGINT NOT NULL,
    designer_id BIGINT NOT NULL,

    reason VARCHAR(1000) NULL,

    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(190) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,

    status ENUM(
        'pending',
        'approved',
        'denied',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',

    reviewed_by_designer_id BIGINT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    KEY collab_update_request_collab_status(collab_id,status),
    KEY collab_update_request_file(collab_file_id),
    KEY collab_update_request_designer(designer_id),

    CONSTRAINT collab_update_request_collab_fk
        FOREIGN KEY(collab_id)
        REFERENCES collab_events(id)
        ON DELETE RESTRICT,

    CONSTRAINT collab_update_request_file_fk
        FOREIGN KEY(collab_file_id)
        REFERENCES collab_files(id)
        ON DELETE RESTRICT,

    CONSTRAINT collab_update_request_designer_fk
        FOREIGN KEY(designer_id)
        REFERENCES designers(id)
        ON DELETE RESTRICT,

    CONSTRAINT collab_update_request_reviewer_fk
        FOREIGN KEY(reviewed_by_designer_id)
        REFERENCES designers(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

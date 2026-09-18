ALTER TABLE users
    ADD COLUMN merged_into_user_id BIGINT NULL AFTER updated_at,
    ADD COLUMN merged_at TIMESTAMP NULL AFTER merged_into_user_id,
    ADD KEY users_merged_into_idx (merged_into_user_id),
    ADD CONSTRAINT users_merged_into_fk FOREIGN KEY (merged_into_user_id) REFERENCES users(id) ON DELETE RESTRICT;

CREATE TABLE account_merge_audits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    source_user_id BIGINT NOT NULL,
    target_user_id BIGINT NOT NULL,
    designer_id BIGINT NOT NULL,
    source_seller_email VARCHAR(190) NOT NULL,
    previous_admin_email VARCHAR(190) NOT NULL,
    final_canonical_email VARCHAR(190) NOT NULL,
    acting_admin_user_id BIGINT NOT NULL,
    counts_summary JSON NOT NULL,
    reconciliation_summary JSON NOT NULL,
    source_disabled TINYINT(1) NOT NULL,
    password_reset_confirmed TINYINT(1) NOT NULL,
    merged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY account_merge_source_target_unique (source_user_id,target_user_id),
    KEY account_merge_target_idx (target_user_id,merged_at),
    CONSTRAINT account_merge_source_fk FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT account_merge_target_fk FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT account_merge_designer_fk FOREIGN KEY (designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
    CONSTRAINT account_merge_actor_fk FOREIGN KEY (acting_admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_access_profiles (
    user_id BIGINT PRIMARY KEY,
    full_access TINYINT(1) NOT NULL DEFAULT 0,
    granted_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT admin_access_profile_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT admin_access_profile_granter_fk FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_permission_grants (
    user_id BIGINT NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    granted_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,permission_key),
    CONSTRAINT admin_permission_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT admin_permission_granter_fk FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_permission_audits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    admin_user_id BIGINT NOT NULL,
    permission_key VARCHAR(100) NULL,
    action ENUM('profile_full_access_enabled','profile_full_access_disabled','permission_granted','permission_revoked') NOT NULL,
    acting_admin_user_id BIGINT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY admin_permission_audit_admin_idx (admin_user_id,created_at),
    CONSTRAINT admin_permission_audit_admin_fk FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT admin_permission_audit_actor_fk FOREIGN KEY (acting_admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

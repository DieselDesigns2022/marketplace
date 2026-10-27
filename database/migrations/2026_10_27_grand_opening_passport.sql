CREATE TABLE grand_opening_passport_stamps (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    stamp_key VARCHAR(64) NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY grand_opening_passport_stamp_unique (user_id,stamp_key),
    KEY grand_opening_passport_stamp_user_idx (user_id,completed_at),
    CONSTRAINT grand_opening_passport_stamp_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grand_opening_store_visits (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    designer_id BIGINT NOT NULL,
    first_visited_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY grand_opening_store_visit_unique (user_id,designer_id),
    KEY grand_opening_store_visit_user_idx (user_id,first_visited_at),
    KEY grand_opening_store_visit_designer_idx (designer_id),
    CONSTRAINT grand_opening_store_visit_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT grand_opening_store_visit_designer_fk
        FOREIGN KEY (designer_id) REFERENCES designers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE grand_opening_giveaway_entries
    MODIFY storefront_designer_id BIGINT NULL,
    MODIFY source_type ENUM('prize_wheel','passport')
        NOT NULL DEFAULT 'prize_wheel',
    MODIFY source_spin_id BIGINT NULL,
    ADD COLUMN source_key VARCHAR(190) NULL AFTER source_spin_id,
    ADD UNIQUE KEY grand_opening_entry_source_unique
        (campaign_key,user_id,source_type,source_key);

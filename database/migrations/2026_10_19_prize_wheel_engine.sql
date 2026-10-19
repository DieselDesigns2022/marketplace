ALTER TABLE prize_wheel_settings
    ADD COLUMN IF NOT EXISTS campaign_key
        VARCHAR(64) NOT NULL DEFAULT 'grand-opening-2026',
    ADD COLUMN IF NOT EXISTS spins_per_user
        SMALLINT UNSIGNED NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS prize_wheel_spins (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    campaign_key VARCHAR(64) NOT NULL,
    user_id BIGINT NOT NULL,
    storefront_designer_id BIGINT NOT NULL,
    prize_donation_id BIGINT NOT NULL,
    prize_assignment_id BIGINT NOT NULL,
    spin_number SMALLINT UNSIGNED NOT NULL,
    result_title VARCHAR(190) NOT NULL,
    result_preview_path VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY prize_wheel_spin_user_unique
        (campaign_key,user_id,spin_number),

    UNIQUE KEY prize_wheel_spin_assignment_unique
        (prize_assignment_id),

    KEY prize_wheel_spin_campaign_idx
        (campaign_key,created_at),

    KEY prize_wheel_spin_store_idx
        (storefront_designer_id,created_at),

    CONSTRAINT prize_wheel_spin_user_fk
        FOREIGN KEY(user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_wheel_spin_store_fk
        FOREIGN KEY(storefront_designer_id)
        REFERENCES designers(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_wheel_spin_donation_fk
        FOREIGN KEY(prize_donation_id)
        REFERENCES prize_donations(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_wheel_spin_assignment_fk
        FOREIGN KEY(prize_assignment_id)
        REFERENCES prize_assignments(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

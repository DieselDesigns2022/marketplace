CREATE TABLE IF NOT EXISTS prize_donations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    designer_id BIGINT NOT NULL,

    prize_type ENUM(
        'finished_product',
        'bundle',
        'custom_design',
        'store_credit',
        'exclusive',
        'service',
        'other'
    ) NOT NULL,

    title VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,

    approximate_retail_value DECIMAL(12,2) NULL,

    quantity_donated INT UNSIGNED NOT NULL,
    quantity_remaining INT UNSIGNED NOT NULL,

    fulfillment_instructions TEXT NOT NULL,
    restrictions TEXT NULL,

    usage_designations VARCHAR(255) NOT NULL,

    seller_authorized TINYINT(1) NOT NULL DEFAULT 0,

    status ENUM(
        'submitted',
        'approved',
        'available',
        'assigned',
        'awarded',
        'fulfilled',
        'cancelled'
    ) NOT NULL DEFAULT 'submitted',

    approved_by_admin_id BIGINT NULL,
    approved_at DATETIME NULL,

    cancelled_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    KEY prize_donations_designer_status_idx
        (designer_id,status),

    KEY prize_donations_status_created_idx
        (status,created_at),

    CONSTRAINT prize_donations_designer_fk
        FOREIGN KEY (designer_id)
        REFERENCES designers(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_donations_approved_admin_fk
        FOREIGN KEY (approved_by_admin_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_donations_quantity_chk
        CHECK (
            quantity_donated >= 1
            AND quantity_remaining <= quantity_donated
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS prize_assignments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,

    prize_donation_id BIGINT NOT NULL,

    quantity INT UNSIGNED NOT NULL DEFAULT 1,

    event_type ENUM(
        'prize_wheel',
        'giveaway',
        'social_game',
        'battle',
        'other'
    ) NOT NULL,

    event_reference VARCHAR(190) NULL,

    idempotency_key VARCHAR(190) NOT NULL,

    status ENUM(
        'assigned',
        'awarded',
        'fulfilled',
        'cancelled'
    ) NOT NULL DEFAULT 'assigned',

    winner_user_id BIGINT NULL,
    winner_name VARCHAR(190) NULL,
    winner_email VARCHAR(190) NULL,

    assigned_by_admin_id BIGINT NOT NULL,
    awarded_by_admin_id BIGINT NULL,
    fulfilled_by_admin_id BIGINT NULL,

    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    awarded_at DATETIME NULL,
    fulfilled_at DATETIME NULL,
    cancelled_at DATETIME NULL,

    fulfillment_notes TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY prize_assignments_idempotency_uq
        (idempotency_key),

    KEY prize_assignments_donation_status_idx
        (prize_donation_id,status),

    KEY prize_assignments_winner_idx
        (winner_user_id,status),

    KEY prize_assignments_event_idx
        (event_type,event_reference),

    CONSTRAINT prize_assignments_donation_fk
        FOREIGN KEY (prize_donation_id)
        REFERENCES prize_donations(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_assignments_winner_fk
        FOREIGN KEY (winner_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_assignments_assigned_admin_fk
        FOREIGN KEY (assigned_by_admin_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_assignments_awarded_admin_fk
        FOREIGN KEY (awarded_by_admin_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_assignments_fulfilled_admin_fk
        FOREIGN KEY (fulfilled_by_admin_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_assignments_quantity_chk
        CHECK (quantity >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

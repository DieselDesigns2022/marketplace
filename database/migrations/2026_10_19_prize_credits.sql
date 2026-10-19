ALTER TABLE designers
    ADD COLUMN IF NOT EXISTS prize_credit_mode
        ENUM(
            'none',
            'full',
            'fixed_5',
            'fixed_10',
            'percent_25',
            'percent_50'
        )
        NOT NULL DEFAULT 'none';

CREATE TABLE IF NOT EXISTS prize_credit_grants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    original_amount DECIMAL(12,2) NOT NULL,
    remaining_amount DECIMAL(12,2) NOT NULL,
    reserved_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expires_on DATE NULL,
    source_type VARCHAR(80) NOT NULL DEFAULT 'admin',
    source_reference VARCHAR(190) NULL,
    admin_user_id BIGINT NULL,
    description VARCHAR(500) NULL,
    status ENUM('active','exhausted','revoked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    KEY prize_credit_grants_user_active_idx
        (user_id,status,expires_on),

    CONSTRAINT prize_credit_grants_user_fk
        FOREIGN KEY(user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_grants_admin_fk
        FOREIGN KEY(admin_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_grants_amount_chk
        CHECK(
            original_amount > 0
            AND remaining_amount >= 0
            AND reserved_amount >= 0
            AND reserved_amount <= remaining_amount
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prize_credit_transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    grant_id BIGINT NULL,
    order_id BIGINT NULL,
    order_item_id BIGINT NULL,
    designer_id BIGINT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type ENUM(
        'admin_adjustment',
        'award',
        'reserve',
        'finalize',
        'release',
        'expire',
        'reversal',
        'account_merge_transfer_in',
        'account_merge_transfer_out'
    ) NOT NULL,
    status ENUM(
        'pending',
        'reserved',
        'finalized',
        'released',
        'reversed'
    ) NOT NULL DEFAULT 'finalized',
    idempotency_key VARCHAR(190) NOT NULL,
    admin_user_id BIGINT NULL,
    description VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalized_at TIMESTAMP NULL,

    UNIQUE KEY prize_credit_transactions_idempotency_unique
        (idempotency_key),

    KEY prize_credit_transactions_user_idx
        (user_id,id),

    KEY prize_credit_transactions_order_idx
        (order_id,id),

    CONSTRAINT prize_credit_transactions_user_fk
        FOREIGN KEY(user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_transactions_grant_fk
        FOREIGN KEY(grant_id)
        REFERENCES prize_credit_grants(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_transactions_order_fk
        FOREIGN KEY(order_id)
        REFERENCES orders(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_transactions_order_item_fk
        FOREIGN KEY(order_item_id)
        REFERENCES order_items(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_transactions_designer_fk
        FOREIGN KEY(designer_id)
        REFERENCES designers(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_transactions_admin_fk
        FOREIGN KEY(admin_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prize_credit_transaction_allocations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    transaction_id BIGINT NOT NULL,
    grant_id BIGINT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY prize_credit_transaction_grant_unique
        (transaction_id,grant_id),

    CONSTRAINT prize_credit_allocation_transaction_fk
        FOREIGN KEY(transaction_id)
        REFERENCES prize_credit_transactions(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_allocation_grant_fk
        FOREIGN KEY(grant_id)
        REFERENCES prize_credit_grants(id)
        ON DELETE RESTRICT,

    CONSTRAINT prize_credit_allocation_amount_chk
        CHECK(amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

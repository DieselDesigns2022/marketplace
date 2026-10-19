-- Creative Moth Advertising Credit foundation
-- Seller-only credit used exclusively toward Creative Moth paid promotions.
-- Marketplace buyer credits remain financially separate.

CREATE TABLE IF NOT EXISTS advertising_credits (
    user_id BIGINT NOT NULL,
    total_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reserved_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT advertising_credits_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS advertising_credit_recurring_grants (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    frequency ENUM('monthly') NOT NULL DEFAULT 'monthly',
    day_of_month TINYINT UNSIGNED NOT NULL DEFAULT 1,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    next_run_at DATETIME NOT NULL,
    last_granted_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_admin_id BIGINT NOT NULL,
    description VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY advertising_credit_recurring_due_idx (is_active,next_run_at,id),
    KEY advertising_credit_recurring_user_idx (user_id,id),
    CONSTRAINT advertising_credit_recurring_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT advertising_credit_recurring_admin_fk
        FOREIGN KEY (created_by_admin_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT advertising_credit_recurring_day_chk
        CHECK (day_of_month BETWEEN 1 AND 28),
    CONSTRAINT advertising_credit_recurring_amount_chk
        CHECK (amount > 0)
);

CREATE TABLE IF NOT EXISTS advertising_credit_transactions (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type VARCHAR(40) NOT NULL,
    status ENUM('reserved','finalized','released') NOT NULL,
    idempotency_key VARCHAR(190) NOT NULL,
    ad_id BIGINT NULL,
    recurring_grant_id BIGINT NULL,
    admin_user_id BIGINT NULL,
    related_transaction_id BIGINT NULL,
    description VARCHAR(500) NULL,
    finalized_at DATETIME NULL,
    released_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY advertising_credit_transactions_idempotency_unique (idempotency_key),
    KEY advertising_credit_transactions_user_created_idx (user_id,created_at),
    KEY advertising_credit_transactions_ad_idx (ad_id),
    KEY advertising_credit_transactions_recurring_idx (recurring_grant_id),
    KEY advertising_credit_transactions_related_idx (related_transaction_id),
    CONSTRAINT advertising_credit_transactions_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT advertising_credit_transactions_ad_fk
        FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE RESTRICT,
    CONSTRAINT advertising_credit_transactions_recurring_fk
        FOREIGN KEY (recurring_grant_id)
        REFERENCES advertising_credit_recurring_grants(id) ON DELETE RESTRICT,
    CONSTRAINT advertising_credit_transactions_admin_fk
        FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT advertising_credit_transactions_related_fk
        FOREIGN KEY (related_transaction_id)
        REFERENCES advertising_credit_transactions(id) ON DELETE RESTRICT
);

ALTER TABLE ads
    ADD COLUMN IF NOT EXISTS advertising_credit_reserved_cents INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS advertising_credit_applied_cents INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS stripe_due_cents INT UNSIGNED NULL;

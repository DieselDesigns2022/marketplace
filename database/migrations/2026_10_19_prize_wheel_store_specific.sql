ALTER TABLE prize_wheel_settings
    ADD COLUMN IF NOT EXISTS minimum_purchase_cents
        INT UNSIGNED NOT NULL DEFAULT 500,
    ADD COLUMN IF NOT EXISTS campaign_started_at
        DATETIME NULL;

ALTER TABLE prize_wheel_spins
    ADD COLUMN IF NOT EXISTS qualifying_order_id
        BIGINT NULL AFTER storefront_designer_id;

ALTER TABLE prize_wheel_spins
    ADD UNIQUE KEY prize_wheel_order_campaign_uq
        (campaign_key,qualifying_order_id);

ALTER TABLE prize_wheel_spins
    ADD KEY prize_wheel_qualifying_order_idx
        (qualifying_order_id);

ALTER TABLE prize_wheel_spins
    ADD CONSTRAINT prize_wheel_qualifying_order_fk
        FOREIGN KEY(qualifying_order_id)
        REFERENCES orders(id)
        ON DELETE RESTRICT;

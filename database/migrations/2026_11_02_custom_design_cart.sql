ALTER TABLE cart_items
    MODIFY product_id BIGINT NULL;

ALTER TABLE cart_items
    ADD COLUMN IF NOT EXISTS custom_service_id BIGINT NULL AFTER product_id,
    ADD COLUMN IF NOT EXISTS custom_checkout_token CHAR(48) NULL AFTER custom_service_id;

CREATE INDEX IF NOT EXISTS cart_items_custom_service_idx
    ON cart_items (custom_service_id);

CREATE UNIQUE INDEX IF NOT EXISTS cart_items_custom_checkout_token_unique
    ON cart_items (custom_checkout_token);

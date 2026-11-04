
CREATE TABLE IF NOT EXISTS custom_checkout_drafts (
    token CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    buyer_user_id BIGINT NOT NULL,
    custom_service_id BIGINT NOT NULL,
    payload LONGTEXT NOT NULL CHECK (JSON_VALID(payload)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(token),
    KEY custom_draft_buyer_idx(buyer_user_id),
    KEY custom_draft_service_idx(custom_service_id)
) ENGINE=InnoDB;
CREATE INDEX IF NOT EXISTS custom_orders_order_idx ON custom_orders(order_id);
ALTER TABLE custom_orders DROP INDEX IF EXISTS custom_orders_order_unique;

CREATE TABLE grand_opening_pricing_snapshots (
    order_item_id BIGINT NOT NULL PRIMARY KEY,
    event_id BIGINT NOT NULL,
    pricing_snapshot JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY grand_opening_pricing_event (event_id,order_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

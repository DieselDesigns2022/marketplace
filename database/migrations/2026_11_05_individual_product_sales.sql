-- Individual product sale configuration.
-- Dates are stored in UTC.
-- Regular product prices and existing financial tables are unchanged.

CREATE TABLE IF NOT EXISTS product_sales (
    product_id BIGINT NOT NULL PRIMARY KEY,
    designer_id BIGINT NOT NULL,
    discount_type ENUM('percent','fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    KEY product_sales_designer_idx (designer_id),
    KEY product_sales_schedule_idx
        (is_active,starts_at,ends_at),
    CONSTRAINT product_sales_positive_discount
        CHECK (discount_value > 0),
    CONSTRAINT product_sales_valid_window
        CHECK (ends_at > starts_at)
);

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS store_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured;

CREATE INDEX IF NOT EXISTS idx_products_store_featured
    ON products (designer_id, store_featured, status, created_at);

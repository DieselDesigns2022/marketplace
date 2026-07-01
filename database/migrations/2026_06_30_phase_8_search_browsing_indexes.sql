-- Phase 8 Search & Browsing indexes for approved public product discovery.
CREATE INDEX IF NOT EXISTS idx_products_public_browse ON products (status, is_featured, created_at, id);
CREATE INDEX IF NOT EXISTS idx_products_category_status ON products (category_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_products_designer_status ON products (designer_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_products_price_status ON products (status, price);
CREATE INDEX IF NOT EXISTS idx_products_ai_pod_status ON products (status, ai_disclosure, pod_allowed);
CREATE INDEX IF NOT EXISTS idx_designers_status_slug ON designers (status, store_slug);
CREATE INDEX IF NOT EXISTS idx_product_tags_tag_product ON product_tags (tag_id, product_id);

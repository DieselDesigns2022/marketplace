-- Phase 10.1 product cleanup statuses for seller/admin archive and safe delete flows.
ALTER TABLE products
    MODIFY status ENUM('draft','pending_review','approved','published','rejected','disabled','archived','deleted') DEFAULT 'draft';

CREATE INDEX idx_products_status_designer_updated ON products (status, designer_id, updated_at);

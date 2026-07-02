-- Phase 9: cart, orders, downloads, and manual delivery foundation.
ALTER TABLE products ADD COLUMN IF NOT EXISTS fulfillment_type ENUM('downloadable','google_drive') NOT NULL DEFAULT 'downloadable' AFTER price;
ALTER TABLE products ADD COLUMN IF NOT EXISTS manual_delivery_instructions TEXT NULL AFTER fulfillment_type;

ALTER TABLE cart_items MODIFY license_type VARCHAR(255) DEFAULT 'personal';
ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS session_id VARCHAR(128) NULL AFTER user_id;
ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS quantity INT NOT NULL DEFAULT 1 AFTER license_type;
ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS price_snapshot DECIMAL(10,2) DEFAULT 0.00 AFTER quantity;
ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS license_price_snapshot DECIMAL(10,2) DEFAULT 0.00 AFTER price_snapshot;
ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS total_snapshot DECIMAL(10,2) DEFAULT 0.00 AFTER license_price_snapshot;
ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS fulfillment_type_snapshot VARCHAR(40) NULL AFTER total_snapshot;
-- Keep the existing cart_user_product_license unique key from the Phase 4 cart migration.
CREATE INDEX IF NOT EXISTS cart_session_id_phase9 ON cart_items (session_id);

ALTER TABLE orders MODIFY status ENUM('pending','paid','failed','cancelled','refunded','partially_fulfilled','fulfilled','completed') DEFAULT 'pending';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER subtotal;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(80) NULL AFTER credits_applied;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_discount DECIMAL(10,2) DEFAULT 0.00 AFTER coupon_code;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS fulfillment_status VARCHAR(40) DEFAULT 'pending' AFTER total;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS phase9_foundation_order BOOLEAN DEFAULT 1 AFTER fulfillment_status;

ALTER TABLE order_items MODIFY license_type VARCHAR(255) DEFAULT 'personal';
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS product_title VARCHAR(190) NULL AFTER product_id;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS product_slug VARCHAR(210) NULL AFTER product_title;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS product_image VARCHAR(255) NULL AFTER product_slug;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS seller_name VARCHAR(120) NULL AFTER designer_id;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS fulfillment_type VARCHAR(40) DEFAULT 'downloadable' AFTER license_snapshot;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS delivery_instructions_snapshot TEXT NULL AFTER fulfillment_type;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS buyer_google_drive_email VARCHAR(190) NULL AFTER delivery_instructions_snapshot;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS manual_delivery_status VARCHAR(40) DEFAULT 'not_applicable' AFTER buyer_google_drive_email;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL AFTER manual_delivery_status;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS delivery_notes TEXT NULL AFTER delivered_at;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS purchased_file_version VARCHAR(120) NULL AFTER delivery_notes;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS download_count INT NOT NULL DEFAULT 0 AFTER purchased_file_version;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS download_expires_at TIMESTAMP NULL AFTER download_count;
CREATE INDEX IF NOT EXISTS order_items_fulfillment_phase9 ON order_items (fulfillment_type, manual_delivery_status);

ALTER TABLE downloads ADD COLUMN IF NOT EXISTS order_id BIGINT NULL AFTER user_id;
ALTER TABLE downloads ADD COLUMN IF NOT EXISTS order_item_id BIGINT NULL AFTER order_id;
ALTER TABLE downloads ADD COLUMN IF NOT EXISTS status VARCHAR(40) DEFAULT 'served' AFTER product_file_id;
ALTER TABLE downloads ADD COLUMN IF NOT EXISTS message VARCHAR(255) NULL AFTER status;
CREATE INDEX IF NOT EXISTS downloads_order_item_phase9 ON downloads (order_item_id);

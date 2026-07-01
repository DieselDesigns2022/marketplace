CREATE TABLE license_types
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES
('personal','Personal','Personal use for one buyer. Digital resale, sharing, and redistribution are prohibited.',10,1),
('commercial','Commercial','Commercial use for one buyer under the seller-provided product terms. Digital resale, sharing, and redistribution are prohibited.',20,1),
('pod','POD','Print-on-demand use is allowed under the seller-provided product terms.',30,1),
('wholesale','Wholesale','Wholesale production use is allowed under the seller-provided product terms.',40,1),
('fabric','Fabric','Fabric production use is allowed under the seller-provided product terms.',50,1),
('va','VA','Virtual assistant use is allowed under the seller-provided product terms.',60,1),
('extended-commercial','Extended Commercial','Expanded commercial use is allowed under the seller-provided product terms.',70,1)
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),sort_order=VALUES(sort_order),is_active=VALUES(is_active);

CREATE TABLE product_license_types
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    license_type_id BIGINT NOT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    is_default BOOLEAN DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    custom_name VARCHAR(120),
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE(product_id, license_type_id),
    INDEX idx_product_license_product_enabled (product_id,is_enabled,sort_order),
    INDEX idx_product_license_type (license_type_id)
);

ALTER TABLE cart_items MODIFY license_type VARCHAR(80) NOT NULL DEFAULT 'personal';
-- Phase 4 created this uniqueness constraint as cart_user_product_license.
-- Drop that named key before re-adding the same columns after license_type becomes VARCHAR.
ALTER TABLE cart_items DROP INDEX cart_user_product_license;
ALTER TABLE cart_items ADD UNIQUE KEY cart_items_user_product_license_unique (user_id,product_id,license_type);

ALTER TABLE order_items MODIFY license_type VARCHAR(80) NOT NULL DEFAULT 'personal';
ALTER TABLE order_items ADD COLUMN license_name VARCHAR(120) NULL AFTER license_type;
ALTER TABLE order_items ADD COLUMN license_price DECIMAL(10,2) NULL AFTER license_name;
ALTER TABLE order_items ADD COLUMN license_description TEXT NULL AFTER license_price;
ALTER TABLE order_items ADD COLUMN license_snapshot JSON NULL AFTER license_description;

INSERT INTO product_license_types (product_id,license_type_id,is_enabled,is_default,price,description,sort_order)
SELECT p.id, lt.id, 1, 1, p.price, lt.description, 10
FROM products p JOIN license_types lt ON lt.license_key='personal'
WHERE NOT EXISTS (SELECT 1 FROM product_license_types plt WHERE plt.product_id=p.id);

INSERT INTO product_license_types (product_id,license_type_id,is_enabled,is_default,price,description,sort_order)
SELECT p.id, lt.id, 1, 0, p.price + COALESCE(p.commercial_license_price, 0), lt.description, 20
FROM products p JOIN license_types lt ON lt.license_key='commercial'
WHERE p.commercial_license_enabled=1
  AND NOT EXISTS (SELECT 1 FROM product_license_types plt WHERE plt.product_id=p.id AND plt.license_type_id=lt.id);

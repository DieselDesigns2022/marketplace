-- Phase 10.2 coupons, discounts, usage tracking, and commission-after-discount snapshots.
CREATE TABLE IF NOT EXISTS coupons (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(80) NOT NULL,
    scope ENUM('platform','seller') NOT NULL DEFAULT 'platform',
    seller_id BIGINT NULL,
    discount_type ENUM('percent','fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    starts_at DATE NULL,
    ends_at DATE NULL,
    is_active BOOLEAN DEFAULT 1,
    min_cart_amount DECIMAL(10,2) DEFAULT 0.00,
    usage_limit INT NULL,
    per_user_limit INT NULL,
    usage_count INT DEFAULT 0,
    created_by BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY coupons_code_unique (code),
    KEY coupons_scope_seller_idx (scope,seller_id),
    KEY coupons_active_dates_idx (is_active,starts_at,ends_at)
);

CREATE TABLE IF NOT EXISTS coupon_restrictions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    coupon_id BIGINT NOT NULL,
    restrictable_type ENUM('seller','product','category') NOT NULL,
    restrictable_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY coupon_restrictions_unique (coupon_id,restrictable_type,restrictable_id),
    KEY coupon_restrictions_lookup_idx (restrictable_type,restrictable_id)
);

CREATE TABLE IF NOT EXISTS coupon_usages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    coupon_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    order_id BIGINT NOT NULL,
    code_snapshot VARCHAR(80) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY coupon_usages_order_unique (order_id),
    KEY coupon_usages_coupon_user_idx (coupon_id,user_id)
);

ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_discount DECIMAL(10,2) DEFAULT 0.00 AFTER credits_applied;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_id BIGINT NULL AFTER coupon_discount;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(80) NULL AFTER coupon_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_snapshot JSON NULL AFTER coupon_code;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS coupon_id BIGINT NULL AFTER commission_rate;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(80) NULL AFTER coupon_id;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS coupon_discount DECIMAL(10,2) DEFAULT 0.00 AFTER coupon_code;

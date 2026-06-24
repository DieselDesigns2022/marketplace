CREATE TABLE IF NOT EXISTS cart_items (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  product_id BIGINT NOT NULL,
  license_type ENUM('personal','commercial') DEFAULT 'personal',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY cart_user_product_license (user_id, product_id, license_type),
  KEY cart_user_id (user_id),
  KEY cart_product_id (product_id)
);

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  status ENUM('pending','paid','completed','failed','refunded') DEFAULT 'pending',
  payment_processor VARCHAR(40) DEFAULT 'mock',
  payment_mode VARCHAR(40),
  subtotal DECIMAL(10,2) DEFAULT 0.00,
  credits_applied DECIMAL(10,2) DEFAULT 0.00,
  total DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY orders_user_id (user_id)
);

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT NOT NULL,
  product_id BIGINT NOT NULL,
  designer_id BIGINT NOT NULL,
  license_type ENUM('personal','commercial') DEFAULT 'personal',
  unit_price DECIMAL(10,2) DEFAULT 0.00,
  commercial_license_price DECIMAL(10,2) DEFAULT 0.00,
  total_price DECIMAL(10,2) DEFAULT 0.00,
  commission_rate DECIMAL(5,4) DEFAULT .2000,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY order_items_order_id (order_id),
  KEY order_items_product_id (product_id),
  KEY order_items_designer_id (designer_id)
);

CREATE TABLE IF NOT EXISTS downloads (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  product_id BIGINT NOT NULL,
  product_file_id BIGINT NOT NULL,
  ip_address VARCHAR(80),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY downloads_user_id (user_id),
  KEY downloads_product_file_id (product_file_id)
);

CREATE TABLE IF NOT EXISTS seller_earnings (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT,
  product_id BIGINT,
  designer_id BIGINT,
  buyer_id BIGINT,
  gross_sale DECIMAL(10,2) DEFAULT 0.00,
  marketplace_commission DECIMAL(10,2) DEFAULT 0.00,
  seller_earning DECIMAL(10,2) DEFAULT 0.00,
  status VARCHAR(40),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY seller_earnings_order_id (order_id),
  KEY seller_earnings_designer_id (designer_id)
);

CREATE TABLE IF NOT EXISTS platform_commissions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT,
  product_id BIGINT,
  designer_id BIGINT,
  gross_sale DECIMAL(10,2) DEFAULT 0.00,
  commission_amount DECIMAL(10,2) DEFAULT 0.00,
  referral_commission_placeholder DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY platform_commissions_order_id (order_id)
);

ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_processor VARCHAR(40) DEFAULT 'mock' AFTER status;
ALTER TABLE orders MODIFY status ENUM('pending','paid','completed','failed','refunded') DEFAULT 'pending';

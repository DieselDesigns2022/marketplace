-- Phase 2 Designer Storefront Management fields for existing MariaDB installs.
ALTER TABLE designers
  ADD COLUMN IF NOT EXISTS website_url VARCHAR(255) NULL AFTER social_links,
  ADD COLUMN IF NOT EXISTS sales_count INT DEFAULT 0 AFTER is_featured,
  ADD COLUMN IF NOT EXISTS follower_count INT DEFAULT 0 AFTER sales_count,
  ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0.00 AFTER follower_count,
  ADD COLUMN IF NOT EXISTS seo_title VARCHAR(70) NULL AFTER average_rating,
  ADD COLUMN IF NOT EXISTS seo_description VARCHAR(170) NULL AFTER seo_title;

CREATE TABLE IF NOT EXISTS follows (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  designer_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_follow (user_id, designer_id)
);

CREATE TABLE users
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('buyer','designer','admin') DEFAULT 'buyer',
    status ENUM('active','disabled') DEFAULT 'active',
    referral_code VARCHAR(40),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE designer_applications
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    display_name VARCHAR(120),
    desired_slug VARCHAR(140),
    bio TEXT,
    portfolio_url VARCHAR(255),
    social_links TEXT,
    design_types TEXT,
    uses_ai VARCHAR(80),
    agreement BOOLEAN DEFAULT 0,
    status ENUM('pending','approved','denied') DEFAULT 'pending',
    denial_reason TEXT,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE designers
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNIQUE NOT NULL,
    display_name VARCHAR(120),
    store_slug VARCHAR(140) UNIQUE,
    bio TEXT,
    banner_path VARCHAR(255),
    avatar_path VARCHAR(255),
    social_links TEXT,
    website_url VARCHAR(255),
    announcement TEXT,
    status ENUM('approved','disabled') DEFAULT 'approved',
    creator_rank ENUM('Bronze','Silver','Gold','Platinum','Legend') DEFAULT 'Bronze',
    rank_override BOOLEAN DEFAULT 0,
    is_featured BOOLEAN DEFAULT 0,
    sales_count INT DEFAULT 0,
    follower_count INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    seo_title VARCHAR(70),
    seo_description VARCHAR(170),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE categories
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(120),
    slug VARCHAR(140) UNIQUE,
    description TEXT,
    image_path VARCHAR(255),
    is_active BOOLEAN DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE products
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    designer_id BIGINT NOT NULL,
    category_id BIGINT NULL,
    title VARCHAR(190),
    slug VARCHAR(210) UNIQUE,
    short_description TEXT,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0,
    tags_text TEXT,
    file_types VARCHAR(255),
    commercial_license_enabled BOOLEAN DEFAULT 0,
    commercial_license_price DECIMAL(10,2) DEFAULT 0.00,
    pod_allowed BOOLEAN DEFAULT 0,
    digital_resale_prohibited BOOLEAN DEFAULT 1,
    ai_disclosure ENUM('No AI Used','AI Assisted','AI Generated') NOT NULL,
    seo_title VARCHAR(70),
    seo_description VARCHAR(170),
    status ENUM('draft','pending_review','approved','rejected','disabled') DEFAULT 'draft',
    rejection_reason TEXT,
    is_featured BOOLEAN DEFAULT 0,
    sales_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Phase 8.5 licenses are included permissions/use-case options, not paid add-ons.
-- Product price is the only buyer-facing price; Personal is always included.
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
('extended-commercial','Extended Commercial','Expanded commercial use is allowed under the seller-provided product terms.',70,1);

-- price is retained for compatibility and should remain 0.00 for included licenses.
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

CREATE TABLE product_images
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    image_path VARCHAR(255),
    alt_text VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE product_files
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    storage_path VARCHAR(255),
    original_name VARCHAR(255),
    file_size BIGINT,
    mime_type VARCHAR(120),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE tags
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(80),
    slug VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE product_tags
(
    product_id BIGINT NOT NULL,
    tag_id BIGINT NOT NULL,
    PRIMARY KEY(product_id,tag_id)
);

-- cart_items.license_type may store a normalized comma-separated selected license key list.
CREATE TABLE cart_items
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    license_type VARCHAR(80) NOT NULL DEFAULT 'personal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY cart_items_user_product_license_unique (user_id,product_id,license_type)
);

CREATE TABLE orders
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    status ENUM('pending','paid','completed','failed','refunded') DEFAULT 'pending',
    payment_processor VARCHAR(40) DEFAULT 'mock',
    payment_mode VARCHAR(40),
    subtotal DECIMAL(10,2),
    credits_applied DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- order_items retain selected included license snapshots; license_price should be 0.00.
CREATE TABLE order_items
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    designer_id BIGINT NOT NULL,
    license_type VARCHAR(80) NOT NULL DEFAULT 'personal',
    license_name VARCHAR(120),
    license_price DECIMAL(10,2),
    license_description TEXT,
    license_snapshot JSON,
    unit_price DECIMAL(10,2),
    commercial_license_price DECIMAL(10,2),
    total_price DECIMAL(10,2),
    commission_rate DECIMAL(5,4) DEFAULT .2000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE downloads
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    product_file_id BIGINT NOT NULL,
    ip_address VARCHAR(80),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE wishlists
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE(user_id,product_id)
);

CREATE TABLE follows
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    designer_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE(user_id,designer_id)
);

CREATE TABLE reviews
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    product_id BIGINT,
    designer_id BIGINT,
    rating TINYINT,
    body TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE referrals
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    referrer_user_id BIGINT,
    referred_user_id BIGINT,
    referred_designer_id BIGINT,
    referral_type ENUM('buyer','designer'),
    status ENUM('pending','approved','eligible') DEFAULT 'pending',
    sales_count INT DEFAULT 0,
    reward_status ENUM('pending','active','inactive') DEFAULT 'pending',
    estimated_earnings DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE creator_rank_history
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    designer_id BIGINT,
    old_rank VARCHAR(40),
    new_rank VARCHAR(40),
    changed_by BIGINT,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE marketplace_credits
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNIQUE,
    balance DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE credit_transactions
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    amount DECIMAL(10,2),
    type VARCHAR(40),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE seller_earnings
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT,
    product_id BIGINT,
    designer_id BIGINT,
    buyer_id BIGINT,
    gross_sale DECIMAL(10,2),
    marketplace_commission DECIMAL(10,2),
    seller_earning DECIMAL(10,2),
    status VARCHAR(40),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE platform_commissions
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT,
    product_id BIGINT,
    designer_id BIGINT,
    gross_sale DECIMAL(10,2),
    commission_amount DECIMAL(10,2),
    referral_commission_placeholder DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE ads
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT,
    designer_id BIGINT,
    placement VARCHAR(80),
    start_date DATE,
    end_date DATE,
    status ENUM('draft','active','paused','ended') DEFAULT 'draft',
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE homepage_features
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    feature_type ENUM('product','designer','category'),
    feature_id BIGINT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE admin_logs
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    admin_user_id BIGINT,
    action VARCHAR(120),
    entity_type VARCHAR(80),
    entity_id BIGINT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO categories (name,slug,description) VALUES ('SVG Cut Files','svg-cut-files','Layered files for cutting machines'),('Fonts','fonts','Display and script fonts'),('Canva Templates','canva-templates','Editable templates for creators');

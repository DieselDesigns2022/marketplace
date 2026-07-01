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
    facebook_url VARCHAR(255),
    instagram_url VARCHAR(255),
    tiktok_url VARCHAR(255),
    pinterest_url VARCHAR(255),
    etsy_url VARCHAR(255),
    shopify_url VARCHAR(255),
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

-- Phase 8.5 licenses support Personal as included/free plus seller-enabled add-on permissions that may be free ($0.00) or paid.
-- Buyers see product base price plus selected paid add-on license prices; Personal is always included/free.
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
('personal','Personal','The Personal License is for personal use only. This license allows the buyer to use the design for themselves, gifts, personal projects, and non-profit use where no money is being made.

This license allows you to use the purchased file for personal projects only. You may create items for yourself or as gifts, but you may not sell, trade, donate for promotion, or profit from any item made with this file.

You may not share, upload, transfer, copy, resell, modify for resale, or distribute the digital file in any way. The file must remain private and under your control at all times.

This license does not allow commercial use, print-on-demand use, transfer sales, fabric production, wholesale production, VA access, or digital resale.',10,1),
('basic','Basic','The Basic License allows small business use for finished physical products only. This license is for buyers who personally make and sell finished physical products using their own equipment.

This license allows you to create and sell finished physical products using the purchased design.

Examples include shirts, tumblers, mugs, signs, decals applied to finished items, tote bags, ornaments, keychains, and similar completed products.

You must personally create the finished product yourself using equipment you personally own or directly operate.

This license does not allow you to sell production-ready items such as DTF transfers, sublimation transfers, screen prints, UV DTF decals, waterslides, vinyl transfers, sticker sheets, fabric, tattoos, or similar items.

You may not upload the file to any third-party printing service, print-on-demand platform, manufacturer, gang sheet website, shared drive, or outside production company.

You may not send the file to another person or business to produce items for you.

You may not sell, share, transfer, upload, or distribute the digital file.

This license does not allow POD use, wholesale production, extended production, fabric production, VA access, or digital resale.',20,1),
('commercial','Commercial','The Commercial License allows buyers to print and sell production-based physical products such as DTF transfers, stickers, sublimation transfers, screen prints, UV decals, and similar products — but only when they are produced using equipment the buyer personally owns.

This license allows you to create and sell physical production items using the purchased design, as long as you personally produce them yourself using equipment you own or directly operate.

Examples include DTF transfers, sublimation transfers, screen prints, stickers, waterslides, UV DTF decals, vinyl transfers, temporary tattoos, decals, and similar physical printed products.

You may not upload the file to any third-party printing service, gang sheet website, print shop, POD platform, manufacturer, shared production site, marketplace production service, or outside production company.

You may not send the file to another person or business to print, cut, press, manufacture, or produce items for you.

All production must be done by you, using your own equipment.

This license does not allow digital resale, file sharing, outsourcing, POD use, fabric production, VA access, reseller rights, or claiming the design as your own.

Files must remain private and under your control at all times.',30,1),
('pod','POD','The POD License allows the buyer to use the design with print-on-demand services such as Printify, Printful, Gelato, or similar production platforms.

This license allows you to upload the design to an approved print-on-demand platform for the purpose of selling finished physical products only.

You may sell POD products such as shirts, mugs, tumblers, bags, pillows, wall art, and similar finished physical items.

The file may only be uploaded to the POD platform needed to fulfill your own products. You are responsible for making sure the file stays private, protected, and unavailable to customers or other sellers.

You may not upload the file to public marketplaces as a digital download, editable file, template, clipart, PNG, SVG, sublimation file, transfer file, or any other downloadable product.

You may not share the file with another person, VA, designer, shop, team member, manufacturer, or business unless the correct additional license has been purchased.

This license does not allow transfer sales, fabric production, wholesale production, digital resale, or claiming ownership of the design.',40,1),
('wholesale','Wholesale','The Wholesale License is required when producing more than the standard allowed amount of finished physical products using a single design. This license is for larger production runs, boutique orders, bulk orders, and wholesale finished product sales.

This license allows you to produce and sell larger quantities of finished physical products using the purchased design.

This license is intended for larger production runs of finished physical products such as shirts, tumblers, mugs, bags, signs, decals, or similar completed items.

You may sell finished physical products retail or wholesale to boutiques, shops, events, or customers.

This license does not allow you to sell the digital file, transfer the file, share the file, upload the file for public access, or allow another business to use the file.

This license does not automatically allow POD, fabric production, transfer sales, VA access, digital resale, or reseller rights unless those license types are also purchased or specifically included.

If you plan to produce or sell 1,000 or more physical products of a single design, an Extended License is required.

All product images and mockups using the design must be visibly watermarked.',50,1),
('fabric-overseas','Fabric — Allows Overseas Printing','The Fabric License with Overseas Printing allows the buyer to use the design for fabric, textiles, and sewn-product production, including production through overseas fabric printers or manufacturers.

This license allows you to use the design for fabric or textile production.

This includes custom fabric, printed fabric, bows, blankets, clothing, handmade sewn goods, accessories, bags, baby items, and similar textile-based products.

You may send the file to an overseas fabric printer, textile printer, or manufacturer only for the purpose of producing your own licensed fabric or finished textile products.

The printer or manufacturer may not sell, reuse, share, distribute, display, or make the design available to any other customer or business.

You are responsible for making sure the production company protects the file and does not reuse or distribute it.

You may sell finished fabric-based products created with the licensed design.

You may not sell or distribute the digital file itself. You may not list the design as a downloadable seamless file, PNG, SVG, pattern file, or digital fabric design.

This license does not allow POD use, transfer sales, VA access, digital resale, reseller rights, or claiming ownership of the design unless those licenses are also purchased or clearly included.',60,1),
('fabric-no-overseas','Fabric — No Overseas Printing','The Fabric License without Overseas Printing allows the buyer to use the design for fabric, textiles, and sewn-product production, but the file may not be sent to overseas printers, manufacturers, or production companies.

This license allows you to use the design for fabric or textile production.

This includes custom fabric, printed fabric, bows, blankets, clothing, handmade sewn goods, accessories, bags, baby items, and similar textile-based products.

You may sell finished fabric-based products created with the licensed design.

You may only produce fabric yourself or through an approved domestic printer if the listing or designer allows it.

You may not send the file to overseas printers, overseas manufacturers, overseas fabric companies, overseas production partners, or any overseas third-party service.

You may not upload the file to any public fabric printing website, shared production website, marketplace, or platform where the file may be accessed by others.

You may not sell or distribute the digital file itself. You may not list the design as a downloadable seamless file, PNG, SVG, pattern file, or digital fabric design.

This license does not allow POD use, transfer sales, VA access, digital resale, reseller rights, or claiming ownership of the design unless those licenses are also purchased or clearly included.',70,1),
('va','VA','The VA License allows limited file access for a virtual assistant, employee, or approved helper. This license may be used by a business owner who needs help with their own files, or by a VA who purchases the file and license to use for their own clients.

This license allows one approved virtual assistant, employee, or helper to access the purchased file for business-related tasks.

Allowed tasks may include creating listings, mockups, product photos, marketing graphics, customer previews, product setup, or other approved design-related business tasks.

If the business owner purchases the file and VA License, the VA may only use the file for that business owner. The VA may not use the file for their own business, other clients, freebies, bundles, templates, products, or resale.

If the VA personally purchases the file and the correct VA License, the VA may use the file for their own clients.

However, the file must be protected at all times. Clients may only receive watermarked previews or finished flattened images when needed. Clients may not receive, download, access, or keep unwatermarked files.

The VA may not give clients access to the raw file, editable file, transparent PNG, SVG, layered file, Procreate file, stamp file, clipart file, seamless file, or any unwatermarked design file.

The VA may not upload the file to shared drives, team folders, Facebook groups, marketplace listings, public links, or any location where unauthorized people can access it.

The original purchaser remains fully responsible for the file and for anything done with it.

This license does not transfer ownership. It only gives limited usage access under the terms of the license.

If more than one VA, employee, helper, or client-facing assistant needs access, additional VA licensing may be required.',80,1),
('reseller-credit-required','Reseller — Credit Required','The Credit Required Reseller License allows the buyer to resell specific digital products through their own website or business, but they must clearly credit the original designer or business.

This license allows you to resell the licensed file through your own website or business when reseller rights are clearly included with the product purchased.

This may include clipart, lineart, digital files, Procreate stamps, templates, or similar digital products only when the product listing specifically allows reseller use.

Credit is required.

The product listing must clearly credit the original business or designer. Credit must be easy to see, easy to read, and clearly placed in the product listing.

Credit must include a hyperlink to the original designer’s Asset Moth store.

Example credit format: “Original design by [Designer/Business Name] on Asset Moth.”

The credit must be clickable and must link to the original designer’s Asset Moth shop.

This license does not give you ownership of the file.

Reseller rights do not give you the right to allow your own customers to resell, redistribute, share, give away, or claim ownership of the file.

You may not claim the original artwork as your own.

You may not trademark, copyright, register, or use the design as your logo or brand identity.

You may not include the file in design drives, memberships, mega bundles, freebie groups, shared folders, giveaways, or file-sharing communities unless the original listing clearly allows it.

This license only applies to the specific file or product purchased. It does not apply to the seller’s full shop, brand, future products, or other files.',90,1),
('reseller-no-credit-required','Reseller — No Credit Required','The No Credit Required Reseller License allows the buyer to resell specific digital products through their own website or business without giving visible credit to the original designer.

This license allows you to resell the licensed file through your own website or business when no-credit reseller rights are clearly included with the product purchased.

This may include clipart, lineart, digital files, Procreate stamps, templates, or similar digital products only when the product listing specifically allows reseller use.

Credit is not required with this license.

This license does not give you ownership of the file.

Reseller rights do not give you the right to allow your own customers to resell, redistribute, share, give away, or claim ownership of the file.

You may not claim the original artwork as your own.

You may not trademark, copyright, register, or use the design as your logo or brand identity.

You may not include the file in design drives, memberships, mega bundles, freebie groups, shared folders, giveaways, or file-sharing communities unless the original listing clearly allows it.

You may not sell the file in a way that suggests you created the original artwork unless the listing specifically grants that right.

This license only applies to the specific file or product purchased. It does not apply to the seller’s full shop, brand, future products, or other files.',100,1),
('extended-commercial','Extended Commercial','The Extended Commercial License allows the buyer to print and sell 1,000 or more physical products of a single design.

This license allows high-volume production and sale of physical products using the purchased design.

This license is required when producing or selling 1,000 or more physical products using one design.

Examples include large product launches, boutique collections, event inventory, retail collections, bulk business production, or high-volume handmade business use.

This license allows finished physical product sales only unless the listing clearly states otherwise.

You may not sell, share, transfer, upload, or distribute the digital file.

This license does not automatically allow print-on-demand use, fabric production, transfer sales, VA access, overseas production, third-party manufacturing, digital resale, or reseller rights unless those rights are clearly included in the product listing or purchased as separate licenses.

You may not claim the design as your own, trademark the design, copyright the design, or use it as a logo or main brand identity.

All mockups, product photos, customer previews, and promotional images must be visibly watermarked when the design is shown online.',110,1);

-- product_license_types.price stores seller-configured add-on pricing for enabled non-personal licenses; Personal/included permissions should remain 0.00, and buyers may see paid add-on license pricing where applicable.
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
    original_image_path VARCHAR(255),
    watermark_status VARCHAR(40),
    watermark_error VARCHAR(255),
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

-- order_items retain selected license snapshots; license_price is 0.00 for included/free permissions and stores selected paid add-on prices where applicable.
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

CREATE TABLE ip_risk_terms
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    term VARCHAR(190) NOT NULL,
    normalized_term VARCHAR(190) NOT NULL UNIQUE,
    category VARCHAR(60) NOT NULL,
    internal_note TEXT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    created_by_admin_id BIGINT NULL,
    updated_by_admin_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip_risk_terms_category (category),
    INDEX idx_ip_risk_terms_enabled (is_enabled),
    CONSTRAINT fk_ip_risk_terms_created_by FOREIGN KEY (created_by_admin_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ip_risk_terms_updated_by FOREIGN KEY (updated_by_admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE ip_risk_term_aliases
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    ip_risk_term_id BIGINT NOT NULL,
    alias VARCHAR(190) NOT NULL,
    normalized_alias VARCHAR(190) NOT NULL,
    is_enabled BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ip_risk_alias_global (normalized_alias),
    INDEX idx_ip_risk_alias_term (ip_risk_term_id),
    INDEX idx_ip_risk_alias_enabled (is_enabled),
    CONSTRAINT fk_ip_risk_alias_term FOREIGN KEY (ip_risk_term_id) REFERENCES ip_risk_terms(id) ON DELETE RESTRICT
);

CREATE TABLE product_ip_risk_scans
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    seller_id BIGINT NOT NULL,
    content_fingerprint CHAR(64) NOT NULL,
    match_fingerprint CHAR(64) NOT NULL,
    active_match_count INT NOT NULL DEFAULT 0,
    scanned_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_scans_product (product_id),
    INDEX idx_ip_scans_seller (seller_id),
    INDEX idx_ip_scans_product_scanned (product_id, scanned_at),
    CONSTRAINT fk_ip_scans_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ip_scans_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE product_ip_risk_detections
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    scan_id BIGINT NOT NULL,
    ip_risk_term_id BIGINT NOT NULL,
    matched_term VARCHAR(190) NOT NULL,
    matched_alias VARCHAR(190) NULL,
    matched_value_key VARCHAR(190) NOT NULL,
    category VARCHAR(60) NOT NULL,
    source_field ENUM('title','description','tags','seo_title','seo_description','file_name') NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    first_detected_at TIMESTAMP NULL,
    last_detected_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ip_detection_scan_match (scan_id, ip_risk_term_id, matched_value_key, source_field),
    INDEX idx_ip_detection_product_active (product_id, is_active),
    INDEX idx_ip_detection_scan (scan_id),
    INDEX idx_ip_detection_term (ip_risk_term_id),
    INDEX idx_ip_detection_category (category),
    CONSTRAINT fk_ip_detection_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ip_detection_scan FOREIGN KEY (scan_id) REFERENCES product_ip_risk_scans(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ip_detection_term FOREIGN KEY (ip_risk_term_id) REFERENCES ip_risk_terms(id) ON DELETE RESTRICT
);

CREATE TABLE product_ip_risk_states
(
    product_id BIGINT PRIMARY KEY,
    latest_scan_id BIGINT NULL,
    review_status ENUM('clear','pending_review','approved','rejected','archived','published_flagged') NOT NULL DEFAULT 'clear',
    latest_match_fingerprint CHAR(64) NULL,
    admin_note TEXT NULL,
    reviewed_by_admin_id BIGINT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ip_state_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ip_state_scan FOREIGN KEY (latest_scan_id) REFERENCES product_ip_risk_scans(id) ON DELETE SET NULL,
    CONSTRAINT fk_ip_state_reviewed_by FOREIGN KEY (reviewed_by_admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE product_ip_rights_confirmations
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    scan_id BIGINT NOT NULL,
    seller_id BIGINT NOT NULL,
    confirmation_text TEXT NOT NULL,
    confirmed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ip_confirmation (product_id, scan_id, seller_id),
    INDEX idx_ip_confirmation_product (product_id),
    CONSTRAINT fk_ip_confirmation_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ip_confirmation_scan FOREIGN KEY (scan_id) REFERENCES product_ip_risk_scans(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ip_confirmation_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE product_ip_risk_review_history
(
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    scan_id BIGINT NULL,
    previous_review_status VARCHAR(40) NULL,
    new_review_status VARCHAR(40) NOT NULL,
    previous_product_status VARCHAR(40) NULL,
    new_product_status VARCHAR(40) NULL,
    admin_id BIGINT NOT NULL,
    admin_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_history_product (product_id),
    INDEX idx_ip_history_scan (scan_id),
    CONSTRAINT fk_ip_history_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ip_history_scan FOREIGN KEY (scan_id) REFERENCES product_ip_risk_scans(id) ON DELETE SET NULL,
    CONSTRAINT fk_ip_history_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT
);

INSERT INTO ip_risk_terms (term, normalized_term, category, internal_note, is_enabled) VALUES
('Disney', 'disney', 'brand', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Mickey Mouse', 'mickey mouse', 'character', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Marvel', 'marvel', 'franchise', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Star Wars', 'star wars', 'franchise', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Harry Potter', 'harry potter', 'book', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Taylor Swift', 'taylor swift', 'music_artist', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Barbie', 'barbie', 'product_line', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Nike', 'nike', 'brand', 'Advisory starter data for feature testing only; not a complete database.', 1),
('NFL', 'nfl', 'sports_league', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Super Bowl', 'super bowl', 'sports_league', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Coca-Cola', 'coca cola', 'brand', 'Advisory starter data for feature testing only; not a complete database.', 1),
('Minecraft', 'minecraft', 'video_game', 'Advisory starter data for feature testing only; not a complete database.', 1);

INSERT INTO ip_risk_term_aliases (ip_risk_term_id, alias, normalized_alias, is_enabled)
SELECT id, 'Coke', 'coke', 1 FROM ip_risk_terms WHERE normalized_term='coca cola';

-- Rollback order:
-- DROP TABLE product_ip_risk_review_history;
-- DROP TABLE product_ip_rights_confirmations;
-- DROP TABLE product_ip_risk_states;
-- DROP TABLE product_ip_risk_detections;
-- DROP TABLE product_ip_risk_scans;
-- DROP TABLE ip_risk_term_aliases;
-- DROP TABLE ip_risk_terms;

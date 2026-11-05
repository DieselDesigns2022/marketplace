CREATE TABLE ip_audit_findings (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    content_fingerprint CHAR(64) NOT NULL,
    match_fingerprint CHAR(64) NOT NULL,
    matches_json LONGTEXT NOT NULL,
    match_count INT NOT NULL DEFAULT 0,
    review_status ENUM('pending','dismissed','violation')
        NOT NULL DEFAULT 'pending',
    reviewed_by_admin_id BIGINT NULL,
    reviewed_at DATETIME NULL,
    admin_note TEXT NULL,
    last_scanned_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY ip_audit_product_unique (product_id),
    KEY ip_audit_review_status (review_status),
    CONSTRAINT ip_audit_product_fk
        FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT ip_audit_admin_fk
        FOREIGN KEY (reviewed_by_admin_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seller_ip_warnings (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    designer_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    warning_number INT NOT NULL,
    reason TEXT NOT NULL,
    issued_by_admin_id BIGINT NOT NULL,
    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by_admin_id BIGINT NULL,
    resolution_note TEXT NULL,
    UNIQUE KEY seller_ip_product_warning (product_id),
    UNIQUE KEY seller_ip_warning_number (designer_id,warning_number),
    KEY seller_ip_warning_history (designer_id,issued_at),
    CONSTRAINT seller_warning_designer_fk
        FOREIGN KEY (designer_id) REFERENCES designers(id),
    CONSTRAINT seller_warning_product_fk
        FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT seller_warning_admin_fk
        FOREIGN KEY (issued_by_admin_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$

CREATE TRIGGER cm_ip_warning_publication_guard
BEFORE UPDATE ON products
FOR EACH ROW
BEGIN
    IF NEW.status IN ('approved','published','scheduled')
       AND EXISTS (
           SELECT 1 FROM seller_ip_warnings w
           WHERE w.product_id=OLD.id
             AND w.resolved_at IS NULL
       )
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'IP violation requires admin clearance before publication';
    END IF;
END$$

DELIMITER ;

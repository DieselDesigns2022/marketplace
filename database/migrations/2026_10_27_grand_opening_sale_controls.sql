CREATE TABLE grand_opening_events (
 id BIGINT PRIMARY KEY,
 campaign_key VARCHAR(80) NOT NULL UNIQUE,
 starts_at DATETIME NULL,
 ends_at DATETIME NULL,
 is_enabled TINYINT NOT NULL DEFAULT 0,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO grand_opening_events(id,campaign_key)
VALUES(1,'grand-opening-2026');

CREATE TABLE grand_opening_sales (
 event_id BIGINT NOT NULL,
 designer_id BIGINT NOT NULL,
 is_participating TINYINT NOT NULL DEFAULT 0,
 discount_basis_points INT NOT NULL DEFAULT 1000,
 product_scope VARCHAR(20) NOT NULL DEFAULT 'shop',
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(event_id,designer_id),
 KEY grand_opening_sales_participation(event_id,is_participating,designer_id),
 CHECK(discount_basis_points BETWEEN 1 AND 9999),
 CHECK(product_scope IN ('shop','selected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE grand_opening_sale_products (
 event_id BIGINT NOT NULL,
 designer_id BIGINT NOT NULL,
 product_id BIGINT NOT NULL,
 PRIMARY KEY(event_id,designer_id,product_id),
 KEY grand_opening_sale_product_lookup(event_id,product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE grand_opening_sale_audits (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 event_id BIGINT NOT NULL,
 designer_id BIGINT NULL,
 actor_user_id BIGINT NOT NULL,
 action VARCHAR(40) NOT NULL,
 before_snapshot JSON NOT NULL,
 after_snapshot JSON NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY grand_opening_sale_audit_subject(event_id,designer_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

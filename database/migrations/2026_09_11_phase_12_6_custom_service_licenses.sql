CREATE TABLE IF NOT EXISTS custom_service_license_options (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    custom_service_id BIGINT NOT NULL,
    license_type_id BIGINT NULL,
    license_key VARCHAR(80) NOT NULL,
    custom_name VARCHAR(120) NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY custom_service_license_unique(custom_service_id,license_key),
    KEY custom_service_license_service_idx(custom_service_id,sort_order),
    KEY custom_service_license_type_idx(license_type_id),
    CONSTRAINT custom_service_license_service_fk
        FOREIGN KEY(custom_service_id)
        REFERENCES custom_design_services(id)
        ON DELETE CASCADE,
    CONSTRAINT custom_service_license_type_fk
        FOREIGN KEY(license_type_id)
        REFERENCES license_types(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO custom_service_license_options
(custom_service_id,license_type_id,license_key,custom_name,description,price,is_default,sort_order)
SELECT
    s.id,
    lt.id,
    lt.license_key,
    NULL,
    '',
    0.00,
    1,
    lt.sort_order
FROM custom_design_services s
JOIN license_types lt ON lt.license_key='personal' AND lt.is_active=1;

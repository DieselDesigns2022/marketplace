CREATE TABLE store_sections (
    id BIGINT NOT NULL AUTO_INCREMENT,
    designer_id BIGINT NOT NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY store_sections_designer_slug (designer_id, slug),
    UNIQUE KEY store_sections_designer_name (designer_id, name),
    KEY store_sections_designer_sort (designer_id, sort_order, id),
    CONSTRAINT store_sections_designer_fk
        FOREIGN KEY (designer_id) REFERENCES designers(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE products
    ADD COLUMN store_section_id BIGINT NULL AFTER category_id,
    ADD KEY idx_products_store_section (store_section_id, status, created_at),
    ADD CONSTRAINT products_store_section_fk
        FOREIGN KEY (store_section_id) REFERENCES store_sections(id)
        ON DELETE SET NULL;

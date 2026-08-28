ALTER TABLE product_import_items MODIFY result_status ENUM('ready','queued','processing','invalid','duplicate','imported','failed','skipped') NOT NULL;

CREATE TABLE IF NOT EXISTS product_import_images (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, import_item_id BIGINT NOT NULL, product_image_id BIGINT NULL,
 source_url TEXT NOT NULL, sort_order INT UNSIGNED NOT NULL, status ENUM('queued','processing','imported','failed') NOT NULL DEFAULT 'queued',
 claim_token CHAR(40) NULL, claimed_at TIMESTAMP NULL, warning VARCHAR(1000) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY product_import_image_item_sort_unique(import_item_id,sort_order),
 KEY product_import_images_claim_idx(status,claimed_at,id),
 CONSTRAINT product_import_image_item_fk FOREIGN KEY(import_item_id) REFERENCES product_import_items(id) ON DELETE CASCADE,
 CONSTRAINT product_import_image_product_fk FOREIGN KEY(product_image_id) REFERENCES product_images(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

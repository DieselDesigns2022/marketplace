ALTER TABLE product_batch_items ADD COLUMN submission_errors JSON NULL AFTER validation_errors;

ALTER TABLE products MODIFY ai_disclosure ENUM('No AI Used','AI Assisted','AI Generated') NULL;

CREATE TABLE product_import_runs (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, designer_id BIGINT NOT NULL, batch_id BIGINT NULL,
 source_platform ENUM('shopify','etsy','payhip','square','squarespace','wix','weebly','woocommerce') NOT NULL,
 display_filename VARCHAR(190) NOT NULL, stored_path VARCHAR(500) NULL,
 total_detected INT UNSIGNED NOT NULL DEFAULT 0, selected_count INT UNSIGNED NOT NULL DEFAULT 0,
 imported_count INT UNSIGNED NOT NULL DEFAULT 0, duplicate_count INT UNSIGNED NOT NULL DEFAULT 0,
 invalid_count INT UNSIGNED NOT NULL DEFAULT 0, skipped_count INT UNSIGNED NOT NULL DEFAULT 0, failed_count INT UNSIGNED NOT NULL DEFAULT 0,
 status ENUM('preview','processing','completed','partial','failed') NOT NULL DEFAULT 'preview',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, completed_at TIMESTAMP NULL,
 KEY product_import_runs_owner_idx(designer_id,created_at), KEY product_import_runs_batch_idx(batch_id),
 CONSTRAINT product_import_runs_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE CASCADE,
 CONSTRAINT product_import_runs_batch_fk FOREIGN KEY(batch_id) REFERENCES product_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_import_items (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, import_run_id BIGINT NOT NULL, product_id BIGINT NULL,
 duplicate_product_id BIGINT NULL, source_key VARCHAR(190) NOT NULL, source_fingerprint CHAR(64) NOT NULL, source_sku VARCHAR(190) NULL,
 source_title VARCHAR(190) NOT NULL, normalized_data JSON NOT NULL,
 result_status ENUM('ready','queued','processing','invalid','duplicate','imported','failed','skipped') NOT NULL,
 warnings JSON NULL, error_message VARCHAR(1000) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY product_import_item_run_key_unique(import_run_id,source_fingerprint),
 KEY product_import_items_result_idx(import_run_id,result_status,id), KEY product_import_items_product_idx(product_id),
 CONSTRAINT product_import_items_run_fk FOREIGN KEY(import_run_id) REFERENCES product_import_runs(id) ON DELETE CASCADE,
 CONSTRAINT product_import_items_product_fk FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 CONSTRAINT product_import_items_duplicate_fk FOREIGN KEY(duplicate_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_import_sources (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, designer_id BIGINT NOT NULL, product_id BIGINT NOT NULL,
 import_item_id BIGINT NOT NULL, source_platform ENUM('shopify','etsy','payhip','square','squarespace','wix','weebly','woocommerce') NOT NULL,
 source_key VARCHAR(190) NOT NULL, source_fingerprint CHAR(64) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY product_import_source_owner_unique(designer_id,source_platform,source_fingerprint),
 UNIQUE KEY product_import_source_item_unique(import_item_id), KEY product_import_source_product_idx(product_id),
 CONSTRAINT product_import_source_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE CASCADE,
 CONSTRAINT product_import_source_product_fk FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 CONSTRAINT product_import_source_item_fk FOREIGN KEY(import_item_id) REFERENCES product_import_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_import_requirements (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, product_id BIGINT NOT NULL, requirement_key VARCHAR(40) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, cleared_at TIMESTAMP NULL,
 UNIQUE KEY product_import_requirement_unique(product_id,requirement_key), KEY product_import_requirement_open_idx(product_id,cleared_at),
 CONSTRAINT product_import_requirement_product_fk FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_import_images (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, import_item_id BIGINT NOT NULL, product_image_id BIGINT NULL,
 source_url TEXT NOT NULL, sort_order INT UNSIGNED NOT NULL, status ENUM('queued','processing','imported','failed') NOT NULL DEFAULT 'queued',
 claim_token CHAR(40) NULL, claimed_at TIMESTAMP NULL, warning VARCHAR(1000) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY product_import_image_item_sort_unique(import_item_id,sort_order), KEY product_import_images_claim_idx(status,claimed_at,id),
 CONSTRAINT product_import_image_item_fk FOREIGN KEY(import_item_id) REFERENCES product_import_items(id) ON DELETE CASCADE,
 CONSTRAINT product_import_image_product_fk FOREIGN KEY(product_image_id) REFERENCES product_images(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

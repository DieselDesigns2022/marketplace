CREATE TABLE IF NOT EXISTS product_batches (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, designer_id BIGINT NOT NULL, name VARCHAR(190) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY product_batches_designer_idx(designer_id,updated_at), CONSTRAINT product_batches_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS product_batch_items (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, batch_id BIGINT NOT NULL, product_id BIGINT NOT NULL, sort_order INT NOT NULL DEFAULT 0,
 validation_errors JSON NULL, submitted_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY product_batch_product_unique(product_id), KEY product_batch_order_idx(batch_id,sort_order,id),
 CONSTRAINT product_batch_items_batch_fk FOREIGN KEY(batch_id) REFERENCES product_batches(id) ON DELETE CASCADE,
 CONSTRAINT product_batch_items_product_fk FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

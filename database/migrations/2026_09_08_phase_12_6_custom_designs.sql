-- Phase 12.6: custom services share orders, Stripe, messaging, notifications and protected storage.
CREATE TABLE custom_design_services (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, designer_id BIGINT NOT NULL, slug VARCHAR(220) NOT NULL,
 title VARCHAR(190) NOT NULL, description TEXT NOT NULL, price DECIMAL(10,2) NOT NULL,
 turnaround_days SMALLINT UNSIGNED NOT NULL, included_revisions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 buyer_instructions TEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY custom_services_slug_unique(slug), KEY custom_services_seller_active_idx(designer_id,is_active),
 CONSTRAINT custom_services_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE custom_service_questions (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, custom_service_id BIGINT NOT NULL, question_text VARCHAR(500) NOT NULL,
 is_required TINYINT(1) NOT NULL DEFAULT 0, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT custom_questions_service_fk FOREIGN KEY(custom_service_id) REFERENCES custom_design_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE custom_service_images (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, custom_service_id BIGINT NOT NULL, image_path VARCHAR(500) NOT NULL,
 sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT custom_images_service_fk FOREIGN KEY(custom_service_id) REFERENCES custom_design_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE order_items MODIFY product_id BIGINT NULL, ADD COLUMN custom_service_id BIGINT NULL AFTER product_id,
 ADD KEY order_items_custom_service_idx(custom_service_id), ADD CONSTRAINT order_items_custom_service_fk FOREIGN KEY(custom_service_id) REFERENCES custom_design_services(id) ON DELETE RESTRICT;
ALTER TABLE seller_earnings MODIFY product_id BIGINT NULL;
ALTER TABLE platform_commissions MODIFY product_id BIGINT NULL, ADD COLUMN custom_service_id BIGINT NULL AFTER product_id, ADD KEY platform_commissions_custom_service_idx(custom_service_id), ADD CONSTRAINT platform_commissions_custom_service_fk FOREIGN KEY(custom_service_id) REFERENCES custom_design_services(id) ON DELETE RESTRICT;
CREATE TABLE custom_orders (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, order_id BIGINT NOT NULL, order_item_id BIGINT NOT NULL, custom_service_id BIGINT NOT NULL,
 buyer_user_id BIGINT NOT NULL, designer_id BIGINT NOT NULL,
 status ENUM('new','in_progress','proof_review','revision_requested','completed','cancelled','refunded') NOT NULL DEFAULT 'new',
 service_snapshot JSON NOT NULL, brief_snapshot JSON NOT NULL, agreed_price DECIMAL(10,2) NOT NULL,
 turnaround_days SMALLINT UNSIGNED NOT NULL, included_revisions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 revisions_used SMALLINT UNSIGNED NOT NULL DEFAULT 0, completed_at TIMESTAMP NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY custom_orders_order_unique(order_id), UNIQUE KEY custom_orders_item_unique(order_item_id),
 KEY custom_orders_buyer_idx(buyer_user_id,status), KEY custom_orders_designer_idx(designer_id,status),
 CONSTRAINT custom_orders_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT,
 CONSTRAINT custom_orders_item_fk FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
 CONSTRAINT custom_orders_service_fk FOREIGN KEY(custom_service_id) REFERENCES custom_design_services(id) ON DELETE RESTRICT,
 CONSTRAINT custom_orders_buyer_fk FOREIGN KEY(buyer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT custom_orders_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE custom_order_files (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, custom_order_id BIGINT NOT NULL, uploader_user_id BIGINT NOT NULL,
 file_kind ENUM('reference','proof','final') NOT NULL, original_name VARCHAR(190) NOT NULL, storage_path VARCHAR(500) NOT NULL,
 mime_type VARCHAR(100) NOT NULL, file_size BIGINT UNSIGNED NOT NULL, revision_number SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY custom_files_order_kind_idx(custom_order_id,file_kind),
 CONSTRAINT custom_files_order_fk FOREIGN KEY(custom_order_id) REFERENCES custom_orders(id) ON DELETE RESTRICT,
 CONSTRAINT custom_files_uploader_fk FOREIGN KEY(uploader_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE custom_order_status_history (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, custom_order_id BIGINT NOT NULL, from_status VARCHAR(40) NULL, to_status VARCHAR(40) NOT NULL,
 actor_user_id BIGINT NULL, transition_source ENUM('user','stripe_cancel','stripe_expired','stripe_refund') NOT NULL DEFAULT 'user', system_event_key VARCHAR(100) NULL, note VARCHAR(1000) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY custom_history_order_idx(custom_order_id,created_at), UNIQUE KEY custom_history_system_unique(custom_order_id,system_event_key),
 CONSTRAINT custom_history_order_fk FOREIGN KEY(custom_order_id) REFERENCES custom_orders(id) ON DELETE RESTRICT,
 CONSTRAINT custom_history_actor_fk FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE message_conversations ADD COLUMN custom_order_id BIGINT NULL AFTER order_item_id,
 ADD KEY message_conversations_custom_order_idx(custom_order_id),
 ADD CONSTRAINT message_conversations_custom_order_fk FOREIGN KEY(custom_order_id) REFERENCES custom_orders(id) ON DELETE SET NULL;

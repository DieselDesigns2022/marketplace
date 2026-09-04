-- Phase 12.5 private buyer/seller messaging. Files are stored under storage/protected_uploads/messages.
CREATE TABLE message_conversations (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, buyer_user_id BIGINT NOT NULL, seller_user_id BIGINT NOT NULL, designer_id BIGINT NOT NULL,
 product_id BIGINT NULL, order_id BIGINT NULL, order_item_id BIGINT NULL, context_label VARCHAR(190) NOT NULL, context_key VARCHAR(190) NOT NULL,
 buyer_archived_at TIMESTAMP NULL, seller_archived_at TIMESTAMP NULL, buyer_last_read_message_id BIGINT NULL, seller_last_read_message_id BIGINT NULL,
 last_message_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY message_conversation_context_unique(context_key), KEY message_conversation_buyer_idx(buyer_user_id,last_message_at), KEY message_conversation_seller_idx(seller_user_id,last_message_at),
 CONSTRAINT message_conversation_buyer_fk FOREIGN KEY(buyer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT message_conversation_seller_fk FOREIGN KEY(seller_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT message_conversation_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
 CONSTRAINT message_conversation_product_fk FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 CONSTRAINT message_conversation_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL,
 CONSTRAINT message_conversation_order_item_fk FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE conversation_messages (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, conversation_id BIGINT NOT NULL, sender_user_id BIGINT NOT NULL, body TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY conversation_messages_thread_idx(conversation_id,id), CONSTRAINT conversation_messages_conversation_fk FOREIGN KEY(conversation_id) REFERENCES message_conversations(id) ON DELETE CASCADE,
 CONSTRAINT conversation_messages_sender_fk FOREIGN KEY(sender_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE message_attachments (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, message_id BIGINT NOT NULL, original_name VARCHAR(190) NOT NULL, stored_name VARCHAR(100) NOT NULL, mime_type VARCHAR(40) NOT NULL, byte_size BIGINT UNSIGNED NOT NULL, width INT UNSIGNED NOT NULL, height INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY message_attachment_stored_unique(stored_name), KEY message_attachment_message_idx(message_id), CONSTRAINT message_attachment_message_fk FOREIGN KEY(message_id) REFERENCES conversation_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE message_blocks (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, blocker_user_id BIGINT NOT NULL, blocked_user_id BIGINT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, removed_at TIMESTAMP NULL,
 KEY message_blocks_pair_idx(blocker_user_id,blocked_user_id,removed_at), CONSTRAINT message_blocks_blocker_fk FOREIGN KEY(blocker_user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT message_blocks_blocked_fk FOREIGN KEY(blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE message_reports (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, conversation_id BIGINT NOT NULL, reporter_user_id BIGINT NOT NULL, reason ENUM('abuse','spam','inappropriate','other') NOT NULL, details VARCHAR(1000) NULL,
 status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open', moderator_user_id BIGINT NULL, moderator_notes VARCHAR(1000) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, reviewed_at TIMESTAMP NULL,
 UNIQUE KEY message_report_reporter_unique(conversation_id,reporter_user_id), KEY message_reports_status_idx(status,created_at), CONSTRAINT message_report_conversation_fk FOREIGN KEY(conversation_id) REFERENCES message_conversations(id) ON DELETE CASCADE,
 CONSTRAINT message_report_reporter_fk FOREIGN KEY(reporter_user_id) REFERENCES users(id) ON DELETE RESTRICT, CONSTRAINT message_report_moderator_fk FOREIGN KEY(moderator_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

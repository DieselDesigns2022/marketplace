ALTER TABLE order_items
  ADD COLUMN downloaded_at DATETIME NULL AFTER updated_at,
  ADD COLUMN review_eligible_at DATETIME NULL AFTER downloaded_at,
  ADD COLUMN reviewed_at DATETIME NULL AFTER review_eligible_at;

ALTER TABLE designers
  MODIFY average_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN review_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER average_rating,
  ADD COLUMN rating_5_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER review_count,
  ADD COLUMN rating_4_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER rating_5_count,
  ADD COLUMN rating_3_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER rating_4_count,
  ADD COLUMN rating_2_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER rating_3_count,
  ADD COLUMN rating_1_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER rating_2_count;

CREATE TABLE seller_reviews (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  buyer_id BIGINT NOT NULL,
  designer_id BIGINT NOT NULL,
  product_id BIGINT NOT NULL,
  order_id BIGINT NOT NULL,
  order_item_id BIGINT NOT NULL,
  buyer_name_snapshot VARCHAR(120) NOT NULL,
  seller_name_snapshot VARCHAR(120) NOT NULL,
  product_title_snapshot VARCHAR(190) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_text TEXT NULL,
  moderation_status ENUM('published','under_review','removed') NOT NULL DEFAULT 'published',
  verified_purchase TINYINT(1) NOT NULL DEFAULT 1,
  reviewed_at DATETIME NOT NULL,
  edited_at DATETIME NULL,
  removed_by BIGINT NULL,
  removed_at DATETIME NULL,
  moderation_reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY seller_reviews_order_item_unique(order_item_id),
  KEY seller_reviews_designer_idx(designer_id),
  KEY seller_reviews_buyer_idx(buyer_id),
  KEY seller_reviews_product_idx(product_id),
  KEY seller_reviews_order_idx(order_id),
  KEY seller_reviews_rating_idx(rating),
  KEY seller_reviews_status_idx(moderation_status),
  KEY seller_reviews_reviewed_idx(reviewed_at),
  KEY seller_reviews_created_idx(created_at),
  KEY seller_reviews_public(designer_id,moderation_status,reviewed_at),
  CONSTRAINT seller_reviews_rating_check CHECK(rating BETWEEN 1 AND 5),
  CONSTRAINT seller_reviews_buyer_fk FOREIGN KEY(buyer_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT seller_reviews_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
  CONSTRAINT seller_reviews_product_fk FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT seller_reviews_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT seller_reviews_item_fk FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
  CONSTRAINT seller_reviews_moderator_fk FOREIGN KEY(removed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seller_review_replies (
  id BIGINT PRIMARY KEY AUTO_INCREMENT, review_id BIGINT NOT NULL, designer_id BIGINT NOT NULL,
  reply_text TEXT NOT NULL, moderation_status ENUM('published','removed') NOT NULL DEFAULT 'published',
  moderated_by BIGINT NULL, moderated_at DATETIME NULL, moderation_reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY seller_review_reply_unique(review_id),
  CONSTRAINT seller_review_reply_review_fk FOREIGN KEY(review_id) REFERENCES seller_reviews(id) ON DELETE RESTRICT,
  CONSTRAINT seller_review_reply_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
  CONSTRAINT seller_review_reply_moderator_fk FOREIGN KEY(moderated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE review_reports (
  id BIGINT PRIMARY KEY AUTO_INCREMENT, review_id BIGINT NOT NULL, reporter_user_id BIGINT NOT NULL,
  designer_id BIGINT NOT NULL, reason ENUM('spam','harassment','personal_information','hate_threatening','unrelated','other') NOT NULL,
  details VARCHAR(2000) NULL, status ENUM('open','reviewing','reviewed','no_action','review_removed','resolved') NOT NULL DEFAULT 'open',
  admin_user_id BIGINT NULL, admin_note VARCHAR(2000) NULL, reviewed_at DATETIME NULL, resolved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY review_report_seller_unique(review_id,designer_id),
  CONSTRAINT review_report_review_fk FOREIGN KEY(review_id) REFERENCES seller_reviews(id) ON DELETE RESTRICT,
  CONSTRAINT review_report_user_fk FOREIGN KEY(reporter_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT review_report_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
  CONSTRAINT review_report_admin_fk FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seller_review_moderation_audits (
  id BIGINT PRIMARY KEY AUTO_INCREMENT, review_id BIGINT NOT NULL, admin_user_id BIGINT NOT NULL,
  action ENUM('under_review','removed','restored') NOT NULL, previous_status VARCHAR(30) NOT NULL,
  reason VARCHAR(500) NOT NULL, internal_note VARCHAR(2000) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT review_audit_review_fk FOREIGN KEY(review_id) REFERENCES seller_reviews(id) ON DELETE RESTRICT,
  CONSTRAINT review_audit_admin_fk FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE seller_review_reply_moderation_audits (
  id BIGINT PRIMARY KEY AUTO_INCREMENT, reply_id BIGINT NOT NULL, admin_user_id BIGINT NOT NULL,
  action ENUM('removed','restored') NOT NULL, previous_status VARCHAR(30) NOT NULL,
  reason VARCHAR(500) NOT NULL, internal_note VARCHAR(2000) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT reply_audit_reply_fk FOREIGN KEY(reply_id) REFERENCES seller_review_replies(id) ON DELETE RESTRICT,
  CONSTRAINT reply_audit_admin_fk FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill only from reliable successful-delivery evidence; intentionally emits no notifications.
UPDATE order_items oi JOIN (SELECT order_item_id,MIN(created_at) served_at FROM downloads WHERE status='served' GROUP BY order_item_id) dl ON dl.order_item_id=oi.id
SET oi.downloaded_at=COALESCE(oi.downloaded_at,dl.served_at), oi.review_eligible_at=COALESCE(oi.review_eligible_at,dl.served_at);

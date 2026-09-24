-- Phase 14: seller-hosted collaborative bundle events. Timestamps use the established marketplace database timezone.
CREATE TABLE collab_events (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, host_designer_id BIGINT NOT NULL, title VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL, description TEXT NOT NULL, price_cents INT UNSIGNED NOT NULL,
 participation_type ENUM('open','closed') NOT NULL, minimum_file_count INT UNSIGNED NOT NULL,
 upload_deadline DATETIME NOT NULL, sale_starts_at DATETIME NOT NULL, sale_close_date DATE NOT NULL,
 invite_token_hash CHAR(64) NULL, invite_expires_at DATETIME NULL,
 status ENUM('draft','collecting','processing','ready','ended','failed','ineligible') NOT NULL DEFAULT 'collecting',
 ip_risk_state ENUM('clear','review_required','approved','rejected') NOT NULL DEFAULT 'clear',
 ip_content_fingerprint CHAR(64) NULL, snapshot_at DATETIME NULL, eligible_count INT UNSIGNED NULL,
 final_zip_path VARCHAR(500) NULL, final_zip_sha256 CHAR(64) NULL, zip_error VARCHAR(500) NULL,
 zip_attempts INT UNSIGNED NOT NULL DEFAULT 0, ready_at DATETIME NULL, ended_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY collab_events_slug_unique(slug), UNIQUE KEY collab_events_invite_unique(invite_token_hash),
 KEY collab_events_deadline(upload_deadline,status), KEY collab_events_public(status,sale_starts_at,sale_close_date),
 CONSTRAINT collab_events_host_fk FOREIGN KEY(host_designer_id) REFERENCES designers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE collab_participants (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, collab_id BIGINT NOT NULL, designer_id BIGINT NOT NULL,
 membership_status ENUM('host','invited','requested','accepted','denied') NOT NULL,
 eligibility ENUM('pending','eligible','excluded') NOT NULL DEFAULT 'pending', qualifying_file_count INT UNSIGNED NULL,
 exclusion_reason VARCHAR(190) NULL, eligibility_snapshotted_at DATETIME NULL, invited_email VARCHAR(190) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY collab_participant_unique(collab_id,designer_id), KEY collab_participant_eligibility(collab_id,eligibility),
 CONSTRAINT collab_participant_event_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT,
 CONSTRAINT collab_participant_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE collab_invitations (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, collab_id BIGINT NOT NULL, email VARCHAR(190) NOT NULL, token_hash CHAR(64) NOT NULL,
 invited_by_designer_id BIGINT NOT NULL, accepted_designer_id BIGINT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY collab_invitation_token(token_hash),
 UNIQUE KEY collab_invitation_email(collab_id,email),
 CONSTRAINT collab_invitation_event_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT,
 CONSTRAINT collab_invitation_inviter_fk FOREIGN KEY(invited_by_designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
 CONSTRAINT collab_invitation_acceptor_fk FOREIGN KEY(accepted_designer_id) REFERENCES designers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE collab_files (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, collab_id BIGINT NOT NULL, participant_id BIGINT NOT NULL, designer_id BIGINT NOT NULL,
 file_kind ENUM('contribution','terms') NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(190) NOT NULL,
 storage_path VARCHAR(500) NOT NULL, mime_type VARCHAR(120) NOT NULL, byte_size BIGINT UNSIGNED NOT NULL, sha256 CHAR(64) NOT NULL,
 included_in_snapshot TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY collab_file_stored(stored_name), KEY collab_file_owner(collab_id,designer_id,file_kind),
 CONSTRAINT collab_file_event_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT,
 CONSTRAINT collab_file_participant_fk FOREIGN KEY(participant_id) REFERENCES collab_participants(id) ON DELETE RESTRICT,
 CONSTRAINT collab_file_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE collab_ip_risk_detections (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, collab_id BIGINT NOT NULL, risk_term_id BIGINT NOT NULL,
 matched_term VARCHAR(190) NOT NULL, matched_alias VARCHAR(190) NULL, source_field VARCHAR(40) NOT NULL,
 content_fingerprint CHAR(64) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY collab_ip_current(collab_id,is_active),
 CONSTRAINT collab_ip_event_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT,
 CONSTRAINT collab_ip_term_fk FOREIGN KEY(risk_term_id) REFERENCES ip_risk_terms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE collab_ip_risk_reviews (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, collab_id BIGINT NOT NULL, admin_user_id BIGINT NOT NULL,
 decision ENUM('approved','rejected') NOT NULL, content_fingerprint CHAR(64) NOT NULL, notes VARCHAR(1000) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT collab_review_event_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT,
 CONSTRAINT collab_review_admin_fk FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE order_items MODIFY product_id BIGINT NULL, ADD COLUMN collab_id BIGINT NULL AFTER custom_service_id,
 ADD COLUMN collab_storefront_designer_id BIGINT NULL AFTER collab_id,
 ADD KEY order_items_collab(collab_id), ADD CONSTRAINT order_items_collab_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT,
 ADD CONSTRAINT order_items_collab_storefront_fk FOREIGN KEY(collab_storefront_designer_id) REFERENCES designers(id) ON DELETE SET NULL,
 ADD CONSTRAINT order_items_purchase_identity_chk CHECK (
  (product_id IS NOT NULL AND custom_service_id IS NULL AND collab_id IS NULL) OR
  (product_id IS NULL AND custom_service_id IS NOT NULL AND collab_id IS NULL) OR
  (product_id IS NULL AND custom_service_id IS NULL AND collab_id IS NOT NULL));
ALTER TABLE downloads MODIFY product_id BIGINT NULL, MODIFY product_file_id BIGINT NULL,
 ADD COLUMN collab_id BIGINT NULL AFTER product_file_id, ADD KEY downloads_collab(collab_id),
 ADD CONSTRAINT downloads_collab_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT,
 ADD CONSTRAINT downloads_identity_chk CHECK ((product_file_id IS NOT NULL AND collab_id IS NULL) OR (product_file_id IS NULL AND collab_id IS NOT NULL));
ALTER TABLE seller_earnings MODIFY product_id BIGINT NULL, ADD COLUMN collab_id BIGINT NULL AFTER product_id,
 ADD COLUMN order_item_id BIGINT NULL AFTER order_id, ADD KEY seller_earnings_collab(collab_id),
 ADD CONSTRAINT seller_earnings_collab_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT;
ALTER TABLE platform_commissions ADD COLUMN collab_id BIGINT NULL AFTER custom_service_id,
 ADD KEY platform_commissions_collab(collab_id), ADD CONSTRAINT platform_commissions_collab_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT;
CREATE TABLE collab_order_allocations (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, collab_id BIGINT NOT NULL, order_id BIGINT NOT NULL, order_item_id BIGINT NOT NULL,
 designer_id BIGINT NOT NULL, qualifying_file_count INT UNSIGNED NOT NULL, eligible_count_snapshot INT UNSIGNED NOT NULL,
 gross_basis_cents INT UNSIGNED NOT NULL, marketplace_fee_cents INT UNSIGNED NOT NULL, contributor_pool_cents INT UNSIGNED NOT NULL,
 allocation_cents INT UNSIGNED NOT NULL, refunded_allocation_cents INT UNSIGNED NOT NULL DEFAULT 0,
 storefront_designer_id BIGINT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY collab_allocation_once(order_item_id,designer_id), KEY collab_allocation_event(collab_id,order_id),
 CONSTRAINT collab_allocation_event_fk FOREIGN KEY(collab_id) REFERENCES collab_events(id) ON DELETE RESTRICT,
 CONSTRAINT collab_allocation_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT,
 CONSTRAINT collab_allocation_item_fk FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
 CONSTRAINT collab_allocation_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
 CONSTRAINT collab_allocation_storefront_fk FOREIGN KEY(storefront_designer_id) REFERENCES designers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

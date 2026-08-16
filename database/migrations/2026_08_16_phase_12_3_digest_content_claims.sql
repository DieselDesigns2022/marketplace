-- Phase 12.3 correction: durable cross-preference digest content assignment.
CREATE TABLE email_digest_content_claims (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 user_id BIGINT NOT NULL,
 product_id BIGINT NOT NULL,
 preference_category ENUM('favorite_shop','weekly','monthly') NOT NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 email_message_id BIGINT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_digest_claim_message_product (email_message_id,product_id),
 INDEX idx_digest_claim_overlap (user_id,product_id,period_start,period_end),
 CONSTRAINT fk_digest_claim_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_digest_claim_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
 CONSTRAINT fk_digest_claim_message FOREIGN KEY (email_message_id) REFERENCES email_messages(id) ON DELETE CASCADE
);

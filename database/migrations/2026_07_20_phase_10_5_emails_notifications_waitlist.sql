-- Phase 10.5: durable notifications, consent, waitlist, campaigns and email queue.
CREATE TABLE notifications (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, user_id BIGINT NOT NULL, notification_type VARCHAR(80) NOT NULL,
 audience ENUM('buyer','designer','admin','system') NOT NULL DEFAULT 'system', title VARCHAR(190) NOT NULL,
 message TEXT NOT NULL, action_url VARCHAR(500) NULL, event_key VARCHAR(190) NOT NULL, read_at TIMESTAMP NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_notification_event (user_id,event_key),
 INDEX idx_notifications_user_created (user_id,created_at), INDEX idx_notifications_user_unread (user_id,read_at,created_at),
 CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE email_preferences (
 user_id BIGINT PRIMARY KEY, marketing_opt_in BOOLEAN NOT NULL DEFAULT 0, marketing_opted_in_at TIMESTAMP NULL,
 marketing_opted_out_at TIMESTAMP NULL, unsubscribe_nonce CHAR(64) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_email_preferences_nonce (unsubscribe_nonce),
 CONSTRAINT fk_email_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE waitlist_entries (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL,
 interest_type ENUM('seller','buyer','both','tester') NOT NULL, business_name VARCHAR(190) NULL,
 source ENUM('direct','homepage','seller','social','referral','campaign') NOT NULL DEFAULT 'direct',
 status ENUM('subscribed','invited','unsubscribed','suppressed') NOT NULL DEFAULT 'subscribed', consent_at TIMESTAMP NOT NULL,
 unsubscribed_at TIMESTAMP NULL, unsubscribe_nonce CHAR(64) NOT NULL, confirmation_sent_at TIMESTAMP NULL,
 invited_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_waitlist_email (email), UNIQUE KEY uq_waitlist_nonce (unsubscribe_nonce),
 INDEX idx_waitlist_filters (status,interest_type,source,created_at)
);
CREATE TABLE email_campaigns (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, campaign_type ENUM('promotional','launch_invite') NOT NULL,
 audience VARCHAR(80) NOT NULL, subject VARCHAR(190) NOT NULL, body TEXT NOT NULL, cta_label VARCHAR(80) NULL,
 cta_url VARCHAR(500) NULL, status ENUM('draft','queued','sending','sent','completed','partially_failed','failed','cancelled') NOT NULL DEFAULT 'draft',
 created_by BIGINT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, queued_at TIMESTAMP NULL,
 sent_at TIMESTAMP NULL, completed_at TIMESTAMP NULL, cancelled_at TIMESTAMP NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_campaign_status (status,created_at), CONSTRAINT fk_campaign_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);
CREATE TABLE email_campaign_recipients (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, campaign_id BIGINT NOT NULL, waitlist_entry_id BIGINT NULL, user_id BIGINT NULL,
 email VARCHAR(190) NOT NULL, name VARCHAR(120) NULL,
 status ENUM('pending','queued','sent','failed','cancelled','suppressed') NOT NULL DEFAULT 'pending', last_error VARCHAR(500) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_campaign_recipient (campaign_id,email), INDEX idx_campaign_recipient_status (campaign_id,status),
 CONSTRAINT fk_recipient_campaign FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
 CONSTRAINT fk_recipient_waitlist FOREIGN KEY (waitlist_entry_id) REFERENCES waitlist_entries(id) ON DELETE SET NULL,
 CONSTRAINT fk_recipient_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE email_messages (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, classification ENUM('transactional','marketing') NOT NULL,
 recipient_email VARCHAR(190) NOT NULL, recipient_name VARCHAR(120) NULL, subject VARCHAR(190) NOT NULL,
 template VARCHAR(80) NOT NULL, template_data JSON NOT NULL, campaign_id BIGINT NULL, campaign_recipient_id BIGINT NULL,
 waitlist_entry_id BIGINT NULL, deduplication_key VARCHAR(190) NOT NULL,
 status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending', attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at TIMESTAMP NULL, last_error VARCHAR(500) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 claimed_at TIMESTAMP NULL, sent_at TIMESTAMP NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_email_dedupe (deduplication_key), INDEX idx_email_queue (status,next_attempt_at,created_at),
 INDEX idx_email_claim (status,claimed_at),
 CONSTRAINT fk_message_campaign FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE SET NULL,
 CONSTRAINT fk_message_recipient FOREIGN KEY (campaign_recipient_id) REFERENCES email_campaign_recipients(id) ON DELETE SET NULL,
 CONSTRAINT fk_message_waitlist FOREIGN KEY (waitlist_entry_id) REFERENCES waitlist_entries(id) ON DELETE SET NULL
);

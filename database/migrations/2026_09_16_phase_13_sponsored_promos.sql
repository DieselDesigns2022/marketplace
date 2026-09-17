CREATE TABLE promo_packages (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, placement ENUM('marketplace','homepage','category','weekly_email') NOT NULL,
 duration_value INT UNSIGNED NOT NULL, duration_unit ENUM('day','week') NOT NULL, price_cents INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY promo_package_choice (placement,duration_value,duration_unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO promo_packages(placement,duration_value,duration_unit,price_cents) VALUES
('marketplace',1,'day',300),('marketplace',3,'day',800),('marketplace',7,'day',1500),('marketplace',14,'day',2500),
('homepage',1,'day',500),('homepage',3,'day',1200),('homepage',7,'day',2500),('homepage',14,'day',4000),
('category',1,'day',400),('category',3,'day',1000),('category',7,'day',2000),('category',14,'day',3500),
('weekly_email',1,'week',800),('weekly_email',2,'week',1500),('weekly_email',4,'week',2500),('weekly_email',6,'week',3500);

UPDATE ads SET status='ended';
UPDATE ads SET placement='marketplace'
WHERE placement IS NULL OR TRIM(placement)='' OR placement NOT IN ('marketplace','homepage','category','weekly_email');
ALTER TABLE ads CHANGE start_date starts_at DATETIME NULL, CHANGE end_date ends_at DATETIME NULL,
 MODIFY placement ENUM('marketplace','homepage','category','weekly_email') NOT NULL,
 MODIFY status ENUM('pending_payment','active','paused','ended','payment_failed','cancelled') NOT NULL DEFAULT 'pending_payment',
 ADD target_type ENUM('shop','product') NOT NULL DEFAULT 'product' AFTER placement, ADD category_id BIGINT NULL AFTER target_type,
 ADD package_id BIGINT NULL AFTER category_id, ADD duration_value INT UNSIGNED NULL, ADD duration_unit ENUM('day','week') NULL,
 ADD price_cents INT UNSIGNED NULL, ADD currency CHAR(3) NOT NULL DEFAULT 'usd',
 ADD payment_status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
 ADD stripe_checkout_session_id VARCHAR(255) NULL, ADD stripe_payment_intent_id VARCHAR(255) NULL, ADD paid_at DATETIME NULL,
 ADD paused_at DATETIME NULL, ADD last_served_at DATETIME(6) NULL, ADD email_total_appearances INT UNSIGNED NULL,
 ADD email_appearances_used INT UNSIGNED NOT NULL DEFAULT 0, ADD click_token CHAR(64) NULL,
 ADD UNIQUE KEY ads_click_token_unique(click_token), ADD KEY ads_rotation(placement,category_id,status,payment_status,impressions,last_served_at),
 ADD CONSTRAINT ads_category_fk FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL,
 ADD CONSTRAINT ads_package_fk FOREIGN KEY(package_id) REFERENCES promo_packages(id) ON DELETE RESTRICT;
UPDATE ads SET click_token=SHA2(CONCAT(UUID(),':',id),256) WHERE click_token IS NULL;
ALTER TABLE ads MODIFY click_token CHAR(64) NOT NULL;

CREATE TABLE promo_email_appearances (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, ad_id BIGINT NOT NULL, period_start DATE NOT NULL, period_end DATE NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY promo_email_period(ad_id,period_start,period_end),
 CONSTRAINT promo_email_ad_fk FOREIGN KEY(ad_id) REFERENCES ads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE promo_email_sends (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, ad_id BIGINT NOT NULL, email_message_id BIGINT NOT NULL,
 period_start DATE NOT NULL, period_end DATE NOT NULL, sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY promo_email_send_message(ad_id,email_message_id), KEY promo_email_send_totals(ad_id,sent_at),
 CONSTRAINT promo_email_send_ad_fk FOREIGN KEY(ad_id) REFERENCES ads(id) ON DELETE CASCADE,
 CONSTRAINT promo_email_send_message_fk FOREIGN KEY(email_message_id) REFERENCES email_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE buyer_referral_giveaway_settings (
 id TINYINT PRIMARY KEY,
 is_enabled TINYINT NOT NULL DEFAULT 0,
 starts_at DATETIME NULL,
 ends_at DATETIME NULL,
 updated_by BIGINT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CHECK(id=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO buyer_referral_giveaway_settings(id) VALUES(1);
CREATE TABLE buyer_referral_giveaway_entries (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 referral_id BIGINT NOT NULL UNIQUE,
 referrer_user_id BIGINT NOT NULL,
 referred_user_id BIGINT NOT NULL UNIQUE,
 status ENUM('pending','eligible','ineligible','review','excluded') NOT NULL DEFAULT 'pending',
 reason VARCHAR(500) NOT NULL DEFAULT '',
 qualifying_cents BIGINT NOT NULL DEFAULT 0,
 qualifying_orders JSON NOT NULL,
 excluded_reason VARCHAR(500) NULL,
 first_qualified_at DATETIME NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY referral_giveaway_owner(referrer_user_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE buyer_referral_giveaway_draws (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 winner_entry_id BIGINT NOT NULL,
 winner_user_id BIGINT NOT NULL,
 pool_snapshot LONGTEXT NOT NULL,
 pool_hash CHAR(64) NOT NULL,
 pool_count INT NOT NULL,
 selected_index INT NOT NULL,
 status ENUM('selected','disqualified','awarded') NOT NULL DEFAULT 'selected',
 reason VARCHAR(500) NULL,
 assignment_id BIGINT NULL UNIQUE,
 drawn_by BIGINT NOT NULL,
 drawn_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE buyer_referral_giveaway_audits (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 action VARCHAR(60) NOT NULL,
 subject_id BIGINT NULL,
 actor_id BIGINT NULL,
 details JSON NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

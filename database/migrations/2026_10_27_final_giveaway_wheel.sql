CREATE TABLE final_giveaway_settings (
 id BIGINT PRIMARY KEY,
 live_until DATETIME NULL,
 updated_by BIGINT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO final_giveaway_settings(id) VALUES(1);
CREATE TABLE final_giveaway_draws (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 request_key VARCHAR(64) NOT NULL UNIQUE,
 pool_type VARCHAR(30) NOT NULL,
 campaign_key VARCHAR(80) NOT NULL,
 winner_entry_key VARCHAR(100) NOT NULL UNIQUE,
 winner_user_id BIGINT NOT NULL,
 winner_label VARCHAR(190) NOT NULL,
 prize_donation_id BIGINT NOT NULL,
 prize_title VARCHAR(190) NOT NULL,
 delivery_instructions TEXT NOT NULL,
 delivery_file_path VARCHAR(500) NULL,
 delivery_file_name VARCHAR(255) NULL,
 pool_snapshot LONGTEXT NOT NULL,
 pool_hash CHAR(64) NOT NULL,
 pool_count INT NOT NULL,
 selected_index INT NOT NULL,
 assignment_id BIGINT NULL UNIQUE,
 conversation_id BIGINT NULL,
 message_id BIGINT NULL UNIQUE,
 drawn_by BIGINT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reveal_at DATETIME NOT NULL,
 INDEX final_giveaway_winner(winner_user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE final_giveaway_audits (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 actor_id BIGINT NOT NULL,
 action VARCHAR(60) NOT NULL,
 details LONGTEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE social_content_states (
 content_key VARCHAR(190) PRIMARY KEY,
 status ENUM('pending','done') NOT NULL DEFAULT 'pending',
 version INT UNSIGNED NOT NULL DEFAULT 0,
 done_at DATETIME NULL,
 done_by BIGINT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE social_content_manual (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 content_type VARCHAR(30) NOT NULL,
 title VARCHAR(190) NOT NULL,
 target_path VARCHAR(500) NOT NULL,
 relevant_at DATETIME NULL,
 caption TEXT NOT NULL,
 created_by BIGINT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE social_content_audits (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,
 content_key VARCHAR(190) NOT NULL,
 action ENUM('created','done','reopened') NOT NULL,
 actor_id BIGINT NOT NULL,
 title_snapshot VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY social_content_audit_key (content_key,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

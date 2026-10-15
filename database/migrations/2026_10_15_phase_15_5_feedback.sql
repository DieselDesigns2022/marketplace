-- Phase 15.5: built-in feedback/suggestion inbox.

CREATE TABLE feedback_submissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NULL,
    user_name_snapshot VARCHAR(120) NOT NULL,
    user_email_snapshot VARCHAR(190) NOT NULL,
    submission_type ENUM('feedback','suggestion') NOT NULL,
    message TEXT NOT NULL,
    context_url VARCHAR(500) NULL,
    status ENUM('open','resolved') NOT NULL DEFAULT 'open',
    resolved_by BIGINT NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_feedback_status_created (status, created_at, id),
    INDEX idx_feedback_type_created (submission_type, created_at, id),
    INDEX idx_feedback_user_created (user_id, created_at, id),

    CONSTRAINT fk_feedback_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT fk_feedback_resolver
        FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

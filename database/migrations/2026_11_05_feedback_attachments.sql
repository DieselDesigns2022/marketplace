CREATE TABLE feedback_attachments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    feedback_submission_id BIGINT NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    byte_size BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_feedback_attachment_submission (feedback_submission_id),
    UNIQUE KEY uq_feedback_attachment_stored (stored_name),
    CONSTRAINT fk_feedback_attachment_submission
        FOREIGN KEY (feedback_submission_id)
        REFERENCES feedback_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

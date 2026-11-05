-- Support conversations with no seller storefront.
ALTER TABLE message_conversations
  MODIFY designer_id BIGINT NULL,
  ADD COLUMN conversation_type ENUM('standard','support')
    NOT NULL DEFAULT 'standard',
  ADD COLUMN feedback_submission_id BIGINT NULL,
  ADD UNIQUE KEY support_feedback_unique(feedback_submission_id),
  ADD CONSTRAINT support_feedback_fk
    FOREIGN KEY(feedback_submission_id)
    REFERENCES feedback_submissions(id) ON DELETE SET NULL;

CREATE TABLE feedback_reply_requests (
  reply_token CHAR(32) PRIMARY KEY,
  feedback_submission_id BIGINT NOT NULL,
  message_id BIGINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT feedback_reply_request_submission_fk
    FOREIGN KEY(feedback_submission_id)
    REFERENCES feedback_submissions(id) ON DELETE CASCADE,
  CONSTRAINT feedback_reply_request_message_fk
    FOREIGN KEY(message_id)
    REFERENCES conversation_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

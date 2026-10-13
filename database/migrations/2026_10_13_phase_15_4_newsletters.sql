-- Phase 15.4: editable/schedulable newsletters and recurring newsletter definitions.
-- All scheduled instants are stored in UTC.

ALTER TABLE email_campaigns
    MODIFY status ENUM(
        'draft',
        'scheduled',
        'queued',
        'sending',
        'sent',
        'completed',
        'partially_failed',
        'failed',
        'cancelled'
    ) NOT NULL DEFAULT 'draft',
    ADD COLUMN preheader VARCHAR(255) NULL AFTER subject,
    ADD COLUMN scheduled_for DATETIME NULL COMMENT 'UTC scheduled send instant' AFTER status,
    ADD COLUMN schedule_timezone VARCHAR(64) NULL AFTER scheduled_for,
    ADD COLUMN newsletter_type ENUM('one_off','weekly','monthly') NOT NULL DEFAULT 'one_off' AFTER campaign_type,
    ADD COLUMN recurring_definition_id BIGINT NULL AFTER newsletter_type,
    ADD INDEX idx_email_campaign_schedule (status, scheduled_for, id),
    ADD INDEX idx_email_campaign_recurring_definition (recurring_definition_id);

CREATE TABLE email_newsletter_recurring_definitions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    frequency ENUM('weekly','monthly') NOT NULL,
    name VARCHAR(190) NOT NULL,
    audience VARCHAR(80) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    preheader VARCHAR(255) NULL,
    body TEXT NOT NULL,
    body_format ENUM('plain','rich_html') NOT NULL DEFAULT 'rich_html',
    cta_label VARCHAR(80) NULL,
    cta_url VARCHAR(500) NULL,
    timezone VARCHAR(64) NOT NULL,
    send_hour TINYINT UNSIGNED NOT NULL,
    send_minute TINYINT UNSIGNED NOT NULL DEFAULT 0,
    weekly_day TINYINT UNSIGNED NULL COMMENT 'ISO weekday 1=Monday through 7=Sunday',
    monthly_day TINYINT UNSIGNED NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    next_run_at DATETIME NULL COMMENT 'UTC next due instant',
    last_run_at DATETIME NULL COMMENT 'UTC last generated issue instant',
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_newsletter_frequency (frequency),
    INDEX idx_newsletter_due (enabled, next_run_at, id),
    CONSTRAINT fk_newsletter_definition_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

ALTER TABLE email_campaigns
    ADD CONSTRAINT fk_email_campaign_recurring_definition
        FOREIGN KEY (recurring_definition_id)
        REFERENCES email_newsletter_recurring_definitions(id)
        ON DELETE SET NULL;

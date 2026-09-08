ALTER TABLE message_reports
    ADD COLUMN notification_cycle INT UNSIGNED NOT NULL DEFAULT 0 AFTER reviewed_at;

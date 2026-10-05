ALTER TABLE email_campaigns
    ADD COLUMN IF NOT EXISTS body_format
        ENUM('plain','rich_html')
        NOT NULL
        DEFAULT 'plain'
        AFTER body;

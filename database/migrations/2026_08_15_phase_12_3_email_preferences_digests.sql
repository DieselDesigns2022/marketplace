-- Phase 12.3: independent registered-user marketing preferences.
ALTER TABLE email_preferences
 ADD COLUMN weekly_emails BOOLEAN NOT NULL DEFAULT 0 AFTER marketing_opt_in,
 ADD COLUMN monthly_emails BOOLEAN NOT NULL DEFAULT 0 AFTER weekly_emails,
 ADD COLUMN favorite_shop_emails BOOLEAN NOT NULL DEFAULT 0 AFTER monthly_emails,
 ADD COLUMN preference_changed_at TIMESTAMP NULL AFTER marketing_opted_out_at,
 ADD INDEX idx_email_preferences_weekly (weekly_emails,user_id),
 ADD INDEX idx_email_preferences_monthly (monthly_emails,user_id),
 ADD INDEX idx_email_preferences_favorite_shop (favorite_shop_emails,user_id);

-- Preserve existing consent: general subscribers receive all three choices; opted-out users receive none.
UPDATE email_preferences
SET weekly_emails=IF(marketing_opt_in=1,1,0),
    monthly_emails=IF(marketing_opt_in=1,1,0),
    favorite_shop_emails=IF(marketing_opt_in=1,1,0),
    preference_changed_at=COALESCE(updated_at,created_at);

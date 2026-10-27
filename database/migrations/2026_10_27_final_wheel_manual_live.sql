ALTER TABLE final_giveaway_settings ADD COLUMN is_live TINYINT(1) NOT NULL DEFAULT 0;
UPDATE final_giveaway_settings SET is_live=CASE WHEN live_until>UTC_TIMESTAMP() THEN 1 ELSE 0 END;

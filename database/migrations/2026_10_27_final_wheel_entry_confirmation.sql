ALTER TABLE final_giveaway_draws ADD COLUMN removed_at DATETIME NULL;
UPDATE final_giveaway_draws SET removed_at=UTC_TIMESTAMP();

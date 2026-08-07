-- Phase 12 creator recognition. Repeatable on pre-Phase-12 and migrated MariaDB installations.
SET @phase12_had_calculated=EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=database() AND table_name='designers' AND column_name='calculated_rank');
ALTER TABLE designers MODIFY creator_rank ENUM('Bronze','Silver','Gold','Platinum','Legend','Diamond') NOT NULL DEFAULT 'Bronze';
UPDATE designers SET creator_rank='Diamond' WHERE creator_rank='Legend';
ALTER TABLE designers MODIFY creator_rank ENUM('Bronze','Silver','Gold','Platinum','Diamond') NOT NULL DEFAULT 'Bronze';
ALTER TABLE designers
 ADD COLUMN IF NOT EXISTS calculated_rank ENUM('Bronze','Silver','Gold','Platinum','Diamond') NOT NULL DEFAULT 'Bronze' AFTER creator_rank,
 ADD COLUMN IF NOT EXISTS qualifying_sales_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER calculated_rank,
 ADD COLUMN IF NOT EXISTS last_qualifying_sale_at DATETIME NULL AFTER qualifying_sales_count,
 ADD COLUMN IF NOT EXISTS rank_override_value ENUM('Bronze','Silver','Gold','Platinum','Diamond') NULL AFTER rank_override,
 ADD COLUMN IF NOT EXISTS rank_override_reason VARCHAR(500) NULL,
 ADD COLUMN IF NOT EXISTS rank_override_admin_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS rank_override_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS founder_position TINYINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS founder_earned_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS founder_qualifying_order_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS founder_active TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS founder_inactive_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS founder_override_state ENUM('automatic','force_active','force_inactive') NOT NULL DEFAULT 'automatic',
 ADD COLUMN IF NOT EXISTS founder_override_reason VARCHAR(500) NULL,
 ADD COLUMN IF NOT EXISTS founder_override_admin_id BIGINT NULL;
SET @phase12_seed_calculated=IF(@phase12_had_calculated,'SELECT 1','UPDATE designers SET calculated_rank=creator_rank');
PREPARE phase12_stmt FROM @phase12_seed_calculated;EXECUTE phase12_stmt;DEALLOCATE PREPARE phase12_stmt;
CREATE UNIQUE INDEX IF NOT EXISTS designers_founder_position_unique ON designers(founder_position);
CREATE INDEX IF NOT EXISTS designers_recognition_idx ON designers(status,qualifying_sales_count,calculated_rank);

ALTER TABLE creator_rank_history
 ADD COLUMN IF NOT EXISTS previous_calculated_rank VARCHAR(40) NULL AFTER designer_id,
 ADD COLUMN IF NOT EXISTS new_calculated_rank VARCHAR(40) NULL,
 ADD COLUMN IF NOT EXISTS previous_effective_rank VARCHAR(40) NULL,
 ADD COLUMN IF NOT EXISTS new_effective_rank VARCHAR(40) NULL,
 ADD COLUMN IF NOT EXISTS qualifying_sales_count INT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS change_source VARCHAR(40) NOT NULL DEFAULT 'legacy',
 ADD COLUMN IF NOT EXISTS event_key VARCHAR(190) NULL;
UPDATE creator_rank_history SET old_rank='Diamond' WHERE old_rank='Legend';
UPDATE creator_rank_history SET new_rank='Diamond' WHERE new_rank='Legend';
UPDATE creator_rank_history SET previous_calculated_rank=IF(old_rank='Legend','Diamond',old_rank),new_calculated_rank=IF(new_rank='Legend','Diamond',new_rank),previous_effective_rank=IF(old_rank='Legend','Diamond',old_rank),new_effective_rank=IF(new_rank='Legend','Diamond',new_rank) WHERE previous_calculated_rank IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS creator_rank_history_event_unique ON creator_rank_history(event_key);
CREATE INDEX IF NOT EXISTS creator_rank_history_designer_created_idx ON creator_rank_history(designer_id,created_at);

CREATE TABLE IF NOT EXISTS creator_recognition_lock(id TINYINT PRIMARY KEY,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
INSERT IGNORE INTO creator_recognition_lock(id) VALUES(1);
CREATE TABLE IF NOT EXISTS creator_recognition_events(
 id BIGINT PRIMARY KEY AUTO_INCREMENT,designer_id BIGINT NOT NULL,source VARCHAR(40) NOT NULL,trigger_key VARCHAR(190) NULL,before_state JSON NOT NULL,after_state JSON NOT NULL,rank_changed TINYINT(1) NOT NULL DEFAULT 0,founder_changed TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY creator_recognition_events_trigger_unique(trigger_key),KEY creator_recognition_events_designer_created_idx(designer_id,created_at)
);
CREATE TABLE IF NOT EXISTS creator_badge_history(
 id BIGINT PRIMARY KEY AUTO_INCREMENT,designer_id BIGINT NOT NULL,action VARCHAR(40) NOT NULL,before_state JSON NULL,after_state JSON NULL,founder_position TINYINT UNSIGNED NULL,change_source VARCHAR(40) NOT NULL,admin_user_id BIGINT NULL,reason VARCHAR(500) NULL,event_key VARCHAR(190) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY creator_badge_history_event_unique(event_key),KEY creator_badge_history_designer_idx(designer_id,created_at),
 CONSTRAINT creator_badge_history_position_check CHECK(founder_position IS NULL OR founder_position BETWEEN 1 AND 50)
);

-- MariaDB lacks portable ADD CONSTRAINT IF NOT EXISTS; guard canonical constraints.
SET @phase12_check=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='designers' AND constraint_name='designers_founder_position_check'),'SELECT 1','ALTER TABLE designers ADD CONSTRAINT designers_founder_position_check CHECK(founder_position IS NULL OR founder_position BETWEEN 1 AND 50)');
PREPARE phase12_stmt FROM @phase12_check;EXECUTE phase12_stmt;DEALLOCATE PREPARE phase12_stmt;
SET @phase12_order_fk=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='designers' AND constraint_name='designers_founder_qualifying_order_fk'),'SELECT 1','ALTER TABLE designers ADD CONSTRAINT designers_founder_qualifying_order_fk FOREIGN KEY(founder_qualifying_order_id) REFERENCES orders(id) ON DELETE RESTRICT');
PREPARE phase12_stmt FROM @phase12_order_fk;EXECUTE phase12_stmt;DEALLOCATE PREPARE phase12_stmt;
-- Legacy creator_rank_history rows are deliberately preserved even when their old actor/designer no longer exists; no new FK is imposed on that retained table.
SET @phase12_rank_admin_fk=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='designers' AND constraint_name='designers_rank_override_admin_fk'),'SELECT 1','ALTER TABLE designers ADD CONSTRAINT designers_rank_override_admin_fk FOREIGN KEY(rank_override_admin_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase12_stmt FROM @phase12_rank_admin_fk;EXECUTE phase12_stmt;DEALLOCATE PREPARE phase12_stmt;
SET @phase12_founder_admin_fk=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='designers' AND constraint_name='designers_founder_override_admin_fk'),'SELECT 1','ALTER TABLE designers ADD CONSTRAINT designers_founder_override_admin_fk FOREIGN KEY(founder_override_admin_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase12_stmt FROM @phase12_founder_admin_fk;EXECUTE phase12_stmt;DEALLOCATE PREPARE phase12_stmt;
SET @phase12_badge_designer_fk=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='creator_badge_history' AND constraint_name='creator_badge_history_designer_fk'),'SELECT 1','ALTER TABLE creator_badge_history ADD CONSTRAINT creator_badge_history_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT');
PREPARE phase12_stmt FROM @phase12_badge_designer_fk;EXECUTE phase12_stmt;DEALLOCATE PREPARE phase12_stmt;
SET @phase12_badge_admin_fk=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='creator_badge_history' AND constraint_name='creator_badge_history_admin_fk'),'SELECT 1','ALTER TABLE creator_badge_history ADD CONSTRAINT creator_badge_history_admin_fk FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase12_stmt FROM @phase12_badge_admin_fk;EXECUTE phase12_stmt;DEALLOCATE PREPARE phase12_stmt;
SET @phase12_event_designer_fk=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='creator_recognition_events' AND constraint_name='creator_recognition_events_designer_fk'),'SELECT 1','ALTER TABLE creator_recognition_events ADD CONSTRAINT creator_recognition_events_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT');
PREPARE phase12_stmt FROM @phase12_event_designer_fk;EXECUTE phase12_stmt;DEALLOCATE PREPARE phase12_stmt;

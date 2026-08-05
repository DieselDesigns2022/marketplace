-- Phase 11 seller-referral role rewards and append-only lifetime commission accounting.
-- Rerunnable against the deployed 2026-07-31 Phase 11 schema.
ALTER TABLE designers MODIFY status ENUM('approved','disabled','inactive','deleted') NOT NULL DEFAULT 'approved';
ALTER TABLE referrals
 ADD COLUMN IF NOT EXISTS seller_reward_type ENUM('store_credit','lifetime_commission','legacy_store_credit_pair') NULL,
 ADD COLUMN IF NOT EXISTS seller_reward_type_selected_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS commission_ended_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS commission_end_reason ENUM('store_disabled','store_inactive','store_deleted') NULL,
 ADD COLUMN IF NOT EXISTS seller_legacy_pair_credit TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE referrals MODIFY seller_reward_type ENUM('store_credit','lifetime_commission','legacy_store_credit_pair') NULL;
-- Preserve both historical credit ledger entries and their summaries. The legacy classification
-- makes it explicit that these pre-correction rows used the retired paired-$5 behavior.
UPDATE referrals
 SET seller_reward_type='legacy_store_credit_pair',seller_reward_type_selected_at=COALESCE(seller_reward_type_selected_at,seller_rewarded_at,qualified_at),seller_legacy_pair_credit=1
 WHERE seller_status='rewarded' AND seller_reward_type IS NULL;
CREATE INDEX IF NOT EXISTS referrals_commission_processing_idx ON referrals(seller_reward_type,commission_ended_at,referrer_user_id);

CREATE TABLE IF NOT EXISTS seller_referral_payout_batches (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,referrer_user_id BIGINT NOT NULL,period_start DATE NOT NULL,period_end DATE NOT NULL,
 sequence_no INT NOT NULL DEFAULT 1,amount_cents BIGINT NOT NULL,status ENUM('processing','paid','failed','not_ready') NOT NULL,
 stripe_transfer_id VARCHAR(255) NULL,idempotency_key VARCHAR(190) NOT NULL,failure_reason VARCHAR(500) NULL,
 claim_token VARCHAR(64) NULL,processing_started_at TIMESTAMP NULL,attempted_at TIMESTAMP NULL,succeeded_at TIMESTAMP NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY seller_ref_payout_period_sequence_unique(referrer_user_id,period_start,period_end,sequence_no),
 UNIQUE KEY seller_ref_payout_key_unique(idempotency_key),KEY seller_ref_payout_processing_idx(status,processing_started_at),
 KEY seller_ref_payout_referrer_idx(referrer_user_id,status),
 CONSTRAINT seller_ref_payout_user_fk FOREIGN KEY(referrer_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
ALTER TABLE seller_referral_payout_batches
 ADD COLUMN IF NOT EXISTS sequence_no INT NOT NULL DEFAULT 1,
 ADD COLUMN IF NOT EXISTS claim_token VARCHAR(64) NULL,
 ADD COLUMN IF NOT EXISTS processing_started_at TIMESTAMP NULL;
-- Install the replacement before removing the legacy unique key: its referrer_id
-- prefix may be the index InnoDB selected for seller_ref_payout_user_fk.
CREATE UNIQUE INDEX IF NOT EXISTS seller_ref_payout_period_sequence_unique ON seller_referral_payout_batches(referrer_user_id,period_start,period_end,sequence_no);
SET @drop_old_period_key=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=database() AND table_name='seller_referral_payout_batches' AND index_name='seller_ref_payout_period_unique'),'ALTER TABLE seller_referral_payout_batches DROP INDEX seller_ref_payout_period_unique','SELECT 1');
PREPARE phase11_stmt FROM @drop_old_period_key; EXECUTE phase11_stmt; DEALLOCATE PREPARE phase11_stmt;
SET @replace_processing_idx=IF(
 EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=database() AND table_name='seller_referral_payout_batches' AND index_name='seller_ref_payout_processing_idx')
 AND (SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema=database() AND table_name='seller_referral_payout_batches' AND index_name='seller_ref_payout_processing_idx') <> 'status,processing_started_at',
 'ALTER TABLE seller_referral_payout_batches DROP INDEX seller_ref_payout_processing_idx','SELECT 1');
PREPARE phase11_stmt FROM @replace_processing_idx; EXECUTE phase11_stmt; DEALLOCATE PREPARE phase11_stmt;
CREATE INDEX IF NOT EXISTS seller_ref_payout_processing_idx ON seller_referral_payout_batches(status,processing_started_at);
CREATE INDEX IF NOT EXISTS seller_ref_payout_referrer_idx ON seller_referral_payout_batches(referrer_user_id,status);

CREATE TABLE IF NOT EXISTS seller_referral_commission_ledger (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,referral_id BIGINT NOT NULL,order_id BIGINT NOT NULL,order_item_id BIGINT NOT NULL,
 entry_type ENUM('accrual','refund_adjustment','recovery_adjustment') NOT NULL,amount_cents BIGINT NOT NULL,
 seller_earning_cents BIGINT NOT NULL DEFAULT 0,related_entry_id BIGINT NULL,event_key VARCHAR(190) NOT NULL,
 claimed_batch_id BIGINT NULL,payout_item_id BIGINT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY seller_ref_commission_event_unique(event_key),KEY seller_ref_commission_unpaid_idx(referral_id,payout_item_id,claimed_batch_id,created_at),
 KEY seller_ref_commission_order_idx(order_id,order_item_id),KEY seller_ref_commission_claim_idx(claimed_batch_id),
 CONSTRAINT seller_ref_commission_referral_fk FOREIGN KEY(referral_id) REFERENCES referrals(id) ON DELETE RESTRICT,
 CONSTRAINT seller_ref_commission_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT,
 CONSTRAINT seller_ref_commission_item_fk FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
 CONSTRAINT seller_ref_commission_related_fk FOREIGN KEY(related_entry_id) REFERENCES seller_referral_commission_ledger(id) ON DELETE RESTRICT,
 CONSTRAINT seller_ref_commission_claim_fk FOREIGN KEY(claimed_batch_id) REFERENCES seller_referral_payout_batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
ALTER TABLE seller_referral_commission_ledger ADD COLUMN IF NOT EXISTS claimed_batch_id BIGINT NULL;
-- The legacy unpaid index can be the only index supporting the referral FK.
-- Give that FK another leftmost referral_id prefix before replacing the index.
CREATE INDEX IF NOT EXISTS seller_ref_commission_referral_upgrade_idx ON seller_referral_commission_ledger(referral_id);
SET @replace_unpaid_idx=IF(
 EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=database() AND table_name='seller_referral_commission_ledger' AND index_name='seller_ref_commission_unpaid_idx')
 AND (SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema=database() AND table_name='seller_referral_commission_ledger' AND index_name='seller_ref_commission_unpaid_idx') <> 'referral_id,payout_item_id,claimed_batch_id,created_at',
 'ALTER TABLE seller_referral_commission_ledger DROP INDEX seller_ref_commission_unpaid_idx','SELECT 1');
PREPARE phase11_stmt FROM @replace_unpaid_idx; EXECUTE phase11_stmt; DEALLOCATE PREPARE phase11_stmt;
CREATE INDEX IF NOT EXISTS seller_ref_commission_unpaid_idx ON seller_referral_commission_ledger(referral_id,payout_item_id,claimed_batch_id,created_at);
ALTER TABLE seller_referral_commission_ledger DROP INDEX seller_ref_commission_referral_upgrade_idx;
CREATE INDEX IF NOT EXISTS seller_ref_commission_claim_idx ON seller_referral_commission_ledger(claimed_batch_id);

CREATE TABLE IF NOT EXISTS seller_referral_payout_items (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,batch_id BIGINT NOT NULL,ledger_entry_id BIGINT NOT NULL,amount_cents BIGINT NOT NULL,
 UNIQUE KEY seller_ref_payout_ledger_unique(ledger_entry_id),KEY seller_ref_payout_batch_idx(batch_id),
 CONSTRAINT seller_ref_payout_item_batch_fk FOREIGN KEY(batch_id) REFERENCES seller_referral_payout_batches(id) ON DELETE RESTRICT,
 CONSTRAINT seller_ref_payout_item_ledger_fk FOREIGN KEY(ledger_entry_id) REFERENCES seller_referral_commission_ledger(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS seller_referral_transfer_attempts (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,batch_id BIGINT NOT NULL,status ENUM('attempted','succeeded','failed','not_ready') NOT NULL,
 stripe_transfer_id VARCHAR(255) NULL,failure_reason VARCHAR(500) NULL,attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,succeeded_at TIMESTAMP NULL,
 KEY seller_ref_attempt_batch_idx(batch_id,attempted_at),CONSTRAINT seller_ref_attempt_batch_fk FOREIGN KEY(batch_id) REFERENCES seller_referral_payout_batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
ALTER TABLE seller_referral_transfer_attempts MODIFY status ENUM('attempted','succeeded','failed','not_ready') NOT NULL;
-- As above, retain a batch_id prefix throughout an upgrade from the old shape.
CREATE INDEX IF NOT EXISTS seller_ref_attempt_batch_upgrade_idx ON seller_referral_transfer_attempts(batch_id);
SET @replace_attempt_idx=IF(
 EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=database() AND table_name='seller_referral_transfer_attempts' AND index_name='seller_ref_attempt_batch_idx')
 AND (SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema=database() AND table_name='seller_referral_transfer_attempts' AND index_name='seller_ref_attempt_batch_idx') <> 'batch_id,attempted_at',
 'ALTER TABLE seller_referral_transfer_attempts DROP INDEX seller_ref_attempt_batch_idx','SELECT 1');
PREPARE phase11_stmt FROM @replace_attempt_idx; EXECUTE phase11_stmt; DEALLOCATE PREPARE phase11_stmt;
CREATE INDEX IF NOT EXISTS seller_ref_attempt_batch_idx ON seller_referral_transfer_attempts(batch_id,attempted_at);
ALTER TABLE seller_referral_transfer_attempts DROP INDEX seller_ref_attempt_batch_upgrade_idx;

CREATE TABLE IF NOT EXISTS seller_referral_admin_audits (
 id BIGINT PRIMARY KEY AUTO_INCREMENT,admin_user_id BIGINT NOT NULL,batch_id BIGINT NOT NULL,action VARCHAR(80) NOT NULL,
 amount_cents BIGINT NOT NULL,result_status VARCHAR(40) NOT NULL,reason VARCHAR(500) NULL,stripe_transfer_id VARCHAR(255) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY seller_ref_admin_audit_batch_idx(batch_id,created_at),
 CONSTRAINT seller_ref_admin_audit_admin_fk FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT seller_ref_admin_audit_batch_fk FOREIGN KEY(batch_id) REFERENCES seller_referral_payout_batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Add circular/upgrade foreign keys only after all referenced tables exist.
SET @claim_fk=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='seller_referral_commission_ledger' AND constraint_name='seller_ref_commission_claim_fk'),'SELECT 1','ALTER TABLE seller_referral_commission_ledger ADD CONSTRAINT seller_ref_commission_claim_fk FOREIGN KEY(claimed_batch_id) REFERENCES seller_referral_payout_batches(id) ON DELETE RESTRICT');
PREPARE phase11_stmt FROM @claim_fk; EXECUTE phase11_stmt; DEALLOCATE PREPARE phase11_stmt;
SET @payout_fk=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND table_name='seller_referral_commission_ledger' AND constraint_name='seller_ref_commission_payout_item_fk'),'SELECT 1','ALTER TABLE seller_referral_commission_ledger ADD CONSTRAINT seller_ref_commission_payout_item_fk FOREIGN KEY(payout_item_id) REFERENCES seller_referral_payout_items(id) ON DELETE RESTRICT');
PREPARE phase11_stmt FROM @payout_fk; EXECUTE phase11_stmt; DEALLOCATE PREPARE phase11_stmt;

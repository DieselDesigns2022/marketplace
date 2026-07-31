-- Phase 11 referrals and append-only store-credit ledger. Safe to rerun on Phase 10.6.
-- Reject legacy credit rows that cannot be retained under the Phase 11 user foreign keys.
-- The deliberately missing table makes the migration fail before any schema mutation; operators must repair the source row rather than lose or silently reassign financial history.
SET @phase11_credit_user_guard=IF(
 EXISTS(SELECT 1 FROM marketplace_credits c LEFT JOIN users u ON u.id=c.user_id WHERE c.user_id IS NULL OR u.id IS NULL)
 OR EXISTS(SELECT 1 FROM credit_transactions c LEFT JOIN users u ON u.id=c.user_id WHERE c.user_id IS NULL OR u.id IS NULL),
 'SELECT * FROM phase11_migration_blocked_invalid_credit_user_reference',
 'SELECT 1'
);
PREPARE phase11_credit_user_guard_statement FROM @phase11_credit_user_guard;
EXECUTE phase11_credit_user_guard_statement;
DEALLOCATE PREPARE phase11_credit_user_guard_statement;
SET @phase11_credit_type_guard=IF(
 EXISTS(SELECT 1 FROM credit_transactions WHERE type IS NULL),
 'SELECT * FROM phase11_migration_blocked_null_credit_transaction_type',
 'SELECT 1'
);
PREPARE phase11_credit_type_guard_statement FROM @phase11_credit_type_guard;
EXECUTE phase11_credit_type_guard_statement;
DEALLOCATE PREPARE phase11_credit_type_guard_statement;

ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_code VARCHAR(40) NULL;
UPDATE users u
LEFT JOIN (SELECT referral_code FROM users WHERE referral_code IS NOT NULL AND referral_code<>'' GROUP BY referral_code HAVING COUNT(*)=1) ok ON ok.referral_code=u.referral_code
SET u.referral_code=CONCAT('AM',UPPER(LPAD(HEX(u.id),12,'0')),UPPER(SUBSTRING(SHA2(CONCAT(u.id,':',u.email),256),1,12)))
WHERE u.referral_code IS NULL OR u.referral_code='' OR u.referral_code REGEXP '^[0-9]+$' OR u.referral_code NOT REGEXP '^[A-Za-z0-9_-]{8,40}$' OR ok.referral_code IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS users_referral_code_unique ON users(referral_code);

ALTER TABLE referrals
 ADD COLUMN IF NOT EXISTS referral_code_snapshot VARCHAR(40) NULL,
 ADD COLUMN IF NOT EXISTS seller_intent TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS buyer_status ENUM('pending','rewarded','rejected') NOT NULL DEFAULT 'pending',
 ADD COLUMN IF NOT EXISTS seller_status ENUM('pending','rewarded','rejected') NOT NULL DEFAULT 'pending',
 ADD COLUMN IF NOT EXISTS buyer_qualifying_order_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS seller_qualifying_order_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS seller_qualifying_order_item_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS referrer_reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 ADD COLUMN IF NOT EXISTS referred_reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 ADD COLUMN IF NOT EXISTS qualification_event_key VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS reward_idempotency_key VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS qualified_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS rewarded_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS buyer_rewarded_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS seller_rewarded_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS buyer_referrer_reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 ADD COLUMN IF NOT EXISTS buyer_referred_reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 ADD COLUMN IF NOT EXISTS buyer_reward_event_key VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS seller_referrer_reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 ADD COLUMN IF NOT EXISTS seller_referred_reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 ADD COLUMN IF NOT EXISTS seller_reward_event_key VARCHAR(190) NULL;
ALTER TABLE referrals MODIFY referral_type ENUM('buyer','designer','seller') NULL;
ALTER TABLE referrals MODIFY status ENUM('pending','approved','eligible','attached','qualified','rejected') NOT NULL DEFAULT 'pending';
ALTER TABLE referrals MODIFY reward_status ENUM('pending','active','inactive','rewarded','rejected') NOT NULL DEFAULT 'pending';
UPDATE referrals SET referral_type='seller',seller_intent=1 WHERE referral_type='designer';
-- Convert only legacy statuses. Already migrated attached/qualified/rejected values survive every rerun.
UPDATE referrals SET status=CASE WHEN status IN ('approved','eligible') THEN 'qualified' ELSE 'attached' END WHERE status IN ('pending','approved','eligible');
-- Convert only legacy reward statuses. Already migrated pending/rewarded/rejected values are unchanged.
UPDATE referrals SET buyer_status='rewarded' WHERE reward_status='active' AND referral_type='buyer' AND buyer_status='pending';
UPDATE referrals SET seller_status='rewarded',seller_intent=1 WHERE reward_status='active' AND referral_type='seller' AND seller_status='pending';
UPDATE referrals SET reward_status=CASE WHEN reward_status='active' THEN 'rewarded' ELSE 'rejected' END WHERE reward_status IN ('active','inactive');
UPDATE referrals r JOIN users u ON u.id=r.referrer_user_id SET r.referral_code_snapshot=COALESCE(NULLIF(r.referral_code_snapshot,''),u.referral_code),r.referrer_reward_amount=CASE WHEN r.referrer_reward_amount=0 THEN IF(r.referral_type='seller',5.00,1.50) ELSE r.referrer_reward_amount END,r.referred_reward_amount=CASE WHEN r.referred_reward_amount=0 THEN IF(r.referral_type='seller',5.00,1.50) ELSE r.referred_reward_amount END,r.reward_idempotency_key=COALESCE(r.reward_idempotency_key,CONCAT('legacy-referral:',r.id)) WHERE r.referral_code_snapshot IS NULL OR r.referral_code_snapshot='' OR r.referrer_reward_amount=0 OR r.referred_reward_amount=0 OR r.reward_idempotency_key IS NULL;
-- Snapshot legacy rewarded rows into the appropriate independent reward fields without overwriting Phase 11 data.
UPDATE referrals SET buyer_referrer_reward_amount=IF(buyer_referrer_reward_amount=0,1.50,buyer_referrer_reward_amount),buyer_referred_reward_amount=IF(buyer_referred_reward_amount=0,1.50,buyer_referred_reward_amount),buyer_reward_event_key=COALESCE(buyer_reward_event_key,CONCAT('legacy-referral:',id,':buyer')) WHERE buyer_status='rewarded' AND (buyer_referrer_reward_amount=0 OR buyer_referred_reward_amount=0 OR buyer_reward_event_key IS NULL);
UPDATE referrals SET seller_referrer_reward_amount=IF(seller_referrer_reward_amount=0,5.00,seller_referrer_reward_amount),seller_referred_reward_amount=IF(seller_referred_reward_amount=0,5.00,seller_referred_reward_amount),seller_reward_event_key=COALESCE(seller_reward_event_key,CONCAT('legacy-referral:',id,':seller')) WHERE seller_status='rewarded' AND (seller_referrer_reward_amount=0 OR seller_referred_reward_amount=0 OR seller_reward_event_key IS NULL);
-- Retain conflicting legacy rows as rejected audit history while only the earliest row remains attached.
UPDATE referrals duplicate JOIN referrals keeper ON keeper.referred_user_id=duplicate.referred_user_id AND keeper.id<duplicate.id SET duplicate.status='rejected',duplicate.reward_status='rejected',duplicate.referred_user_id=NULL WHERE duplicate.referred_user_id IS NOT NULL;
ALTER TABLE referrals MODIFY referral_type ENUM('buyer','seller') NULL, MODIFY status ENUM('attached','qualified','rejected') NOT NULL DEFAULT 'attached', MODIFY reward_status ENUM('pending','rewarded','rejected') NOT NULL DEFAULT 'pending';
CREATE UNIQUE INDEX IF NOT EXISTS referrals_one_referrer_per_user ON referrals(referred_user_id);
CREATE UNIQUE INDEX IF NOT EXISTS referrals_reward_key_unique ON referrals(reward_idempotency_key);
CREATE UNIQUE INDEX IF NOT EXISTS referrals_buyer_reward_event_unique ON referrals(buyer_reward_event_key);
CREATE UNIQUE INDEX IF NOT EXISTS referrals_seller_reward_event_unique ON referrals(seller_reward_event_key);
CREATE INDEX IF NOT EXISTS referrals_referrer_status_idx ON referrals(referrer_user_id,status);
-- Guard qualification indexes through information_schema for deployed MariaDB versions.
SET @phase11_idx_buyer_order=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=database() AND table_name='referrals' AND index_name='referrals_buyer_qualifying_order_idx'),'SELECT 1','CREATE INDEX referrals_buyer_qualifying_order_idx ON referrals(buyer_qualifying_order_id)');
PREPARE phase11_idx_statement FROM @phase11_idx_buyer_order;
EXECUTE phase11_idx_statement;
DEALLOCATE PREPARE phase11_idx_statement;
SET @phase11_idx_seller_order=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=database() AND table_name='referrals' AND index_name='referrals_seller_qualifying_order_idx'),'SELECT 1','CREATE INDEX referrals_seller_qualifying_order_idx ON referrals(seller_qualifying_order_id)');
PREPARE phase11_idx_statement FROM @phase11_idx_seller_order;
EXECUTE phase11_idx_statement;
DEALLOCATE PREPARE phase11_idx_statement;
SET @phase11_idx_seller_item=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=database() AND table_name='referrals' AND index_name='referrals_seller_qualifying_item_idx'),'SELECT 1','CREATE INDEX referrals_seller_qualifying_item_idx ON referrals(seller_qualifying_order_item_id)');
PREPARE phase11_idx_statement FROM @phase11_idx_seller_item;
EXECUTE phase11_idx_statement;
DEALLOCATE PREPARE phase11_idx_statement;
SET @phase11_idx_seller_lookup=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=database() AND table_name='referrals' AND index_name='referrals_seller_qualification_lookup_idx'),'SELECT 1','CREATE INDEX referrals_seller_qualification_lookup_idx ON referrals(referred_user_id,seller_status,seller_qualifying_order_id)');
PREPARE phase11_idx_statement FROM @phase11_idx_seller_lookup;
EXECUTE phase11_idx_statement;
DEALLOCATE PREPARE phase11_idx_statement;

ALTER TABLE marketplace_credits ADD COLUMN IF NOT EXISTS total_balance DECIMAL(12,2) NULL, ADD COLUMN IF NOT EXISTS reserved_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00;
SET @phase11_balance_copy=IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=database() AND table_name='marketplace_credits' AND column_name='balance'),'UPDATE marketplace_credits SET total_balance=COALESCE(total_balance,balance,0.00)','SELECT 1');
PREPARE phase11_balance_statement FROM @phase11_balance_copy;
EXECUTE phase11_balance_statement;
DEALLOCATE PREPARE phase11_balance_statement;
ALTER TABLE marketplace_credits MODIFY user_id BIGINT NOT NULL, MODIFY total_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00;
ALTER TABLE marketplace_credits DROP COLUMN IF EXISTS balance;

ALTER TABLE credit_transactions
 ADD COLUMN IF NOT EXISTS status ENUM('reserved','finalized','released') NOT NULL DEFAULT 'finalized',
 ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS order_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS referral_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS admin_user_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS related_transaction_id BIGINT NULL,
 ADD COLUMN IF NOT EXISTS finalized_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS released_at TIMESTAMP NULL;
UPDATE credit_transactions SET idempotency_key=CONCAT('legacy-credit:',id) WHERE idempotency_key IS NULL OR idempotency_key='';
UPDATE credit_transactions SET finalized_at=created_at WHERE status='finalized' AND finalized_at IS NULL;
ALTER TABLE credit_transactions MODIFY user_id BIGINT NOT NULL, MODIFY amount DECIMAL(12,2) NOT NULL, MODIFY type VARCHAR(40) NOT NULL, MODIFY idempotency_key VARCHAR(190) NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS credit_transactions_idempotency_unique ON credit_transactions(idempotency_key);
CREATE INDEX IF NOT EXISTS credit_transactions_user_created_idx ON credit_transactions(user_id,created_at);
CREATE INDEX IF NOT EXISTS credit_transactions_order_idx ON credit_transactions(order_id);
CREATE INDEX IF NOT EXISTS credit_transactions_referral_idx ON credit_transactions(referral_id);
CREATE INDEX IF NOT EXISTS credit_transactions_related_idx ON credit_transactions(related_transaction_id);

ALTER TABLE orders
 ADD COLUMN IF NOT EXISTS credit_reserved DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 ADD COLUMN IF NOT EXISTS credit_payment_status ENUM('none','reserved','finalized','released') NOT NULL DEFAULT 'none',
 ADD COLUMN IF NOT EXISTS internally_completed TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS stripe_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 ADD COLUMN IF NOT EXISTS tax_calculation_id VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS tax_transaction_id VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS tax_transaction_status ENUM('pending','created','failed') NOT NULL DEFAULT 'pending',
 ADD COLUMN IF NOT EXISTS billing_address_snapshot JSON NULL,
 ADD COLUMN IF NOT EXISTS finalization_key VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS finalized_at TIMESTAMP NULL;
CREATE UNIQUE INDEX IF NOT EXISTS orders_finalization_key_unique ON orders(finalization_key);

ALTER TABLE seller_payouts
 ADD COLUMN IF NOT EXISTS platform_credit_attempt_key VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS platform_credit_settled_at TIMESTAMP NULL,
 ADD COLUMN IF NOT EXISTS platform_credit_settled_by BIGINT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS seller_payouts_platform_credit_attempt_unique ON seller_payouts(platform_credit_attempt_key);

-- MariaDB does not consistently support ADD CONSTRAINT IF NOT EXISTS; guard every FK through information_schema.
SET @phase11_fk_0=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_referrals_referrer_fk'),'SELECT 1','ALTER TABLE referrals ADD CONSTRAINT phase11_referrals_referrer_fk FOREIGN KEY(referrer_user_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_0;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_buyer_order=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_referrals_buyer_order_fk'),'SELECT 1','ALTER TABLE referrals ADD CONSTRAINT phase11_referrals_buyer_order_fk FOREIGN KEY(buyer_qualifying_order_id) REFERENCES orders(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_buyer_order;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_seller_order=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_referrals_seller_order_fk'),'SELECT 1','ALTER TABLE referrals ADD CONSTRAINT phase11_referrals_seller_order_fk FOREIGN KEY(seller_qualifying_order_id) REFERENCES orders(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_seller_order;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_seller_item=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_referrals_seller_item_fk'),'SELECT 1','ALTER TABLE referrals ADD CONSTRAINT phase11_referrals_seller_item_fk FOREIGN KEY(seller_qualifying_order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_seller_item;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_settled_admin=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_platform_credit_admin_fk'),'SELECT 1','ALTER TABLE seller_payouts ADD CONSTRAINT phase11_platform_credit_admin_fk FOREIGN KEY(platform_credit_settled_by) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_settled_admin;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_1=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_referrals_referred_fk'),'SELECT 1','ALTER TABLE referrals ADD CONSTRAINT phase11_referrals_referred_fk FOREIGN KEY(referred_user_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_1;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_2=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_credit_user_fk'),'SELECT 1','ALTER TABLE marketplace_credits ADD CONSTRAINT phase11_credit_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_2;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_3=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_ledger_user_fk'),'SELECT 1','ALTER TABLE credit_transactions ADD CONSTRAINT phase11_ledger_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_3;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_4=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_ledger_order_fk'),'SELECT 1','ALTER TABLE credit_transactions ADD CONSTRAINT phase11_ledger_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_4;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_5=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_ledger_referral_fk'),'SELECT 1','ALTER TABLE credit_transactions ADD CONSTRAINT phase11_ledger_referral_fk FOREIGN KEY(referral_id) REFERENCES referrals(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_5;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_6=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_ledger_admin_fk'),'SELECT 1','ALTER TABLE credit_transactions ADD CONSTRAINT phase11_ledger_admin_fk FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_6;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;
SET @phase11_fk_7=IF(EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=database() AND constraint_name='phase11_ledger_related_fk'),'SELECT 1','ALTER TABLE credit_transactions ADD CONSTRAINT phase11_ledger_related_fk FOREIGN KEY(related_transaction_id) REFERENCES credit_transactions(id) ON DELETE RESTRICT');
PREPARE phase11_fk_statement FROM @phase11_fk_7;
EXECUTE phase11_fk_statement;
DEALLOCATE PREPARE phase11_fk_statement;

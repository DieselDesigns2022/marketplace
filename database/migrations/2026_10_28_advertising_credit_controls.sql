ALTER TABLE advertising_credit_recurring_grants
 ADD COLUMN expiry_days SMALLINT UNSIGNED NULL,
 ADD COLUMN schedule_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC';
CREATE TABLE advertising_credit_lots (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT NOT NULL,
 grant_transaction_id BIGINT NULL UNIQUE,
 remaining_amount DECIMAL(12,2) NOT NULL,
 reserved_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 expires_at DATETIME NULL,
 KEY advertising_lot_owner(user_id,expires_at,id),
 CONSTRAINT advertising_lot_user_fk FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT advertising_lot_grant_fk FOREIGN KEY(grant_transaction_id) REFERENCES advertising_credit_transactions(id),
 CONSTRAINT advertising_lot_balance_chk CHECK(remaining_amount>=reserved_amount AND reserved_amount>=0)
) ENGINE=InnoDB;
CREATE TABLE advertising_credit_allocations (
 transaction_id BIGINT NOT NULL,
 lot_id BIGINT NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 PRIMARY KEY(transaction_id,lot_id),
 CONSTRAINT advertising_allocation_transaction_fk FOREIGN KEY(transaction_id) REFERENCES advertising_credit_transactions(id),
 CONSTRAINT advertising_allocation_lot_fk FOREIGN KEY(lot_id) REFERENCES advertising_credit_lots(id),
 CONSTRAINT advertising_allocation_amount_chk CHECK(amount>0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prize_wheel_prizes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    prize_type ENUM(
        'seller_fixed_coupon',
        'seller_percent_coupon',
        'spin_again',
        'grand_opening_entry'
    ) NOT NULL,
    prize_value DECIMAL(10,2) NULL,
    weight INT UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY prize_wheel_prizes_active_sort_idx(is_active,sort_order,id),
    CONSTRAINT prize_wheel_prizes_created_by_fk
        FOREIGN KEY(created_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prize_wheel_rewards (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    spin_id BIGINT NULL,
    wheel_prize_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    storefront_designer_id BIGINT NOT NULL,
    reward_type ENUM(
        'seller_fixed_coupon',
        'seller_percent_coupon',
        'spin_again',
        'grand_opening_entry'
    ) NOT NULL,
    coupon_id BIGINT NULL,
    reward_value DECIMAL(10,2) NULL,
    fulfillment_status ENUM(
        'awarded',
        'fulfilled',
        'consumed'
    ) NOT NULL DEFAULT 'awarded',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY prize_wheel_rewards_spin_unique(spin_id),
    KEY prize_wheel_rewards_user_idx(user_id,created_at),
    KEY prize_wheel_rewards_store_idx(storefront_designer_id,created_at),
    CONSTRAINT prize_wheel_rewards_prize_fk
        FOREIGN KEY(wheel_prize_id) REFERENCES prize_wheel_prizes(id)
        ON DELETE RESTRICT,
    CONSTRAINT prize_wheel_rewards_user_fk
        FOREIGN KEY(user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT prize_wheel_rewards_store_fk
        FOREIGN KEY(storefront_designer_id) REFERENCES designers(id)
        ON DELETE RESTRICT,
    CONSTRAINT prize_wheel_rewards_coupon_fk
        FOREIGN KEY(coupon_id) REFERENCES coupons(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prize_wheel_bonus_spins (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    campaign_key VARCHAR(64) NOT NULL,
    user_id BIGINT NOT NULL,
    storefront_designer_id BIGINT NOT NULL,
    source_spin_id BIGINT NULL,
    status ENUM('available','consumed') NOT NULL DEFAULT 'available',
    consumed_spin_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consumed_at TIMESTAMP NULL,
    KEY prize_wheel_bonus_lookup_idx(
        campaign_key,
        user_id,
        storefront_designer_id,
        status
    ),
    CONSTRAINT prize_wheel_bonus_user_fk
        FOREIGN KEY(user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT prize_wheel_bonus_store_fk
        FOREIGN KEY(storefront_designer_id) REFERENCES designers(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS grand_opening_giveaway_entries (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    campaign_key VARCHAR(64) NOT NULL,
    user_id BIGINT NOT NULL,
    storefront_designer_id BIGINT NOT NULL,
    source_type ENUM('prize_wheel') NOT NULL DEFAULT 'prize_wheel',
    source_spin_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY grand_opening_entry_spin_unique(source_spin_id),
    KEY grand_opening_entries_user_idx(user_id,created_at),
    KEY grand_opening_entries_store_idx(storefront_designer_id,created_at),
    CONSTRAINT grand_opening_entries_user_fk
        FOREIGN KEY(user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT grand_opening_entries_store_fk
        FOREIGN KEY(storefront_designer_id) REFERENCES designers(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @wheel_prize_col = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='prize_wheel_spins'
      AND COLUMN_NAME='wheel_prize_id'
);

SET @wheel_prize_sql = IF(
    @wheel_prize_col=0,
    'ALTER TABLE prize_wheel_spins ADD COLUMN wheel_prize_id BIGINT NULL AFTER qualifying_order_id',
    'SELECT 1'
);

PREPARE wheel_prize_stmt FROM @wheel_prize_sql;
EXECUTE wheel_prize_stmt;
DEALLOCATE PREPARE wheel_prize_stmt;

SET @reward_col = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='prize_wheel_spins'
      AND COLUMN_NAME='reward_id'
);

SET @reward_sql = IF(
    @reward_col=0,
    'ALTER TABLE prize_wheel_spins ADD COLUMN reward_id BIGINT NULL AFTER wheel_prize_id',
    'SELECT 1'
);

PREPARE reward_stmt FROM @reward_sql;
EXECUTE reward_stmt;
DEALLOCATE PREPARE reward_stmt;

ALTER TABLE prize_wheel_spins
    MODIFY prize_donation_id BIGINT NULL,
    MODIFY prize_assignment_id BIGINT NULL;

SET @wheel_prize_fk = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='prize_wheel_spins'
      AND CONSTRAINT_NAME='prize_wheel_spin_prize_fk'
);

SET @wheel_prize_fk_sql = IF(
    @wheel_prize_fk=0,
    'ALTER TABLE prize_wheel_spins
       ADD CONSTRAINT prize_wheel_spin_prize_fk
       FOREIGN KEY(wheel_prize_id)
       REFERENCES prize_wheel_prizes(id)
       ON DELETE RESTRICT',
    'SELECT 1'
);

PREPARE wheel_prize_fk_stmt FROM @wheel_prize_fk_sql;
EXECUTE wheel_prize_fk_stmt;
DEALLOCATE PREPARE wheel_prize_fk_stmt;

INSERT INTO prize_wheel_prizes
(title,prize_type,prize_value,weight,sort_order,is_active)
SELECT '$5 Store Credit','seller_fixed_coupon',5.00,1,10,1
WHERE NOT EXISTS (
    SELECT 1 FROM prize_wheel_prizes
    WHERE title='$5 Store Credit'
);

INSERT INTO prize_wheel_prizes
(title,prize_type,prize_value,weight,sort_order,is_active)
SELECT '$3 Store Credit','seller_fixed_coupon',3.00,1,20,1
WHERE NOT EXISTS (
    SELECT 1 FROM prize_wheel_prizes
    WHERE title='$3 Store Credit'
);

INSERT INTO prize_wheel_prizes
(title,prize_type,prize_value,weight,sort_order,is_active)
SELECT '$1 Store Credit','seller_fixed_coupon',1.00,1,30,1
WHERE NOT EXISTS (
    SELECT 1 FROM prize_wheel_prizes
    WHERE title='$1 Store Credit'
);

INSERT INTO prize_wheel_prizes
(title,prize_type,prize_value,weight,sort_order,is_active)
SELECT '20% Off Coupon','seller_percent_coupon',20.00,1,40,1
WHERE NOT EXISTS (
    SELECT 1 FROM prize_wheel_prizes
    WHERE title='20% Off Coupon'
);

INSERT INTO prize_wheel_prizes
(title,prize_type,prize_value,weight,sort_order,is_active)
SELECT '10% Off Coupon','seller_percent_coupon',10.00,1,50,1
WHERE NOT EXISTS (
    SELECT 1 FROM prize_wheel_prizes
    WHERE title='10% Off Coupon'
);

INSERT INTO prize_wheel_prizes
(title,prize_type,prize_value,weight,sort_order,is_active)
SELECT '15% Off Coupon','seller_percent_coupon',15.00,1,60,1
WHERE NOT EXISTS (
    SELECT 1 FROM prize_wheel_prizes
    WHERE title='15% Off Coupon'
);

INSERT INTO prize_wheel_prizes
(title,prize_type,prize_value,weight,sort_order,is_active)
SELECT 'Spin Again','spin_again',NULL,1,70,1
WHERE NOT EXISTS (
    SELECT 1 FROM prize_wheel_prizes
    WHERE title='Spin Again'
);

INSERT INTO prize_wheel_prizes
(title,prize_type,prize_value,weight,sort_order,is_active)
SELECT 'Grand Opening Giveaway Entry','grand_opening_entry',NULL,1,80,1
WHERE NOT EXISTS (
    SELECT 1 FROM prize_wheel_prizes
    WHERE title='Grand Opening Giveaway Entry'
);

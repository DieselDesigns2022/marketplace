ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS prize_credit_applied
        DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER credits_applied,
    ADD COLUMN IF NOT EXISTS prize_credit_reserved
        DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER prize_credit_applied,
    ADD COLUMN IF NOT EXISTS prize_credit_payment_status
        ENUM('none','reserved','finalized','released')
        NOT NULL DEFAULT 'none'
        AFTER prize_credit_reserved;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS prize_credit_applied
        DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER coupon_discount,
    ADD COLUMN IF NOT EXISTS prize_credit_mode_snapshot
        VARCHAR(40) NULL AFTER prize_credit_applied;

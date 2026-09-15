ALTER TABLE products
    ADD COLUMN IF NOT EXISTS extra_protection_watermark
    TINYINT(1) NOT NULL DEFAULT 0
    AFTER is_hand_drawn;

ALTER TABLE custom_design_services
    ADD COLUMN IF NOT EXISTS extra_protection_watermark
    TINYINT(1) NOT NULL DEFAULT 0
    AFTER brief_fields;

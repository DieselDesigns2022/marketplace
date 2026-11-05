-- Task 7: Shop Collection coupon restrictions.
-- Preserve existing seller/product/category restriction values.
ALTER TABLE coupon_restrictions
MODIFY COLUMN restrictable_type
ENUM('seller','product','category','collection') NOT NULL;

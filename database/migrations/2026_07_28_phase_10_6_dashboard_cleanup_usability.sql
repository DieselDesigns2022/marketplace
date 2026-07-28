-- Phase 10.6: safe seller receipt snapshots and canonical marketplace categories.
-- Idempotent on MariaDB; historical duplicate categories are retained inactive.
SET @schema_name = DATABASE();
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='designers' AND COLUMN_NAME='receipt_note')=0,
 'ALTER TABLE designers ADD COLUMN receipt_note VARCHAR(500) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='designers' AND COLUMN_NAME='receipt_image_path')=0,
 'ALTER TABLE designers ADD COLUMN receipt_image_path VARCHAR(255) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='order_items' AND COLUMN_NAME='seller_receipt_note_snapshot')=0,
 'ALTER TABLE order_items ADD COLUMN seller_receipt_note_snapshot VARCHAR(500) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='order_items' AND COLUMN_NAME='seller_receipt_image_path_snapshot')=0,
 'ALTER TABLE order_items ADD COLUMN seller_receipt_image_path_snapshot VARCHAR(255) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TEMPORARY TABLE IF NOT EXISTS phase106_categories (name VARCHAR(120), slug VARCHAR(140), seq INT, PRIMARY KEY(slug));
DELETE FROM phase106_categories;
INSERT INTO phase106_categories VALUES
('Engagement Graphics','engagement-graphics',1),('Social Media Graphics','social-media-graphics',2),('Libby Wraps','libby-wraps',3),('Digital Papers','digital-papers',4),('Freebies','freebies',5),('Digital Services','digital-services',6),('Customs / Personalized','customs-personalized',7);
SET @base_sort=(SELECT COALESCE(MAX(sort_order),0) FROM categories);
INSERT INTO categories(name,slug,is_active,sort_order)
 SELECT name,slug,1,@base_sort+seq FROM phase106_categories
 ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1;
-- Normalize case and remove spacing, slash, hyphen, and punctuation before matching duplicates.
CREATE TEMPORARY TABLE IF NOT EXISTS phase106_duplicate_map (duplicate_id BIGINT PRIMARY KEY, canonical_id BIGINT NOT NULL);
DELETE FROM phase106_duplicate_map;
INSERT INTO phase106_duplicate_map(duplicate_id,canonical_id)
SELECT selected.duplicate_id,selected.canonical_id
FROM (
 SELECT duplicate.id duplicate_id,
  COALESCE(
   -- Canonical-slug normalization is authoritative when legacy name and slug disagree.
   (SELECT canonical.id FROM phase106_categories wanted JOIN categories canonical ON canonical.slug=wanted.slug
    WHERE REGEXP_REPLACE(LOWER(TRIM(duplicate.slug)), '[^a-z0-9]+', '')=REGEXP_REPLACE(LOWER(wanted.slug), '[^a-z0-9]+', '')
    ORDER BY wanted.seq,canonical.id LIMIT 1),
   (SELECT canonical.id FROM phase106_categories wanted JOIN categories canonical ON canonical.slug=wanted.slug
    WHERE REGEXP_REPLACE(LOWER(TRIM(duplicate.name)), '[^a-z0-9]+', '')=REGEXP_REPLACE(LOWER(wanted.name), '[^a-z0-9]+', '')
    ORDER BY wanted.seq,canonical.id LIMIT 1)
  ) canonical_id
 FROM categories duplicate
) selected
WHERE selected.canonical_id IS NOT NULL AND selected.canonical_id<>selected.duplicate_id;
UPDATE products p JOIN phase106_duplicate_map m ON m.duplicate_id=p.category_id SET p.category_id=m.canonical_id;
-- Copy each historical category restriction unless that coupon already restricts the canonical category.
INSERT IGNORE INTO coupon_restrictions(coupon_id,restrictable_type,restrictable_id,created_at)
SELECT r.coupon_id,'category',m.canonical_id,r.created_at FROM coupon_restrictions r JOIN phase106_duplicate_map m ON m.duplicate_id=r.restrictable_id WHERE r.restrictable_type='category';
DELETE r FROM coupon_restrictions r JOIN phase106_duplicate_map m ON m.duplicate_id=r.restrictable_id WHERE r.restrictable_type='category';
UPDATE categories duplicate JOIN phase106_duplicate_map m ON m.duplicate_id=duplicate.id SET duplicate.is_active=0;
DROP TEMPORARY TABLE phase106_duplicate_map;
DROP TEMPORARY TABLE phase106_categories;

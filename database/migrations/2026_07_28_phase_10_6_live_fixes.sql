-- Phase 10.6 live fixes: two canonical marketplace categories.
-- Idempotent on MariaDB; canonical slugs are reused and historical duplicates remain inactive.
CREATE TEMPORARY TABLE IF NOT EXISTS phase106_live_categories (name VARCHAR(120), slug VARCHAR(140), seq INT, PRIMARY KEY(slug));
DELETE FROM phase106_live_categories;
INSERT INTO phase106_live_categories VALUES
('Google Drives','google-drives',1),
('Bundles & Collaborations','bundles-collaborations',2);
SET @phase106_live_max_sort=(SELECT COALESCE(MAX(sort_order),0) FROM categories);
INSERT INTO categories(name,slug,is_active,sort_order)
 SELECT name,slug,1,@phase106_live_max_sort+seq FROM phase106_live_categories
 ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1;

-- Resolve each legacy row to exactly one canonical target. A normalized canonical slug
-- wins over the normalized name when inconsistent legacy data matches both.
CREATE TEMPORARY TABLE IF NOT EXISTS phase106_live_duplicate_map (duplicate_id BIGINT PRIMARY KEY, canonical_id BIGINT NOT NULL);
DELETE FROM phase106_live_duplicate_map;
INSERT INTO phase106_live_duplicate_map(duplicate_id,canonical_id)
SELECT selected.duplicate_id,selected.canonical_id
FROM (
 SELECT duplicate.id duplicate_id,
  COALESCE(
   (SELECT canonical.id FROM phase106_live_categories wanted JOIN categories canonical ON canonical.slug=wanted.slug
    WHERE REGEXP_REPLACE(LOWER(TRIM(duplicate.slug)), '[^a-z0-9]+', '')=REGEXP_REPLACE(LOWER(wanted.slug), '[^a-z0-9]+', '')
    ORDER BY wanted.seq,canonical.id LIMIT 1),
   (SELECT canonical.id FROM phase106_live_categories wanted JOIN categories canonical ON canonical.slug=wanted.slug
    WHERE REGEXP_REPLACE(LOWER(TRIM(duplicate.name)), '[^a-z0-9]+', '')=REGEXP_REPLACE(LOWER(wanted.name), '[^a-z0-9]+', '')
    ORDER BY wanted.seq,canonical.id LIMIT 1)
  ) canonical_id
 FROM categories duplicate
) selected
WHERE selected.canonical_id IS NOT NULL AND selected.canonical_id<>selected.duplicate_id;
UPDATE products p JOIN phase106_live_duplicate_map m ON m.duplicate_id=p.category_id SET p.category_id=m.canonical_id;
INSERT IGNORE INTO coupon_restrictions(coupon_id,restrictable_type,restrictable_id,created_at)
 SELECT r.coupon_id,'category',m.canonical_id,r.created_at FROM coupon_restrictions r JOIN phase106_live_duplicate_map m ON m.duplicate_id=r.restrictable_id WHERE r.restrictable_type='category';
DELETE r FROM coupon_restrictions r JOIN phase106_live_duplicate_map m ON m.duplicate_id=r.restrictable_id WHERE r.restrictable_type='category';
UPDATE categories duplicate JOIN phase106_live_duplicate_map m ON m.duplicate_id=duplicate.id SET duplicate.is_active=0;
DROP TEMPORARY TABLE phase106_live_duplicate_map;
DROP TEMPORARY TABLE phase106_live_categories;

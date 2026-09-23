ALTER TABLE social_post_logs
  MODIFY product_id BIGINT NULL,
  ADD COLUMN custom_service_id BIGINT NULL AFTER product_id,
  ADD COLUMN listing_title VARCHAR(190) NULL AFTER custom_service_id,
  ADD KEY social_post_custom_service_idx(custom_service_id,platform);

UPDATE social_post_logs l
LEFT JOIN products p ON p.id=l.product_id
SET l.listing_title=COALESCE(p.title,CONCAT('Deleted product #',l.product_id))
WHERE l.listing_title IS NULL;

ALTER TABLE social_post_logs
  MODIFY listing_title VARCHAR(190) NOT NULL,
  ADD CONSTRAINT social_post_listing_identity_chk CHECK (
    (product_id IS NOT NULL AND custom_service_id IS NULL)
    OR (product_id IS NULL AND custom_service_id IS NOT NULL)
  );

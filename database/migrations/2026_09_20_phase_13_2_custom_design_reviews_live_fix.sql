-- Phase 13.2 live-testing fix: retain either product or Custom Design service identity.
ALTER TABLE seller_reviews
  MODIFY product_id BIGINT NULL,
  ADD COLUMN custom_service_id BIGINT NULL AFTER product_id,
  ADD KEY seller_reviews_custom_service_idx(custom_service_id),
  ADD CONSTRAINT seller_reviews_purchase_link_check
    CHECK ((product_id IS NOT NULL) <> (custom_service_id IS NOT NULL)),
  ADD CONSTRAINT seller_reviews_custom_service_fk
    FOREIGN KEY(custom_service_id) REFERENCES custom_design_services(id) ON DELETE RESTRICT;

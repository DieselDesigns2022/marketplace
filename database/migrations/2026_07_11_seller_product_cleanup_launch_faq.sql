-- Seller product cleanup, seller license presets, and PNG category consolidation.
-- Idempotent: safe to run more than once.

CREATE TABLE IF NOT EXISTS seller_license_presets (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    designer_id BIGINT NOT NULL,
    license_type_id BIGINT NOT NULL,
    is_enabled BOOLEAN DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_seller_license_preset (designer_id, license_type_id),
    KEY idx_seller_license_presets_designer (designer_id,is_enabled,sort_order),
    KEY idx_seller_license_presets_type (license_type_id)
);

INSERT INTO categories (name,slug,description,is_active,sort_order)
SELECT 'PNG Files','png-files','Transparent and print-ready PNG design files',1,20
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug='png-files');

UPDATE categories
SET name='PNG Files', description=COALESCE(NULLIF(description,''),'Transparent and print-ready PNG design files'), is_active=1, updated_at=now()
WHERE slug='png-files';

UPDATE products p
JOIN categories old ON old.id=p.category_id
JOIN categories final_png ON final_png.slug='png-files'
SET p.category_id=final_png.id
WHERE old.id<>final_png.id
  AND (old.slug IN ('sublimation','png','png-files') OR lower(old.name) IN ('png','png files'));

UPDATE categories old
JOIN categories final_png ON final_png.slug='png-files'
SET old.is_active=0, old.updated_at=now()
WHERE old.id<>final_png.id
  AND (old.slug IN ('sublimation','png','png-files') OR lower(old.name) IN ('png','png files'));

UPDATE categories SET name='PNG Files', is_active=1, updated_at=now() WHERE slug='png-files';

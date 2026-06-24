ALTER TABLE products
  ADD COLUMN short_description TEXT NULL AFTER slug,
  ADD COLUMN seo_title VARCHAR(70) NULL AFTER ai_disclosure,
  ADD COLUMN seo_description VARCHAR(170) NULL AFTER seo_title;

UPDATE products SET ai_disclosure='No AI Used' WHERE ai_disclosure IN ('Hand Drawn','Digitally Created');

ALTER TABLE products
  MODIFY ai_disclosure ENUM('No AI Used','AI Assisted','AI Generated') NOT NULL;

ALTER TABLE product_images
  ADD COLUMN alt_text VARCHAR(255) NULL AFTER image_path;

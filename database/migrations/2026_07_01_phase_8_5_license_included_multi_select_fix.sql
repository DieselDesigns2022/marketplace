-- Phase 8.5 correction: licenses are included permissions, not buyer-facing prices.
-- Preserve existing tables/data and normalize existing product license prices to free/included.

UPDATE product_license_types SET price = 0.00;

ALTER TABLE product_license_types MODIFY price DECIMAL(10,2) NOT NULL DEFAULT 0.00;

UPDATE products SET commercial_license_price = 0.00;

ALTER TABLE products MODIFY commercial_license_price DECIMAL(10,2) DEFAULT 0.00;

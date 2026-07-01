ALTER TABLE product_images
    ADD COLUMN original_image_path VARCHAR(255) NULL AFTER image_path,
    ADD COLUMN watermark_status VARCHAR(40) NULL AFTER original_image_path,
    ADD COLUMN watermark_error VARCHAR(255) NULL AFTER watermark_status;

ALTER TABLE designers
    ADD COLUMN facebook_url VARCHAR(255) NULL AFTER website_url,
    ADD COLUMN instagram_url VARCHAR(255) NULL AFTER facebook_url,
    ADD COLUMN tiktok_url VARCHAR(255) NULL AFTER instagram_url,
    ADD COLUMN pinterest_url VARCHAR(255) NULL AFTER tiktok_url,
    ADD COLUMN etsy_url VARCHAR(255) NULL AFTER pinterest_url,
    ADD COLUMN shopify_url VARCHAR(255) NULL AFTER etsy_url;

ALTER TABLE prize_donations
    ADD COLUMN IF NOT EXISTS preview_image_path VARCHAR(500) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS prize_file_storage_path VARCHAR(500) NULL AFTER preview_image_path,
    ADD COLUMN IF NOT EXISTS prize_file_original_name VARCHAR(255) NULL AFTER prize_file_storage_path,
    ADD COLUMN IF NOT EXISTS prize_file_mime_type VARCHAR(190) NULL AFTER prize_file_original_name,
    ADD COLUMN IF NOT EXISTS prize_file_size BIGINT UNSIGNED NULL AFTER prize_file_mime_type;

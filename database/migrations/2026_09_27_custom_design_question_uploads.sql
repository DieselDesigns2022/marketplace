ALTER TABLE custom_service_questions
ADD COLUMN allow_upload TINYINT(1) NOT NULL DEFAULT 0
AFTER is_required;

ALTER TABLE custom_order_files
ADD COLUMN reference_context_key VARCHAR(190) NULL
AFTER file_size,
ADD COLUMN reference_context_label VARCHAR(500) NULL
AFTER reference_context_key;

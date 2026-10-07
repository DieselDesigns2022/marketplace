-- Ordinary product schedules are UTC, separate from collaboration-event dates.
ALTER TABLE designers ADD COLUMN timezone VARCHAR(64) NULL;
ALTER TABLE products
 MODIFY status ENUM('draft','pending_review','scheduled','approved','published','rejected','disabled','archived','deleted') DEFAULT 'draft',
 ADD COLUMN scheduled_publish_at DATETIME NULL COMMENT 'UTC publication instant',
 ADD COLUMN publication_timezone VARCHAR(64) NULL,
 ADD COLUMN schedule_checked_at DATETIME NULL COMMENT 'UTC last eligibility check',
 ADD COLUMN schedule_error VARCHAR(1000) NULL,
 ADD KEY products_schedule_due_idx(status,scheduled_publish_at,id);

-- Repair fresh/older product schemas missing the existing editor's preview setting.
ALTER TABLE products ADD COLUMN IF NOT EXISTS extra_protection_watermark TINYINT(1) NOT NULL DEFAULT 0;

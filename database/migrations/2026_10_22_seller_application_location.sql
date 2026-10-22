ALTER TABLE designer_applications
    ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL AFTER desired_slug,
    ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL AFTER country;

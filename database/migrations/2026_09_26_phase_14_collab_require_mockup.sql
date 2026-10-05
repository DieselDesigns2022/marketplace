ALTER TABLE collab_events
ADD COLUMN require_mockup TINYINT(1) NOT NULL DEFAULT 0
AFTER minimum_file_count;

ALTER TABLE collab_events
ADD COLUMN host_timezone VARCHAR(64) NOT NULL DEFAULT 'America/New_York'
AFTER require_mockup;

UPDATE collab_events
SET host_timezone='America/New_York'
WHERE id=1;

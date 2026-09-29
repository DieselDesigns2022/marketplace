ALTER TABLE collab_events
ADD COLUMN quantity_limit INT UNSIGNED NULL AFTER price_cents;

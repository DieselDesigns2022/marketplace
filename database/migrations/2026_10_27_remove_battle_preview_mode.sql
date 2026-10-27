ALTER TABLE battle_events
    DROP INDEX battle_events_preview_idx,
    DROP COLUMN is_preview;

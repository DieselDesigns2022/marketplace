ALTER TABLE battle_events
    ADD COLUMN is_preview TINYINT(1) NOT NULL DEFAULT 0
    AFTER status,
    ADD KEY battle_events_preview_idx (is_preview,status);

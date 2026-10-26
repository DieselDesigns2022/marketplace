ALTER TABLE battle_events
    DROP CONSTRAINT battle_events_voting_minutes_chk;

ALTER TABLE battle_events
    DROP COLUMN voting_minutes;

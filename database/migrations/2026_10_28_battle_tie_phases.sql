ALTER TABLE battle_matchups ADD COLUMN voting_phase TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE battle_votes ADD COLUMN voting_phase TINYINT UNSIGNED NOT NULL DEFAULT 0,
 DROP INDEX battle_vote_one_per_matchup_uq,
 ADD UNIQUE KEY battle_vote_one_per_phase_uq (matchup_id,user_id,voting_phase);

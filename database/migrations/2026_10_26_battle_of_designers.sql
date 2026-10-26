CREATE TABLE battle_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,

    status ENUM(
        'signup',
        'seeded',
        'active',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'signup',

    voting_minutes INT UNSIGNED NULL,

    seed_token CHAR(64) NULL,
    bracket_size INT UNSIGNED NULL,
    total_rounds INT UNSIGNED NULL,
    current_round INT UNSIGNED NULL,

    champion_participant_id BIGINT NULL,
    prize_awarded_at DATETIME NULL,

    created_by_admin_id BIGINT NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY battle_events_slug_uq (slug),
    KEY battle_events_status_idx (status,created_at),

    CONSTRAINT battle_events_created_admin_fk
        FOREIGN KEY (created_by_admin_id)
        REFERENCES users(id),

    CONSTRAINT battle_events_voting_minutes_chk
        CHECK (voting_minutes IS NULL OR voting_minutes >= 1)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE battle_participants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    battle_id BIGINT NOT NULL,
    designer_id BIGINT NOT NULL,

    logo_path VARCHAR(500) NOT NULL,

    status ENUM(
        'active',
        'withdrawn',
        'champion'
    ) NOT NULL DEFAULT 'active',

    seed_position INT UNSIGNED NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY battle_participant_designer_uq (
        battle_id,
        designer_id
    ),

    KEY battle_participant_seed_idx (
        battle_id,
        seed_position
    ),

    CONSTRAINT battle_participant_event_fk
        FOREIGN KEY (battle_id)
        REFERENCES battle_events(id),

    CONSTRAINT battle_participant_designer_fk
        FOREIGN KEY (designer_id)
        REFERENCES designers(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE battle_rounds (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    battle_id BIGINT NOT NULL,
    round_number INT UNSIGNED NOT NULL,

    status ENUM(
        'pending',
        'open',
        'tied',
        'completed'
    ) NOT NULL DEFAULT 'pending',

    voting_opens_at DATETIME NULL,
    voting_closes_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY battle_round_number_uq (
        battle_id,
        round_number
    ),

    CONSTRAINT battle_round_event_fk
        FOREIGN KEY (battle_id)
        REFERENCES battle_events(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE battle_matchups (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,

    battle_id BIGINT NOT NULL,
    round_id BIGINT NOT NULL,
    round_number INT UNSIGNED NOT NULL,
    bracket_position INT UNSIGNED NOT NULL,

    participant_one_id BIGINT NULL,
    participant_two_id BIGINT NULL,
    winner_participant_id BIGINT NULL,

    status ENUM(
        'pending',
        'open',
        'tied',
        'completed'
    ) NOT NULL DEFAULT 'pending',

    voting_opens_at DATETIME NULL,
    voting_closes_at DATETIME NULL,

    tie_resolved_by_admin_id BIGINT NULL,
    tie_resolved_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY battle_matchup_position_uq (
        round_id,
        bracket_position
    ),

    KEY battle_matchup_event_round_idx (
        battle_id,
        round_number,
        status
    ),

    CONSTRAINT battle_matchup_event_fk
        FOREIGN KEY (battle_id)
        REFERENCES battle_events(id),

    CONSTRAINT battle_matchup_round_fk
        FOREIGN KEY (round_id)
        REFERENCES battle_rounds(id),

    CONSTRAINT battle_matchup_p1_fk
        FOREIGN KEY (participant_one_id)
        REFERENCES battle_participants(id),

    CONSTRAINT battle_matchup_p2_fk
        FOREIGN KEY (participant_two_id)
        REFERENCES battle_participants(id),

    CONSTRAINT battle_matchup_winner_fk
        FOREIGN KEY (winner_participant_id)
        REFERENCES battle_participants(id),

    CONSTRAINT battle_matchup_tie_admin_fk
        FOREIGN KEY (tie_resolved_by_admin_id)
        REFERENCES users(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE battle_votes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,

    battle_id BIGINT NOT NULL,
    matchup_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    participant_id BIGINT NOT NULL,

    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NOT NULL,
    fingerprint_hash CHAR(64) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY battle_vote_one_per_matchup_uq (
        matchup_id,
        user_id
    ),

    KEY battle_vote_matchup_choice_idx (
        matchup_id,
        participant_id
    ),

    KEY battle_vote_ip_time_idx (
        ip_address,
        created_at
    ),

    CONSTRAINT battle_vote_event_fk
        FOREIGN KEY (battle_id)
        REFERENCES battle_events(id),

    CONSTRAINT battle_vote_matchup_fk
        FOREIGN KEY (matchup_id)
        REFERENCES battle_matchups(id),

    CONSTRAINT battle_vote_user_fk
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    CONSTRAINT battle_vote_participant_fk
        FOREIGN KEY (participant_id)
        REFERENCES battle_participants(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE battle_vote_attempts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,

    battle_id BIGINT NOT NULL,
    matchup_id BIGINT NULL,
    user_id BIGINT NULL,

    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NOT NULL,
    fingerprint_hash CHAR(64) NOT NULL,

    outcome ENUM(
        'accepted',
        'duplicate',
        'closed',
        'invalid_choice',
        'rate_limited'
    ) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY battle_attempt_user_time_idx (
        user_id,
        created_at
    ),

    KEY battle_attempt_ip_time_idx (
        ip_address,
        created_at
    ),

    KEY battle_attempt_matchup_idx (
        matchup_id,
        created_at
    ),

    CONSTRAINT battle_attempt_event_fk
        FOREIGN KEY (battle_id)
        REFERENCES battle_events(id),

    CONSTRAINT battle_attempt_matchup_fk
        FOREIGN KEY (matchup_id)
        REFERENCES battle_matchups(id),

    CONSTRAINT battle_attempt_user_fk
        FOREIGN KEY (user_id)
        REFERENCES users(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE battle_vote_flags (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,

    battle_id BIGINT NOT NULL,
    matchup_id BIGINT NULL,

    signal_type ENUM(
        'ip_burst',
        'fingerprint_burst'
    ) NOT NULL,

    signal_hash CHAR(64) NOT NULL,
    event_count INT UNSIGNED NOT NULL,

    status ENUM(
        'open',
        'reviewed',
        'dismissed'
    ) NOT NULL DEFAULT 'open',

    reviewed_by_admin_id BIGINT NULL,
    reviewed_at DATETIME NULL,
    admin_notes VARCHAR(1000) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY battle_vote_flag_signal_uq (
        battle_id,
        matchup_id,
        signal_type,
        signal_hash,
        status
    ),

    KEY battle_vote_flag_status_idx (
        status,
        created_at
    ),

    CONSTRAINT battle_vote_flag_event_fk
        FOREIGN KEY (battle_id)
        REFERENCES battle_events(id),

    CONSTRAINT battle_vote_flag_matchup_fk
        FOREIGN KEY (matchup_id)
        REFERENCES battle_matchups(id),

    CONSTRAINT battle_vote_flag_admin_fk
        FOREIGN KEY (reviewed_by_admin_id)
        REFERENCES users(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


ALTER TABLE battle_events
    ADD CONSTRAINT battle_events_champion_fk
        FOREIGN KEY (champion_participant_id)
        REFERENCES battle_participants(id);

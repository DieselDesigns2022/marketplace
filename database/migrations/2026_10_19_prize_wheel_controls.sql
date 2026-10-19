ALTER TABLE designers
    ADD COLUMN IF NOT EXISTS prize_wheel_opt_in
        TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS prize_wheel_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    heading VARCHAR(190) NOT NULL DEFAULT 'Grand Opening Prize Wheel',
    description VARCHAR(1000) NULL,
    updated_by BIGINT NULL,
    enabled_at DATETIME NULL,
    disabled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT prize_wheel_settings_singleton_chk
        CHECK(id=1),

    CONSTRAINT prize_wheel_settings_admin_fk
        FOREIGN KEY(updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO prize_wheel_settings
(
    id,
    is_enabled,
    heading,
    description
)
VALUES
(
    1,
    0,
    'Grand Opening Prize Wheel',
    'Spin the Creative Moth Grand Opening Prize Wheel for a chance to win!'
);

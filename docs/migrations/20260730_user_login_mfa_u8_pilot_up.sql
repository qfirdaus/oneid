CREATE TABLE user_login_mfa_pilot_users (
    u_id VARCHAR(20) NOT NULL,
    pilot_category VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    pilot_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE',
    enrolled_by VARCHAR(20) NOT NULL,
    change_reference VARCHAR(100) NOT NULL,
    enrolled_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    removed_at DATETIME(6) NULL,
    PRIMARY KEY (u_id),
    KEY idx_user_mfa_pilot_status (pilot_status, pilot_category),
    CONSTRAINT fk_user_mfa_pilot_user
        FOREIGN KEY (u_id) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_mfa_pilot_actor
        FOREIGN KEY (enrolled_by) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_pilot_category CHECK (
        pilot_category IN (
            'STAFF','LECTURER','LOCAL_STUDENT','INTERNATIONAL_STUDENT'
        )
    ),
    CONSTRAINT chk_user_mfa_pilot_status
        CHECK (pilot_status IN ('ACTIVE','REMOVED')),
    CONSTRAINT chk_user_mfa_pilot_terminal CHECK (
        (pilot_status='ACTIVE' AND removed_at IS NULL)
        OR (pilot_status='REMOVED' AND removed_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

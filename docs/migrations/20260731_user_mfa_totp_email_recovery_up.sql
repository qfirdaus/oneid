CREATE TABLE user_mfa_recovery_challenges (
    challenge_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    u_id VARCHAR(20) NOT NULL,
    purpose VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    destination_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    session_binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    browser_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requesting_ip VARCHAR(45) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    sent_at DATETIME(6) NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (challenge_id),
    UNIQUE KEY uq_user_mfa_recovery_correlation (correlation_id),
    KEY idx_user_mfa_recovery_user_time (u_id,purpose,created_at),
    KEY idx_user_mfa_recovery_limits (requesting_ip,created_at),
    KEY idx_user_mfa_recovery_cleanup (expires_at,consumed_at,revoked_at),
    CONSTRAINT fk_user_mfa_recovery_user
        FOREIGN KEY (u_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_recovery_purpose
        CHECK (purpose='TOTP_RECOVERY'),
    CONSTRAINT chk_user_mfa_recovery_attempts
        CHECK (attempts<=max_attempts AND max_attempts BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

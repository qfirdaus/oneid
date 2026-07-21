ALTER TABLE sys_config
    ADD COLUMN admin_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0
    AFTER password_reset_email_enabled,
    ADD CONSTRAINT chk_sys_config_admin_2fa_enabled
        CHECK (admin_2fa_enabled IN (0,1));

CREATE TABLE admin_mfa_factors (
    factor_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id VARCHAR(20) NOT NULL,
    factor_type VARCHAR(16) NOT NULL,
    encrypted_secret VARBINARY(512) NOT NULL,
    secret_nonce BINARY(24) NOT NULL,
    key_version VARCHAR(32) NOT NULL,
    factor_status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
    device_label VARCHAR(100) NULL,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_by VARCHAR(20) NOT NULL,
    correlation_id CHAR(16) NOT NULL,
    PRIMARY KEY (factor_id),
    UNIQUE KEY uq_admin_mfa_factor_correlation (correlation_id),
    KEY idx_admin_mfa_factor_admin_status (admin_user_id, factor_status),
    KEY idx_admin_mfa_factor_key_version (key_version, factor_status),
    CONSTRAINT fk_admin_mfa_factor_user
        FOREIGN KEY (admin_user_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_admin_mfa_factor_type CHECK (factor_type IN ('TOTP')),
    CONSTRAINT chk_admin_mfa_factor_status
        CHECK (factor_status IN ('PENDING','ACTIVE','REVOKED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE admin_mfa_preferences (
    admin_user_id VARCHAR(20) NOT NULL,
    preferred_factor VARCHAR(16) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(20) NOT NULL,
    correlation_id CHAR(16) NOT NULL,
    PRIMARY KEY (admin_user_id),
    UNIQUE KEY uq_admin_mfa_preference_correlation (correlation_id),
    CONSTRAINT fk_admin_mfa_preference_user
        FOREIGN KEY (admin_user_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_admin_mfa_preferred_factor
        CHECK (preferred_factor IN ('EMAIL_OTP','TOTP'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE admin_step_up_challenges (
    challenge_id CHAR(64) NOT NULL,
    admin_user_id VARCHAR(20) NOT NULL,
    purpose VARCHAR(40) NOT NULL,
    factor_type VARCHAR(16) NOT NULL,
    otp_hash VARCHAR(255) NULL,
    session_binding_hash CHAR(64) NOT NULL,
    browser_digest CHAR(64) NOT NULL,
    requesting_ip VARCHAR(45) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    resend_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    revoked_at DATETIME NULL,
    correlation_id CHAR(16) NOT NULL,
    PRIMARY KEY (challenge_id),
    UNIQUE KEY uq_admin_step_up_challenge_correlation (correlation_id),
    KEY idx_admin_step_up_challenge_admin_expiry
        (admin_user_id, expires_at),
    KEY idx_admin_step_up_challenge_session
        (session_binding_hash, purpose, expires_at),
    KEY idx_admin_step_up_challenge_cleanup
        (expires_at, consumed_at, revoked_at),
    CONSTRAINT fk_admin_step_up_challenge_user
        FOREIGN KEY (admin_user_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_admin_step_up_challenge_purpose
        CHECK (purpose IN ('ADMIN_ACCESS','SECURITY_CONFIGURATION_CHANGE','ACTIVE_SESSION_REVOCATION')),
    CONSTRAINT chk_admin_step_up_challenge_factor
        CHECK (factor_type IN ('EMAIL_OTP','TOTP')),
    CONSTRAINT chk_admin_step_up_challenge_attempts
        CHECK (attempts <= max_attempts AND max_attempts BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE admin_step_up_grants (
    grant_id CHAR(64) NOT NULL,
    admin_user_id VARCHAR(20) NOT NULL,
    session_binding_hash CHAR(64) NOT NULL,
    browser_digest CHAR(64) NOT NULL,
    purpose VARCHAR(40) NOT NULL,
    verified_factor VARCHAR(16) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    correlation_id CHAR(16) NOT NULL,
    PRIMARY KEY (grant_id),
    UNIQUE KEY uq_admin_step_up_grant_correlation (correlation_id),
    KEY idx_admin_step_up_grant_authorization
        (admin_user_id, session_binding_hash, purpose, expires_at),
    KEY idx_admin_step_up_grant_cleanup (expires_at, revoked_at),
    CONSTRAINT fk_admin_step_up_grant_user
        FOREIGN KEY (admin_user_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_admin_step_up_grant_purpose
        CHECK (purpose IN ('ADMIN_ACCESS','SECURITY_CONFIGURATION_CHANGE','ACTIVE_SESSION_REVOCATION')),
    CONSTRAINT chk_admin_step_up_grant_factor
        CHECK (verified_factor IN ('EMAIL_OTP','TOTP'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

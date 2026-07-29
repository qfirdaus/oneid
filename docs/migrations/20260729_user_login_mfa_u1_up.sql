CREATE TABLE user_login_mfa_policy (
    singleton_key TINYINT UNSIGNED NOT NULL DEFAULT 1,
    policy_mode VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'OFF',
    login_scope VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PASSWORD_ONLY',
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    pending_ttl_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 300,
    otp_ttl_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 300,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    resend_cooldown_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    hourly_send_limit SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    readiness_reference VARCHAR(100) NULL,
    updated_by VARCHAR(20) NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (singleton_key),
    CONSTRAINT fk_user_mfa_policy_updated_by
        FOREIGN KEY (updated_by) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_user_mfa_policy_singleton CHECK (singleton_key = 1),
    CONSTRAINT chk_user_mfa_policy_mode
        CHECK (policy_mode IN ('OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED')),
    CONSTRAINT chk_user_mfa_policy_scope CHECK (login_scope = 'PASSWORD_ONLY'),
    CONSTRAINT chk_user_mfa_policy_email CHECK (email_enabled IN (0,1)),
    CONSTRAINT chk_user_mfa_policy_totp CHECK (totp_enabled IN (0,1)),
    CONSTRAINT chk_user_mfa_policy_email_required
        CHECK (policy_mode = 'OFF' OR email_enabled = 1),
    CONSTRAINT chk_user_mfa_policy_limits CHECK (
        pending_ttl_seconds BETWEEN 60 AND 900
        AND otp_ttl_seconds BETWEEN 60 AND 900
        AND max_attempts BETWEEN 1 AND 10
        AND resend_cooldown_seconds BETWEEN 30 AND 300
        AND hourly_send_limit BETWEEN 1 AND 30
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO user_login_mfa_policy (
    singleton_key, policy_mode, login_scope, email_enabled, totp_enabled,
    pending_ttl_seconds, otp_ttl_seconds, max_attempts,
    resend_cooldown_seconds, hourly_send_limit, configuration_version
) VALUES (1, 'OFF', 'PASSWORD_ONLY', 1, 0, 300, 300, 5, 60, 10, 1);

CREATE TABLE user_login_mfa_policy_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    configuration_version BIGINT UNSIGNED NOT NULL,
    previous_policy JSON NULL,
    resulting_policy JSON NOT NULL,
    changed_by VARCHAR(20) NOT NULL,
    change_reason VARCHAR(500) NOT NULL,
    change_reference VARCHAR(100) NOT NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (history_id),
    UNIQUE KEY uq_user_mfa_policy_history_version (configuration_version),
    UNIQUE KEY uq_user_mfa_policy_history_correlation (correlation_id),
    KEY idx_user_mfa_policy_history_time (changed_at),
    CONSTRAINT fk_user_mfa_policy_history_actor
        FOREIGN KEY (changed_by) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE user_mfa_factors (
    factor_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    u_id VARCHAR(20) NOT NULL,
    factor_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    encrypted_secret VARBINARY(512) NOT NULL,
    secret_nonce BINARY(24) NOT NULL,
    key_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    factor_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PENDING',
    device_label VARCHAR(100) NULL,
    enrollment_session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    enrollment_browser_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    last_used_time_step BIGINT UNSIGNED NULL,
    enrolled_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    confirmed_at DATETIME(6) NULL,
    last_used_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    active_user_slot VARCHAR(20)
        GENERATED ALWAYS AS (
            CASE WHEN factor_status = 'ACTIVE' THEN u_id ELSE NULL END
        ) STORED,
    PRIMARY KEY (factor_id),
    UNIQUE KEY uq_user_mfa_factor_correlation (correlation_id),
    UNIQUE KEY uq_user_mfa_single_active_totp (active_user_slot, factor_type),
    KEY idx_user_mfa_factor_user_status (u_id, factor_status),
    KEY idx_user_mfa_factor_key_status (key_version, factor_status),
    CONSTRAINT fk_user_mfa_factor_user
        FOREIGN KEY (u_id) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_factor_type CHECK (factor_type = 'TOTP'),
    CONSTRAINT chk_user_mfa_factor_status
        CHECK (factor_status IN ('PENDING','ACTIVE','REVOKED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE user_mfa_preferences (
    u_id VARCHAR(20) NOT NULL,
    preferred_factor VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'EMAIL_OTP',
    configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (u_id),
    UNIQUE KEY uq_user_mfa_preference_correlation (correlation_id),
    CONSTRAINT fk_user_mfa_preference_user
        FOREIGN KEY (u_id) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_preferred_factor
        CHECK (preferred_factor IN ('EMAIL_OTP','TOTP'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE user_login_mfa_transactions (
    transaction_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    u_id VARCHAR(20) NOT NULL,
    primary_method VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    transaction_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PENDING',
    session_binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    browser_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requesting_ip VARCHAR(45) NOT NULL,
    policy_version BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (transaction_id),
    UNIQUE KEY uq_user_mfa_transaction_correlation (correlation_id),
    KEY idx_user_mfa_transaction_user_expiry (u_id, expires_at),
    KEY idx_user_mfa_transaction_session
        (session_binding_hash, transaction_status, expires_at),
    KEY idx_user_mfa_transaction_cleanup
        (transaction_status, expires_at, consumed_at, revoked_at),
    CONSTRAINT fk_user_mfa_transaction_user
        FOREIGN KEY (u_id) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_primary_method CHECK (primary_method = 'PASSWORD'),
    CONSTRAINT chk_user_mfa_transaction_status
        CHECK (transaction_status IN ('PENDING','VERIFIED','CONSUMED','EXPIRED','REVOKED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE user_login_mfa_challenges (
    challenge_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    transaction_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    u_id VARCHAR(20) NOT NULL,
    factor_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    factor_id BIGINT UNSIGNED NULL,
    otp_hash VARCHAR(255) NULL,
    destination_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    resend_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    sent_at DATETIME(6) NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (challenge_id),
    UNIQUE KEY uq_user_mfa_challenge_correlation (correlation_id),
    KEY idx_user_mfa_challenge_user_factor_time (u_id, factor_type, created_at),
    KEY idx_user_mfa_challenge_cleanup
        (expires_at, consumed_at, revoked_at),
    CONSTRAINT fk_user_mfa_challenge_transaction
        FOREIGN KEY (transaction_id) REFERENCES user_login_mfa_transactions (transaction_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_mfa_challenge_user
        FOREIGN KEY (u_id) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_mfa_challenge_factor
        FOREIGN KEY (factor_id) REFERENCES user_mfa_factors (factor_id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_user_mfa_challenge_factor_type
        CHECK (factor_type IN ('EMAIL_OTP','TOTP')),
    CONSTRAINT chk_user_mfa_challenge_attempts
        CHECK (attempts <= max_attempts AND max_attempts BETWEEN 1 AND 10),
    CONSTRAINT chk_user_mfa_challenge_material CHECK (
        (factor_type = 'EMAIL_OTP' AND otp_hash IS NOT NULL AND destination_hmac IS NOT NULL)
        OR (factor_type = 'TOTP' AND otp_hash IS NULL AND destination_hmac IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

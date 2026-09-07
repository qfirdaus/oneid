-- MR2 dormant schema only. This migration does not activate or change User MFA.

ALTER TABLE user_login_mfa_policy
    DROP CHECK chk_user_mfa_policy_mode,
    ADD CONSTRAINT chk_user_mfa_policy_mode CHECK (
        policy_mode IN ('OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED','EMERGENCY_BYPASS')
    );

CREATE TABLE maintenance_mfa_policy (
    singleton_key TINYINT UNSIGNED NOT NULL DEFAULT 1,
    policy_mode VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ENFORCED',
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 1,
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
    CONSTRAINT fk_maintenance_mfa_policy_actor
        FOREIGN KEY (updated_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_maintenance_mfa_policy_singleton CHECK (singleton_key=1),
    CONSTRAINT chk_maintenance_mfa_policy_mode CHECK (policy_mode IN ('ENFORCED','DISABLED')),
    CONSTRAINT chk_maintenance_mfa_policy_email CHECK (email_enabled=1),
    CONSTRAINT chk_maintenance_mfa_policy_totp CHECK (totp_enabled IN (0,1)),
    CONSTRAINT chk_maintenance_mfa_policy_limits CHECK (
        pending_ttl_seconds BETWEEN 60 AND 900
        AND otp_ttl_seconds BETWEEN 60 AND 900
        AND max_attempts BETWEEN 1 AND 10
        AND resend_cooldown_seconds BETWEEN 30 AND 300
        AND hourly_send_limit BETWEEN 1 AND 30
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO maintenance_mfa_policy (
    singleton_key,policy_mode,email_enabled,totp_enabled,pending_ttl_seconds,
    otp_ttl_seconds,max_attempts,resend_cooldown_seconds,hourly_send_limit,
    configuration_version,readiness_reference
) VALUES (1,'ENFORCED',1,1,300,300,5,60,10,1,'ONEID-MR2-DORMANT-20260907');

CREATE TABLE maintenance_mfa_policy_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    configuration_version BIGINT UNSIGNED NOT NULL,
    previous_policy JSON NULL,
    resulting_policy JSON NOT NULL,
    changed_by VARCHAR(20) NOT NULL,
    change_reason VARCHAR(500) NOT NULL,
    change_reference VARCHAR(100) NOT NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (history_id),
    UNIQUE KEY uq_maintenance_mfa_history_version (configuration_version),
    UNIQUE KEY uq_maintenance_mfa_history_correlation (correlation_id),
    KEY idx_maintenance_mfa_history_time (changed_at),
    CONSTRAINT fk_maintenance_mfa_history_actor
        FOREIGN KEY (changed_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_maintenance_mfa_history_reason
        CHECK (CHAR_LENGTH(TRIM(change_reason)) BETWEEN 10 AND 500),
    CONSTRAINT chk_maintenance_mfa_history_reference
        CHECK (CHAR_LENGTH(TRIM(change_reference)) BETWEEN 8 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE user_mfa_policy_change_requests (
    request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    environment VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    previous_mode VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requested_mode VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    restore_mode VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL,
    transition_strategy VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    grace_until DATETIME(6) NULL,
    starts_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NULL,
    request_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PENDING_APPROVAL',
    requested_by VARCHAR(20) NOT NULL,
    approved_by VARCHAR(20) NULL,
    change_reason VARCHAR(500) NOT NULL,
    change_reference VARCHAR(100) NOT NULL,
    expected_policy_version BIGINT UNSIGNED NOT NULL,
    applied_policy_version BIGINT UNSIGNED NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requested_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    approved_at DATETIME(6) NULL,
    activated_at DATETIME(6) NULL,
    restored_at DATETIME(6) NULL,
    rejected_at DATETIME(6) NULL,
    cancelled_at DATETIME(6) NULL,
    last_error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    open_environment_slot VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin
        GENERATED ALWAYS AS (
            CASE WHEN request_status IN ('PENDING_APPROVAL','APPROVED','ACTIVE')
                 THEN environment ELSE NULL END
        ) STORED,
    PRIMARY KEY (request_id),
    UNIQUE KEY uq_user_mfa_change_correlation (correlation_id),
    UNIQUE KEY uq_user_mfa_change_open_environment (open_environment_slot),
    KEY idx_user_mfa_change_schedule (request_status,starts_at,expires_at),
    KEY idx_user_mfa_change_reference (change_reference),
    CONSTRAINT fk_user_mfa_change_requester
        FOREIGN KEY (requested_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_mfa_change_approver
        FOREIGN KEY (approved_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_change_environment
        CHECK (environment IN ('local','staging','production')),
    CONSTRAINT chk_user_mfa_change_previous
        CHECK (previous_mode IN ('OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED')),
    CONSTRAINT chk_user_mfa_change_requested
        CHECK (requested_mode IN ('OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED','EMERGENCY_BYPASS')),
    CONSTRAINT chk_user_mfa_change_restore
        CHECK (restore_mode IS NULL OR restore_mode IN ('OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED')),
    CONSTRAINT chk_user_mfa_change_strategy
        CHECK (transition_strategy IN ('GRACE','IMMEDIATE')),
    CONSTRAINT chk_user_mfa_change_status CHECK (
        request_status IN ('PENDING_APPROVAL','APPROVED','ACTIVE','RESTORED','REJECTED','CANCELLED','FAILED')
    ),
    CONSTRAINT chk_user_mfa_change_reason
        CHECK (CHAR_LENGTH(TRIM(change_reason)) BETWEEN 10 AND 500),
    CONSTRAINT chk_user_mfa_change_reference
        CHECK (CHAR_LENGTH(TRIM(change_reference)) BETWEEN 8 AND 100),
    CONSTRAINT chk_user_mfa_change_window CHECK (
        (requested_mode='EMERGENCY_BYPASS'
            AND restore_mode IS NOT NULL
            AND expires_at IS NOT NULL
            AND expires_at>starts_at
            AND expires_at<=starts_at+INTERVAL 8 HOUR)
        OR
        (requested_mode<>'EMERGENCY_BYPASS' AND restore_mode IS NULL AND expires_at IS NULL)
    ),
    CONSTRAINT chk_user_mfa_change_grace CHECK (
        (transition_strategy='IMMEDIATE' AND grace_until IS NULL)
        OR
        (transition_strategy='GRACE' AND grace_until IS NOT NULL
            AND grace_until>=starts_at AND grace_until<=starts_at+INTERVAL 5 MINUTE)
    ),
    CONSTRAINT chk_user_mfa_change_approval CHECK (
        (approved_by IS NULL AND approved_at IS NULL)
        OR (approved_by IS NOT NULL AND approved_at IS NOT NULL)
    ),
    CONSTRAINT chk_user_mfa_change_self_approval CHECK (
        environment<>'production' OR approved_by IS NULL OR requested_by<>approved_by
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE user_mfa_policy_change_approvals (
    approval_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    decided_by VARCHAR(20) NOT NULL,
    decision_reason VARCHAR(500) NOT NULL,
    expected_policy_version BIGINT UNSIGNED NOT NULL,
    request_payload_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    decided_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (approval_id),
    UNIQUE KEY uq_user_mfa_approval_correlation (correlation_id),
    KEY idx_user_mfa_approval_request (request_id,decided_at),
    CONSTRAINT fk_user_mfa_approval_request
        FOREIGN KEY (request_id) REFERENCES user_mfa_policy_change_requests(request_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_mfa_approval_actor
        FOREIGN KEY (decided_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_approval_decision CHECK (decision IN ('APPROVED','REJECTED')),
    CONSTRAINT chk_user_mfa_approval_reason
        CHECK (CHAR_LENGTH(TRIM(decision_reason)) BETWEEN 10 AND 500),
    CONSTRAINT chk_user_mfa_approval_digest
        CHECK (request_payload_digest REGEXP '^[a-f0-9]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE user_mfa_policy_transition_runs (
    run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    run_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    run_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_number SMALLINT UNSIGNED NOT NULL,
    planned_transactions BIGINT UNSIGNED NOT NULL DEFAULT 0,
    planned_challenges BIGINT UNSIGNED NOT NULL DEFAULT 0,
    executed_transactions BIGINT UNSIGNED NOT NULL DEFAULT 0,
    executed_challenges BIGINT UNSIGNED NOT NULL DEFAULT 0,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME(6) NULL,
    PRIMARY KEY (run_id),
    UNIQUE KEY uq_user_mfa_transition_attempt (request_id,run_type,attempt_number),
    UNIQUE KEY uq_user_mfa_transition_correlation (correlation_id),
    KEY idx_user_mfa_transition_worker (run_status,started_at),
    CONSTRAINT fk_user_mfa_transition_request
        FOREIGN KEY (request_id) REFERENCES user_mfa_policy_change_requests(request_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_transition_type
        CHECK (run_type IN ('ACTIVATE','GRACE_REVOKE','AUTO_RESTORE','MANUAL_RESTORE')),
    CONSTRAINT chk_user_mfa_transition_status
        CHECK (run_status IN ('STARTED','SUCCEEDED','FAILED','NOOP')),
    CONSTRAINT chk_user_mfa_transition_attempt CHECK (attempt_number BETWEEN 1 AND 100),
    CONSTRAINT chk_user_mfa_transition_completion CHECK (
        (run_status='STARTED' AND completed_at IS NULL)
        OR (run_status<>'STARTED' AND completed_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

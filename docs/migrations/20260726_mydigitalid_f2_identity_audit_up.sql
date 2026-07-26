CREATE TABLE user_federated_identity (
    identity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    u_id VARCHAR(20) NOT NULL,
    provider_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    issuer VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nric_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    hmac_key_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    identity_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE',
    first_verified_at DATETIME(6) NOT NULL,
    last_verified_at DATETIME(6) NOT NULL,
    last_login_at DATETIME(6) NULL,
    login_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (identity_id),
    UNIQUE KEY uq_federated_provider_subject (
        provider_code, issuer, subject_hmac
    ),
    UNIQUE KEY uq_federated_provider_user (provider_code, u_id),
    KEY idx_federated_user_status (u_id, identity_status),
    CONSTRAINT fk_federated_identity_user
        FOREIGN KEY (u_id) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_federated_identity_status
        CHECK (identity_status IN ('ACTIVE', 'REVOKED')),
    CONSTRAINT chk_federated_login_count
        CHECK (login_count >= 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE federated_auth_event (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identity_id BIGINT UNSIGNED NULL,
    u_id VARCHAR(20) NULL,
    provider_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    nric_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    hmac_key_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ip_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    user_agent_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    session_id_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (event_id),
    UNIQUE KEY uq_federated_event_correlation (correlation_id),
    KEY idx_federated_event_user_time (u_id, occurred_at),
    KEY idx_federated_event_outcome_time (outcome, occurred_at),
    KEY idx_federated_event_reason_time (reason_code, occurred_at),
    KEY idx_federated_event_identity_time (identity_id, occurred_at),
    CONSTRAINT fk_federated_event_identity
        FOREIGN KEY (identity_id)
        REFERENCES user_federated_identity (identity_id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT fk_federated_event_user
        FOREIGN KEY (u_id) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_federated_event_outcome
        CHECK (outcome IN ('SUCCESS', 'REJECTED', 'ERROR'))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;

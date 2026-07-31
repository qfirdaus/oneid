CREATE TABLE user_login_mfa_exemptions (
    exemption_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    u_id VARCHAR(20) NOT NULL,
    exemption_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE',
    starts_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    expires_at DATETIME(6) NOT NULL,
    approved_by VARCHAR(20) NOT NULL,
    change_reason VARCHAR(500) NOT NULL,
    change_reference VARCHAR(100) NOT NULL,
    compensating_control VARCHAR(500) NOT NULL,
    revoked_by VARCHAR(20) NULL,
    revoked_at DATETIME(6) NULL,
    revoke_reason VARCHAR(500) NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    active_user_slot VARCHAR(20)
        GENERATED ALWAYS AS (
            CASE WHEN exemption_status = 'ACTIVE' THEN u_id ELSE NULL END
        ) STORED,
    PRIMARY KEY (exemption_id),
    UNIQUE KEY uq_user_mfa_exemption_active (active_user_slot),
    UNIQUE KEY uq_user_mfa_exemption_correlation (correlation_id),
    KEY idx_user_mfa_exemption_expiry (exemption_status,expires_at),
    KEY idx_user_mfa_exemption_history (u_id,created_at),
    CONSTRAINT fk_user_mfa_exemption_user
        FOREIGN KEY (u_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_mfa_exemption_approver
        FOREIGN KEY (approved_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_mfa_exemption_revoker
        FOREIGN KEY (revoked_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_exemption_status
        CHECK (exemption_status IN ('ACTIVE','REVOKED','EXPIRED')),
    CONSTRAINT chk_user_mfa_exemption_window
        CHECK (expires_at > starts_at AND expires_at <= starts_at + INTERVAL 72 HOUR),
    CONSTRAINT chk_user_mfa_exemption_revoke
        CHECK (
            (exemption_status = 'ACTIVE' AND revoked_by IS NULL AND revoked_at IS NULL)
            OR
            (exemption_status = 'EXPIRED' AND revoked_by IS NULL AND revoked_at IS NULL)
            OR
            (exemption_status = 'REVOKED' AND revoked_by IS NOT NULL
                AND revoked_at IS NOT NULL AND revoke_reason IS NOT NULL)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

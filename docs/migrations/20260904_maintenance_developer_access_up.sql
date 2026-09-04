CREATE TABLE maintenance_developer_access_grants (
    grant_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    u_id VARCHAR(20) NOT NULL,
    grant_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE',
    valid_from DATETIME(6) NOT NULL,
    valid_until DATETIME(6) NOT NULL,
    approved_by VARCHAR(20) NOT NULL,
    change_reason VARCHAR(500) NOT NULL,
    change_reference VARCHAR(100) NOT NULL,
    configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revoked_by VARCHAR(20) NULL,
    revoked_at DATETIME(6) NULL,
    revoke_reason VARCHAR(500) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    active_user_slot VARCHAR(20)
        GENERATED ALWAYS AS (
            CASE WHEN grant_status = 'ACTIVE' THEN u_id ELSE NULL END
        ) STORED,
    PRIMARY KEY (grant_id),
    UNIQUE KEY uq_maintenance_developer_active_user (active_user_slot),
    UNIQUE KEY uq_maintenance_developer_grant_correlation (correlation_id),
    KEY idx_maintenance_developer_effective (grant_status,valid_from,valid_until),
    KEY idx_maintenance_developer_history (u_id,created_at),
    CONSTRAINT fk_maintenance_developer_user
        FOREIGN KEY (u_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_developer_approver
        FOREIGN KEY (approved_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_developer_revoker
        FOREIGN KEY (revoked_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_maintenance_developer_status
        CHECK (grant_status IN ('ACTIVE','EXPIRED','REVOKED')),
    CONSTRAINT chk_maintenance_developer_window
        CHECK (
            valid_until > valid_from
            AND valid_until <= valid_from + INTERVAL 30 DAY
        ),
    CONSTRAINT chk_maintenance_developer_reason
        CHECK (CHAR_LENGTH(TRIM(change_reason)) BETWEEN 10 AND 500),
    CONSTRAINT chk_maintenance_developer_reference
        CHECK (CHAR_LENGTH(TRIM(change_reference)) BETWEEN 8 AND 100),
    CONSTRAINT chk_maintenance_developer_version
        CHECK (configuration_version >= 1),
    CONSTRAINT chk_maintenance_developer_revocation
        CHECK (
            (grant_status IN ('ACTIVE','EXPIRED')
                AND revoked_by IS NULL AND revoked_at IS NULL AND revoke_reason IS NULL)
            OR
            (grant_status = 'REVOKED'
                AND revoked_by IS NOT NULL AND revoked_at IS NOT NULL
                AND CHAR_LENGTH(TRIM(revoke_reason)) BETWEEN 10 AND 500)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE maintenance_developer_access_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    grant_id BIGINT UNSIGNED NOT NULL,
    u_id VARCHAR(20) NOT NULL,
    action_name VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_user_id VARCHAR(20) NULL,
    configuration_version_before BIGINT UNSIGNED NULL,
    configuration_version_after BIGINT UNSIGNED NOT NULL,
    change_reason VARCHAR(500) NOT NULL,
    change_reference VARCHAR(100) NOT NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (history_id),
    UNIQUE KEY uq_maintenance_developer_history_correlation (correlation_id),
    KEY idx_maintenance_developer_audit_grant (grant_id,occurred_at),
    KEY idx_maintenance_developer_audit_user (u_id,occurred_at),
    CONSTRAINT fk_maintenance_developer_history_grant
        FOREIGN KEY (grant_id) REFERENCES maintenance_developer_access_grants(grant_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_developer_history_user
        FOREIGN KEY (u_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_developer_history_actor
        FOREIGN KEY (actor_user_id) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_maintenance_developer_history_action
        CHECK (action_name IN ('GRANTED','REVOKED','EXPIRED')),
    CONSTRAINT chk_maintenance_developer_history_version
        CHECK (
            configuration_version_after >= 1
            AND (
                configuration_version_before IS NULL
                OR configuration_version_after = configuration_version_before + 1
            )
        ),
    CONSTRAINT chk_maintenance_developer_history_reason
        CHECK (CHAR_LENGTH(TRIM(change_reason)) BETWEEN 10 AND 500),
    CONSTRAINT chk_maintenance_developer_history_reference
        CHECK (CHAR_LENGTH(TRIM(change_reference)) BETWEEN 8 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

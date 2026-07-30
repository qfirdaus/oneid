CREATE TABLE user_login_mfa_category_policy (
    category_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    enforcement_enabled TINYINT(1) NOT NULL DEFAULT 1,
    configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    change_reference VARCHAR(100) NULL,
    updated_by VARCHAR(20) NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (category_code),
    CONSTRAINT fk_user_mfa_category_policy_actor
        FOREIGN KEY (updated_by) REFERENCES user_tbl (u_id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_user_mfa_category_code
        CHECK (category_code IN ('STAFF','STUDENT')),
    CONSTRAINT chk_user_mfa_category_enabled
        CHECK (enforcement_enabled IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO user_login_mfa_category_policy(
    category_code,enforcement_enabled,configuration_version
) VALUES
    ('STAFF',1,1),
    ('STUDENT',1,1);

CREATE TABLE user_login_mfa_category_policy_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    configuration_version BIGINT UNSIGNED NOT NULL,
    previous_enabled TINYINT(1) NOT NULL,
    resulting_enabled TINYINT(1) NOT NULL,
    changed_by VARCHAR(20) NOT NULL,
    change_reason VARCHAR(500) NOT NULL,
    change_reference VARCHAR(100) NOT NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (history_id),
    UNIQUE KEY uq_user_mfa_category_history_version
        (category_code,configuration_version),
    UNIQUE KEY uq_user_mfa_category_history_correlation (correlation_id),
    CONSTRAINT fk_user_mfa_category_history_category
        FOREIGN KEY (category_code)
        REFERENCES user_login_mfa_category_policy(category_code)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_mfa_category_history_actor
        FOREIGN KEY (changed_by) REFERENCES user_tbl(u_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_user_mfa_category_history_flags
        CHECK (previous_enabled IN (0,1) AND resulting_enabled IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

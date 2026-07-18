ALTER TABLE sys_config
    ADD COLUMN configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER singleton_key;

CREATE TABLE configuration_change_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    configuration_version_before BIGINT UNSIGNED NULL,
    configuration_version_after BIGINT UNSIGNED NULL,
    actor_id VARCHAR(20) NOT NULL,
    ip_address VARCHAR(50) NOT NULL,
    action_name VARCHAR(64) NOT NULL,
    outcome VARCHAR(16) NOT NULL,
    reason_code VARCHAR(64) NOT NULL,
    change_reason VARCHAR(500) NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    correlation_id CHAR(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    UNIQUE KEY uq_configuration_history_correlation (correlation_id),
    KEY idx_configuration_history_created (created_at, history_id),
    KEY idx_configuration_history_outcome (outcome, created_at),
    CONSTRAINT chk_configuration_history_outcome CHECK (outcome IN ('SUCCESS','REJECTED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

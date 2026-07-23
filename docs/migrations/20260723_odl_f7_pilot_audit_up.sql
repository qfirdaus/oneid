-- ODL F7 implementation-only audit foundation. No pilot user is written.
CREATE TABLE user_external_identity_event (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    correlation_id CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_code VARCHAR(50) NOT NULL,
    u_id VARCHAR(20) NOT NULL,
    external_user_id VARCHAR(20) NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rolled_back_at DATETIME NULL,
    PRIMARY KEY (event_id),
    UNIQUE KEY uq_external_identity_event_correlation_user
        (correlation_id, source_code, external_user_id),
    KEY idx_external_identity_event_user (u_id, created_at),
    KEY idx_external_identity_event_source (source_code, created_at),
    CONSTRAINT chk_external_identity_event_type CHECK (
        event_type IN ('PILOT_NEW','PILOT_ROLLED_BACK')
    ),
    CONSTRAINT fk_external_identity_event_source
        FOREIGN KEY (source_code) REFERENCES external_source (source_code)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;

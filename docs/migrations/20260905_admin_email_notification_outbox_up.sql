CREATE TABLE admin_email_notification_outbox (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_name VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    recipient_user_id VARCHAR(20) NULL,
    recipient_email VARCHAR(254) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    locale VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ms',
    payload_json JSON NOT NULL,
    delivery_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PENDING',
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    locked_until DATETIME(6) NULL,
    lock_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    sent_at DATETIME(6) NULL,
    last_error_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
    provider_message_id VARCHAR(255) NULL,
    idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (notification_id),
    UNIQUE KEY uq_admin_email_notification_idempotency (idempotency_key),
    KEY idx_admin_email_notification_dispatch (delivery_status,available_at,notification_id),
    KEY idx_admin_email_notification_recipient (recipient_user_id,created_at),
    KEY idx_admin_email_notification_correlation (correlation_id),
    CONSTRAINT chk_admin_email_notification_status CHECK (
        delivery_status IN ('PENDING','PROCESSING','SENT','FAILED','SUPPRESSED')
    ),
    CONSTRAINT chk_admin_email_notification_attempt CHECK (attempt_count <= 100),
    CONSTRAINT chk_admin_email_notification_locale CHECK (locale IN ('ms','en')),
    CONSTRAINT chk_admin_email_notification_recipient CHECK (
        CHAR_LENGTH(TRIM(recipient_email)) BETWEEN 3 AND 254
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE admin_email_notification_delivery_history (
    delivery_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    attempt_number SMALLINT UNSIGNED NOT NULL,
    delivery_outcome VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    error_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
    provider_message_id VARCHAR(255) NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (delivery_id),
    UNIQUE KEY uq_admin_email_delivery_attempt (notification_id,attempt_number),
    KEY idx_admin_email_delivery_correlation (correlation_id),
    CONSTRAINT fk_admin_email_delivery_notification
        FOREIGN KEY (notification_id) REFERENCES admin_email_notification_outbox(notification_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_admin_email_delivery_outcome CHECK (
        delivery_outcome IN ('SENT','FAILED','SUPPRESSED')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

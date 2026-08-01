CREATE TABLE login_banner (
    banner_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    banner_key VARCHAR(64) NOT NULL,
    banner_status VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    starts_at_utc DATETIME NULL,
    ends_at_utc DATETIME NULL,
    configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_by VARCHAR(20) NOT NULL,
    updated_by VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (banner_id),
    UNIQUE KEY uq_login_banner_key (banner_key),
    KEY idx_login_banner_effective (banner_status, starts_at_utc, ends_at_utc, display_order, banner_id),
    CONSTRAINT chk_login_banner_key
        CHECK (banner_key REGEXP '^LB-[A-Z0-9][A-Z0-9_-]{2,61}$'),
    CONSTRAINT chk_login_banner_status
        CHECK (banner_status IN ('DRAFT','PUBLISHED','INACTIVE','ARCHIVED')),
    CONSTRAINT chk_login_banner_order
        CHECK (display_order BETWEEN 1 AND 5),
    CONSTRAINT chk_login_banner_schedule
        CHECK (starts_at_utc IS NULL OR ends_at_utc IS NULL OR starts_at_utc < ends_at_utc),
    CONSTRAINT chk_login_banner_version
        CHECK (configuration_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE login_banner_translation (
    banner_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(2) NOT NULL,
    alt_text VARCHAR(160) NOT NULL,
    fallback_policy VARCHAR(16) NOT NULL DEFAULT 'OWN_ASSET',
    created_by VARCHAR(20) NOT NULL,
    updated_by VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (banner_id, locale),
    CONSTRAINT fk_login_banner_translation_banner
        FOREIGN KEY (banner_id) REFERENCES login_banner(banner_id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT chk_login_banner_translation_locale
        CHECK (locale IN ('ms','en')),
    CONSTRAINT chk_login_banner_translation_alt
        CHECK (CHAR_LENGTH(TRIM(alt_text)) BETWEEN 5 AND 160),
    CONSTRAINT chk_login_banner_translation_fallback
        CHECK (fallback_policy IN ('OWN_ASSET','SAME_AS_MS')),
    CONSTRAINT chk_login_banner_translation_ms_policy
        CHECK (locale <> 'ms' OR fallback_policy = 'OWN_ASSET')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE login_banner_asset (
    asset_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    banner_id BIGINT UNSIGNED NOT NULL,
    environment VARCHAR(32) NOT NULL,
    source_locale VARCHAR(7) NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(32) NOT NULL,
    image_width SMALLINT UNSIGNED NOT NULL,
    image_height SMALLINT UNSIGNED NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    sha256_digest CHAR(64) NOT NULL,
    storage_status VARCHAR(16) NOT NULL DEFAULT 'AVAILABLE',
    created_by VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (asset_id),
    UNIQUE KEY uq_login_banner_asset_filename (environment, image_filename),
    UNIQUE KEY uq_login_banner_asset_digest (banner_id, environment, sha256_digest),
    UNIQUE KEY uq_login_banner_asset_identity (asset_id, banner_id, environment),
    KEY idx_login_banner_asset_banner (banner_id, environment, storage_status),
    CONSTRAINT fk_login_banner_asset_banner
        FOREIGN KEY (banner_id) REFERENCES login_banner(banner_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_login_banner_asset_environment
        CHECK (environment REGEXP '^[a-z][a-z0-9_-]{1,31}$'),
    CONSTRAINT chk_login_banner_asset_locale
        CHECK (source_locale IN ('neutral','ms','en')),
    CONSTRAINT chk_login_banner_asset_filename
        CHECK (image_filename REGEXP '^login_banner_[a-f0-9]{32}\\.webp$'),
    CONSTRAINT chk_login_banner_asset_mime
        CHECK (mime_type = 'image/webp'),
    CONSTRAINT chk_login_banner_asset_dimensions
        CHECK (image_width = 1600 AND image_height = 800),
    CONSTRAINT chk_login_banner_asset_size
        CHECK (byte_size BETWEEN 1 AND 512000),
    CONSTRAINT chk_login_banner_asset_digest
        CHECK (sha256_digest REGEXP '^[a-f0-9]{64}$'),
    CONSTRAINT chk_login_banner_asset_status
        CHECK (storage_status IN ('STAGED','AVAILABLE','QUARANTINED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE login_banner_locale_asset (
    banner_id BIGINT UNSIGNED NOT NULL,
    environment VARCHAR(32) NOT NULL,
    locale VARCHAR(2) NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    mapped_by VARCHAR(20) NOT NULL,
    mapped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (banner_id, environment, locale),
    KEY idx_login_banner_locale_asset_asset (asset_id, banner_id, environment),
    CONSTRAINT fk_login_banner_locale_asset_translation
        FOREIGN KEY (banner_id, locale)
        REFERENCES login_banner_translation(banner_id, locale)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_login_banner_locale_asset_asset
        FOREIGN KEY (asset_id, banner_id, environment)
        REFERENCES login_banner_asset(asset_id, banner_id, environment)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_login_banner_locale_asset_environment
        CHECK (environment REGEXP '^[a-z][a-z0-9_-]{1,31}$'),
    CONSTRAINT chk_login_banner_locale_asset_locale
        CHECK (locale IN ('ms','en'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE login_banner_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    banner_id BIGINT UNSIGNED NULL,
    environment VARCHAR(32) NOT NULL,
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
    UNIQUE KEY uq_login_banner_history_correlation (correlation_id),
    KEY idx_login_banner_history_banner (banner_id, created_at, history_id),
    KEY idx_login_banner_history_created (created_at, history_id),
    KEY idx_login_banner_history_outcome (outcome, created_at),
    CONSTRAINT fk_login_banner_history_banner
        FOREIGN KEY (banner_id) REFERENCES login_banner(banner_id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_login_banner_history_environment
        CHECK (environment REGEXP '^[a-z][a-z0-9_-]{1,31}$'),
    CONSTRAINT chk_login_banner_history_outcome
        CHECK (outcome IN ('SUCCESS','REJECTED')),
    CONSTRAINT chk_login_banner_history_correlation
        CHECK (correlation_id REGEXP '^[a-f0-9]{16}$'),
    CONSTRAINT chk_login_banner_history_reason
        CHECK (change_reason IS NULL OR CHAR_LENGTH(TRIM(change_reason)) BETWEEN 10 AND 500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

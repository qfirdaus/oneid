CREATE TABLE sp_app_translation (
    translation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sp_id VARCHAR(20) NOT NULL,
    locale CHAR(2) NOT NULL,
    sp_name VARCHAR(255) NOT NULL,
    sp_description TEXT NOT NULL,
    translation_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_by VARCHAR(20) NOT NULL,
    updated_by VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (translation_id),
    UNIQUE KEY uq_sp_app_translation_locale (sp_id, locale),
    KEY idx_sp_app_translation_locale_name (locale, sp_name),
    CONSTRAINT fk_sp_app_translation_app
        FOREIGN KEY (sp_id) REFERENCES sp_list (sp_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_sp_app_translation_locale CHECK (locale IN ('ms','en'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE sp_group_translation (
    translation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sp_group_id BIGINT NOT NULL,
    locale CHAR(2) NOT NULL,
    sp_group_name VARCHAR(100) NOT NULL,
    translation_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_by VARCHAR(20) NOT NULL,
    updated_by VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (translation_id),
    UNIQUE KEY uq_sp_group_translation_locale (sp_group_id, locale),
    KEY idx_sp_group_translation_locale_name (locale, sp_group_name),
    CONSTRAINT fk_sp_group_translation_group
        FOREIGN KEY (sp_group_id) REFERENCES sp_group (sp_group_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_sp_group_translation_locale CHECK (locale IN ('ms','en'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE metadata_translation_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(16) NOT NULL,
    entity_id VARCHAR(20) NOT NULL,
    locale CHAR(2) NOT NULL,
    version_before BIGINT UNSIGNED NULL,
    version_after BIGINT UNSIGNED NOT NULL,
    actor_id VARCHAR(20) NOT NULL,
    change_reason VARCHAR(500) NOT NULL,
    before_json JSON NULL,
    after_json JSON NOT NULL,
    correlation_id CHAR(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    UNIQUE KEY uq_metadata_translation_history_correlation (correlation_id),
    KEY idx_metadata_translation_history_entity (entity_type, entity_id, locale, history_id),
    CONSTRAINT chk_metadata_translation_history_entity CHECK (entity_type IN ('application','category')),
    CONSTRAINT chk_metadata_translation_history_locale CHECK (locale IN ('ms','en'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

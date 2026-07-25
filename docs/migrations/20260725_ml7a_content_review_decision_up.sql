CREATE TABLE metadata_content_review (
    review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(16) NOT NULL,
    entity_id VARCHAR(20) NOT NULL,
    locale CHAR(2) NOT NULL DEFAULT 'en',
    classification VARCHAR(40) NOT NULL,
    review_decision VARCHAR(40) NOT NULL,
    source_digest CHAR(64) NOT NULL,
    manifest_digest CHAR(64) NOT NULL,
    reviewed_by VARCHAR(20) NOT NULL,
    evidence_reference VARCHAR(100) NOT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    UNIQUE KEY uq_metadata_content_review_entity (entity_type, entity_id, locale),
    KEY idx_metadata_content_review_manifest (manifest_digest, review_decision),
    CONSTRAINT chk_metadata_content_review_entity
        CHECK (entity_type IN ('application','category')),
    CONSTRAINT chk_metadata_content_review_locale
        CHECK (locale IN ('ms','en')),
    CONSTRAINT chk_metadata_content_review_classification
        CHECK (classification IN (
            'EXISTING_TRANSLATION_APPROVED',
            'TRANSLATION_REQUIRED',
            'PROPER_NOUN_INVARIANT',
            'INTENTIONALLY_FALLBACK',
            'REVIEW_REQUIRED'
        )),
    CONSTRAINT chk_metadata_content_review_decision
        CHECK (review_decision IN (
            'ACCEPT_EXISTING',
            'ACCEPT_TRANSLATION',
            'ACCEPT_INVARIANT',
            'ACCEPT_INTENTIONAL_FALLBACK',
            'EXCLUDE_QUARANTINE'
        ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

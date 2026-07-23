-- OneID ODL Fasa 1: additive dormant provenance foundation.
-- This migration does not backfill memberships and does not wire ODL to
-- Preview or Apply.

CREATE TABLE external_source (
    source_code VARCHAR(50) NOT NULL,
    source_name VARCHAR(100) NOT NULL,
    source_family VARCHAR(30) NOT NULL,
    lifecycle_state VARCHAR(16) NOT NULL DEFAULT 'dormant',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    avail_status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (source_code),
    KEY idx_external_source_runtime
        (source_family, lifecycle_state, avail_status),
    CONSTRAINT chk_external_source_family
        CHECK (source_family IN ('staff', 'student')),
    CONSTRAINT chk_external_source_lifecycle
        CHECK (
            lifecycle_state IN
            ('dormant', 'shadow', 'mandatory', 'optional',
             'disabled', 'retired')
        ),
    CONSTRAINT chk_external_source_required
        CHECK (is_required IN (0, 1)),
    CONSTRAINT chk_external_source_available
        CHECK (avail_status IN (0, 1))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE user_external_identity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    u_id VARCHAR(20) NOT NULL,
    source_code VARCHAR(50) NOT NULL,
    external_user_id VARCHAR(20) NOT NULL,
    source_active TINYINT(1) NOT NULL DEFAULT 1,
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    last_sync_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_external_identity_source_user
        (source_code, external_user_id),
    UNIQUE KEY uq_external_identity_user_source
        (u_id, source_code),
    KEY idx_external_identity_user_active
        (u_id, source_active),
    KEY idx_external_identity_source_active
        (source_code, source_active),
    CONSTRAINT chk_external_identity_active
        CHECK (source_active IN (0, 1)),
    CONSTRAINT fk_external_identity_user
        FOREIGN KEY (u_id) REFERENCES user_tbl (u_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_external_identity_source
        FOREIGN KEY (source_code) REFERENCES external_source (source_code)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO external_source (
    source_code,
    source_name,
    source_family,
    lifecycle_state,
    is_required,
    avail_status
) VALUES (
    'STUDENT_ODL_PG',
    'ODL Postgraduate Student',
    'student',
    'dormant',
    0,
    1
);

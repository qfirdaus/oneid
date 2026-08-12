CREATE TABLE IF NOT EXISTS sp_api_credential (
    sp_id VARCHAR(20) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    code_ciphertext VARBINARY(255) NULL,
    code_nonce BINARY(24) NULL,
    key_version VARCHAR(32) NULL,
    code_hint VARCHAR(12) NOT NULL,
    credential_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    rotated_at DATETIME NOT NULL,
    rotated_by VARCHAR(20) NOT NULL,
    PRIMARY KEY (sp_id),
    UNIQUE KEY uq_sp_api_credential_hash (code_hash),
    CONSTRAINT fk_sp_api_credential_app
        FOREIGN KEY (sp_id) REFERENCES sp_list(sp_id)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

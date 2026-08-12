ALTER TABLE sp_api_credential
    ADD COLUMN code_ciphertext VARBINARY(255) NULL AFTER code_hash,
    ADD COLUMN code_nonce BINARY(24) NULL AFTER code_ciphertext,
    ADD COLUMN key_version VARCHAR(32) NULL AFTER code_nonce;

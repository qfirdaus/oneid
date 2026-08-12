ALTER TABLE sp_api_credential
    DROP COLUMN key_version,
    DROP COLUMN code_nonce,
    DROP COLUMN code_ciphertext;

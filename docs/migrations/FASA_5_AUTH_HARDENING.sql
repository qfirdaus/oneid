-- OneID UAT Fasa 5: expanding migration, safe for legacy MD5/token rows.
-- Applied on 2026-07-13 before the Fasa 5 application deployment.

ALTER TABLE user_tbl
  MODIFY u_password VARCHAR(255) NOT NULL,
  ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 0 AFTER u_password;

ALTER TABLE token_tbl
  MODIFY token_id VARCHAR(64) NOT NULL;

UPDATE token_tbl
  SET token_id = SHA2(token_id, 256)
  WHERE CHAR_LENGTH(token_id) < 64;

ALTER TABLE otp_codes
  MODIFY otp_code VARCHAR(255) NOT NULL,
  ADD COLUMN otp_expires_at DATETIME NULL AFTER otp_create_date,
  ADD COLUMN otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER otp_expires_at,
  ADD COLUMN otp_consumed_at DATETIME NULL AFTER otp_attempts,
  ADD INDEX idx_otp_user_created (u_id, otp_create_date);

-- Historical plaintext OTP rows are expired and irreversibly transformed.
UPDATE otp_codes
  SET otp_code = SHA2(otp_code, 256),
      otp_expires_at = COALESCE(otp_expires_at, otp_create_date),
      otp_consumed_at = COALESCE(otp_consumed_at, NOW())
  WHERE CHAR_LENGTH(otp_code) <= 6;

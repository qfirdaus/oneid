ALTER TABLE token_tbl ADD COLUMN token_issued_at DATETIME NULL AFTER token_datetime;
UPDATE token_tbl SET token_issued_at = token_datetime WHERE token_issued_at IS NULL;
ALTER TABLE token_tbl MODIFY token_issued_at DATETIME NOT NULL;
CREATE INDEX idx_token_issued_at ON token_tbl (token_issued_at);

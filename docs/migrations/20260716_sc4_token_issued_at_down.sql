DROP INDEX idx_token_issued_at ON token_tbl;
ALTER TABLE token_tbl DROP COLUMN token_issued_at;

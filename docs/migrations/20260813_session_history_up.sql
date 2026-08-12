ALTER TABLE token_tbl
    ADD COLUMN ended_at DATETIME NULL AFTER status,
    ADD COLUMN end_reason VARCHAR(32) NULL AFTER ended_at,
    ADD INDEX idx_token_history_status_end (status, ended_at),
    ADD INDEX idx_token_history_reason (end_reason);

ALTER TABLE token_tbl
    DROP INDEX idx_token_history_reason,
    DROP INDEX idx_token_history_status_end,
    DROP COLUMN end_reason,
    DROP COLUMN ended_at;

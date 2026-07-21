ALTER TABLE admin_mfa_factors
    DROP KEY idx_admin_mfa_factor_replay,
    DROP COLUMN last_used_time_step,
    DROP COLUMN enrollment_browser_digest,
    DROP COLUMN enrollment_session_hash;

-- Event dictionary rows 44-49 are deliberately retained so historical syslog
-- records remain readable after a code/schema rollback.

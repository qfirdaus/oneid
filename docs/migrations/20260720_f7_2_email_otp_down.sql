ALTER TABLE admin_step_up_challenges
    DROP INDEX idx_admin_step_up_challenge_ip_rate,
    DROP INDEX idx_admin_step_up_challenge_rate,
    DROP COLUMN sent_at;

-- Event dictionary rows 37-43 are deliberately retained so historical audit
-- records remain readable after application rollback.

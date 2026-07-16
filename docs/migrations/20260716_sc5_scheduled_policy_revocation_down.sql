DELETE FROM syslog_event_conf WHERE syslog_event_id IN (30,31,32)
  AND syslog_event_name IN ('ADMIN_SSO_POLICY_REVOCATION_SCHEDULED','SSO_POLICY_TOKEN_REVOKED','ADMIN_SSO_POLICY_REVOCATION_CANCELLED');
ALTER TABLE token_tbl
    DROP INDEX idx_token_policy_revoke,
    DROP COLUMN policy_revoke_correlation,
    DROP COLUMN policy_revoke_at;

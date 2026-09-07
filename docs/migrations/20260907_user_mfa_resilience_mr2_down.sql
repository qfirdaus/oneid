-- PRECONDITION: no open MR2 request and policy_mode is not EMERGENCY_BYPASS.
-- Export audit evidence before rollback. This rollback is intentionally explicit.

DROP TABLE IF EXISTS user_mfa_policy_transition_runs;
DROP TABLE IF EXISTS user_mfa_policy_change_approvals;
DROP TABLE IF EXISTS user_mfa_policy_change_requests;
DROP TABLE IF EXISTS maintenance_mfa_policy_history;
DROP TABLE IF EXISTS maintenance_mfa_policy;

ALTER TABLE user_login_mfa_policy
    DROP CHECK chk_user_mfa_policy_mode,
    ADD CONSTRAINT chk_user_mfa_policy_mode CHECK (
        policy_mode IN ('OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED')
    );

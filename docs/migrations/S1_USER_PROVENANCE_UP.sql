-- OneID S1 expanding migration: manual/external account provenance.
-- Apply before enabling Manual Add User after the S1 code deployment.
-- Existing rows remain classified as external because historical provenance
-- cannot be inferred safely from current columns.

ALTER TABLE user_tbl
  ADD COLUMN account_source VARCHAR(16) NOT NULL DEFAULT 'external' AFTER avail_status,
  ADD COLUMN sync_protected TINYINT(1) NOT NULL DEFAULT 0 AFTER account_source,
  ADD INDEX idx_user_sync_scope (avail_status, account_source, sync_protected);


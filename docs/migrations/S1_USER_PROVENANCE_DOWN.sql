-- OneID S1 rollback migration.
-- Apply only after rolling application code back or while compatibility
-- detection remains present. Dropping these columns removes sync protection.

ALTER TABLE user_tbl
  DROP INDEX idx_user_sync_scope,
  DROP COLUMN sync_protected,
  DROP COLUMN account_source;


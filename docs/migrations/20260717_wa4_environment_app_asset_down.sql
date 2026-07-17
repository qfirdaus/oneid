-- Destructive rollback is intentionally separate from code rollback.
-- Run only after all WA4 code has been rolled back and the asset rows exported.
DROP TABLE IF EXISTS sp_app_asset;

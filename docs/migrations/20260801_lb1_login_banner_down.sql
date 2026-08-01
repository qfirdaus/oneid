-- Destructive rollback is intentionally separate from code/feature rollback.
-- Export history and asset manifests first. Run only after every deployment has
-- returned to static login banners and no code references these tables.
DROP TABLE IF EXISTS login_banner_history;
DROP TABLE IF EXISTS login_banner_locale_asset;
DROP TABLE IF EXISTS login_banner_asset;
DROP TABLE IF EXISTS login_banner_translation;
DROP TABLE IF EXISTS login_banner;

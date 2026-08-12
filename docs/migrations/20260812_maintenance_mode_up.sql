ALTER TABLE sys_config
    ADD COLUMN maintenance_mode VARCHAR(12) NOT NULL DEFAULT 'OFF' AFTER default_locale,
    ADD COLUMN maintenance_starts_at DATETIME NULL AFTER maintenance_mode,
    ADD COLUMN maintenance_ends_at DATETIME NULL AFTER maintenance_starts_at,
    ADD COLUMN maintenance_title_ms VARCHAR(160) NOT NULL DEFAULT 'Sistem OneID sedang diselenggara' AFTER maintenance_ends_at,
    ADD COLUMN maintenance_title_en VARCHAR(160) NOT NULL DEFAULT 'OneID is under maintenance' AFTER maintenance_title_ms,
    ADD COLUMN maintenance_message_ms VARCHAR(1000) NOT NULL DEFAULT 'Perkhidmatan tidak tersedia buat sementara waktu.' AFTER maintenance_title_en,
    ADD COLUMN maintenance_message_en VARCHAR(1000) NOT NULL DEFAULT 'The service is temporarily unavailable.' AFTER maintenance_message_ms,
    ADD CONSTRAINT chk_sys_config_maintenance_mode CHECK (maintenance_mode IN ('OFF','SCHEDULED','INDEFINITE'));

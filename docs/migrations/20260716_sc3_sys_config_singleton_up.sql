ALTER TABLE sys_config
    ADD COLUMN singleton_key TINYINT NOT NULL DEFAULT 1 AFTER id,
    ADD CONSTRAINT chk_sys_config_singleton CHECK (singleton_key = 1),
    ADD CONSTRAINT uq_sys_config_singleton UNIQUE (singleton_key);

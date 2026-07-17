CREATE TABLE IF NOT EXISTS sp_app_asset (
    sp_id VARCHAR(20) NOT NULL,
    environment VARCHAR(32) NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(20) NOT NULL,
    PRIMARY KEY (sp_id, environment),
    CONSTRAINT fk_sp_app_asset_sp_list
        FOREIGN KEY (sp_id) REFERENCES sp_list(sp_id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT chk_sp_app_asset_environment
        CHECK (environment REGEXP '^[a-z][a-z0-9_-]{1,31}$'),
    CONSTRAINT chk_sp_app_asset_filename
        CHECK (image_filename REGEXP '^app_icon_[A-Za-z0-9_-]+\\.(jpg|jpeg|png|gif|webp)$')
) ENGINE=InnoDB;

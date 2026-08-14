ALTER TABLE sp_list
    ADD COLUMN production_ready TINYINT(1) NOT NULL DEFAULT 0 AFTER avail_status,
    ADD COLUMN production_domain VARCHAR(2048) NULL AFTER sp_domain,
    ADD CONSTRAINT chk_sp_list_production_ready CHECK (production_ready IN (0, 1)),
    ADD CONSTRAINT chk_sp_list_production_domain CHECK (
        production_ready = 0 OR production_domain LIKE 'https://%'
    );

-- Deliberately do not mark existing applications ready automatically.
-- Review each active application in staging and set its Production URL and
-- Ready for Production flag before taking the production database snapshot.

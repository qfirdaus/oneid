ALTER TABLE sp_list
    DROP CHECK chk_sp_list_production_domain,
    DROP CHECK chk_sp_list_production_ready,
    DROP COLUMN production_domain,
    DROP COLUMN production_ready;

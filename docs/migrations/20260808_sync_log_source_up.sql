ALTER TABLE ext_data_temp_header
    ADD COLUMN source_code VARCHAR(64) NULL AFTER ext_head_type;

CREATE INDEX idx_ext_data_temp_header_source_code
    ON ext_data_temp_header (source_code, ext_head_id);

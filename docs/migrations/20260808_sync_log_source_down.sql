DROP INDEX idx_ext_data_temp_header_source_code ON ext_data_temp_header;

ALTER TABLE ext_data_temp_header
    DROP COLUMN source_code;

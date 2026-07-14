-- W4: apply only after W0/W3 reconciliation reports zero orphan references.
-- The migration tool applies these statements conditionally and verifies them.
ALTER TABLE sp_group
    MODIFY sp_group_name VARCHAR(100) NOT NULL,
    ADD UNIQUE KEY uq_sp_group_name (sp_group_name);

ALTER TABLE sp_list
    ADD CONSTRAINT fk_sp_list_sp_group
    FOREIGN KEY (sp_group_id) REFERENCES sp_group (sp_group_id)
    ON UPDATE RESTRICT ON DELETE RESTRICT;

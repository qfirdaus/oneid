-- W4 emergency rollback. This removes enforcement only; it does not alter data.
ALTER TABLE sp_list DROP FOREIGN KEY fk_sp_list_sp_group;
ALTER TABLE sp_group DROP INDEX uq_sp_group_name;

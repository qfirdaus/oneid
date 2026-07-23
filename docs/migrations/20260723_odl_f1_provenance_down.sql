-- OneID ODL Fasa 1 rollback.
-- Run only while every source is dormant and user_external_identity is empty.
-- The guarded migration tool enforces these preconditions before executing
-- this file.

DROP TABLE user_external_identity;
DROP TABLE external_source;

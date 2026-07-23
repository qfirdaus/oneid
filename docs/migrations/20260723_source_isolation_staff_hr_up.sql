-- Cross-source isolation foundation. Registration only; no membership backfill.
INSERT INTO external_source (
    source_code, source_name, source_family, lifecycle_state,
    is_required, avail_status
) VALUES (
    'STAFF_HR', 'Human Resources Staff', 'staff', 'dormant', 0, 1
);

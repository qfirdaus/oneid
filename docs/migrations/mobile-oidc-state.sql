-- REVIEW ONLY. Not applied by Phase B; run only in isolated UAT during Phase C.
-- Same PDO/transaction as OneID identity reads and TOTP CAS.
-- Do not attach bootstrap or enable routes by applying this schema.
CREATE TABLE mobile_oidc_mutex (
    singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;
INSERT INTO mobile_oidc_mutex(singleton_id) VALUES (1);
CREATE TABLE mobile_oidc_records (
    record_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    record_json JSON NOT NULL,
    updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB;
-- Phase C must add retention/purge and security writer/outbox integration before activation.
-- Never purge subject/security mappings when purging expired transactions or rate buckets.

-- ML1 additive presentation preference only. This table does not grant access
-- and does not alter authentication, authorization, category or ACL data.

CREATE TABLE IF NOT EXISTS user_locale_preference (
  u_id VARCHAR(20) NOT NULL,
  locale VARCHAR(5) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (u_id),
  CONSTRAINT chk_user_locale_preference_locale CHECK (locale IN ('ms','en'))
) ENGINE=InnoDB;

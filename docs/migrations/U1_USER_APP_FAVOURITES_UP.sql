-- OneID U1: durable, per-account application favourites.
-- This table stores presentation preference only and never grants application
-- access. Effective access remains governed by acl_group, acl_single and
-- acl_blacklist.

CREATE TABLE IF NOT EXISTS user_app_favourite (
  u_id VARCHAR(20) NOT NULL,
  sp_id VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (u_id, sp_id),
  KEY idx_user_app_favourite_sp (sp_id)
) ENGINE=InnoDB;

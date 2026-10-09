-- Durable per-account Recently Used presentation history. This table never
-- grants application access; effective ACL is rechecked for every read/open.
CREATE TABLE IF NOT EXISTS user_app_recent (
  u_id VARCHAR(20) NOT NULL,
  sp_id VARCHAR(20) NOT NULL,
  last_used_at DATETIME NOT NULL,
  PRIMARY KEY (u_id, sp_id),
  KEY idx_user_app_recent_order (u_id, last_used_at),
  KEY idx_user_app_recent_sp (sp_id)
) ENGINE=InnoDB;

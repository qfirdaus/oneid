-- Per-account presentation progress only. This table does not grant access and
-- does not alter authentication, authorization, category or application data.
CREATE TABLE IF NOT EXISTS user_product_tour_progress (
  u_id VARCHAR(20) NOT NULL,
  tour_id VARCHAR(64) NOT NULL,
  tour_version INT UNSIGNED NOT NULL,
  completion_status VARCHAR(16) NOT NULL,
  completed_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (u_id, tour_id, tour_version),
  KEY idx_user_product_tour_progress_tour (tour_id, tour_version),
  CONSTRAINT chk_user_product_tour_progress_status CHECK (completion_status IN ('completed','skipped'))
) ENGINE=InnoDB;

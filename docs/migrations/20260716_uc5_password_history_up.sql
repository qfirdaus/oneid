CREATE TABLE user_password_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    changed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_user_password_history_user (user_id,id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    imei VARCHAR(15) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    download_count INT DEFAULT 0,
    max_downloads INT DEFAULT 5,
    deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_imei (imei),
    INDEX idx_deleted (deleted),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drop the existing table if you need to recreate it
-- DROP TABLE IF EXISTS certificates;

-- Alternative: If the table already exists and has data, use ALTER TABLE instead
-- ALTER TABLE certificates ADD COLUMN imei VARCHAR(15) NOT NULL AFTER user_id;
-- ALTER TABLE certificates ADD INDEX idx_imei (imei);
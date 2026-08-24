-- =============================================================================
-- SEAS — ENSURE chunked uploads + Reading Upload tables (safe to re-run)
-- Date: 20 Aug 2026
-- phpMyAdmin → select SEAS database → SQL tab → Run
-- Does NOT drop data. Skips tables / columns that already exist.
-- =============================================================================

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1) chunked_uploads — resumable upload sessions (parts live on disk)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chunked_uploads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `purpose` VARCHAR(64) NOT NULL,
  `meta_json` JSON NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(191) NULL,
  `extension` VARCHAR(16) NULL,
  `total_size` BIGINT UNSIGNED NOT NULL,
  `chunk_size` INT UNSIGNED NOT NULL,
  `total_chunks` INT UNSIGNED NOT NULL,
  `received_chunks` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `path` VARCHAR(255) NULL,
  `error` TEXT NULL,
  `completed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chunked_uploads_uuid_unique` (`uuid`),
  KEY `chunked_uploads_status_index` (`status`),
  KEY `chunked_uploads_user_id_index` (`user_id`),
  KEY `chunked_uploads_purpose_index` (`purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='chunked_uploads'
  AND CONSTRAINT_NAME='chunked_uploads_user_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `chunked_uploads` ADD CONSTRAINT `chunked_uploads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
  'SELECT ''chunked_uploads.user_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2) reading_uploads — one row per uploaded consumption file
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reading_uploads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(32) NOT NULL,
  `chunked_upload_id` BIGINT UNSIGNED NULL,
  `path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `size_bytes` BIGINT UNSIGNED NULL,
  `period_from` DATE NULL,
  `period_to` DATE NULL,
  `period_label` VARCHAR(64) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `rows_total` INT UNSIGNED NULL,
  `rows_imported` INT UNSIGNED NULL,
  `rows_failed` INT UNSIGNED NULL,
  `headers_json` JSON NULL,
  `error` TEXT NULL,
  `processed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `reading_uploads_type_index` (`type`),
  KEY `reading_uploads_status_index` (`status`),
  KEY `reading_uploads_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='reading_uploads'
  AND CONSTRAINT_NAME='reading_uploads_user_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `reading_uploads` ADD CONSTRAINT `reading_uploads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
  'SELECT ''reading_uploads.user_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 3) feeder_readings
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feeder_readings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reading_upload_id` BIGINT UNSIGNED NOT NULL,
  `feeder_id` BIGINT UNSIGNED NULL,
  `feeder_code` VARCHAR(64) NOT NULL,
  `feeder_name` VARCHAR(191) NULL,
  `reading_date` DATE NULL,
  `period_label` VARCHAR(64) NULL,
  `kwh_import` DECIMAL(18,3) NULL,
  `kwh_export` DECIMAL(18,3) NULL,
  `kvah` DECIMAL(18,3) NULL,
  `md_kw` DECIMAL(14,3) NULL,
  `raw_json` JSON NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `feeder_readings_reading_upload_id_foreign` (`reading_upload_id`),
  KEY `feeder_readings_feeder_id_foreign` (`feeder_id`),
  KEY `feeder_readings_feeder_code_index` (`feeder_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='feeder_readings'
  AND CONSTRAINT_NAME='feeder_readings_reading_upload_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `feeder_readings` ADD CONSTRAINT `feeder_readings_reading_upload_id_foreign` FOREIGN KEY (`reading_upload_id`) REFERENCES `reading_uploads` (`id`) ON DELETE CASCADE',
  'SELECT ''feeder_readings.reading_upload_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='feeder_readings'
  AND CONSTRAINT_NAME='feeder_readings_feeder_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `feeder_readings` ADD CONSTRAINT `feeder_readings_feeder_id_foreign` FOREIGN KEY (`feeder_id`) REFERENCES `feeders` (`id`) ON DELETE SET NULL',
  'SELECT ''feeder_readings.feeder_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 4) dtr_readings
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dtr_readings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reading_upload_id` BIGINT UNSIGNED NOT NULL,
  `dtr_id` BIGINT UNSIGNED NULL,
  `dtr_code` VARCHAR(64) NOT NULL,
  `dtr_name` VARCHAR(191) NULL,
  `feeder_code` VARCHAR(64) NULL,
  `reading_date` DATE NULL,
  `period_label` VARCHAR(64) NULL,
  `kwh_import` DECIMAL(18,3) NULL,
  `kwh_export` DECIMAL(18,3) NULL,
  `kvah` DECIMAL(18,3) NULL,
  `md_kw` DECIMAL(14,3) NULL,
  `raw_json` JSON NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `dtr_readings_reading_upload_id_foreign` (`reading_upload_id`),
  KEY `dtr_readings_dtr_id_foreign` (`dtr_id`),
  KEY `dtr_readings_dtr_code_index` (`dtr_code`),
  KEY `dtr_readings_feeder_code_index` (`feeder_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dtr_readings'
  AND CONSTRAINT_NAME='dtr_readings_reading_upload_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `dtr_readings` ADD CONSTRAINT `dtr_readings_reading_upload_id_foreign` FOREIGN KEY (`reading_upload_id`) REFERENCES `reading_uploads` (`id`) ON DELETE CASCADE',
  'SELECT ''dtr_readings.reading_upload_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dtr_readings'
  AND CONSTRAINT_NAME='dtr_readings_dtr_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `dtr_readings` ADD CONSTRAINT `dtr_readings_dtr_id_foreign` FOREIGN KEY (`dtr_id`) REFERENCES `dtrs` (`id`) ON DELETE SET NULL',
  'SELECT ''dtr_readings.dtr_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 5) consumer_readings
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `consumer_readings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reading_upload_id` BIGINT UNSIGNED NOT NULL,
  `consumer_id` BIGINT UNSIGNED NULL,
  `ivrs` VARCHAR(64) NULL,
  `msn` VARCHAR(64) NULL,
  `account_no` VARCHAR(64) NULL,
  `consumer_name` VARCHAR(191) NULL,
  `dtr_code` VARCHAR(64) NULL,
  `feeder_code` VARCHAR(64) NULL,
  `reading_date` DATE NULL,
  `period_label` VARCHAR(64) NULL,
  `kwh_import` DECIMAL(18,3) NULL,
  `kwh_export` DECIMAL(18,3) NULL,
  `kvah` DECIMAL(18,3) NULL,
  `md_kw` DECIMAL(14,3) NULL,
  `raw_json` JSON NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `consumer_readings_reading_upload_id_foreign` (`reading_upload_id`),
  KEY `consumer_readings_consumer_id_foreign` (`consumer_id`),
  KEY `consumer_readings_ivrs_index` (`ivrs`),
  KEY `consumer_readings_msn_index` (`msn`),
  KEY `consumer_readings_account_no_index` (`account_no`),
  KEY `consumer_readings_dtr_code_index` (`dtr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='consumer_readings'
  AND CONSTRAINT_NAME='consumer_readings_reading_upload_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `consumer_readings` ADD CONSTRAINT `consumer_readings_reading_upload_id_foreign` FOREIGN KEY (`reading_upload_id`) REFERENCES `reading_uploads` (`id`) ON DELETE CASCADE',
  'SELECT ''consumer_readings.reading_upload_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='consumer_readings'
  AND CONSTRAINT_NAME='consumer_readings_consumer_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `consumer_readings` ADD CONSTRAINT `consumer_readings_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE SET NULL',
  'SELECT ''consumer_readings.consumer_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 6) report_analysis_uploads — parse state columns (added, never dropped)
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='report_analysis_uploads' AND COLUMN_NAME='status';
SET @sql := IF(@c=0,
  'ALTER TABLE `report_analysis_uploads` ADD COLUMN `status` VARCHAR(32) NOT NULL DEFAULT ''completed''',
  'SELECT ''report_analysis_uploads.status exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='report_analysis_uploads' AND COLUMN_NAME='parse_error';
SET @sql := IF(@c=0,
  'ALTER TABLE `report_analysis_uploads` ADD COLUMN `parse_error` TEXT NULL',
  'SELECT ''report_analysis_uploads.parse_error exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='report_analysis_uploads' AND COLUMN_NAME='chunked_upload_id';
SET @sql := IF(@c=0,
  'ALTER TABLE `report_analysis_uploads` ADD COLUMN `chunked_upload_id` BIGINT UNSIGNED NULL',
  'SELECT ''report_analysis_uploads.chunked_upload_id exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='report_analysis_uploads' AND COLUMN_NAME='size_bytes';
SET @sql := IF(@c=0,
  'ALTER TABLE `report_analysis_uploads` ADD COLUMN `size_bytes` BIGINT UNSIGNED NULL',
  'SELECT ''report_analysis_uploads.size_bytes exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='report_analysis_uploads' AND COLUMN_NAME='parsed_at';
SET @sql := IF(@c=0,
  'ALTER TABLE `report_analysis_uploads` ADD COLUMN `parsed_at` TIMESTAMP NULL',
  'SELECT ''report_analysis_uploads.parsed_at exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @i FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='report_analysis_uploads' AND INDEX_NAME='report_analysis_uploads_status_index';
SET @sql := IF(@i=0,
  'ALTER TABLE `report_analysis_uploads` ADD INDEX `report_analysis_uploads_status_index` (`status`)',
  'SELECT ''report_analysis_uploads status index exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Existing rows predate background parsing — mark them done so the UI is not stuck.
UPDATE `report_analysis_uploads` SET `status` = 'completed' WHERE `status` IS NULL OR `status` = '';

-- -----------------------------------------------------------------------------
-- 7) Record the migrations so `php artisan migrate` does not re-run them
-- -----------------------------------------------------------------------------
SELECT IFNULL(MAX(`batch`), 0) + 1 INTO @batch FROM `migrations`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_110000_create_chunked_uploads_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_110000_create_chunked_uploads_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_110100_add_parse_state_to_report_analysis_uploads_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_110100_add_parse_state_to_report_analysis_uploads_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_110200_create_reading_uploads_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_110200_create_reading_uploads_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_110300_create_feeder_readings_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_110300_create_feeder_readings_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_110400_create_dtr_readings_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_110400_create_dtr_readings_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_110500_create_consumer_readings_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_110500_create_consumer_readings_table');

-- -----------------------------------------------------------------------------
-- Sanity check
-- -----------------------------------------------------------------------------
SELECT
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db
  AND TABLE_NAME IN ('chunked_uploads','reading_uploads','feeder_readings','dtr_readings','consumer_readings')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='report_analysis_uploads'
ORDER BY ORDINAL_POSITION;

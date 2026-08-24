-- =============================================================================
-- SEAS — ENSURE Substation Survey / Audit + geo columns (safe to re-run)
-- Date: 20 Aug 2026
-- phpMyAdmin → select SEAS database → SQL tab → Run
-- Does NOT drop data. Skips tables / columns that already exist.
-- =============================================================================

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1) Map coordinates on masters (poles already had latitude / longitude)
--    NOTE: no AFTER clause — columns are appended at the end of each table.
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substations' AND COLUMN_NAME='latitude';
SET @sql := IF(@c=0,
  'ALTER TABLE `substations` ADD COLUMN `latitude` DECIMAL(10,7) NULL',
  'SELECT ''substations.latitude exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substations' AND COLUMN_NAME='longitude';
SET @sql := IF(@c=0,
  'ALTER TABLE `substations` ADD COLUMN `longitude` DECIMAL(10,7) NULL',
  'SELECT ''substations.longitude exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='feeders' AND COLUMN_NAME='latitude';
SET @sql := IF(@c=0,
  'ALTER TABLE `feeders` ADD COLUMN `latitude` DECIMAL(10,7) NULL',
  'SELECT ''feeders.latitude exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='feeders' AND COLUMN_NAME='longitude';
SET @sql := IF(@c=0,
  'ALTER TABLE `feeders` ADD COLUMN `longitude` DECIMAL(10,7) NULL',
  'SELECT ''feeders.longitude exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dtrs' AND COLUMN_NAME='latitude';
SET @sql := IF(@c=0,
  'ALTER TABLE `dtrs` ADD COLUMN `latitude` DECIMAL(10,7) NULL',
  'SELECT ''dtrs.latitude exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dtrs' AND COLUMN_NAME='longitude';
SET @sql := IF(@c=0,
  'ALTER TABLE `dtrs` ADD COLUMN `longitude` DECIMAL(10,7) NULL',
  'SELECT ''dtrs.longitude exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2) Pole photo
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='poles' AND COLUMN_NAME='photo';
SET @sql := IF(@c=0,
  'ALTER TABLE `poles` ADD COLUMN `photo` VARCHAR(255) NULL',
  'SELECT ''poles.photo exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 3) substation_surveys
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `substation_surveys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `surveyor_id` BIGINT UNSIGNED NOT NULL,
  `supervisor_id` BIGINT UNSIGNED NULL,
  `surveyed_at` TIMESTAMP NULL,
  `region_id` BIGINT UNSIGNED NULL,
  `circle_id` BIGINT UNSIGNED NULL,
  `division_id` BIGINT UNSIGNED NULL,
  `zone_id` BIGINT UNSIGNED NULL,
  `substation_id` BIGINT UNSIGNED NULL,
  `substation_code` VARCHAR(255) NULL,
  `substation_name` VARCHAR(255) NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `gps_accuracy` DECIMAL(8,2) NULL,
  `substation_type` VARCHAR(255) NULL,
  `capacity_mva` DECIMAL(10,3) NULL,
  `transformer_count` INT UNSIGNED NULL,
  `incoming_voltage` VARCHAR(255) NULL,
  `outgoing_voltage` VARCHAR(255) NULL,
  `feeder_count_declared` INT UNSIGNED NULL,
  `meter_number` VARCHAR(255) NULL,
  `meter_make` VARCHAR(255) NULL,
  `meter_serial_no` VARCHAR(255) NULL,
  `metering_type` VARCHAR(255) NULL,
  `ct_ratio` VARCHAR(255) NULL,
  `pt_ratio` VARCHAR(255) NULL,
  `mf` VARCHAR(255) NULL,
  `meter_condition` VARCHAR(255) NULL,
  `meter_working` TINYINT(1) NULL,
  `substation_photo` VARCHAR(255) NULL,
  `meter_photo` VARCHAR(255) NULL,
  `nameplate_photo` VARCHAR(255) NULL,
  `sld_photo` VARCHAR(255) NULL,
  `observation` TEXT NULL,
  `remarks` TEXT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `review_remarks` TEXT NULL,
  `reviewed_at` TIMESTAMP NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `locked_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `substation_surveys_substation_id_index` (`substation_id`),
  KEY `substation_surveys_status_index` (`status`),
  KEY `substation_surveys_surveyor_id_index` (`surveyor_id`),
  KEY `substation_surveys_zone_id_index` (`zone_id`),
  KEY `substation_surveys_supervisor_id_foreign` (`supervisor_id`),
  KEY `substation_surveys_region_id_foreign` (`region_id`),
  KEY `substation_surveys_circle_id_foreign` (`circle_id`),
  KEY `substation_surveys_division_id_foreign` (`division_id`),
  KEY `substation_surveys_reviewed_by_foreign` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FKs only when missing (older MySQL / partial deploys)
SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_surveys'
  AND CONSTRAINT_NAME='substation_surveys_surveyor_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_surveys` ADD CONSTRAINT `substation_surveys_surveyor_id_foreign` FOREIGN KEY (`surveyor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
  'SELECT ''surveyor_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_surveys'
  AND CONSTRAINT_NAME='substation_surveys_supervisor_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_surveys` ADD CONSTRAINT `substation_surveys_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT ''supervisor_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_surveys'
  AND CONSTRAINT_NAME='substation_surveys_reviewed_by_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_surveys` ADD CONSTRAINT `substation_surveys_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT ''reviewed_by FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_surveys'
  AND CONSTRAINT_NAME='substation_surveys_region_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_surveys` ADD CONSTRAINT `substation_surveys_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL',
  'SELECT ''region_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_surveys'
  AND CONSTRAINT_NAME='substation_surveys_circle_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_surveys` ADD CONSTRAINT `substation_surveys_circle_id_foreign` FOREIGN KEY (`circle_id`) REFERENCES `circles` (`id`) ON DELETE SET NULL',
  'SELECT ''circle_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_surveys'
  AND CONSTRAINT_NAME='substation_surveys_division_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_surveys` ADD CONSTRAINT `substation_surveys_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL',
  'SELECT ''division_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_surveys'
  AND CONSTRAINT_NAME='substation_surveys_zone_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_surveys` ADD CONSTRAINT `substation_surveys_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL',
  'SELECT ''zone_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_surveys'
  AND CONSTRAINT_NAME='substation_surveys_substation_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_surveys` ADD CONSTRAINT `substation_surveys_substation_id_foreign` FOREIGN KEY (`substation_id`) REFERENCES `substations` (`id`) ON DELETE SET NULL',
  'SELECT ''substation_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 4) substation_survey_photos (extra / history photos)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `substation_survey_photos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `substation_survey_id` BIGINT UNSIGNED NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `kind` VARCHAR(255) NULL,
  `uploaded_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `substation_survey_photos_substation_survey_id_id_index` (`substation_survey_id`, `id`),
  KEY `substation_survey_photos_uploaded_by_foreign` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_survey_photos'
  AND CONSTRAINT_NAME='substation_survey_photos_substation_survey_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_survey_photos` ADD CONSTRAINT `substation_survey_photos_substation_survey_id_foreign` FOREIGN KEY (`substation_survey_id`) REFERENCES `substation_surveys` (`id`) ON DELETE CASCADE',
  'SELECT ''substation_survey_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='substation_survey_photos'
  AND CONSTRAINT_NAME='substation_survey_photos_uploaded_by_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `substation_survey_photos` ADD CONSTRAINT `substation_survey_photos_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT ''uploaded_by FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 5) Mark Laravel migrations as run so `php artisan migrate` stays clean
-- -----------------------------------------------------------------------------
SELECT IFNULL(MAX(`batch`), 0) INTO @batch FROM `migrations`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_100000_add_geo_columns_to_hierarchy_tables', @batch + 1
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_100000_add_geo_columns_to_hierarchy_tables'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_100100_create_substation_surveys_table', @batch + 1
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_100100_create_substation_surveys_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_20_100200_create_substation_survey_photos_table', @batch + 1
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_08_20_100200_create_substation_survey_photos_table'
);

-- -----------------------------------------------------------------------------
-- Sanity checks
-- -----------------------------------------------------------------------------
SELECT
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME IN ('substation_surveys', 'substation_survey_photos')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db
  AND ((TABLE_NAME IN ('substations', 'feeders', 'dtrs') AND COLUMN_NAME IN ('latitude', 'longitude'))
    OR (TABLE_NAME='poles' AND COLUMN_NAME IN ('latitude', 'longitude', 'photo')))
ORDER BY TABLE_NAME, COLUMN_NAME;

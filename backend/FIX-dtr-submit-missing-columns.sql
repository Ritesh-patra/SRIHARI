-- =============================================================================
-- FIX: DTR Survey submit 500 — missing columns on production (Aug 17, 2026)
-- Run in phpMyAdmin → your SEAS database → SQL tab.
-- Safe to re-run: each block skips when the column already exists.
-- =============================================================================

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1) lt_line_type  (Under Ground / Over Ground)
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'lt_line_type';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `lt_line_type` VARCHAR(32) NULL AFTER `dtr_condition`',
  'SELECT ''lt_line_type already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Normalize legacy labels (safe to re-run)
UPDATE `dtr_surveys`
SET `lt_line_type` = 'Over Ground'
WHERE `lt_line_type` IN ('OH Line', 'OH', 'Overhead', 'Overhead Line', 'O.H. Line');

UPDATE `dtr_surveys`
SET `lt_line_type` = 'Under Ground'
WHERE `lt_line_type` IN ('OG Line', 'OG', 'UG', 'UG Line', 'Underground', 'Underground Line', 'O.G. Line');

-- -----------------------------------------------------------------------------
-- 2) ct_ratio_photo
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'ct_ratio_photo';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `ct_ratio_photo` VARCHAR(255) NULL AFTER `smart_meter_photo`',
  'SELECT ''ct_ratio_photo already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 3) entry_source + feeder_survey_id
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'entry_source';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `entry_source` VARCHAR(32) NULL AFTER `observation`',
  'SELECT ''entry_source already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'feeder_survey_id';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `feeder_survey_id` BIGINT UNSIGNED NULL AFTER `entry_source`',
  'SELECT ''feeder_survey_id already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK feeder_survey_id → feeder_surveys (ignore if already present / table missing)
-- Run manually if needed:
-- ALTER TABLE `dtr_surveys`
--   ADD CONSTRAINT `dtr_surveys_feeder_survey_id_foreign`
--   FOREIGN KEY (`feeder_survey_id`) REFERENCES `feeder_surveys`(`id`) ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- 4) locked_at
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'locked_at';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `locked_at` TIMESTAMP NULL AFTER `reviewed_at`',
  'SELECT ''locked_at already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 5) consumer_survey_completed_at (usually already present)
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'consumer_survey_completed_at';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `consumer_survey_completed_at` TIMESTAMP NULL AFTER `reviewed_at`',
  'SELECT ''consumer_survey_completed_at already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 6) mapping_correction_* fields
-- -----------------------------------------------------------------------------
SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'mapping_correction_status';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `mapping_correction_status` VARCHAR(32) NULL AFTER `consumer_survey_completed_at`',
  'SELECT ''mapping_correction_status already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'master_feeder_id';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `master_feeder_id` BIGINT UNSIGNED NULL AFTER `mapping_correction_status`',
  'SELECT ''master_feeder_id already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'reported_feeder_id';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `reported_feeder_id` BIGINT UNSIGNED NULL AFTER `master_feeder_id`',
  'SELECT ''reported_feeder_id already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'field_dtr_name';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `field_dtr_name` VARCHAR(190) NULL AFTER `reported_feeder_id`',
  'SELECT ''field_dtr_name already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'mapping_correction_remarks';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `mapping_correction_remarks` TEXT NULL AFTER `field_dtr_name`',
  'SELECT ''mapping_correction_remarks already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'mapping_correction_reviewed_at';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `mapping_correction_reviewed_at` TIMESTAMP NULL AFTER `mapping_correction_remarks`',
  'SELECT ''mapping_correction_reviewed_at already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @c
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND COLUMN_NAME = 'mapping_correction_reviewed_by';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD COLUMN `mapping_correction_reviewed_by` BIGINT UNSIGNED NULL AFTER `mapping_correction_reviewed_at`',
  'SELECT ''mapping_correction_reviewed_by already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional FKs (ignore errors if already exist):
-- ALTER TABLE `dtr_surveys` ADD CONSTRAINT `dtr_surveys_master_feeder_id_foreign`
--   FOREIGN KEY (`master_feeder_id`) REFERENCES `feeders`(`id`) ON DELETE SET NULL;
-- ALTER TABLE `dtr_surveys` ADD CONSTRAINT `dtr_surveys_reported_feeder_id_foreign`
--   FOREIGN KEY (`reported_feeder_id`) REFERENCES `feeders`(`id`) ON DELETE SET NULL;
-- ALTER TABLE `dtr_surveys` ADD CONSTRAINT `dtr_surveys_mapping_correction_reviewed_by_foreign`
--   FOREIGN KEY (`mapping_correction_reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Optional index
SELECT COUNT(*) INTO @c
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dtr_surveys' AND INDEX_NAME = 'dtr_surveys_mapping_correction_status_index';
SET @sql := IF(@c = 0,
  'ALTER TABLE `dtr_surveys` ADD INDEX `dtr_surveys_mapping_correction_status_index` (`mapping_correction_status`)',
  'SELECT ''mapping_correction_status index already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- Verify
-- -----------------------------------------------------------------------------
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db
  AND TABLE_NAME = 'dtr_surveys'
  AND COLUMN_NAME IN (
    'lt_line_type',
    'ct_ratio_photo',
    'entry_source',
    'feeder_survey_id',
    'locked_at',
    'consumer_survey_completed_at',
    'mapping_correction_status',
    'master_feeder_id',
    'reported_feeder_id',
    'field_dtr_name',
    'mapping_correction_remarks',
    'mapping_correction_reviewed_at',
    'mapping_correction_reviewed_by'
  )
ORDER BY COLUMN_NAME;

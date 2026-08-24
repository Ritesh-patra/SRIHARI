-- =============================================================================
-- SEAS — ENSURE DTR consumer re-activation requests (safe to re-run)
-- Date: 18 Aug 2026
-- phpMyAdmin → select SEAS database → SQL tab → Run
-- Does NOT drop data. Skips table if it already exists.
-- =============================================================================

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1) dtr_reactivation_requests
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dtr_reactivation_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dtr_survey_id` BIGINT UNSIGNED NOT NULL,
  `requested_by` BIGINT UNSIGNED NOT NULL,
  `reason` TEXT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` TIMESTAMP NULL,
  `review_remarks` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `dtr_reactivation_requests_status_created_at_index` (`status`, `created_at`),
  KEY `dtr_reactivation_requests_dtr_survey_id_index` (`dtr_survey_id`),
  KEY `dtr_reactivation_requests_requested_by_foreign` (`requested_by`),
  KEY `dtr_reactivation_requests_reviewed_by_foreign` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FKs only when missing (older MySQL / partial deploys)
SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dtr_reactivation_requests'
  AND CONSTRAINT_NAME='dtr_reactivation_requests_dtr_survey_id_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `dtr_reactivation_requests` ADD CONSTRAINT `dtr_reactivation_requests_dtr_survey_id_foreign` FOREIGN KEY (`dtr_survey_id`) REFERENCES `dtr_surveys` (`id`) ON DELETE CASCADE',
  'SELECT ''dtr_survey_id FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dtr_reactivation_requests'
  AND CONSTRAINT_NAME='dtr_reactivation_requests_requested_by_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `dtr_reactivation_requests` ADD CONSTRAINT `dtr_reactivation_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE',
  'SELECT ''requested_by FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dtr_reactivation_requests'
  AND CONSTRAINT_NAME='dtr_reactivation_requests_reviewed_by_foreign' AND CONSTRAINT_TYPE='FOREIGN KEY';
SET @sql := IF(@fk=0,
  'ALTER TABLE `dtr_reactivation_requests` ADD CONSTRAINT `dtr_reactivation_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT ''reviewed_by FK exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Sanity check
SELECT
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dtr_reactivation_requests'
ORDER BY ORDINAL_POSITION;

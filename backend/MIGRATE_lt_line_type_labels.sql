-- Optional: migrate legacy LT line type labels to Under Ground / Over Ground.
-- Safe to re-run. Column dtr_surveys.lt_line_type already exists on most installs.

-- Ensure column exists (skip if you already ran ADD_lt_line_type.sql)
-- ALTER TABLE dtr_surveys ADD COLUMN lt_line_type VARCHAR(32) NULL;

UPDATE dtr_surveys
SET lt_line_type = 'Over Ground'
WHERE lt_line_type IN ('OH Line', 'OH', 'Overhead', 'Overhead Line');

UPDATE dtr_surveys
SET lt_line_type = 'Under Ground'
WHERE lt_line_type IN ('OG Line', 'OG', 'UG', 'UG Line', 'Underground', 'Underground Line');

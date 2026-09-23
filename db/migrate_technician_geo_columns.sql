-- add_technician.php / load_tech.php still wrote to the legacy specialty/lat/lng
-- columns (varchar) instead of tech_type/latitude/longitude (the columns every
-- other page — dashboard.php's radius search, edit_profile.php, technician_view.php
-- — actually reads). Concretely this meant any technician who signed up through
-- add_technician.php had NULL latitude/longitude and was invisible to radius search.
--
-- specialty/lat/lng stay as columns (existing rows keep their historical values,
-- nothing reads them going forward) but become nullable since new signups no
-- longer populate them.

ALTER TABLE `technicians`
  MODIFY COLUMN `specialty` VARCHAR(50) NULL,
  MODIFY COLUMN `lat` VARCHAR(50) NULL,
  MODIFY COLUMN `lng` VARCHAR(50) NULL;

-- Backfill latitude/longitude for existing rows that only have the old lat/lng set.
UPDATE `technicians`
SET `latitude` = CAST(`lat` AS DOUBLE)
WHERE `latitude` IS NULL AND `lat` IS NOT NULL AND `lat` <> '';

UPDATE `technicians`
SET `longitude` = CAST(`lng` AS DOUBLE)
WHERE `longitude` IS NULL AND `lng` IS NOT NULL AND `lng` <> '';

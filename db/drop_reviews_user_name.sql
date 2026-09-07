-- Follow-up to alter_missing_columns.sql.
-- reviews.php already writes/reads via reviews.user_id + a JOIN to users.
-- technician_profile.php has been updated to do the same JOIN instead of
-- reading the old reviews.user_name snapshot column, so that column (and the
-- staleness/duplication it caused) is no longer needed anywhere in the app.
--
-- Safe to run only after confirming no rows depend on user_name without a
-- matching user_id (checked manually before writing this migration — the
-- only existing row already had user_id set).

ALTER TABLE `reviews`
  MODIFY COLUMN `user_id` INT NOT NULL,
  DROP COLUMN `user_name`;

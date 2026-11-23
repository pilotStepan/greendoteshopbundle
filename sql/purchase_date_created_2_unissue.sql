-- Purchase dates split, part 2 of 2: clear date_issue of purchases that were never issued (see UPGRADE.md, 7.4.0).
--
-- Run right AFTER the app is deployed with bundle >= 7.4.0, never before:
-- older bundles declare Purchase::getDateIssue(): \DateTimeInterface and throw a TypeError on NULL.
-- Requires part 1 (purchase_date_created_1_schema.sql).
-- MariaDB. Safe to re-run.

-- Unconfirmed purchases are not issued. Real order = marking has none of draft/cart/wishlist (do NOT use legacy `state`).
-- `date_issue = date_created` limits this to rows from before the upgrade (part 1 copied the value),
-- so a re-run never clears a date set later by Purchase::issue().
UPDATE `purchase`
SET `date_issue` = NULL
WHERE `date_issue` = `date_created`
  AND JSON_CONTAINS_PATH(`marking`, 'one', '$.draft', '$.cart', '$.wishlist') = 1;

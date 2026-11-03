-- Purchase dates split (see UPGRADE.md, 7.4.0):
--   date_created = when the purchase (cart, wishlist, draft, ...) was created, fixed;
--   date_issue   = when it was issued (confirmed by the client), NULL until then. Prices are resolved for date_issue, or now while NULL.
-- MariaDB. Safe to re-run.

-- 1. New column, nullable for the backfill.
ALTER TABLE `purchase`
    ADD COLUMN IF NOT EXISTS `date_created` DATETIME NULL COMMENT '(DC2Type:datetime_immutable)';

-- 2. Until now date_issue held the creation time of every purchase.
UPDATE `purchase`
SET `date_created` = `date_issue`
WHERE `date_created` IS NULL;

-- 3. Final shape. The DB default keeps inserts from apps that don't map the column yet (CMS) working.
ALTER TABLE `purchase`
    MODIFY `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)',
    MODIFY `date_issue` DATETIME NULL;

-- 4. Unconfirmed purchases are not issued. Real order = marking has none of draft/cart/wishlist (do NOT use legacy `state`).
-- `date_issue = date_created` limits this to rows from before the upgrade (step 2 copied the value),
-- so a re-run never clears a date set later by Purchase::issue().
UPDATE `purchase`
SET `date_issue` = NULL
WHERE `date_issue` = `date_created`
  AND JSON_CONTAINS_PATH(`marking`, 'one', '$.draft', '$.cart', '$.wishlist') = 1;

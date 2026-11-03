-- Purchase dates split, part 1 of 2: schema + backfill (see UPGRADE.md, 7.4.0).
--   date_created = when the purchase (cart, wishlist, draft, ...) was created, fixed;
--   date_issue   = when it was issued (confirmed by the client), NULL until then.
--
-- Run BEFORE deploying bundle >= 7.4.0 (its mapping needs date_created).
-- Additive and safe for apps still on an older bundle and for the old CMS: they keep setting date_issue,
-- and the DB default fills date_created on their inserts.
-- MariaDB. Safe to re-run.

-- 1. New column, nullable for the backfill.
ALTER TABLE `purchase`
    ADD COLUMN IF NOT EXISTS `date_created` DATETIME NULL COMMENT '(DC2Type:datetime_immutable)';

-- 2. Until now date_issue held the creation time of every purchase.
UPDATE `purchase`
SET `date_created` = `date_issue`
WHERE `date_created` IS NULL;

-- 3. Final shape. The DB default keeps inserts from apps that don't map the column yet working.
ALTER TABLE `purchase`
    MODIFY `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)',
    MODIFY `date_issue` DATETIME NULL;

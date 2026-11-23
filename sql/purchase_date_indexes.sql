-- Indexes for reading purchases by date (see UPGRADE.md).
-- The simple-ws shop dashboard filters by these columns in every section.
-- Additive, no code depends on them. MariaDB. Safe to re-run.

CREATE INDEX IF NOT EXISTS `idx_purchase_date_issue` ON `purchase` (`date_issue`);
CREATE INDEX IF NOT EXISTS `idx_purchase_date_created` ON `purchase` (`date_created`);

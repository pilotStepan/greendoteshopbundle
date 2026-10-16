-- Sequential customer-facing order number (see UPGRADE.md).
ALTER TABLE `purchase`
    ADD COLUMN `order_number` INT UNSIGNED NULL;

CREATE UNIQUE INDEX `UNIQ_purchase_order_number` ON `purchase` (`order_number`);

-- Existing real orders keep their id as the order number (numbers already sent to customers / used as VS stay valid).
-- Real order = marking has none of draft/cart/wishlist. Do NOT use `state`, it is legacy for purchases. Safe to re-run.
UPDATE `purchase`
SET `order_number` = `id`
WHERE `order_number` IS NULL
  AND JSON_CONTAINS_PATH(`marking`, 'one', '$.draft', '$.cart', '$.wishlist') = 0;

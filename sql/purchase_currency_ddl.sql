-- Adds currency snapshot columns needed to release the order<->currency link:
--   purchase.currency_id     - the currency the order was priced/placed in
--   payment_type.currency_id - the currency a given payment method charges in (e.g. an
--                              SK payment type -> EUR); PaymentType::setCurrency() drives
--                              Purchase::setPaymentType() to copy this onto the purchase.
--   payment.amount/currency_id - what a card-gateway attempt (GPWebpay::getPayLink()) was
--                              actually asked to charge, in which currency.
-- Tenant apps should still generate their own Doctrine migration for this (bin/console
-- make:migration); this file documents the raw SQL for tenants that apply patches by hand.

ALTER TABLE `purchase`
    ADD COLUMN `currency_id` INT NULL,
    ADD CONSTRAINT `fk_purchase_currency` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`),
    ADD INDEX `idx_purchase_currency` (`currency_id`);

ALTER TABLE `payment_type`
    ADD COLUMN `currency_id` INT NULL,
    ADD CONSTRAINT `fk_payment_type_currency` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`),
    ADD INDEX `idx_payment_type_currency` (`currency_id`);

ALTER TABLE `payment`
    ADD COLUMN `amount` DOUBLE NULL,
    ADD COLUMN `currency_id` INT NULL,
    ADD CONSTRAINT `fk_payment_currency` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`),
    ADD INDEX `idx_payment_currency` (`currency_id`);

UPDATE `payment_type`
    SET `currency_id` = (SELECT `id` FROM `currency` WHERE `name` = 'EUR')
    WHERE `country` = 'SK' AND `currency_id` IS NULL;

UPDATE `purchase` p
    JOIN `payment_type` pt ON pt.id = p.payment_type_id
    SET p.currency_id = pt.currency_id
    WHERE p.currency_id IS NULL AND pt.currency_id IS NOT NULL;

UPDATE `purchase`
    SET `currency_id` = (SELECT `id` FROM `currency` WHERE `is_default` = 1)
    WHERE `currency_id` IS NULL;

ALTER TABLE `client`
    ADD COLUMN `locale` VARCHAR(10) DEFAULT NULL


-- Report-only: purchases whose PaymentType charges in a currency other than the purchase's
--   own frozen snapshot - e.g. an admin switched the payment type via the CMS's unconstrained
--   dropdown before this feature's guard/filter was in place. Review each row by hand; there is
--   no single correct automatic fix (which side is wrong depends on the order).
SELECT p.id AS purchase_id, p.currency_id AS purchase_currency_id, c1.name AS purchase_currency,
       pt.id AS payment_type_id, pt.currency_id AS payment_type_currency_id, c2.name AS payment_type_currency
    FROM `purchase` p
    JOIN `payment_type` pt ON pt.id = p.payment_type_id
    JOIN `currency` c1 ON c1.id = p.currency_id
    JOIN `currency` c2 ON c2.id = pt.currency_id
    WHERE pt.currency_id IS NOT NULL
      AND p.currency_id IS NOT NULL
      AND p.currency_id != pt.currency_id;

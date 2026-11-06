-- payment_type.pay_method: payment method a payment type asks the gateway to open with (GP webpay PAYMETHOD).
-- PaymentType maps this column, so every project must run this BEFORE updating to the bundle version
-- that contains it, otherwise every query on payment_type fails.
-- Additive, NULL keeps the current behaviour. MariaDB. Safe to re-run.

ALTER TABLE `payment_type` ADD COLUMN IF NOT EXISTS `pay_method` VARCHAR(16) NULL DEFAULT NULL;

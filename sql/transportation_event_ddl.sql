CREATE TABLE `transportation_event`
(
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` INT         NOT NULL,
    `occurred_at` DATETIME(6) NULL,
    `recorded_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `state`       VARCHAR(64) NOT NULL,
    `details`     JSON NULL,
    PRIMARY KEY (`id`),
    KEY           `idx_transportation_event_purchase_occurred` (`purchase_id`, `occurred_at`, `id`),
    KEY           `idx_transportation_event_state` (`state`),
    CONSTRAINT `fk_transportation_event_purchase`
        FOREIGN KEY (`purchase_id`)
            REFERENCES `purchase` (`id`)
            ON UPDATE CASCADE
            ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

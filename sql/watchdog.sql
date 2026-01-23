CREATE TABLE `watchdog`
(
    `id`                    INT          NOT NULL AUTO_INCREMENT,
    `product_variant_id`    INT          NOT NULL,
    `type`                  VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
    `state`                 VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
    `email`                 VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
    `last_queued_event_key` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
    `last_sent_event_key`   VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
    `last_queued_at`        DATETIME     NULL DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    `last_notified_at`      DATETIME     NULL DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    `attempt_count`         INT          NOT NULL,
    `last_error`            LONGTEXT     NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
    `created_at`            DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `completed_at`          DATETIME     NULL DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    `meta`                  JSON         NULL DEFAULT NULL,
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE INDEX `watchdog_dedupe_uniq` (`type`, `product_variant_id`, `email`) USING BTREE,
    INDEX `IDX_20A87D78A80EF684` (`product_variant_id`) USING BTREE,
    INDEX `watchdog_variant_lookup` (`type`, `state`, `product_variant_id`) USING BTREE,
    INDEX `watchdog_email_idx` (`email`) USING BTREE,
    CONSTRAINT `FK_20A87D78A80EF684` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variant` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE
)
    COLLATE = 'utf8mb4_unicode_ci'
    ENGINE = InnoDB
;

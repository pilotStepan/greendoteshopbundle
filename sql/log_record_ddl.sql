CREATE TABLE log_record
(
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    datetime  DATETIME(6)      NOT NULL,
    channel   VARCHAR(64) NOT NULL,
    level     VARCHAR(16) NOT NULL,
    message   LONGTEXT    NOT NULL,
    context   JSON NULL,
    extra     JSON NULL,
    formatted LONGTEXT NULL,
    KEY       idx_channel_datetime (channel, datetime),
    KEY       idx_level_datetime (level,   datetime)
) ENGINE = InnoDB
  CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

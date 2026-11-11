# PHP 8.2, the lowest version the bundle supports and the one CI runs, for `composer test` and `composer phpstan`.
FROM php:8.2-cli

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath gd intl pdo_mysql zip
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-tests.ini

WORKDIR /app

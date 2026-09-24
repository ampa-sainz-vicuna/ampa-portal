FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

# pdo_pgsql: Doctrine sobre PostgreSQL
# intl:      ordenar nombres con acentos como en español (Collator)
# opcache:   rendimiento (imprescindible luego en Cloud Run)
# zip:       Composer descomprime los paquetes
RUN install-php-extensions pdo_pgsql intl opcache zip @composer

# git: Composer lo usa para algunos paquetes. zip: para empaquetar el cliente
# (scripts/empaquetar-cliente.sh) igual que lo hace la Action.
RUN apk add --no-cache git bash icu-data-full zip

COPY php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html

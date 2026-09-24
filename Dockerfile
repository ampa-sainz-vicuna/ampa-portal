# Imagen de PRODUCCIÓN (Cloud Run). La de desarrollo está en docker/php/.
#
# Un solo contenedor sirve las dos cosas desde el mismo dominio:
#   /api/...   → Symfony
#   lo demás   → el front de React ya compilado
# Es el mismo montaje que fichajes y listados, a propósito.
#
# cliente/ NO entra: es un paquete para las aplicaciones y se publica aparte
# (la Action de .github/workflows/publicar.yml).
#
# Se construye en tres etapas; solo la última llega a producción.

# ---------------------------------------------------------------------------
# 1. Frontend: compila React a ficheros estáticos (web/dist).
# ---------------------------------------------------------------------------
FROM node:22-alpine AS web

WORKDIR /web
COPY web/package.json web/package-lock.json ./
RUN npm ci
COPY web/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# 2. Base de ejecución: Apache + PHP con las extensiones que usa el portal.
#    Apache con mod_php: un único proceso, lo más sencillo de mantener.
# ---------------------------------------------------------------------------
FROM php:8.4-apache AS base

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl opcache @composer \
    && a2enmod rewrite headers

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/prod/apache.conf /etc/apache2/sites-available/000-default.conf

# Cloud Run indica en $PORT dónde escuchar (8080 por defecto).
ENV PORT=8080
RUN sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# 3. El portal.
# ---------------------------------------------------------------------------
FROM base AS app

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

# Primero solo las dependencias: si no cambia composer.lock, Docker reutiliza
# esta capa y la construcción es mucho más rápida.
COPY api/composer.json api/composer.lock api/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY api/ ./
RUN composer dump-autoload --no-dev --classmap-authoritative \
    # Congela los valores NO secretos de .env en .env.local.php. Los secretos
    # (base de datos, clave de firma) y lo que cambia en producción (dominio de
    # la cookie, URLs de las aplicaciones) llegan como variables de entorno de
    # Cloud Run, que siempre mandan sobre lo que haya aquí.
    && composer dump-env prod \
    && php bin/console cache:warmup

# React compilado, junto a index.php en la carpeta pública.
COPY --from=web /web/dist/ ./public/

COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data var

CMD ["entrypoint"]

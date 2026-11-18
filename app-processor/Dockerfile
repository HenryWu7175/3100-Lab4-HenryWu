FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM php:8.3-apache AS extensions
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq5 \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --create-home --uid 1000 appuser \
    && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/\*:80>/*:8080>/; s|DocumentRoot /var/www/html$|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf \
    && install -d -o appuser -g appuser /var/www/html/storage/uploads /var/run/apache2 /var/lock/apache2 \
    && chown appuser:appuser /var/run/apache2 /var/lock/apache2
COPY --from=extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=extensions /usr/local/etc/php/conf.d/docker-php-ext-pdo_pgsql.ini /usr/local/etc/php/conf.d/
RUN printf 'upload_max_filesize=10M\npost_max_size=11M\n' > /usr/local/etc/php/conf.d/uploads.ini
COPY shared/ /var/www/html/shared/
COPY --from=dependencies /app/vendor/ /var/www/html/vendor/
COPY app-processor/public/ /var/www/html/public/
USER appuser
EXPOSE 8080

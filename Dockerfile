# TicketFlow demo image: PHP 8.4 CLI with the extensions the app needs, Composer, and the
# source. Development-oriented on purpose (APP_ENV=dev, built-in web server) so that the
# logs the agents read land in var/log/dev.log exactly as they do on the host.
FROM php:8.4-cli-alpine

# pdo_sqlite and sqlite3 are compiled into the official image; only intl is missing.
RUN apk add --no-cache icu-dev icu-data-full git unzip sqlite \
    && docker-php-ext-install -j"$(nproc)" intl \
    && printf 'memory_limit = 512M\ndate.timezone = UTC\n' > /usr/local/etc/php/conf.d/ticketflow.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first so source edits do not invalidate the vendor layer.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist

COPY . .
RUN composer run-script --no-interaction auto-scripts || true \
    && chmod +x docker/entrypoint.sh \
    && mkdir -p var/log var/cache var/db

ENV APP_ENV=dev \
    APP_SECRET=ticketflow-demo-not-a-secret \
    DATABASE_URL="sqlite:////app/var/db/data_dev.db"

EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["serve"]

# Mailbox for Laravel demo — a single self-contained web container.
#
# Runs on any host that builds a Dockerfile and gives the container a writable
# filesystem: Railway, Render, Fly.io, Koyeb, a plain VPS. No database and no
# object storage, because the mailbox uses its "file" store driver.
FROM php:8.3-apache

# gd is needed by nothing in the demo itself, but Laravel's image handling and
# a lot of copy-pasted follow-up code expects it; zip is for composer.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip libzip-dev; \
    docker-php-ext-install -j"$(nproc)" zip opcache; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers

WORKDIR /var/www/html

# Dependencies first, so edits to app code don't re-resolve composer.
COPY composer.json composer.lock ./

# --dev is deliberate, not an oversight. redberry/mailbox-for-laravel is a dev
# dependency, and it is the entire subject of this demo, so the image needs it.
# Never copy this line into a real production image.
RUN composer install \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-scripts

COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN set -eux; \
    chmod +x /usr/local/bin/entrypoint; \
    chown -R www-data:www-data storage bootstrap/cache

# Sensible demo defaults. The host's env vars override all of these.
ENV APP_ENV=demo \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=cookie \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=mailbox \
    MAILBOX_ENABLED=true \
    MAILBOX_STORE_DRIVER=file \
    DEMO_PUBLIC_MAILBOX=true \
    PORT=8080

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]

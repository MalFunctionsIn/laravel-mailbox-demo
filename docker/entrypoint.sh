#!/usr/bin/env bash
#
# Binds Apache to whatever port the platform hands us, warms the caches that
# are safe to warm, and hands the writable paths to www-data.
set -euo pipefail

PORT="${PORT:-8080}"

echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -i "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf

# A demo should still boot if nobody remembered to set APP_KEY. An ephemeral
# key means sessions reset on redeploy, which for this app costs nothing.
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
    echo "entrypoint: APP_KEY was not set — generated an ephemeral one." >&2
fi

mkdir -p \
    storage/app/mailbox \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

php artisan package:discover --ansi

# Config and views cache cleanly. Routes do NOT: routes/web.php uses closures,
# and route:cache cannot serialize those. Leaving it out on purpose.
php artisan config:cache
php artisan view:cache

php artisan mailbox:clear --quiet || true

# This has to be the LAST thing before exec. Every artisan command above runs
# as root and creates files and directories as it goes — the mailbox store digs
# out storage/app/mailbox/attachments-index/ on first write, for one. Chowning
# earlier leaves those root-owned and Apache (www-data) gets a 500 on the first
# message it tries to capture.
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache

echo "entrypoint: serving on :${PORT} (APP_ENV=${APP_ENV:-unset})" >&2

exec "$@"

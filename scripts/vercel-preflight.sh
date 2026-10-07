#!/usr/bin/env bash
#
# Runs the app locally with the exact configuration a Vercel deployment uses,
# so a broken deploy can be diagnosed without waiting on a build.
#
# It does NOT emulate the serverless runtime. What it does prove is the part
# that actually differs from local development: the database-backed mailbox
# store, cookie sessions, attachments disabled, and Blade compiling into /tmp.
#
#   scripts/vercel-preflight.sh 'mysql://user:pass@127.0.0.1:9307/db'
#   scripts/vercel-preflight.sh 'postgresql://user:pass@host/db?sslmode=require'
#
set -euo pipefail

DB_URL="${1:-}"
PORT="${PORT:-9200}"

if [ -z "$DB_URL" ]; then
    echo "usage: $0 <connection-url>" >&2
    echo "  e.g. $0 'postgresql://user:pass@ep-x.neon.tech/neondb?sslmode=require'" >&2
    exit 1
fi

cd "$(dirname "$0")/.."

# Exactly what .env.vercel.example tells you to put in the Vercel dashboard.
export APP_ENV=demo
export APP_DEBUG=false
export APP_KEY="${APP_KEY:-base64:$(head -c 32 /dev/urandom | base64)}"
export APP_URL="http://127.0.0.1:${PORT}"
export LOG_CHANNEL=stderr
export SESSION_DRIVER=cookie
export CACHE_STORE=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=mailbox
export MAIL_FROM_ADDRESS=shop@acme-store.test
export MAIL_FROM_NAME="ACME Store"
export MAILBOX_ENABLED=true
export MAILBOX_STORE_DRIVER=database
export MAILBOX_ATTACHMENTS_ENABLED=false
export MAILBOX_POLLING_INTERVAL=3000
export DEMO_PUBLIC_MAILBOX=true
export MAILBOX_DB_URL="$DB_URL"

# api/index.php does this on Vercel, where the bundle is read-only.
export VIEW_COMPILED_PATH=/tmp/mailbox-preflight/views
mkdir -p "$VIEW_COMPILED_PATH"

php artisan config:clear --quiet

echo "→ running the same build checks Vercel runs"
php scripts/vercel-build.php

echo "→ starting the app on http://127.0.0.1:${PORT}"
exec php artisan serve --host=127.0.0.1 --port="$PORT"

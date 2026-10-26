#!/bin/sh
# Prepares the storage volume, then hands over to supervisord. Runs on every start; each
# step is safe to repeat.
set -e

cd /var/www/html

mkdir -p storage/app/public storage/app/media-public storage/app/media-private storage/database \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# The app key encrypts KYC fields and sessions, so it is made once and kept in the volume.
# Pass APP_KEY to use your own.
if [ -z "$APP_KEY" ]; then
    if [ ! -s storage/app/.app-key ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > storage/app/.app-key
    fi
    APP_KEY="$(cat storage/app/.app-key)"
    export APP_KEY
fi

first_run=false
if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    first_run=true
fi

chown -R www-data:www-data storage

run() { su -s /bin/sh www-data -c "$*"; }

run "php artisan migrate --force"
if [ "$first_run" = true ] && [ "${SEED_ON_FIRST_RUN:-true}" = true ]; then
    run "php artisan db:seed --force"
fi
run "php artisan permissions:sync"
run "php artisan settings:clear-cache"
run "php artisan optimize"

exec "$@"

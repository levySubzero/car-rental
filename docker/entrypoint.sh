#!/bin/sh
# Caches config, routes, views and events from the mounted .env before the process starts.
# Migrations run once per deploy from the deploy script, not here, so the php-fpm, Horizon
# and scheduler containers never race each other.
set -e

# storage is a named volume; a fresh one starts empty.
mkdir -p storage/app/public storage/app/media-public storage/app/media-private \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

php artisan optimize --no-interaction

exec "$@"

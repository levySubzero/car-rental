#!/usr/bin/env bash
# Installs a release archive on cPanel (M12.15, D44). Run on the server over SSH:
#   scripts/deploy-cpanel.sh <release.tar.gz> <release-id>
# Layout under BAINES_BASE (default ~/baines):
#   releases/<id>/    one unpacked archive each (vendor and public/build included)
#   shared/.env       the environment file, linked into every release
#   shared/storage/   uploads, logs and caches, linked into every release
#   current ->        the live release; point the domain's document root at current/public
# Rollback: `ln -sfn releases/<previous-id> current` then `php artisan optimize` in it.
# Cron (cPanel → Cron Jobs), every minute:
#   cd ~/baines/current && php artisan schedule:run >> /dev/null 2>&1
# with QUEUE_VIA_SCHEDULER=true and INERTIA_SSR_ENABLED=false in shared/.env.
set -euo pipefail

ARCHIVE="${1:?usage: deploy-cpanel.sh <release.tar.gz> <release-id>}"
RELEASE="${2:?usage: deploy-cpanel.sh <release.tar.gz> <release-id>}"
BASE="${BAINES_BASE:-$HOME/baines}"
PHP="${PHP_BIN:-php}"
KEEP="${BAINES_KEEP_RELEASES:-5}"

DIR="$BASE/releases/$RELEASE"
mkdir -p "$BASE/releases" "$BASE/shared/storage"
test -f "$BASE/shared/.env" || { echo "Missing $BASE/shared/.env"; exit 1; }

rm -rf "$DIR"
mkdir -p "$DIR"
tar -xzf "$ARCHIVE" -C "$DIR"

# Shared state replaces the release's own copies.
rm -rf "$DIR/storage" "$DIR/.env"
ln -s "$BASE/shared/storage" "$DIR/storage"
ln -s "$BASE/shared/.env" "$DIR/.env"
mkdir -p "$BASE/shared/storage/app/public" "$BASE/shared/storage/app/media-public" \
    "$BASE/shared/storage/app/media-private" "$BASE/shared/storage/framework/cache/data" \
    "$BASE/shared/storage/framework/sessions" "$BASE/shared/storage/framework/views" \
    "$BASE/shared/storage/logs"

cd "$DIR"
"$PHP" artisan storage:link --force
"$PHP" artisan migrate --force
"$PHP" artisan permissions:sync
"$PHP" artisan settings:clear-cache
"$PHP" artisan optimize

# Atomic switch: a new link renamed over the old one.
ln -sfn "$DIR" "$BASE/current.new"
mv -Tf "$BASE/current.new" "$BASE/current"

# A worker started by the scheduler finishes its job and exits on the next restart signal.
"$PHP" artisan queue:restart

cd "$BASE/releases"
ls -1t | tail -n +"$((KEEP + 1))" | xargs -r rm -rf
rm -f "$ARCHIVE"
echo "Deployed $RELEASE"

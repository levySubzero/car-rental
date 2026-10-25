#!/usr/bin/env bash
# Deploys one image tag on a Docker VPS (M12.15, D44). Run on the server from the directory
# that holds compose.prod.yaml and .env:
#   scripts/deploy-docker.sh <tag> [image]
# Rollback is the same command with the previous tag (see .deployed-tags); migrations are not
# rolled back, so keep them additive. The CI deploy workflow calls this over SSH.
set -euo pipefail

TAG="${1:?usage: deploy-docker.sh <tag> [image]}"
export BAINES_TAG="$TAG"
export BAINES_IMAGE="${2:-${BAINES_IMAGE:-levysubzero/car-rental}}"

compose() { docker compose -f compose.prod.yaml "$@"; }

compose pull --quiet app ssr web
compose up -d redis
# Once per deploy, before the new code serves requests.
compose run --rm app php artisan migrate --force
compose run --rm app php artisan permissions:sync
# Settings migrations write straight to the table, past the settings cache.
compose run --rm app php artisan settings:clear-cache
compose up -d --remove-orphans

echo "$(date -u +%FT%TZ) $TAG" >> .deployed-tags
docker image prune -f >/dev/null
echo "Deployed $TAG"

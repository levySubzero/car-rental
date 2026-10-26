# The whole app in one container, on SQLite: nginx, php-fpm, the queue worker and the
# scheduler under supervisord. The simplest way to run it; staging and production use
# docker/Dockerfile (D44). Build and run from the repo root:
#
#   docker build -f docker/single/Dockerfile -t baines .
#   docker run -d --name baines -p 8080:80 -v baines-data:/var/www/html/storage \
#       -e APP_URL=http://localhost:8080 baines
#
# Everything that must survive a restart (the database, uploads, the app key, logs) lives in
# the storage volume. Pages render in the browser (no SSR server in this image).
#
# Behind an HTTPS proxy, pass `-e APP_URL=https://your.domain`. A proxy on the same host or a
# private network is trusted by default (TRUSTED_PROXIES), so its X-Forwarded-Proto makes
# Laravel write https URLs. If the proxy reaches the container from a public address
# (Cloudflare straight to the server), set TRUSTED_PROXIES to its ranges, or `*` when the
# container's port is reachable only through it.
#
# For a local demo, add `-e APP_ENV=local`: the login page then has the one-click "sign in as"
# picker, and a first start also seeds the Operations and Content Editor users. Never on a
# server people can reach: the picker signs anyone in as the super admin without a password.

FROM php:8.4-fpm-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis imagick gd intl zip bcmath pcntl exif opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-baines.ini"

WORKDIR /var/www/html

# Composer and node in one stage: the Vite build runs `php artisan wayfinder:generate`.
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
# A throwaway .env so artisan can boot for the build; settings come from the environment.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && cp .env.example .env \
    && php artisan key:generate --force \
    && npm run build \
    && rm -rf .env node_modules \
    && php artisan storage:link --force

FROM base AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/single/nginx.conf /etc/nginx/conf.d/baines.conf
COPY docker/single/supervisord.conf /etc/supervisor/conf.d/baines.conf
COPY docker/single/entrypoint.sh /usr/local/bin/baines-entrypoint
RUN chmod +x /usr/local/bin/baines-entrypoint

# Defaults for one container; any of them can be overridden with -e. `staging` seeds the demo
# cars and lets Cloudflare's always-pass Turnstile test keys through; for a live site pass
# APP_ENV=production, real TURNSTILE_* keys and the other keys in .env.example.
ENV APP_NAME="Baines Executives" \
    APP_ENV=staging \
    APP_DEBUG=false \
    APP_URL=http://localhost:8080 \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/database/baines.sqlite \
    DB_JOURNAL_MODE=wal \
    DB_BUSY_TIMEOUT=10000 \
    DB_SYNCHRONOUS=normal \
    DB_TRANSACTION_MODE=IMMEDIATE \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    MAIL_MAILER=log \
    INERTIA_SSR_ENABLED=false \
    TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16 \
    TURNSTILE_SITE_KEY=1x00000000000000000000AA \
    TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA \
    SETTINGS_CACHE_ENABLED=true

VOLUME /var/www/html/storage
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s CMD curl -fsS http://127.0.0.1/up > /dev/null || exit 1

ENTRYPOINT ["baines-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]

# Production images for a Docker VPS (M12.15, D44). Build from the repo root:
#   docker build -f docker/Dockerfile --target app   -t baines-app .
#   docker build -f docker/Dockerfile --target web   -t baines-web .
#   docker build -f docker/Dockerfile --target ssr   -t baines-ssr .
# `app` runs php-fpm, Horizon and the scheduler (different commands, see compose.prod.yaml),
# `web` is nginx with the built assets, `ssr` is the Inertia node renderer.

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
# Browser Sentry is baked into the assets (VITE_* are read at build time, M12.10).
ARG RELEASE=dev
ARG VITE_SENTRY_DSN=
ARG VITE_SENTRY_ENVIRONMENT=
ENV VITE_SENTRY_RELEASE=${RELEASE} VITE_SENTRY_DSN=${VITE_SENTRY_DSN} VITE_SENTRY_ENVIRONMENT=${VITE_SENTRY_ENVIRONMENT}
# A throwaway .env so artisan can boot for the build; the real one is mounted at run time.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && cp .env.example .env \
    && php artisan key:generate --force \
    && npm run build:ssr \
    && rm .env \
    && php artisan storage:link --force \
    && npm prune --omit=dev \
    && mv node_modules /opt/ssr-node_modules

FROM base AS app

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/baines-entrypoint
RUN chmod +x /usr/local/bin/baines-entrypoint

ARG RELEASE=dev
ENV SENTRY_RELEASE=${RELEASE}

USER www-data
ENTRYPOINT ["baines-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-bookworm AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public

FROM node:22-bookworm-slim AS ssr

WORKDIR /app
COPY --from=build /var/www/html/package.json ./package.json
COPY --from=build /opt/ssr-node_modules ./node_modules
COPY --from=build /var/www/html/bootstrap/ssr ./bootstrap/ssr

USER node
EXPOSE 13714
CMD ["node", "bootstrap/ssr/app.js"]

# Baines Executives — Runbook

How to deploy, roll back, recover and look after the app on staging and production (M12.14). The design behind it is TECHNICAL_PLAN §17, decision D44 for hosting and D45 for backups. Keys and accounts are listed in `docs/INTEGRATIONS.md` (M12B.1).

The app runs on one of two hosts, picked per environment by the `DEPLOY_TARGET` variable of its GitHub environment:

| | Docker VPS | cPanel |
| --- | --- | --- |
| Web | `web` (nginx) → `app` (php-fpm) | Apache → PHP |
| Pages rendered on the server (SSR) | `ssr` container | Off (`INERTIA_SSR_ENABLED=false`); pages render in the browser |
| Queue | Redis, worked by `horizon` | Database, drained each minute by the scheduler (`QUEUE_VIA_SCHEDULER=true`) |
| Scheduler | `scheduler` container (`schedule:work`) | cron: `schedule:run` every minute |
| Cache and sessions | Redis | Database |
| Deploy script | `scripts/deploy-docker.sh` | `scripts/deploy-cpanel.sh` |

## 1. Environment file

Start from `.env.example`. On staging and production, also set:

| Key | Docker VPS | cPanel |
| --- | --- | --- |
| `APP_ENV`, `APP_DEBUG` | `staging` or `production`, `false` | same |
| `APP_KEY` | `php artisan key:generate --show`, once; see §6 before ever changing it | same |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `redis`, `redis`, `redis` | `database`, `database`, `database` |
| `REDIS_HOST` | `redis` | — |
| `QUEUE_VIA_SCHEDULER` | `false` | `true` |
| `INERTIA_SSR_ENABLED`, `INERTIA_SSR_URL` | `true`, `http://ssr:13714` | `false` |
| `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT` | `true`, `true` | `true`, `true` |
| `SETTINGS_CACHE_ENABLED` | `true` | `true` |
| `TRUSTED_PROXIES` | Docker ranges (`10.0.0.0/8,172.16.0.0/12,192.168.0.0/16`) plus Cloudflare's ranges | Cloudflare's ranges if Cloudflare is in front, else empty |
| `OPS_ALERT_EMAIL` | who gets Horizon alerts | — |
| `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT` | the PHP project's DSN, the environment name | same |

`DARAJA_ALLOWED_IPS` is required with `DARAJA_ENV=production`: without it, every M-Pesa callback is refused (M12.16). Browser Sentry (`VITE_SENTRY_DSN`) is built into the assets, so it is set as the `SENTRY_JS_DSN` variable of the GitHub environment, not in `.env`.

## 2. First deploy

### Docker VPS

1. Install Docker with the compose plugin. Put a reverse proxy (Caddy, or nginx with Let's Encrypt) in front of port `BAINES_HTTP_PORT` (default 8080) for TLS, or point Cloudflare at it.
2. Create the deploy directory (`DEPLOY_PATH`, for example `/srv/baines`) with the `.env` from §1. MySQL is managed or on the host; set `DB_HOST`.
3. Log in to GHCR once so the server can pull: `docker login ghcr.io` with a token that can read packages.
4. In GitHub → Settings → Environments → `staging`: set the variables `DEPLOY_TARGET=docker`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, and the secrets `DEPLOY_SSH_KEY` (a key that can only log in as `DEPLOY_USER`) and `DEPLOY_KNOWN_HOSTS` (`ssh-keyscan <host>`).
5. Push to `main`. Once ci passes, the deploy workflow builds and pushes the images and runs `deploy-docker.sh`.
6. Seed once: `docker compose -f compose.prod.yaml run --rm app php artisan db:seed --force`. Then create the first super admin in the admin.

### cPanel

1. In cPanel, pick PHP 8.4 with the `imagick` (or `gd`), `intl`, `pdo_mysql`, `zip`, `bcmath` and `exif` extensions, `upload_max_filesize` at least 21M and `post_max_size` at least 24M. Create the MySQL database and user.
2. Enable SSH access and add the deploy public key. Choose `DEPLOY_PATH` (for example `/home/<user>/baines`), create `DEPLOY_PATH/shared/.env` from §1, and point the domain's document root at `DEPLOY_PATH/current/public`.
3. Add the cron job (cPanel → Cron Jobs, every minute):
   `cd ~/baines/current && php artisan schedule:run >> /dev/null 2>&1`
   (use the full PHP path cPanel shows, for example `/usr/local/bin/ea-php84`, if `php` is a different version).
4. Set the GitHub environment as for Docker, with `DEPLOY_TARGET=cpanel`.
5. Push to `main`, then seed once over SSH: `cd ~/baines/current && php artisan db:seed --force`.

## 3. Deploy

Staging deploys itself after ci passes on `main`. Production: Actions → deploy → Run workflow → `production`, from the commit that passed on staging.

Both scripts, in order: migrate, `permissions:sync`, `settings:clear-cache` (settings migrations write past the cache), cache config, routes, views and events (`optimize`), then switch traffic. Keep migrations additive (new columns nullable, drops in a later release), since the old code serves requests until the switch.

## 4. Roll back

- **Docker:** `.deployed-tags` in the deploy directory lists every deployed tag. Run `bash deploy-docker.sh <previous-tag>`.
- **cPanel:** `releases/` keeps the last five. `ln -sfn ~/baines/releases/<previous-sha> ~/baines/current`, then `cd ~/baines/current && php artisan optimize && php artisan queue:restart`.

Neither undoes migrations. If a migration must be reversed, run `php artisan migrate:rollback --step=1` **before** switching back, and only if its `down()` loses no data.

## 5. Health and alerts

- **`/health`** (uptime monitor, set up in M12B.8) returns 200 or 503 with four checks:
  - `database`: MySQL unreachable. Check the DB host and credentials in `.env`, and the managed database's status page.
  - `cache`: Redis (Docker) or the `cache` table (cPanel) unreachable.
  - `scheduler`: no heartbeat for 5 minutes. Docker: `docker compose -f compose.prod.yaml ps scheduler`, `logs scheduler`. cPanel: check the cron job still exists and its PHP path works (run the line by hand over SSH).
  - `queue`: database-queue jobs waiting over 15 minutes (cPanel). Same as `scheduler`, then `php artisan queue:failed`.
- **Horizon** (Docker): `/horizon`, for staff with `system.horizon`. Long waits and failures mail `OPS_ALERT_EMAIL`.
- **Failed jobs:** `php artisan queue:failed`, retry with `queue:retry <id>` or `queue:retry all`. They are pruned after 7 days.
- **Sentry:** PHP and browser errors, tagged with the git sha as the release. Request bodies, booking tokens and the Daraja secret are scrubbed before sending (M12.4).

## 6. Rotate keys

| Key | How | Watch out |
| --- | --- | --- |
| `APP_KEY` | Put the old key in `APP_PREVIOUS_KEYS`, set the new one, deploy. | Encrypted columns (driver ID, KRA PIN, licence numbers, payer phones) still decrypt through `APP_PREVIOUS_KEYS`, but `booking_drivers.id_number_hash` is an HMAC keyed on `APP_KEY`, so lookups by ID number stop matching for older rows. Rotate only if the key leaked, and then rehash the column (one-off tinker loop over `BookingDriver`: `id_number_hash = BookingDriver::hashIdNumber($driver->id_number)`). Sessions are signed out. |
| `DARAJA_CALLBACK_SECRET` | Set a new long random value, deploy, then `php artisan mpesa:register-c2b`. | Safaricom allows registering production C2B URLs once per shortcode; a second change needs Safaricom support. STK pushes started before the switch call back on the old URL and are picked up by `payments:reconcile`. |
| Daraja consumer key and secret, passkey | Regenerate on the Daraja portal, update `.env`, deploy (or `php artisan optimize` on cPanel). | The cached OAuth token expires within the hour. |
| `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY` | Roll the keys in the Paystack dashboard, update `.env`, deploy. | Webhook signatures use the secret key: events signed with the old key are refused, and `payments:reconcile` picks those payments up. |
| `RESEND_API_KEY`, `FX_API_KEY`, Turnstile, AWS keys | Create the new key, update `.env`, deploy, then revoke the old key. | — |
| Deploy SSH key, GHCR token | Replace in the GitHub environment and on the server. | — |

After any rotation, check that no key sits in the repo, the logs or Sentry (M12B.11).

## 7. Backups and restore (D45)

The app has no backup code. The host keeps:

- **Database:** daily snapshots kept 30 days. On a VPS, from the managed MySQL service or the provider's volume snapshots; on cPanel, from JetBackup or the host's daily backup (confirm the retention with the host).
- **Media:** on S3, bucket versioning plus a lifecycle rule that expires old versions after 30 days, on both buckets. On local disks (cPanel, or a VPS before M12B.7), `shared/storage` (cPanel) or the `storage` volume (Docker) must be inside the host backup. Private media holds renters' KYC documents.
- **`.env`:** kept outside the backups, in the team's password manager. Restoring a database without its `APP_KEY` leaves the encrypted columns unreadable.

**Restore drill (M12.11):** at least once before launch, then every quarter.

1. Restore last night's database snapshot into a new, empty database.
2. Point a staging copy of the app at it, using the production `APP_KEY`.
3. Open a recent booking in the admin; open one of its KYC documents and its voucher.
4. Record the date, the time the restore took and anything that failed in the MILESTONES progress log.

## 8. Switch the media disk

1. Create the buckets and keys (M12B.7) and set `AWS_*` in `.env`.
2. Set `MEDIA_PUBLIC_DISK=media-public-s3` and `MEDIA_PRIVATE_DISK=media-private-s3`, deploy. New uploads go to S3; old files stay readable where they are (each row records its disk).
3. Move the old files when it suits:
   `php artisan media:migrate-disk media-public-local media-public-s3 --dry-run`, then without `--dry-run`. Repeat for the private disks. `--keep-source` leaves the local copies until you have checked the move.

## 9. Maintenance mode

`php artisan down --render="errors::503" --retry=60` (Docker: through `docker compose -f compose.prod.yaml exec app`), and `php artisan up` afterwards. Gateway callbacks get 503 meanwhile; Safaricom and Paystack retry, and `payments:reconcile` catches anything they give up on.

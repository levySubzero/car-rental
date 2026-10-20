# Baines Executives

Car rental website, booking engine and admin CMS for Baines Executives (Nairobi).

| Document                                                               | What it covers                                               |
| ---------------------------------------------------------------------- | ------------------------------------------------------------ |
| [docs/Baines Executives — PRD, Architecture & Delivery Plan.md](docs/) | Requirements (G-, H-, F-, B-, Q-, P-, O-, A- IDs)            |
| [docs/TECHNICAL_PLAN.md](docs/TECHNICAL_PLAN.md)                       | How we build it. It wins wherever it disagrees with the PRD. |
| [docs/MILESTONES.md](docs/MILESTONES.md)                               | Live progress tracker. Update it after every step.           |

Stack: Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4 and shadcn/ui, with MySQL 8, Redis (production) and S3-compatible storage. See TECHNICAL_PLAN §1.

## Local setup

Everything runs on the host: PHP 8.3+ with Composer, Node 22+ and MySQL 8. Docker and Redis aren't needed. Locally, cache, queue and session use the database, and mail goes to `storage/logs/laravel.log`.

Create the database and user once (as a MySQL admin):

```sql
CREATE DATABASE baines CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'baines'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON baines.* TO 'baines'@'localhost';
```

Then:

```bash
composer install
cp .env.example .env          # set DB_PASSWORD
php artisan key:generate
php artisan migrate --seed    # also runs permissions:sync
php artisan storage:link      # serves public media from /media-files
npm install
composer dev                  # server on :8000, queue worker, logs and Vite
```

Sign in at http://localhost:8000/login as `admin@baines.test` / `password`. 2FA is optional unless you turn it on under Settings → Security. Locally there are also `ops@baines.test` and `editor@baines.test`.

**Quick sign-in.** With `APP_ENV=local`, the login page has a "Development: sign in as" dropdown that signs you in as any active user without a password or 2FA. Set `DEV_LOGIN=false` to hide it. The endpoint returns 404 in every other environment.

**Images.** Uploads are re-encoded with Imagick when the PHP extension is installed, otherwise GD. GD can't read HEIC (iPhone photos), so HEIC uploads are refused until you install `php-imagick` with libheif. Set `MEDIA_IMAGE_DRIVER` to force one driver.

**S3.** Media uses local disks by default. To test the S3 path, point the `AWS_*` variables at a bucket and set `MEDIA_PUBLIC_DISK=media-public-s3` and `MEDIA_PRIVATE_DISK=media-private-s3`.

## Commands

| Command                                 | What it runs                                                                        |
| --------------------------------------- | ----------------------------------------------------------------------------------- |
| `composer lint` / `composer lint:check` | Pint (fix / check)                                                                  |
| `composer types`                        | Larastan, level 7                                                                   |
| `composer test`                         | Pest: Unit, Arch and Feature, in parallel                                           |
| `npm run check` / `npm run check:fix`   | vite-plus: oxlint and oxfmt                                                         |
| `npm run types:check`                   | `tsc --noEmit`                                                                      |
| `npm test`                              | Vitest (`vp test`)                                                                  |
| `npm run e2e`                           | Playwright, against a running app (`E2E_BASE_URL`, default `http://localhost:8000`) |
| `composer ci:check`                     | Everything CI runs                                                                  |

Tests use in-memory SQLite (see `phpunit.xml`), so they don't need MySQL.

## Conventions

The full rules are in TECHNICAL_PLAN §2. `tests/Arch` enforces them.

```
Route → Controller → FormRequest (authorize via policy + validate)
             ├→ Service (business logic, transactions, events)
             └→ Resource (output shape) → Inertia::render
```

- **Controllers** are thin: one service call, then a render or redirect. No `DB` or `Storage`.
- **FormRequests** always implement `authorize()`, and `validated()` is the only input that reaches a service.
- **Services** hold the business logic. They never touch `request()`, responses or Inertia.
- **Resources**: admin and public classes are separate. Public resources never expose `car_value*`.
- **Policies** check permission strings (`cars.update`), never role names.
- **Money** is stored as integer minor units in `*_minor` columns. Use `App\Support\Money`.
- **Files**: forms post media UUIDs and never upload files themselves (TECHNICAL_PLAN §6).

## Adding a module

1. Add the module's cases to `app/Enums/Permission.php`, run `php artisan permissions:sync` and `php artisan permissions:types`, and commit the regenerated `resources/js/types/permissions.ts`. Roles are data: grant the new permissions to roles in the admin (Roles page).
2. Write the migration and model (`metadata` JSON where the plan says so), plus a factory.
3. Add a policy whose methods check `$user->can(Permission::…)`.
4. If the module stores files, register its collections in `config/media.php` (owner = the model's morph alias), add the alias to `configureMorphMap()` in `AppServiceProvider`, and `use HasMedia` on the model. Forms validate uuids with `MaxMediaForCollection` and `PendingOrOwnedMedia`, and the service calls `MediaService::sync()` (TECHNICAL_PLAN §6).
5. Add the FormRequests, the service (under `app/Services/{Domain}`) and admin and public resources.
6. Add the controller, routes (in `routes/admin.php` for admin) and Inertia pages (in `resources/js/pages/admin/...`).
7. Add a sidebar entry with its `permission` in `resources/js/config/admin-nav.ts`.
8. Write Pest feature tests for guest, 403, validation and the happy path. The route guard test catches a missing policy.
9. Make sure `composer ci:check` is green, then tick the step in `docs/MILESTONES.md` and add a log row.

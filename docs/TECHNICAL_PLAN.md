# Baines Executives — Technical Plan

Source: _Baines Executives — PRD, Architecture & Delivery Plan_ (16 Oct 2026).
Companion: [MILESTONES.md](MILESTONES.md) is the live progress tracker. Update it after every step.

This plan replaces the PRD's architecture section (§8) where they disagree. The main changes from the PRD:

- We use the classic Laravel layers: **Controllers → Form Requests → Services → Models**, with **API Resources** for output and **Policies** for authorization. We do not use Actions, spatie/laravel-data DTOs, or spatie/laravel-model-states.
- **Media is its own shared feature.** One module owns uploads, storage, retrieval and download for every entity. Forms never post files. They post media UUIDs.
- **Permissions are generated per module.** Each module adds its permissions to a registry as it is built, and a sync command writes them to the database.
- **We make values configurable instead of waiting on client answers.** Every open question in PRD §10 becomes a setting, a table or a driver with a sensible default (see §9). The client can change these values later without a code change.
- **Some models have a `metadata` JSON field** for informal extras that need no column (D20). It is free-form and not shown publicly unless a page picks a key on purpose.

---

## 1. Stack

| Concern                 | Choice                                                                         | Notes                                  |
| ----------------------- | ------------------------------------------------------------------------------ | -------------------------------------- |
| Runtime | PHP 8.3+ (CI runs 8.4), Laravel 13 | D10 |
| Frontend | Inertia.js 3, React 19, TypeScript, Vite 8 via vite-plus, Tailwind CSS 4 | Laravel React starter kit as the base (D10) |
| UI kit                  | shadcn/ui (admin and shared primitives), Embla Carousel (gallery, marquee)     |                                        |
| Typed routes            | Laravel Wayfinder (ships with the starter kit)                                 | Ziggy only if Wayfinder blocks us      |
| SSR                     | Inertia SSR Node sidecar, public routes only                                   |                                        |
| Auth / 2FA              | Laravel Fortify (starter kit) with TOTP 2FA; requiring it for staff is a setting (`SecuritySettings::require_two_factor`, off by default) |                                        |
| Database | MySQL 8.4, InnoDB, utf8mb4 | Tests run on SQLite in-memory; MySQL-only behaviour (row locks, JSON) gets a MySQL CI job in M8 |
| Queue / cache / session | Redis, Laravel Horizon                                                         |                                        |
| Storage | Laravel filesystem disks; local or S3-compatible, chosen by env | Wrapped by our Media module, see §6 |
| Images | intervention/image v4 (Imagick driver, so HEIC works) | Conversions and EXIF stripping |
| Settings                | spatie/laravel-settings                                                        | Typed settings classes per group       |
| Permissions             | spatie/laravel-permission                                                      | Fed by our permission registry, see §5 |
| Audit                   | spatie/laravel-activitylog                                                     |                                        |
| PDF                     | barryvdh/laravel-dompdf                                                        | No headless Chrome in the containers   |
| Bot protection          | Cloudflare Turnstile (custom validation rule)                                  |                                        |
| Testing | Pest 4 (unit, feature, arch), Vitest via `vp test` (TS quote port), Playwright (E2E booking) | Pest 4 because the kit ships PHPUnit 12 |
| Quality | Pint, Larastan (level 7), vite-plus `vp check` (oxlint + oxfmt), `tsc --noEmit` | All run in CI (D11) |
| Errors / uptime         | Sentry (PHP + JS), uptime check on the booking endpoint                        |                                        |

---

## 2. Layering rules

Every feature follows the same path. These rules are enforced by Pest `arch()` tests (§16).

```
Route ──► Controller ──► FormRequest (authorize + validate)
              │
              ├──► Service (business logic, transactions, events)
              │        └──► Models / other Services / Media service
              │
              └──► Resource (shape output) ──► Inertia::render / JSON
Policy ◄── FormRequest::authorize() / $this->authorize() / route `can:` middleware
```

| Layer              | Responsibility                                                                                                                                                                             | Must not                                                                                  |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------- |
| **Controller**     | Receive a request, call one service method, return an Inertia response or a redirect. Stay thin, around 10 lines per action.                                                               | Query the database beyond route-model binding, hold business rules, or touch storage      |
| **FormRequest**    | `authorize()` delegates to a policy. `rules()` validates the request, and `validated()` is the only input that reaches services.                                                           | Contain business logic                                                                    |
| **Service**        | Business logic and orchestration. It owns DB transactions, raises domain exceptions and dispatches events or jobs. It receives plain arrays or models and returns models or value objects. | Read `request()`, return HTTP responses, or render views                                  |
| **Resource**       | Shape output for Inertia props and JSON. Public and admin resources are separate classes.                                                                                                  | Leak admin-only fields such as `car_value` or encrypted identifiers into public resources |
| **Policy**         | Map an ability to a permission string, with an ownership or state check where needed                                                                                                       | Hard-code role names. Policies check permissions only.                                    |
| **Model**          | Relations, casts, scopes, accessors                                                                                                                                                        | Contain workflow logic                                                                    |
| **Enum**           | Statuses, types and bases, including their labels and transition maps                                                                                                                      |                                                                                           |
| **Job / Listener** | Async side effects: conversions, notifications, FX pulls, purges                                                                                                                           |                                                                                           |

Naming:

- Controllers: `Admin\CarController`, `Public\FleetController`, `Media\MediaController`
- Requests: `Admin\Car\StoreCarRequest`, `Admin\Car\UpdateCarRequest`
- Resources: `Admin\CarResource`, `Public\CarCardResource`, `Public\CarDetailResource`
- Services: `Catalog\CarService`, `Pricing\QuoteService`, `Booking\BookingService`
- Policies: `CarPolicy`, auto-discovered

Small immutable value objects, such as `Quote`, `Money` and `DateRange`, are allowed under `app/Support/*`. They are plain readonly classes, not a DTO framework.

---

## 3. Directory layout

```
app/
  Console/Commands/          permissions:sync, fx:refresh, media:cleanup, media:migrate-disk,
                             bookings:expire, kyc:purge
  Enums/                     BookingStatus, PaymentMethodCode, PaymentStatus, IdType, ChargeBasis,
                             MediaVisibility, DayCountingStrategy, Transmission, ...
  Exceptions/Domain/         CarUnavailableException, DriverAgeException, InvalidTransitionException, ...
  Http/
    Controllers/
      Admin/                 CarController, BrandController, CarTypeController, ServiceController,
                             PageController, PolicyController, SettingsController, CurrencyController,
                             FxRateController, ChargeController, DepositBandController, BookingController,
                             PaymentSubmissionController, AvailabilityController,
                             UserController, RoleController, ActivityLogController
      Public/                HomeController, FleetController, CarController, BrandController,
                             CarTypeController, ServiceController, PolicyController, PageController,
                             ContactController, CurrencyController, BookingController,
                             BookingPaymentController
      Media/                 MediaController   (the single upload/serve/download/delete endpoint)
    Middleware/              ShareCurrency, EnsureStaff, ResolveBookingToken, EnsureTwoFactor
    Requests/{Admin,Public,Media}/...
    Resources/{Admin,Public,Shared}/...
  Models/                    Brand, CarType, Car, Service, Page, PageSection, Policy, PolicyVersion,
                             Currency, FxRate, Charge, DeliveryZone, DepositBand, CancellationTier,
                             Booking, BookingDriver, BookingStatusHistory, AvailabilityHold, CarBlackout,
                             PaymentMethod, PaymentSubmission, Media, ContactMessage, User
  Models/Concerns/           HasMedia, HasSlug, Sortable
  Notifications/             BookingReceived, StaffNewBooking, PaymentSubmitted, BookingConfirmed,
                             PaymentRejected
  Notifications/Channels/    WhatsAppChannel (driver-based)
  Policies/                  one per model + MediaPolicy
  Services/
    Catalog/                 BrandService, CarTypeService, CarService
    Content/                 PageService, PolicyService, ServiceCatalogService
    Media/                   MediaService, MediaCollectionRegistry, ImageProcessor, MediaUrlGenerator
    Pricing/                 QuoteService, Quote/*, DayCounter/*, FxRateService, Fx/*, CurrencyService
    Booking/                 BookingService, BookingStatusService, AvailabilityService,
                             DriverEligibilityService, BookingReferenceGenerator
    Payments/                PaymentSubmissionService, PaymentVerificationService
    Notifications/           VoucherService, WhatsAppLinkBuilder, WhatsApp/Drivers/*
    Iam/                     PermissionRegistry, PermissionSyncService
  Settings/                  GeneralSettings, PricingSettings, BookingSettings, FxSettings,
                             NotificationSettings, HomeSettings, RetentionSettings
  Support/                   Money, Quote, DateRange, Rules/*
config/
  media.php                  disks and collection registry
  baines.php                 non-editable app constants (reference format, upload limits)
database/
  migrations/  factories/  seeders/ (CmsSeeder + JSON seed pack under database/seeders/data/)
resources/js/
  pages/{public,admin}/...   mirrors controllers
  components/{ui,public,admin,media}/
  lib/money.ts, lib/whatsapp.ts
  types/                     resource types (hand-maintained, one file per resource group)
tests/
  Unit/  Feature/{Admin,Public,Media,Booking,Payments}/  Arch/  fixtures/quotes.json
  e2e/ (Playwright)
```

---

## 4. Request shapes

Admin CRUD routes are Inertia resource routes:

```php
Route::middleware(['auth', 'verified', 'staff', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('cars', Admin\CarController::class);
    Route::post('cars/{car}/toggle-availability', [Admin\CarController::class, 'toggleAvailability'])
        ->name('cars.toggle-availability');
    // ...
});
```

A typical controller action:

```php
public function store(StoreCarRequest $request, CarService $cars): RedirectResponse
{
    $car = $cars->create($request->validated());

    return to_route('admin.cars.edit', $car)->with('success', 'Car created.');
}
```

A form request with media and extra info (see `Admin\Car\SaveCarRequest` for the real one):

```php
public function authorize(): bool { return $this->user()->can('create', Car::class); }

public function rules(): array
{
    return [
        'name'            => ['required', 'string', 'max:120'],
        'brand_id'        => ['required', 'exists:brands,id'],
        'daily_rate'      => ['required', 'numeric', 'min:0'],            // major units in, minor units stored
        'gallery'         => ['array', new MaxMediaForCollection('car.gallery')],
        'gallery.*'       => ['uuid', new PendingOrOwnedMedia('car.gallery', $this->route('car'))],
        'cover'           => ['nullable', 'uuid', 'in_array:gallery.*'],
        'metadata'        => ['nullable', 'array', 'max:30', new MetadataKeys],   // informal extras (D20)
        'metadata.*'      => ['nullable', 'string', 'max:500'],
    ];
}
```

A service, with the transaction and media sync inside it:

```php
public function create(array $data, User $actor): Car
{
    return DB::transaction(function () use ($data, $actor) {
        $car = Car::create(Arr::except($data, ['gallery', 'cover']));       // the model logs itself (LogsActivity)
        $this->media->sync($car, 'car.gallery', $data['gallery'] ?? [], cover: $data['cover'] ?? null, actor: $actor);

        return $car;
    });
}
```

---

## 5. Permissions (generated per module)

**Convention:** permission names are `{module}.{ability}`, for example `cars.view`, `cars.create`, `cars.update`, `cars.delete`, `cars.view-value`, `bookings.verify-payment` and `media.view-private`.

**Permissions are code; roles are data** (D14, D15).

- `App\Enums\Permission` has one case per permission. Its value is the permission name, and cases are grouped by module. Code only ever checks permissions, so they live in code and are type-checked.
- Roles are rows in `roles` (`App\Models\Role`, extending spatie's model with `description` and `is_system`). Admins create roles, name them and tick their permissions in the admin, then assign roles to users.
- The one exception is **super admin**: a system role (`config('baines.super_admin_role')`), seeded and locked. It can't be renamed, edited or deleted, holds every permission, and passes every check through `Gate::before`. `DatabaseSeeder` also creates "operations" and "content-editor" once, as editable starting points from PRD §3.

```php
enum Permission: string
{
    case CarsView = 'cars.view';
    case CarsViewValue = 'cars.view-value';
    case BookingsVerifyPayment = 'bookings.verify-payment';
    // each milestone adds its module's cases here

    public function module(): string;                               // 'cars'
    public function ability(): string;                              // 'view-value'
    public static function forModule(string ...$modules): array;    // every case of those modules
    public static function except(array $permissions, self ...$excluded): array;
    public static function grouped(): array;                        // ['cars' => ['cars.view', ...]], for checklists
}
```

- `php artisan permissions:sync` is run by the deploy step and by `DatabaseSeeder`. It is idempotent: it upserts the enum's permissions, keeps the super admin role locked with every permission, leaves admin-managed roles alone, and reports orphaned permissions without deleting them unless you pass `--prune`.
- `php artisan permissions:types` writes `resources/js/types/permissions.ts`, a TypeScript union of every permission. `can()`, `useCan()` and the nav config are typed against it, so a misspelled permission fails `tsc`. A test fails when the file is stale.
- Policies call `$user->can(Permission::CarsUpdate)`, and an arch test requires every policy to use the enum. No code checks a role name except the super admin bypass.
- Users get roles and, optionally, extra direct permissions in the admin. A role still assigned to users can't be deleted.
- Rules that must also bind super admins (the locked role, keeping one super admin, not changing your own access) are enforced in the services, because super admins bypass policies.
- **Guard test:** a feature test walks every `admin.*` route and asserts that a user with no permissions gets 403. If a new route lacks a policy, CI fails.
- **Definition of done for any new module:** enum cases, policy, permission test, `permissions:sync` and `permissions:types` run. Granting the new permissions to roles is done in the admin (or in a seeder for the starting roles).

---

## 6. Media module (shared by every entity)

### 6.1 Principles

1. **One controller and one set of endpoints** serve every feature: cars, brands, services, pages, booking KYC documents, payment proofs and vouchers.
2. **Forms never upload files.** The `<MediaUploader>` component uploads each file to `POST /media` as soon as it is picked and receives a UUID. The form then submits the UUIDs, in order. Services call `MediaService::sync()` to attach, reorder or detach files.
3. **Each media row records the disk it was written to.** Changing the env disk affects new uploads only, and existing files stay readable. `media:migrate-disk` moves files between disks.
4. **Visibility belongs to the collection, not the request.** Public collections go to the public disk. Private collections (KYC, payment proofs, vouchers) always go to the private disk.

### 6.2 Disk configuration

`config/filesystems.php` defines `media-public-local`, `media-private-local`, `media-public-s3` and `media-private-s3`.

`config/media.php`:

```php
'disks' => [
    'public'  => env('MEDIA_PUBLIC_DISK', 'media-public-local'),   // switch to media-public-s3
    'private' => env('MEDIA_PRIVATE_DISK', 'media-private-local'), // switch to media-private-s3
],
'signed_url_ttl' => env('MEDIA_SIGNED_URL_TTL', 5),               // minutes, private files
'pending_ttl'    => env('MEDIA_PENDING_TTL', 1440),               // minutes before orphan cleanup
'serve_public_via' => env('MEDIA_PUBLIC_SERVE', 'redirect'),      // redirect (CDN/disk URL) | stream
'conversions' => [
    'thumb' => ['w' => 320,  'format' => 'webp', 'q' => 75],
    'card'  => ['w' => 800,  'format' => 'webp', 'q' => 80],
    'full'  => ['w' => 1920, 'format' => 'webp', 'q' => 82],
],
'collections' => [
    'car.gallery'       => ['model' => Car::class,     'visibility' => 'public',  'max_files' => 6,
                            'mimes' => ['jpg','jpeg','png','webp','heic'], 'max_kb' => 8192,
                            'conversions' => ['thumb','card','full'], 'upload' => ['permission' => 'cars.update']],
    'brand.logo'        => ['model' => Brand::class,   'visibility' => 'public',  'max_files' => 1,
                            'mimes' => ['png','webp'], 'upload' => ['permission' => 'brands.update']],   // no SVG yet (D21)
    'service.image'     => [...], 'page.image' => [...], 'site.hero' => [...],   // page.image: preset page image fields (D22)
    'booking.id_front'  => ['model' => Booking::class, 'visibility' => 'private', 'max_files' => 1,
                            'mimes' => ['jpg','jpeg','png','heic','pdf'], 'max_kb' => 8192,
                            'strip_exif' => true, 'upload' => ['booking_token' => true]],
    'booking.id_back' => [...], 'booking.passport' => [...], 'booking.kra_certificate' => [...],
    'booking.licence' => [...], 'booking.payment_proof' => [...],
    'booking.voucher'   => ['visibility' => 'private', 'upload' => ['system' => true]],
],
```

The block above is the target set. Each collection is registered by the milestone that builds its owner (D16): M2 ships only `site.hero`, then `car.gallery` and `brand.logo` arrive in M4, `service.image` and `page.image` in M5, `booking.*` in M8 and `booking.voucher` in M10. Owners are named by morph alias (`'owner' => 'car'`, D17), and `upload` can also be `['token' => true]` for renter uploads.

Admins can lower `max_files`, `max_kb` and `mimes` per collection on the Uploads settings tab (`MediaSettings`, `settings.media.update`). The config values are hard ceilings, and `max_kb` never exceeds `baines.uploads.max_kb_ceiling`. HEIC is dropped from every collection when the image driver can't decode it (D19).

### 6.3 Table `media`

| Column                                                                      | Notes                                                                  |
| --------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| id, uuid                                                                    | The UUID is the only identifier exposed publicly                       |
| mediable_type, mediable_id                                                  | Nullable while the file is pending                                     |
| collection                                                                  | Registry key, e.g. `car.gallery`                                       |
| disk, path, conversions (JSON)                                              | Physical location of the original and each conversion                  |
| original_name, file_name, mime_type, extension, size_bytes, checksum_sha256 | `mime_type` is sniffed, never taken from the client                    |
| width, height                                                               | Images only                                                            |
| visibility                                                                  | `public` or `private` (enum)                                           |
| status                                                                      | `pending` or `attached` (`MediaStatus`)                                |
| processing                                                                  | `queued`, `done`, `failed` or `skipped` (`MediaProcessing`, D18)       |
| is_primary, sort_order                                                      | Cover image and gallery order                                          |
| metadata (JSON)                                                             | Alt text, caption, credit, page count for PDFs, `exif_stripped`        |
| uploaded_by_type, uploaded_by_id                                            | A staff user or a booking (renter)                                     |
| expires_at                                                                  | Set while pending; `media:cleanup` deletes rows that expire unattached |
| timestamps, deleted_at                                                      | Soft-deleted files are purged by job                                   |

### 6.4 Endpoints, the only media routes in the app

| Method and path                   | Purpose                                                                                                 | Authorization                                                                                                                                                                                                |
| --------------------------------- | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `POST /media`                     | Upload one file (`file`, `collection`) and get back a `MediaResource`. The file stays pending and owner-less until the form's service calls `sync()` | Collection upload rule: a staff permission, or a valid booking token sent in the `X-Booking-Token` header                                                                                                    |
| `GET /media/{uuid}/{conversion?}` | Retrieve or render inline                                                                               | Public: open, with a redirect to the disk or CDN URL and long cache headers. Private: authorized via `MediaPolicy::view`, served through a temporary signed URL (S3) or a signed stream (local), and logged. |
| `GET /media/{uuid}/download`      | Download with `Content-Disposition: attachment`                                                         | Same as above                                                                                                                                                                                                |
| `PATCH /media/{uuid}`             | Update alt text or caption metadata                                                                     | Owner's update permission                                                                                                                                                                                    |
| `DELETE /media/{uuid}`            | Delete a pending file or detach one                                                                     | Owner's update permission, or the uploader while pending                                                                                                                                                     |
| `POST /media/reorder`             | `{collection, owner_type, owner_id, uuids[]}`                                                           | Owner's update permission                                                                                                                                                                                    |
| `GET /media/s/{uuid}/{conversion?}` | Target of temporary signed URLs on local disks                                                        | The signature (`signed` middleware); issued only after an authorized, logged `GET /media/{uuid}`                                                                                                             |

`MediaPolicy` resolves the owner model and delegates to that model's policy: `update` to manage files, and `viewMedia` (falling back to `view`) to open private ones, so `BookingPolicy::viewMedia` can require `bookings.view-documents`. Renters can only see their own pending or attached booking files, and only through their token.

The media routes have no `auth` middleware, because renters use them. `ResolveMediaUploader` fills the request-scoped `CurrentUploader`: an `X-Booking-Token` header resolves through the `TokenUploaderResolver` contract (a null driver until M8 binds the booking lookup), and a session user only counts as staff if they would pass `EnsureStaff` and `EnsureTwoFactor`. All media checks run through `CurrentUploader::allows()` (`Gate::forUser` the qualified staff member), so the super admin bypass also needs a qualifying session. System collections are refused in `StoreMediaRequest` too, because `Gate::before` would let a super admin past the policy. `MediaRouteGuardTest` covers every `media.*` route, since the admin route guard only walks `admin.*`.

### 6.5 Services

- `MediaCollectionRegistry`: reads the config and setting overrides, resolves a collection definition, and validates the model against it.
- `MediaService`: `upload(UploadedFile, collection, uploader)`, `sync(?owner, collection, uuids, cover, actor)` (a null owner syncs a standalone collection; `claim()` is the shorthand), `attach`, `reorder`, `delete`, `updateMetadata`, `open(media, conversion)` for streaming, and `logAccess`. The MIME type is sniffed with `finfo`, and the stored extension comes from it.
- `ImageProcessor` (via `ImageDriver`, Imagick or GD): during the upload request it re-encodes the original to strip EXIF and GPS, apply the orientation and convert HEIC to JPEG (D19). The queued `ProcessMediaJob` then writes the WebP conversions, never upscaling; a failure falls back to the original.
- `MediaMaintenanceService`: `cleanup()` for `media:cleanup` and `migrateDisk()` for `media:migrate-disk` (per-row copy, checksum check, refuses to cross visibilities).
- `MediaUrlGenerator`: the only place URLs are built, which keeps the CDN or disk switch in one spot.
- The `HasMedia` model concern provides `media()`, `mediaIn('car.gallery')` and `primaryMedia('car.gallery')`, and soft-deletes the files with their owner. A model's collections come from `MediaCollectionRegistry::forOwner()`, because models may not use services.
- Validation rules: `MaxMediaForCollection` and `PendingOrOwnedMedia` (in `App\Support\Rules`).

### 6.6 Frontend

- `<MediaUploader limits={...} value={items} onChange={...} sortable coverable cover={...} onCoverChange={...} />` handles drag-to-reorder, choosing a cover, per-file progress and retry (one Inertia `useHttp` per file), `X-Booking-Token` and client-side compression (browser-image-compression). `limits` is the collection's `MediaCollection::toArray()`. It is used by every form that has files.
- `<MediaImage media={m} conversion="card" />` renders responsive `srcset` from the resource's `urls`.
- `<SecureDocumentViewer media={m} />` (admin only) requests the file again on each open, so every view is authorized and logged. PDFs open in a new tab, because the CSP's `frame-src` doesn't allow framing them (revisit in M12.3).

---

## 7. Metadata (informal extras)

`metadata` is a nullable JSON column, cast with `AsArrayObject`, for loose information that needs no migration (D20). There is no definitions table, shared concern or field builder.

- **Columns first.** Anything the system filters, sorts, calculates on or shows as a first-class spec is a column. `cars.fuel_type` and `cars.colour` are columns for that reason.
- **Which tables have it.** Each milestone decides for its own tables. In M4 only `cars` has it (0–100 time, top speed, drive, luggage, features, ...). Since M5, `payment_methods` has it too (paybill, till, bank details, card link). `media` has its own system-owned keys (alt, caption, `exif_stripped`, `legal_hold`).
- **Shape.** An object of snake_case keys (`App\Support\Rules\MetadataKeys`: a letter first, at most 40 characters) to text values of up to 500 characters, at most 30 keys. Empty values are dropped on save.
- **Editing.** Admin forms use `<KeyValueField>` (`resources/js/components/admin/key-value-field.tsx`) with the helpers in `resources/js/lib/metadata.ts`.
- **Public site.** Public resources never include `metadata` wholesale. A page that wants a key (say, 0–100 on the car card) adds that one key to its resource explicitly.

---

## 8. Data model

All money columns are `bigint` in **minor units** (cents) with a `_minor` suffix. The currency is KES unless a column says otherwise.

| Table                                           | Key columns                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ----------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **users**                                       | id, name, email, password, two_factor_*, is_staff, is_active, last_login_at                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| **brands**                                      | id, name, slug, sort_order, show_in_marquee, is_active                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| **car_types**                                   | id, name, slug, description, sort_order, is_active (shown on the public site; its cars stay listed)                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| **cars**                                        | id, brand_id, car_type_id, name, slug, model_year, engine_type, engine_capacity_litres, fuel_type (enum), transmission (enum), colour, doors, seats, daily_rate_minor, car_value_minor, mileage_limit_km (nullable = use the default), damage_excess_minor + damage_excess_currency, is_featured, is_available, is_published, published_at, description, metadata, sort_order                                                                                                                                                                                  |
| **car_blackouts**                               | car_id, starts_at, ends_at, reason (maintenance, private use), notes, created_by                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| **services**                                    | id, title, slug, summary, body (Markdown), cta_type (book, whatsapp, link), cta_value (link URL or WhatsApp message), sort_order, is_active. Image: `service.image`. No `metadata` (D20). |
| **pages**                                       | id, slug (one row per preset page in `config/pages.php`), content (JSON, shaped by the page's fields), meta_title, meta_description, updated_by. Images: `page.image` (D22). |
| **policies**                                    | id, slug, title, group, summary, show_on_home, sort_order, deleted_at (soft deletes: bundles keep pointing at their versions) |
| **policy_versions**                             | policy_id, version (integer per policy), body (Markdown), published_at (null = draft, at most one per policy), created_by (null = seed pack). Published versions are immutable (D24). |
| **policy_bundles**                              | id, version (integer), published_at, created_by, plus the pivot `policy_bundle_version`. A snapshot of the published version of every live policy, taken on each publish; it is what a renter accepts (D24). |
| **currencies**                                  | code, name, symbol, decimals (display only; amounts are stored with 2, D27), is_active, sort_order. The base currency is `PricingSettings::base_currency` (KES), not a column. |
| **fx_rates**                                    | currency_code, market_rate (decimal 14,6 KES per unit), source (`api`, `manual`), provider, fetched_at (the provider's "as of" time), override_by, override_until. Append-only history; the margin is applied when read (D28). |
| **charges**                                     | code, name, description, basis (per_day, per_booking, per_km, info_only), amount_minor (null = on request), currency_code, tax_inclusive, payable_at (reservation, handover, counter), applies (`extra`: the renter picks it by code; `always`: every quote; `listed`: policy page only; D32), is_active, sort_order. `code` is fixed once created. Delivery is priced by zone (D29). |
| **delivery_zones**                              | name, description, fee_minor (null = quoted by staff), is_default, is_active, sort_order                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **deposit_bands**                               | min_age, max_age, percent_bp (basis points of car value), label, is_active                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| **cancellation_tiers**                          | hours_before_pickup, refund_percent_bp, label                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| **payment_methods**                             | code (`PaymentMethodCode`: mpesa, bank, card, cash; a fixed set, D25), name, instructions (Markdown), requires_transaction_message, requires_proof, is_active, sort_order, metadata (paybill, till, account, bank details, card link) |
| **bookings**                                    | id, reference, access_token_hash, car_id, status (enum), pickup_at, return_at, pickup_point, return_point, delivery_zone_id, delivery_address, delivery_landmark, delivery_lat, delivery_lng, delivery_contact_phone, delivery_fee_minor (null while staff-quoted, D33), return_address (same fields with `return_` prefix when collected), chauffeur_required, additional_driver, flight_number, billable_days, display_currency, quote (JSON snapshot), fx (JSON snapshot), settings_snapshot (JSON), cancellation_tiers (JSON snapshot, D34), total_minor, reservation_minor, balance_minor, security_deposit_minor, policy_bundle_id, accepted_terms_at, accepted_terms_ip, current_step, expires_at, confirmed_at, handover_at, returned_at, metadata, timestamps |
| **booking_drivers**                             | booking_id, is_main, surname, first_name, second_name, dob, email, phone, nationality, is_kenyan_resident, id_type (national_id, passport), id_number (encrypted), id_number_hash, kra_pin (encrypted), licence_number (encrypted), licence_expiry, licence_years_declared, metadata                                                                                                                                                                                                                                             |
| **booking_status_histories**                    | booking_id, from, to, actor_type, actor_id, reason, created_at                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| **availability_holds**                          | car_id, booking_id, starts_at, ends_at, expires_at                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| **payment_submissions**                         | booking_id, payment_method_code, amount_minor, currency_code, transaction_message, reference, status (pending, verified, rejected), verified_by, verified_at, rejection_reason, metadata                                                                                                                                                                                                                                                                                                                                         |
| **handover_records**                            | booking_id, type (handover, return), balance_received_minor, deposit_received_minor, deposit_refunded_minor, odometer_km, fuel_level, notes, recorded_by, recorded_at                                                                                                                                                                                                                                                                                                                                                            |
| **contact_messages**                            | name, email, phone, message, car_id, preferred_dates, status (new, in_progress, resolved, spam), handled_by, handled_at |
| **media**                                       | see §6.3                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **settings**                                    | spatie settings                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| **activity_log**, **permissions**, **roles**, … | package tables                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |

Booking references use the format `BE-{YYYY}-{000123}`. The format string lives in `config/baines.php` and the counter is per year.

---

## 9. Configurability: open questions become settings

We do not block on any of PRD §10. Each item ships with a setting or table and a working default that admins can change.

| PRD Q                   | What we build                                                                                                                             | Default                                                                                                             |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Q1 "UAD" currency       | `currencies` table with an active toggle                                                                                                  | KES, USD, GBP, EUR, AED active; AUD seeded but inactive                                                             |
| Q2 Minimum age, 3% band | `deposit_bands` table. The age limits are derived from the bands, and an age outside every band blocks the booking.                       | 23–23 → 3%; 24–70 → 2%                                                                                              |
| Q3 Day counting         | `PricingSettings::day_counting` strategy: `calendar_inclusive`, `rolling_24h` (with grace minutes) or `daytime_blocks` (window start/end) | `calendar_inclusive`, window 07:00–19:00                                                                            |
| Q4 Chauffeur basis      | `charges` row `chauffeur` with basis and currency                                                                                         | per_day, USD 50, tax inclusive                                                                                      |
| Q5 Mileage              | `PricingSettings::default_mileage_km`, `unlimited_mileage_after_days` (nullable), per-car override, `excess_km` charge                    | 300 km, null (off), excess shown as "on request" until set                                                          |
| Q6 KRA for foreigners   | `BookingSettings::document_requirements` matrix by id_type × residency                                                                    | Kenyan resident: ID front + back, KRA PIN + certificate, licence. Passport holder: passport, licence, KRA optional. |
| Q7 Card at pick-up      | `BookingSettings::deposit_payment_methods`                                                                                                | All active methods                                                                                                  |
| Q8 Marquee placement    | `HomeSettings::marquee_position` (`below_hero` or `page_bottom`)                                                                          | `page_bottom`                                                                                                       |
| Q9 Flight notice        | `BookingSettings::flight_notice_days`                                                                                                     | 2                                                                                                                   |
| Q10 Delivery fee        | `delivery_zones` table                                                                                                                    | "Nairobi" KES 2,000 (default); "Outside Nairobi" quoted by staff                                                    |
| Q11 FX source           | `FxSettings::provider` driver (`manual`, `open_exchange_rates`), refresh frequency, margin, staleness, jump guard (`max_jump_percent`)       | `manual` until an API key exists; margin KES 3; refresh hourly                                                      |
| Q12 Damage excess       | `cars.damage_excess_minor` and currency, shown on the detail page                                                                         | null = hidden                                                                                                       |
| Q13 Other fees          | `charges` rows with `info_only` basis and nullable amount                                                                                 | Shown as text only                                                                                                  |
| Q14 Cancellation tiers  | `cancellation_tiers` table, rendered into the policy page and the voucher                                                                 | Empty; the policy text is the only source until the client fills the tiers                                          |
| Q15 Safaris             | `services` rows with `cta_type=whatsapp`                                                                                                  | Seeded safari service                                                                                               |
| Q16 Domain / mail       | Env (`APP_URL`, `MAIL_FROM_*`)                                                                                                            | —                                                                                                                   |

Other settings groups:

- **PricingSettings:** `reservation_percent_bp` (2500), `reservation_rounding` (`ceil_major`), `hold_minutes` (120), `draft_expiry_hours` (24), `turnaround_minutes` (0), `base_currency` (KES).
- **BookingSettings:** `min_licence_years` (3), `licence_must_outlive_return` (true), `require_flight_number_for_airport` (false), `max_additional_drivers` (1).
- **GeneralSettings:** company name, legal name, phones, WhatsApp number, email, address, map embed URL, coordinates, socials, opening hours, WhatsApp message templates (with `{car}`, `{dates}` and `{reference}` placeholders).
- **NotificationSettings:** staff alert emails, staff WhatsApp number, channel toggles per event, voucher email subject and intro copy.
- **RetentionSettings:** `kyc_retention_months` (12), `draft_retention_days` (7), `activity_log_retention_months` (24).
- **SecuritySettings:** `require_two_factor` (false).
- **HomeSettings:** hero media, headline, subline, CTA labels, featured car count, section order and visibility, trust-strip figures.

**Snapshot rule:** at submit time the booking stores the quote, the FX rates _and_ the pricing and booking settings that were used (`settings_snapshot`). Changing a setting never changes an existing booking.

---

## 10. Pricing and quote engine

`Pricing\QuoteService::quote(Car, pickup, return, extras, zone, driver DOB): Quote` loads the rules in force (settings, active charges, active deposit bands, the FX table) and hands them to `Pricing\Quote\QuoteCalculator`, which has no database access and is deterministic. It is the only quote engine (D31): the admin sandbox, the public preview and booking submit all call it.

The input (`QuoteInput`):

- car (daily rate, value, mileage allowance)
- pickup and return times
- the extras the renter picked (charge codes) and a delivery zone
- driver date of birth (optional: without it there is no deposit yet)

The steps:

1. `DayCounter` (the strategy chosen by setting, counted in the business time zone, D30) calculates the billable days.
2. Rental = days × daily rate.
3. Each active charge that applies is listed: an `extra` the renter picked, or an `always` charge; `listed` charges never are (D32). A per-day or per-booking charge with an amount is multiplied out in its own currency and then converted once to KES at the **market** rate, half up to the cent (3 × USD 50 at 129 = KES 19,350). Per-km and info-only charges, and charges without an amount, are listed but have no amount.
4. Delivery comes from the chosen zone. A zone without a fee is listed as quoted by staff and sets `requires_staff_quote` (D29).
5. Total = the lines payable at reservation or handover. Counter charges and lines without an amount are listed but not summed.
6. Reservation = total × reservation % with the rounding mode. Balance = total − reservation.
7. Age on the pickup date (business time zone) selects the deposit band, and security deposit = band % × car value, rounded half up. If no band matches, the quote throws `QuoteException::driverAge` on the `driver_dob` field.
8. Mileage: the car's allowance, else the default, per day; unlimited from `unlimited_mileage_after_days`.
9. Display conversion: amount ÷ (market rate − margin), half up, for every active currency with a rate.

The output, `Quote` (`toArray()` is the booking snapshot):

- line items, each with its unit in the charge's currency, its KES amount and whether it is counted
- totals in minor units, deposit and band, driver age, mileage
- days
- the FX table used, with the margin
- the settings used and their sha256 hash

**Fixtures:** `tests/fixtures/quotes.json` holds the PRD worked example, age edges, every day-counting strategy's edges, every rounding mode, each currency, and staff-quoted, counter and info-only lines. It is generated by `tests/fixtures/generate_quotes.py`, an independent implementation of these rules; the PHP calculator must match every case (`tests/Unit/Pricing/QuoteFixturesTest.php`), and `lib/money.ts` must match every display conversion (Vitest). Gate G3 adds the client's 20 spreadsheet cases to the same file.

**FX (D28):** `FxRateService` fetches from the configured `FxRateProvider` (`fx:refresh`, scheduled every 15 minutes, waits `refresh_minutes` between fetches). Open Exchange Rates is called directly over HTTP: rates are USD-based, so KES per unit is the cross rate rate[KES] ÷ rate[X], worked out with bcmath. A response with a wrong base, a missing or non-positive rate or a future timestamp is rejected whole. A rate that moved more than `max_jump_percent` since the last API rate is held back until staff accept it. A fresh API rate always wins; a manual rate applies only while the API rate is missing or older than `stale_after_hours`; after that the last API rate is used, flagged stale. Missing, stale, failed and held-back rates are listed on the dashboard (mail alerts come with M10).

---

## 11. Booking engine

### 11.1 Flow (PRD §8.4, mapped to our layers)

| Step             | Route                                                                       | Controller → Service                                                                                                                                                                                                                |
| ---------------- | --------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Booking page     | `GET /fleet/{car:slug}/book`                                                | `Public\BookingController@create` → props: `CarDetailResource`, pricing config, FX table, delivery zones, charges, document matrix                                                                                                  |
| Start draft      | `POST /bookings`                                                            | `@store` (`StartBookingRequest`) → `BookingService::startDraft()`. Returns a plain token, stores only the hash, and redirects to `/bookings/{token}`.                                                                               |
| Save step        | `PUT /bookings/{token}/steps/{step}`                                        | `@updateStep` (one FormRequest per step) → `BookingService::saveStep()`                                                                                                                                                             |
| Upload documents | `POST /media` with `X-Booking-Token`                                        | Media module, `booking.*` collections                                                                                                                                                                                               |
| Live quote       | `POST /quote/preview` (rate limited), debounced from the form (D31).        | `QuoteService`                                                                                                                                                                                                                      |
| Submit           | `POST /bookings/{token}/submit`                                             | `SubmitBookingRequest` → `BookingService::submit()`: eligibility checks, required documents, `AvailabilityService::lockAndHold()` in a transaction with `lockForUpdate` on the car, quote recalculation, snapshot, Awaiting payment |
| Payment          | `POST /bookings/{token}/payments`                                           | `BookingPaymentController@store` → `PaymentSubmissionService::submit()`                                                                                                                                                             |
| Status page      | `GET /bookings/{token}`                                                     | Renter's signed view of status, quote and payment instructions                                                                                                                                                                      |

### 11.2 Status machine

`BookingStatus` enum with `transitions(): array` and `blocksAvailability(): bool`.

```
Draft ─► AwaitingPayment ─► PaymentSubmitted ─► Reserved ─► Active ─► Completed
  │            │   └─► AwaitingCash ──────────────┘   │  └─► Cancelled / NoShow
  └─► Expired  └─► Expired      PaymentRejected ◄─────┘ (from PaymentSubmitted)
                                 ├─► PaymentSubmitted (retry)
                                 └─► Cancelled
```

- All changes go through `BookingStatusService::transition($booking, $to, $actor, $reason)`. It validates against the map, writes `booking_status_histories` and the activity log, and dispatches `BookingStatusChanged`.
- Listeners send notifications, create or release holds, and generate vouchers.
- `bookings:expire` runs every 5 minutes. It expires drafts after `draft_expiry_hours` and awaiting-payment bookings after `hold_minutes`, and it releases their holds.

### 11.3 Availability

`AvailabilityService::isAvailable(car, range)` reports a conflict for any of:

- unexpired holds
- bookings whose status `blocksAvailability()`
- `car_blackouts`
- the turnaround buffer

Submission re-checks availability under a row lock. The admin calendar uses the same service.

### 11.4 Eligibility (`DriverEligibilityService`)

- Age at pickup falls within a deposit band.
- Licence expiry is after return when `licence_must_outlive_return` is on.
- `licence_years_declared` is at least `min_licence_years`.
- The document matrix is satisfied for the driver's id_type and residency.
- Flight notice days apply when a flight number is given (warning only).

---

## 12. Payments

- Payment method instructions and account details live in `payment_methods` (Markdown + metadata). They are only revealed after the renter chooses a method (PRD P-1).
- `PaymentSubmissionService::submit()` handles the transaction message and an optional proof UUID (`booking.payment_proof`). It moves the booking to Payment submitted, or to Awaiting cash for the cash method.
- `PaymentVerificationService::verify(submission, reference, amount, actor)` records a verified payment and moves the booking to Reserved. The Reserved listener firm-blocks the dates and queues the voucher. `reject(submission, reason)` moves the booking to Payment rejected and notifies the renter.
- If the amount is less than the reservation, verification needs the `payments.accept-partial` permission and a note.
- **Phase 2 hook:** `PaymentGateway` interface (M-Pesa Daraja, Pesapal) writing to the same `payment_submissions`, auto-verified.

---

## 13. Notifications and voucher

| Event             | Renter                                                    | Staff                                |
| ----------------- | --------------------------------------------------------- | ------------------------------------ |
| Booking submitted | `BookingReceived` (mail, with status link)                | `StaffNewBooking` (mail + WhatsApp)  |
| Payment submitted | —                                                         | `PaymentSubmitted` (mail + WhatsApp) |
| Reserved          | `BookingConfirmed` (mail, voucher PDF attached; WhatsApp) | —                                    |
| Payment rejected  | `PaymentRejected` (mail, with reason and retry link)      | —                                    |

- `VoucherService` renders the voucher with dompdf from a Blade view. Fields follow PRD P-6 and the values come from the **snapshot**. The PDF is stored as `booking.voucher` media and regenerated on resend.
- **WhatsApp channel drivers** (config `services.whatsapp.driver`):
    - `click_to_chat` (v1): builds a `wa.me` link with prefilled text, logs it, and shows staff a "Send on WhatsApp" button.
    - `cloud_api` (v1.1): approved templates.
- All notifications are queued, all are logged to the activity log, and every one can be toggled in `NotificationSettings`.

---

## 14. Public site

- `ShareCurrency` middleware (alias `currency`, public routes only) reads the `currency` cookie, which is validated against the active currencies that have a rate. It shares the selected currency and each option's effective rate as the `currency` prop. `POST /currency` (`Public\CurrencyController@update`) sets the cookie.
- Prices are always sent in KES minor units and converted on the client by `lib/money.ts` with the shared rates, using exact integer maths and the server's half-up rounding. The server-side resources also expose a `display` price for SSR, so there is no flash on first render.
- Brand pages (`/car-brands/{brand}`) and type pages (`/car-types/{type}`) reuse `FleetController@index` with a preset filter. `/fleet` takes query filters: brand, type, transmission, seats, sort.
- SEO:
    - per-page meta from the preset `pages` (D22); the OG image is added in M7.17
    - `sitemap.xml` from published cars, brands, types, services and policies
    - JSON-LD (`AutoRental`, `Car`, `LocalBusiness`)
    - canonical URLs
- `lib/whatsapp.ts` and `WhatsAppLinkBuilder` (PHP) share the template strings from settings.

---

## 15. Security and data protection

- KYC files live on private disk collections only. They are served through signed short-lived URLs, and every view is logged (`media.viewed`).
- `id_number`, `kra_pin` and `licence_number` use encrypted casts, with a hash column for lookup.
- Renter access uses a 64-character random token. Only its SHA-256 hash is stored. Links can be rotated by staff.
- Rate limiters are named limiters:
    - `booking-start`: 10 per hour per IP
    - `booking-step`
    - `media-upload`: 30 per hour per token
    - `contact`: 5 per hour
- Turnstile protects the booking start and contact forms.
- 2FA is optional by default. When `SecuritySettings::require_two_factor` is on (Settings → Security), `EnsureTwoFactor` sends staff without 2FA to their security page before `/admin`. Admin and account-settings sessions time out after inactivity.
- `/admin/*` and the account pages (`/settings/*`) are staff-only (`EnsureStaff`). `EnsureActiveAccount` signs out a deactivated account on any request. Staff can delete their own account (password required, logged), except the last active super admin. The local disk's built-in `serve` routes are off; the Media module serves files.
- Uploads are checked by MIME sniffing (`finfo`) against the collection whitelist, re-encoded, size-capped, and stored with randomized file names.
- `kyc:purge` runs daily. It deletes KYC media `kyc_retention_months` after completion unless `metadata.legal_hold` is set, and logs each purge.
- Security headers (CSP, HSTS, frame-ancestors) are set by middleware.

---

## 16. Testing strategy

| Layer                    | Tests                                                                                                                                                                                                                                                                              |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Arch (Pest `arch()`)** | Controllers do not use `DB` or `Storage`. Services do not use `request()`. Public resources never reference `car_value_minor`. Models do not depend on services. Every Form Request implements `authorize`.                                                                        |
| **Unit**                 | QuoteService against fixtures, day counters, eligibility, status transitions map, MediaCollectionRegistry, permission pattern expansion, Money rounding                                                                                                                            |
| **Feature**              | For each admin controller: guest redirect, 403 without permission, validation, happy path. Media: upload to every collection type, private access denied or allowed, disk switch via `Storage::fake`. Booking: full flow, double-booking race (two submits, one lock), expiry job. |
| **Parity**               | Vitest runs the same `quotes.json` fixture                                                                                                                                                                                                                                         |
| **E2E (Playwright)**     | Renter books with documents → staff verifies → voucher mail captured (Mailpit)                                                                                                                                                                                                     |
| **Coverage target**      | 90% on `app/Services/Pricing`, `Booking` and `Media`; 70% overall                                                                                                                                                                                                                  |

---

## 17. Environments and delivery

| Environment         | Notes                                                                                                                           |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Local | `composer dev` on the host against the host MySQL. Database drivers for cache, queue and session; mail goes to the log; `MEDIA_*_DISK=*-local`. No Redis, Horizon or Docker locally (D12). |
| Staging             | Same images as production, S3 disks, seeded via `CmsSeeder`                                                                     |
| Production          | Docker images: php-fpm + nginx, SSR node, Horizon, scheduler. Single VPS, or the existing k3s/Argo CD setup.                    |
| CI (GitHub Actions) | Pint, Larastan, ESLint, tsc, Pest, Vitest, build, Playwright (on main and release), image push, deploy staging → manual promote |

Scheduler:

| Command             | Frequency       |
| ------------------- | --------------- |
| `fx:refresh`        | hourly          |
| `bookings:expire`   | every 5 minutes |
| `media:cleanup`     | hourly          |
| `kyc:purge`         | daily           |
| `activitylog:clean` | weekly          |
| backups             | daily           |

---

## 18. Engineering decisions log

| #   | Decision                                                                                           | Reason                                                                                                                        |
| --- | -------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| D1  | Classic layers (controllers, form requests, services, resources, policies) instead of Actions/DTOs | Team convention; easier onboarding                                                                                            |
| D2  | Our own Media module instead of spatie/medialibrary                                                | We need one upload endpoint, UUID-referenced forms, token-authorized renter uploads, a per-row disk and a collection registry |
| D3  | Enum-driven status map plus `BookingStatusService` instead of model-states                         | Fits D1; the transitions stay in one readable enum                                                                            |
| D4  | Permission registry plus a sync command (superseded by D14: enums instead of config)                                                  | Permissions are generated per module, reviewable in PRs, and testable                                                         |
| D5  | Open business questions become settings or tables                                                  | Unblocks delivery; the client tunes values after launch                                                                       |
| D6  | Money in KES minor units, FX as decimal(14,6)                                                      | Exact arithmetic; matches PRD §7                                                                                              |
| D7  | dompdf for the voucher                                                                             | No Chrome in the containers                                                                                                   |
| D8  | Snapshot the quote, FX and settings per booking                                                    | PRD Q-4; disputes are settled from stored data                                                                                |
| D9  | ~~`metadata` JSON governed by `metadata_fields`~~ Superseded by D20.                              | Extensible specs without migrations, and still validated                                                                      |
| D10 | 2026-10-17: Stay on Laravel 13, Inertia 3, Pest 4 and intervention/image v4 (plan said 12, 2, 3 and v3) | The starter kit shipped these versions and every package installs cleanly on them. Downgrading costs churn and gains nothing. |
| D11 | 2026-10-17: vite-plus `vp check` (oxlint + oxfmt) and `vp test` replace ESLint, Prettier and a standalone Vitest | Ships with the starter kit, is one toolchain and is faster. `docs/**` is excluded from the formatter. |
| D12 | 2026-10-17: Local development runs on the host (`composer dev`, host MySQL); Sail is removed. Dockerfiles and the staging pipeline move to M12.15. | Hosting isn't decided yet, and Sail's image build failed on this machine. M12.15 must be pulled forward before M5.13 because Gate G1 runs on staging. Redis-only behaviour (Horizon) is exercised on staging. |
| D13 | 2026-10-17: Starter kit auth features: keep 2FA, password confirmation and email verification; drop registration and passkeys | Staff-only admin. Staff accounts are invited by a super admin. |
| D14 | 2026-10-17: Permissions are a PHP enum (`App\Enums\Permission`) instead of `config/permissions.php` (roles: see D15) | Type-safe checks (`can(Permission::UsersView)`), module helpers (`forModule`, `except`, `grouped`), no string patterns to expand, and a generated TS union for the frontend. |
| D15 | 2026-10-17: Roles are data managed in the admin (create, edit, delete, choose permissions), not an enum. Only super admin is a locked system role. | The client defines their own roles. Permissions stay in code because code checks them. |
| D16 | 2026-10-17: Requiring staff 2FA is a setting, off by default; account settings pages are staff-only; self-deletion goes through `UserService` | Client choice. Closes ungated `/settings/*` endpoints, and stops self-deletion bypassing the last-super-admin rule. |
| D16 | 2026-10-17: Media collections are registered in `config/media.php` by the milestone that builds their owner. M2 ships only `site.hero` (standalone). | Owner models don't exist before M4/M5/M8. This mirrors how permission cases are added per module. |
| D17 | 2026-10-17: `Relation::enforceMorphMap` is on. Polymorphic columns store aliases (`user`, `role`, later `car`, `booking`). | Media collections and the uploader name owners by alias, never by class name. Each module adds its aliases. |
| D18 | 2026-10-17: `media.status` (pending, attached) and `media.processing` (queued, done, failed, skipped) are separate columns. | §6.3 merged them, but a pending file can also be processing. |
| D19 | 2026-10-17: The original image is sanitized during the upload request (EXIF stripped, orientation fixed, HEIC→JPEG); only the WebP conversions are queued. The image driver is `MEDIA_IMAGE_DRIVER` (Imagick when loaded, otherwise GD), and HEIC is accepted only when the driver decodes it. | A file with GPS EXIF never reaches the disk. Local development has GD only. CI and production have Imagick. |
| D20 | 2026-10-18: `metadata` is a plain, free-form JSON column for informal extras, added only to tables that need it (M4: `cars`). There is no `metadata_fields` table, `MetadataService` or field builder, and public resources do not expose it by default. M3 is dropped. | Client decision: metadata is misc info, not an admin-defined schema. Real specs stay columns, so they can be validated, filtered and shown. |
| D21 | 2026-10-18: Catalog details. Cars get `fuel_type` and `colour` columns, and engine capacity is `engine_capacity_litres` (decimal, one place: 2.5) as staff quote it, not cc. `brand.logo` takes PNG and WebP only (SVG would need sanitising). Cars soft-delete; brands and car types hard-delete but are refused while any car, deleted or not, uses them. Publishing needs a cover photo and a daily rate. `damage_excess_currency` is checked against `baines.currencies` until M6. Admin catalog routes bind by slug, and slugs stay unique across soft-deleted rows. | Bookings will reference cars, so cars cannot vanish. A published car without a photo would break the fleet grid. |
| D22 | 2026-10-20: Pages are preset. The pages and their fields are defined in code (`config/pages.php`, read by `App\Support\Content\PageRegistry`); staff edit the content but cannot add, remove or lay out pages. Field types are `text`, `markdown`, `image` and `list` (fixed number of items). `page_sections`, `is_system` and `og_media_id` are dropped; the home page keeps its hero and layout on `HomeSettings` and its section copy on the `home` page. Permissions are `pages.view` and `pages.update` only. | Client decision: they edit words and images, the website owns layout and styling (M7). A section engine would be heavy for seven fixed pages. |
| D23 | 2026-10-20: Content is Markdown everywhere: policy, page and service bodies and payment instructions. `App\Support\Markdown::toHtml()` renders it with raw HTML stripped and unsafe links dropped, and public resources expose `*_html` only. The admin edits Markdown in a textarea with a server-rendered preview; `RichTextEditor` stays unused. | The seed pack is Markdown, diffs stay readable, and no HTML purifier is needed. |
| D24 | 2026-10-20: Policy versions and bundles. Versions are integers per policy; a policy has at most one draft; published versions are immutable (enforced on the model). A body containing `[CONFIRM` cannot be published. Each publish, and each deletion of a live policy, snapshots a new `policy_bundles` row linked to the current published version of every live policy. Policies soft-delete. The seeder writes versions with `created_by` null and never replaces a version or draft staff wrote. | Bookings (M8.14) must be able to show exactly what a renter accepted. |
| D25 | 2026-10-20: Payment methods are a fixed set keyed by the `PaymentMethodCode` enum (mpesa, bank, card, cash), created by the seed pack. Staff edit, reorder and switch them off, but cannot create or delete them. Permissions are `payment-methods.view` and `payment-methods.update`. | The booking flow (M9) branches on the code: cash goes to Awaiting cash, the others to Payment submitted. |
| D26 | 2026-10-20: TODO convention. In settings, null or empty means "still to supply"; the seeder never writes `TODO` strings into settings. In content (payment instructions and details), a literal `TODO` word marks a gap. `SetupChecklistService` lists both, plus unpublished policies and preset pages with empty required fields, on the dashboard's "Before launch" card, filtered by the viewer's permissions. Contact messages use `new`, `in_progress`, `resolved` and `spam`, and staff may move between any of them. | One place shows what is missing before launch (M13.4), and only to staff who can fix it. |
| D27 | 2026-10-20: Money is stored in minor units with 2 decimals for every currency, KES included; `currencies.decimals` is for display only (KES shows none). | Cars already stored cents, and one rule for every column avoids per-currency scaling bugs. |
| D28 | 2026-10-20: FX precedence. `fx_rates` is append-only. A fresh API rate always wins; a manual rate (no expiry, or one in the future) applies only while the API rate is missing or older than `stale_after_hours`; then the last API rate, flagged stale; otherwise the currency is hidden. Rows store the market rate only; the margin (`FxSettings::margin_minor`) is applied when read and recorded in each quote's FX snapshot. One provider, Open Exchange Rates, written against its HTTP API with bcmath cross rates and a jump guard (`max_jump_percent`); `exchangerate_api` is dropped. The API key is env-only. | The client wants the API as the source of truth; manual rates cover outages and the time before a key exists. One place holds the margin. |
| D29 | 2026-10-20: Delivery is priced by `delivery_zones` only; there is no delivery charge. A zone without a fee is listed as quoted by staff, left out of the total, and sets `requires_staff_quote`. | Two ways to price delivery would disagree. |
| D30 | 2026-10-20: Billable days and driver age use the business time zone, `config('baines.timezone')` = Africa/Nairobi. The app and database stay in UTC. | Calendar days and birthdays are local. |
| D31 | 2026-10-20: One quote engine, on the server. No TypeScript port (M6.12 dropped): the booking form calls `POST /quote/preview`. `lib/money.ts` only converts and formats display prices. | Every pricing rule written once; G3 checks one implementation. |
| D32 | 2026-10-21: Charges are dynamic. Each charge has `applies` (`extra`, `always`, `listed`) instead of a fixed trigger list; renters pick extras by the charge's `code`, which is fixed once created. Code that must recognise a charge (chauffeur, additional driver in M8) uses its code. | Staff can add a bookable extra, such as a child seat, without a code change. |
| D33 | 2026-10-21: Home delivery. A delivery zone is a pricing area, not an address: the renter picks the zone (its description lists the areas it covers) and types the address, with an optional landmark, map pin and driver phone. Nothing checks the address against the zone; staff confirm it on the booking (M11.13). A zone without a fee does not block the booking: the reservation excludes delivery, and staff set the fee later, which is added to the balance at handover. Map-drawn zones wait for P2.9. | No maps API or new integration before launch, and it matches how the office already confirms deliveries. Nairobi addresses are vague, so the landmark and pin matter more than the street. |
| D34 | 2026-10-21: Cancellation refunds. A booking snapshots the cancellation tiers at submit. On cancellation the tier is the one with the longest notice the renter still met, counted in hours before pickup; the refund is that share of the reservation paid. Less notice than every tier, or a no-show, suggests 0%. The amount is a suggestion: staff may change it with a reason, and both amounts are stored. Refunds are paid back by hand and recorded with a reference. *To confirm with the client: the 0% default and the reservation-only base.* | The renter is held to the terms they accepted (B-8), and staff keep discretion, since payments are manual. |

New decisions are appended here with a date.

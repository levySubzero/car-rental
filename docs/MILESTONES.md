# Baines Executives — Engineering Milestones

Living tracker for the build. The design behind each step is in [TECHNICAL_PLAN.md](TECHNICAL_PLAN.md), and the requirement IDs (G-, H-, F-, B-, Q-, P-, O-, A-) come from the PRD.

## How to update this file

1. **After every step:**
    - Tick the step box.
    - Set the milestone's status.
    - Update the **Progress** column in the summary table.
    - Add a row to the [Progress log](#progress-log).
2. **Status values:** `Not started` · `In progress` · `Blocked` · `Done` · `Dropped`. If a milestone is Blocked, write the reason in the log.
3. **Step state:** `[ ]` is open, `[x]` is done, and `[~]` is partially done or deferred. Explain any `[~]` in the log.
4. **Every module step includes the standard checklist below.** Do not tick a step until all of it is true.
5. **Scope changes:** new steps get the next free ID inside the milestone, for example `M3.12`. Never renumber existing steps.

**Standard definition of done for a module step:**

- Migration and model, with a `metadata` column only where the table needs informal extras (D20)
- Factory
- Permission cases added to `App\Enums\Permission`, then `permissions:sync` and `permissions:types` run
- Policy
- Form Requests
- Service
- Resources (admin and public)
- Controller and routes
- Inertia page(s)
- Pest feature tests covering guest, 403, validation and happy path
- Pint, Larastan, `vp check` and tsc all green

---

## Summary

| #   | Milestone                                                      | Status      | Progress | Gate                 |
| --- | -------------------------------------------------------------- | ----------- | -------- | -------------------- |
| M0  | Project bootstrap and conventions                              | Done        | 9/10     | —                    |
| M1  | Platform foundations (auth, IAM, settings, audit, admin shell) | Done        | 14/14    | —                    |
| M2  | Media module (shared)                                          | Done        | 16/16    | —                    |
| M3  | Metadata fields                                                | Dropped     | —        | —                    |
| M4  | Catalog (brands, car types, cars)                              | Done        | 12/12    | **G1** (with M5)     |
| M5  | Content CMS and seed pack                                      | Blocked     | 12/14    | **G1**               |
| M6  | Pricing, currencies and FX                                     | Not started | 0/16     | —                    |
| M7  | Public site                                                    | Not started | 0/20     | **G2**               |
| M8  | Booking engine                                                 | Not started | 0/20     | —                    |
| M9  | Payments                                                       | Not started | 0/10     | **G3** (with M6, M8) |
| M10 | Notifications and voucher                                      | Not started | 0/11     | —                    |
| M11 | Operations desk                                                | Not started | 0/12     | **G4**               |
| M12 | Hardening (security, performance, SEO, a11y)                   | Not started | 0/15     | —                    |
| M13 | UAT and launch                                                 | Not started | 0/10     | **G5**               |
| P2  | Post-launch backlog                                            | Not started | 0/8      | —                    |

---

## M0 — Project bootstrap and conventions

Status: **Done**

- [x] M0.1 Create the Laravel 12 app from the React starter kit (Inertia 2, React 19, TS, Tailwind 4, shadcn, Wayfinder) and initialize the git repo.
- [x] M0.2 Set up Sail services: MySQL 8, Redis, Mailpit and MinIO, plus `.env.example` with every `MEDIA_*`, `FX_*` and `WHATSAPP_*` key.
- [x] M0.3 Install core packages: spatie/laravel-permission, spatie/laravel-settings, spatie/laravel-activitylog, intervention/image, barryvdh/laravel-dompdf, laravel/horizon, sentry/sentry-laravel.
- [x] M0.4 Set up quality tooling: Pint config, Larastan (level 6), ESLint and Prettier, `tsc --noEmit`, and composer and npm scripts (`lint`, `test`, `types`).
- [x] M0.5 Set up testing: Pest 3 with the arch plugin, Vitest, a Playwright scaffold, and a `tests/fixtures/` folder.
- [x] M0.6 Add the Pest arch tests that enforce the layering rules (TECHNICAL_PLAN §2, §16).
- [x] M0.7 Create the directory skeleton (`Services/*`, `Http/{Controllers,Requests,Resources}/{Admin,Public,Media}`, `Settings`, `Support`, `Enums`) and add `config/baines.php`.
- [x] M0.8 Set up the CI workflow in GitHub Actions: lint, static analysis, types, Pest, Vitest and build.
- [~] M0.9 Add Dockerfiles (php-fpm + nginx, SSR node, Horizon, scheduler) and the staging deploy pipeline.
- [x] M0.10 Write the README covering local setup, the conventions and how to add a module, with a link to this file.

## M1 — Platform foundations

Status: **Done** · PRD: §3 roles, A-7, §7 security

- [x] M1.1 Add the `PermissionRegistry`, with `*` and `!` pattern expansion, plus unit tests.
- [x] M1.2 Add the `permissions:sync` command (idempotent, `--prune`) and wire it into `DatabaseSeeder` and the deploy step.
- [x] M1.3 Seed the roles super-admin, operations and content-editor from config, and add the `Gate::before` bypass for super-admin.
- [x] M1.4 Add the route guard test that checks every `admin.*` route returns 403 for a user with no permissions.
- [x] M1.5 Staff auth: Fortify login, password reset and TOTP 2FA. Add the `EnsureTwoFactor` and `EnsureStaff` middleware and disable public registration.
- [x] M1.6 Users admin (CRUD, assign roles, extra permissions, deactivate), with permissions `users.*`.
- [x] M1.7 Roles admin: create, edit, delete roles and choose their permissions (`roles.view|create|update|delete`). The super admin role is locked. *(Changed from read-only, D15.)*
- [x] M1.8 Settings infrastructure: base settings classes and migrations for General, Pricing, Booking, Fx, Notification, Home and Retention, with defaults from TECHNICAL_PLAN §9.
- [x] M1.9 Settings admin UI: one tabbed page with a FormRequest per group and permissions `settings.{group}.update`.
- [x] M1.10 Activity log: the `LogsActivity` setup convention and an admin viewer with filters (`activity.view`).
- [x] M1.11 Admin shell: layout, sidebar driven by permissions, flash toasts, confirm dialogs, and shared Inertia props (auth user, abilities, settings subset).
- [x] M1.12 Shared `Support` code: `Money` (minor units, rounding modes), `DateRange` and `HasSlug` / `Sortable` concerns, with unit tests.
- [x] M1.13 Shared admin React components: DataTable (server pagination, filters, sort), FormField set, slug input, rich text editor, sortable list.
- [x] M1.14 Security baseline: security headers middleware, named rate limiters, the Turnstile rule and session timeout.

## M2 — Media module (shared)

Status: **Done** · PRD: A-1, B-4–B-6, P-2, §7 documents and uploads

- [x] M2.1 Write `config/media.php`: the public and private disks from env, conversions, TTLs and the collection registry skeleton.
- [x] M2.2 Define the filesystem disks `media-{public,private}-{local,s3}` and verify the switch works with an env change only.
- [x] M2.3 Add the `media` migration and model, the `MediaVisibility` and `MediaStatus` enums, and the `HasMedia` concern.
- [x] M2.4 Add `MediaCollectionRegistry` (config plus admin-setting overrides, with hard ceilings), with unit tests.
- [x] M2.5 `MediaService::upload`: MIME sniffing, whitelist check, randomized path, checksum, pending status and expiry.
- [x] M2.6 `ImageProcessor` and `ProcessMediaJob`: EXIF strip and re-encode, orientation fix, HEIC→JPEG, WebP conversions.
- [x] M2.7 `MediaService` sync, attach, detach, reorder, delete and cover handling, plus the `MaxMediaForCollection` and `PendingOrOwnedMedia` rules.
- [x] M2.8 Add `MediaUrlGenerator`: public redirect or stream, private temporary URLs (S3) and signed stream route (local).
- [x] M2.9 Add `MediaPolicy`, which delegates to the owner policy and supports booking-token uploaders. Permissions `media.upload` and `media.view-private`.
- [x] M2.10 Add `MediaController` with the single endpoint set: `POST /media`, `GET /media/{uuid}/{conversion?}`, `GET /media/{uuid}/download`, `PATCH /media/{uuid}`, `DELETE /media/{uuid}` and `POST /media/reorder`.
- [x] M2.11 Add `MediaResource` (uuid, urls per conversion, name, size, mime, dimensions, alt) and the TS type.
- [x] M2.12 Log every private-media view and download to the activity log.
- [x] M2.13 Add the `media:cleanup` command (expired pending files, soft-deleted purge) and schedule it.
- [x] M2.14 Add the `media:migrate-disk {from} {to} --collection=` command, with a dry-run flag.
- [x] M2.15 React components: `<MediaUploader>` (progress, retry, drag-reorder, cover, client compression, `X-Booking-Token` support), `<MediaImage>` and `<SecureDocumentViewer>`.
- [x] M2.16 Tests: upload per collection type, rejected MIME and size, private access denied or allowed, token uploads, disk switch with `Storage::fake`, cleanup.

## M3 — Metadata fields

Status: **Dropped** (D20). `metadata` is a plain JSON column for informal extras, so there is no field-definition engine to build. What remained folded into M4: the `cars.metadata` column, its key/value validation and the admin editor.

- [~] M3.1 Add the `metadata_fields` migration and model, and the `MetadataFieldType` enum. *Not needed (D20).*
- [~] M3.2 Add the `HasMetadata` concern (`AsArrayObject` cast, unknown keys stripped on save). *Not needed: each model casts its own column.*
- [~] M3.3 Add `MetadataService`: `rulesFor(entity)`, `publicView(model)` and `cardView(model)`. *Not needed: `MetadataKeys` rule in M4.7.*
- [~] M3.4 Metadata fields admin (CRUD per entity, reorder), with permissions `metadata-fields.*`. *Not needed.*
- [~] M3.5 Add the `<MetadataFields entity=...>` dynamic form renderer and a display component. *Replaced by `<KeyValueField>` in M4.9.*
- [~] M3.6 Seed the default car metadata fields. *`fuel_type` and `colour` became columns (M4.4); the rest are informal keys.*
- [~] M3.7 Tests: validation from definitions, public versus hidden exposure, stripping of unknown keys. *Covered by M4.12.*

## M4 — Catalog

Status: **Done** · PRD: A-1, A-2, F-1–F-5

- [x] M4.1 Brands: migration and model, `brand.logo` media, permissions `brands.*`, service, requests, resources, controller and pages. *(No `metadata` column, D20.)*
- [x] M4.2 Brand marquee ordering and the `show_in_marquee` toggle in the admin list (drag sort).
- [x] M4.3 Car types: full module (`car-types.*`), with drag ordering.
- [x] M4.4 Cars: migration and model, covering all spec columns plus `fuel_type` and `colour`, money in minor units, `mileage_limit_km` nullable, damage excess, publish flags and an informal `metadata` column.
- [x] M4.5 Car permissions `cars.view|create|update|delete|view-value|publish`, `availability.manage`, and `CarPolicy`.
- [x] M4.6 `CarService`: create and update with gallery sync, publish and unpublish, toggle availability and toggle featured.
- [x] M4.7 Car form requests, including gallery UUIDs (max from the collection), cover, `metadata` key/value validation and major→minor money conversion.
- [x] M4.8 Car resources: `Admin\CarResource` (value only with `cars.view-value`), `Public\CarCardResource` and `Public\CarDetailResource`.
- [x] M4.9 Car admin pages: list (filters, availability and featured toggles) and an edit form with tabs for specs, pricing, photos, extra info and blackouts.
- [x] M4.10 Car blackouts (maintenance periods): nested under the car (`availability.manage`).
- [x] M4.11 Factories plus a demo seeder with sample cars for local and staging.
- [x] M4.12 Tests: car value never present in public resources, the 6-photo cap, permission matrix per role.

## M5 — Content CMS and seed pack

Status: **Blocked** (M5.13 waits for M12.15) · PRD: A-2, H-5, O-1, §11 · **Gate G1:** the client adds a car with 6 photos and edits a policy on staging.

- [x] M5.1 Services module (`services.*`): cta_type (book, whatsapp, link), image media, and a `metadata` column only if needed (D20). *(No `metadata`.)*
- [x] M5.2 Preset pages (`pages.view|update`): pages and their fields defined in `config/pages.php`, staff edit the content only. *(Changed from pages + typed section engine, D22.)*
- [x] M5.3 Home page sections bound to `HomeSettings` (hero media, CTAs, featured count, `marquee_position`, trust figures).
- [x] M5.4 Policies and versions (`policies.*`, `policies.publish`): draft, publish, diff against the previous version, and the `show_on_home` summary.
- [x] M5.5 Policy bundles: publishing creates a bundle snapshot of every currently published version, which is what renters accept.
- [x] M5.6 Payment methods admin (`payment-methods.view|update`, a fixed set, D25): Markdown instructions, metadata (paybill, till, bank, card link), flags.
- [x] M5.7 Contacts, socials, location and WhatsApp templates edited through `GeneralSettings`.
- [x] M5.8 Contact messages inbox (`contact-messages.*`) with a status workflow.
- [x] M5.9 Seed pack JSON under `database/seeders/data/`: site_settings, policies, catalog, pricing.
- [x] M5.10 `CmsSeeder`: upserts by slug or code, creates a new policy version only when the body changes, and leaves `[CONFIRM]` versions unpublished.
- [x] M5.11 Mark `TODO` values (paybill, bank, card link, hours, legal name) and add an admin dashboard widget that lists the unfilled TODO settings.
- [x] M5.12 Tests: seeder idempotency (run twice, same counts), policy versioning, bundle creation.
- [ ] M5.13 Deploy to staging and load the seed pack.
- [ ] M5.14 **Gate G1** walkthrough with the client: add a car with 6 photos, edit a policy. Record sign-off in the log.

## M6 — Pricing, currencies and FX

Status: **Not started** · PRD: §5, Q-1–Q-4, A-3, A-4, G-1, G-6

- [ ] M6.1 Currencies table and admin (`currencies.*`). Seed KES (base), USD, GBP, EUR, AED (active) and AUD (inactive).
- [ ] M6.2 FX rates: migration, history, and overrides with `override_until` (`fx-rates.view|override`).
- [ ] M6.3 `FxRateProvider` interface with `manual`, `open_exchange_rates` and `exchangerate_api` drivers, selected by `FxSettings`.
- [ ] M6.4 `FxRateService` and the `fx:refresh` command (hourly), with a staleness alert to staff.
- [ ] M6.5 Charges table and admin (`charges.*`). Seed chauffeur (USD 50, per day), additional driver (USD 50, counter, info), one-way, valeting, admin, full protection and excess km (info_only).
- [ ] M6.6 Delivery zones admin (`delivery-zones.*`). Seed Nairobi at KES 2,000 and "Outside Nairobi" as quoted by staff.
- [ ] M6.7 Deposit bands admin (`deposit-bands.*`). Seed 23–23 at 3% and 24–70 at 2%, and add an overlap validation rule.
- [ ] M6.8 Cancellation tiers admin (`cancellation-tiers.*`), empty by default.
- [ ] M6.9 Day counter strategies `calendar_inclusive`, `rolling_24h` and `daytime_blocks`, with unit tests.
- [ ] M6.10 `QuoteService` and the `Quote` value object: line items, totals, reservation rounding, balance, deposit band, display conversions.
- [ ] M6.11 `tests/fixtures/quotes.json` with at least 40 cases, including the PRD worked example (66,350 / 16,588 / 49,762 / 160,000).
- [ ] M6.12 TS port in `resources/js/lib/quote/` plus Vitest parity against the same fixtures.
- [ ] M6.13 `ShareCurrency` middleware, `CurrencyController@update` (cookie) and `lib/money.ts` formatting.
- [ ] M6.14 Pricing settings admin tab: reservation %, rounding, day counting, window, hold and expiry, turnaround, mileage defaults.
- [ ] M6.15 Admin "Quote sandbox" page: pick a car, dates and extras to see the server quote. Used for client validation (G3).
- [ ] M6.16 Tests: FX margin maths, overrides, stale handling, quote edge cases, settings changes not affecting snapshots.

## M7 — Public site

Status: **Not started** · PRD: G-1–G-6, H-1–H-5, F-1–F-5, O-1, §6 design · **Gate G2:** design and content sign-off on staging, on mobile and desktop.

- [ ] M7.1 Design tokens in Tailwind 4 (palette #0B0B0C, #16171A, #C8A45A; Playfair or Cormorant + Inter), with a motion utility that respects `prefers-reduced-motion`.
- [ ] M7.2 Public layout: top bar (tap-to-call, socials, currency switcher), header nav, and a mobile menu.
- [ ] M7.3 Floating WhatsApp and Call buttons, plus `WhatsAppLinkBuilder` and `lib/whatsapp.ts` (prefilled text naming the car).
- [ ] M7.4 Footer: about, brands, types, useful links, contacts, embedded map, socials, payment icons, policy links.
- [ ] M7.5 Home: hero (image or video, overlay, slow zoom) with Rent a Car and Enquire CTAs.
- [ ] M7.6 Home sections in configurable order: About, Featured Fleet, Car Types rows, Services, Policy highlights, Requirements 01–03, Why Choose Us 01–04, Trust strip, Contact CTA.
- [ ] M7.7 Brand marquee (Embla auto-scroll, pause on hover, lazy logos, each logo links to the filtered fleet), positioned by `HomeSettings::marquee_position`.
- [ ] M7.8 Car card component: cover, brand badge, name, price per day in the selected currency, specs (any `cars.metadata` key shown here is added to `CarCardResource` explicitly, D20), mileage, and View, Book and WhatsApp buttons.
- [ ] M7.9 Fleet page: filters for brand, type, transmission and seats, a price sort, query-string state and pagination.
- [ ] M7.10 Brand and car type listing pages, plus filtered views reusing the fleet page.
- [ ] M7.11 Car detail: gallery with lightbox and swipe (6 photos, keyboard accessible), info block (F-4), damage excess, and a Book this unit CTA.
- [ ] M7.12 Services page, including safaris with WhatsApp enquiry.
- [ ] M7.13 About and Privacy pages, rendered from `pages`.
- [ ] M7.14 Rental Policy page: grouped sections, an in-page index, cancellation tiers and the fees table.
- [ ] M7.15 Contact page: form (Turnstile, rate limited), map, call and WhatsApp CTAs. Stores to `contact_messages` and notifies staff.
- [ ] M7.16 Inertia SSR enabled for public routes and verified without hydration warnings.
- [ ] M7.17 SEO: per-page meta and OG, canonical URLs, `sitemap.xml`, JSON-LD (`AutoRental`, `Car`, `LocalBusiness`), clean slugs.
- [ ] M7.18 Responsive image `srcset` from media conversions, plus lazy loading.
- [ ] M7.19 Feature tests for every public route, plus an SSR smoke test.
- [ ] M7.20 **Gate G2** review on staging, mobile and desktop. Record sign-off in the log.

## M8 — Booking engine

Status: **Not started** · PRD: B-1–B-8, Q-1–Q-4, §5 lifecycle, §8.4

- [ ] M8.1 Migrations and models: bookings, booking_drivers (encrypted casts and hash columns), booking_status_histories, availability_holds.
- [ ] M8.2 `BookingStatus` enum with the transitions map and `blocksAvailability()`, with unit tests for every allowed and denied edge.
- [ ] M8.3 `BookingStatusService::transition()`: validation, history row, activity log, `BookingStatusChanged` event.
- [ ] M8.4 `BookingReferenceGenerator` (`BE-YYYY-000123`, safe under concurrency).
- [ ] M8.5 Booking token handling: 64-character token, hash storage, `ResolveBookingToken` middleware, rotation by staff.
- [ ] M8.6 `AvailabilityService`: holds, blocking statuses, blackouts, turnaround buffer, and `lockAndHold()` with `lockForUpdate`.
- [ ] M8.7 `DriverEligibilityService`: age bands, licence expiry, licence years, document matrix, flight notice warning.
- [ ] M8.8 Booking settings tab: document requirements matrix, licence rules, flight notice days, additional drivers.
- [ ] M8.9 `BookingService::startDraft()` with `StartBookingRequest` (Turnstile, rate limit).
- [ ] M8.10 Step 1, trip and extras: `SaveTripStepRequest` (dates, points, delivery zone and address, chauffeur, flight).
- [ ] M8.11 Step 2, driver and documents: `SaveDriverStepRequest`, with document requirements resolved per id_type and residency.
- [ ] M8.12 KYC uploads through `<MediaUploader>` with the booking token into the `booking.*` private collections.
- [ ] M8.13 Step 3, review quote: live TS calculator, a toggle to view other currencies, and an optional server preview endpoint.
- [ ] M8.14 Terms acceptance: policy bundle id, timestamp and IP stored (B-8).
- [ ] M8.15 `BookingService::submit()`: re-validate, recalculate, lock and hold, snapshot quote, FX and settings, then Awaiting payment.
- [ ] M8.16 Renter booking status page `/bookings/{token}`: status, quote and next action.
- [ ] M8.17 `bookings:expire` command (drafts and awaiting payment, releasing holds), scheduled every 5 minutes.
- [ ] M8.18 Booking form UI: multi-step wizard, step state restored from the server after a refresh, mobile-first.
- [ ] M8.19 Tests: full flow, age block, missing documents, expired licence, concurrent submit race (one wins), expiry job, snapshot immutability.
- [ ] M8.20 Playwright E2E: renter completes steps 1–3 and submits with documents.

## M9 — Payments

Status: **Not started** · PRD: P-1–P-4 · **Gate G3:** 20 scripted quote cases match the client's spreadsheet to the shilling.

- [ ] M9.1 payment_submissions migration and model, and the `PaymentStatus` enum.
- [ ] M9.2 Step 4, choose method: instructions revealed only after the renter chooses (P-1).
- [ ] M9.3 Step 5, submit the transaction message and an optional proof (`booking.payment_proof`) → Payment submitted. Cash → Awaiting cash.
- [ ] M9.4 `PaymentSubmissionService` with retry after a rejection.
- [ ] M9.5 `PaymentVerificationService`: verify (reference, amount, partial needs `payments.accept-partial`) → Reserved; reject with a reason → Payment rejected.
- [ ] M9.6 Permissions `payments.view|verify|reject|accept-partial`, and the policy.
- [ ] M9.7 Firm-block dates on Reserved (listener converts the hold to a blocking status).
- [ ] M9.8 Tests: each method path, partial amounts, double verification guard, transitions.
- [ ] M9.9 Load the client's 20 spreadsheet cases into `quotes.json`. PHP and TS both pass.
- [ ] M9.10 **Gate G3** session using the Quote sandbox. Record sign-off in the log.

## M10 — Notifications and voucher

Status: **Not started** · PRD: P-5–P-7

- [ ] M10.1 Mail setup: domain sender, Blade mail layout with the brand, Mailpit locally.
- [ ] M10.2 `WhatsAppChannel` with a driver interface; the `click_to_chat` driver logs and returns a link; `cloud_api` is a stub.
- [ ] M10.3 `BookingReceived` (renter acknowledgement with status link) on submit.
- [ ] M10.4 `StaffNewBooking` (mail and WhatsApp to the configured staff recipients).
- [ ] M10.5 `PaymentSubmitted` staff alert.
- [ ] M10.6 `VoucherService`: dompdf Blade template with the P-6 fields from the snapshot, stored as `booking.voucher` media.
- [ ] M10.7 `BookingConfirmed`: mail with the voucher PDF attached, plus a WhatsApp link with the voucher text.
- [ ] M10.8 `PaymentRejected`: renter mail with the reason and a retry link.
- [ ] M10.9 Notification settings: per-event channel toggles, recipients, copy.
- [ ] M10.10 All notifications queued on Horizon and logged to activity.
- [ ] M10.11 Tests: each event fires the right notifications, the voucher content matches the snapshot, toggles are respected.

## M11 — Operations desk

Status: **Not started** · PRD: A-5–A-7, P-4 · **Gate G4:** end-to-end booking from submission to voucher takes under 10 minutes of staff time.

- [ ] M11.1 Bookings list: status tabs and filters (date range, car, method), search by reference, name or phone, server pagination (`bookings.view`).
- [ ] M11.2 Booking detail: drivers, quote snapshot, FX snapshot, status history, payments, documents.
- [ ] M11.3 Document viewer: signed URLs and a logged view, requires `bookings.view-documents`.
- [ ] M11.4 Verify and reject payments from the detail page, with a confirm dialog and amount check.
- [ ] M11.5 Resend voucher (email and WhatsApp link) with `bookings.resend-voucher`, and rotate the renter link.
- [ ] M11.6 Record handover: balance and deposit received, odometer, fuel, notes → Active (`bookings.record-handover`).
- [ ] M11.7 Record return: deposit refunded or settled, odometer, excess km → Completed (`bookings.record-return`).
- [ ] M11.8 Cancel and mark no-show with a reason, and show the cancellation tier where configured.
- [ ] M11.9 Availability calendar per car and fleet-wide (bookings, holds, blackouts) using `AvailabilityService`.
- [ ] M11.10 Dashboard: today's pickups and returns, pending verifications, stale FX, TODO settings.
- [ ] M11.11 `kyc:purge` retention job with legal hold, and the activity log retention schedule.
- [ ] M11.12 **Gate G4** timed run-through with the operations staff. Record the result in the log.

## M12 — Hardening

Status: **Not started** · PRD: §7 non-functional

- [ ] M12.1 Security review of the upload and document endpoints: MIME bypass, path traversal, IDOR on UUIDs, token brute force.
- [ ] M12.2 Rate limiter and Turnstile verification on every public POST.
- [ ] M12.3 CSP, HSTS and the security headers audit.
- [ ] M12.4 Encrypted-field audit: no plain identifiers in logs, exceptions or Sentry breadcrumbs.
- [ ] M12.5 Lighthouse mobile at least 85 on home and fleet; LCP under 2.5 s on throttled 4G.
- [ ] M12.6 Query audit (N+1 checks, indexes on bookings, holds and media lookups) with `preventLazyLoading` in non-prod.
- [ ] M12.7 Caching: settings, nav data, FX table and the marquee, with cache busting on admin save.
- [ ] M12.8 WCAG 2.1 AA pass: contrast, keyboard gallery and forms, alt text enforced on media.
- [ ] M12.9 SEO verification: sitemap, structured data validator, OG previews.
- [ ] M12.10 Sentry for PHP and JS, Horizon alerts, and the uptime check on the booking endpoint.
- [ ] M12.11 Backups: daily DB and media dumps with 30-day retention, plus a **tested restore**.
- [ ] M12.12 Load test of booking submit and the media upload path.
- [ ] M12.13 Staff and renter privacy notice and consent text in place (Data Protection Act 2019).
- [ ] M12.14 Runbook: deploy, rollback, rotate keys, restore, switch media disk.
- [ ] M12.15 Dockerfiles (php-fpm + nginx, SSR node, Horizon, scheduler) and the staging deploy pipeline (moved from M0.9). **Pull forward before M5.13**: Gate G1 runs on staging.

## M13 — UAT and launch

Status: **Not started** · **Gate G5:** go-live approval.

- [ ] M13.1 UAT scripts written per requirement ID (G, H, F, B, Q, P, O, A).
- [ ] M13.2 Client UAT on staging, with defects triaged and fixed.
- [ ] M13.3 Production content load: real fleet, photos, brand logos, policies with the `[CONFIRM]` markers resolved and published.
- [ ] M13.4 Production settings filled in: no TODO settings remaining, FX provider key, payment details.
- [ ] M13.5 Production infrastructure: S3 media disks switched on, CDN, Cloudflare WAF, DNS records.
- [ ] M13.6 Mail domain: SPF, DKIM and DMARC verified.
- [ ] M13.7 Staff accounts created with 2FA enrolled and roles assigned.
- [ ] M13.8 ODPC registration confirmed by the client.
- [ ] M13.9 DNS cut-over and post-launch smoke test (a booking through to voucher in production with a test car).
- [ ] M13.10 **Gate G5** go-live approval. Record it in the log.

## P2 — Post-launch backlog

Status: **Not started**

- [ ] P2.1 M-Pesa Daraja STK push with automatic reconciliation (a `PaymentGateway` driver writing to `payment_submissions`).
- [ ] P2.2 Card gateway (Pesapal or Flutterwave).
- [ ] P2.3 WhatsApp Cloud API driver with approved templates.
- [ ] P2.4 Renter "Manage booking" page (view, request a change, cancel per tier).
- [ ] P2.5 Safari package booking.
- [ ] P2.6 Generated TS types from the resources (replacing the hand-maintained types).
- [ ] P2.7 Reporting: revenue, utilisation per car, conversion funnel.
- [ ] P2.8 Multi-language content (EN plus another language if requested).

---

## Progress log

Newest first. Add one row per completed step, status change, block or gate.

| Date       | Step | Change       | Notes                                                                   |
| ---------- | ---- | ------------ | ----------------------------------------------------------------------- |
| 2026-10-20 | M5.1–M5.6, M5.8 | Changed | Admin navigation: pages, services, policies and payment methods are one **Content** entry under System, with tabs like Settings (`admin.content.index` opens the first tab the user may see). Their URLs moved under `/admin/content/…`; route names are unchanged. Messages sit in a new **Operations** sidebar group, which bookings and payments join in M11. 348 Pest tests green. |
| 2026-10-20 | M5 | Blocked | 12/14. M5.1–M5.12 done: 346 Pest tests (1 skipped, HEIC), 23 Vitest tests; Pint, Larastan 7, `vp check`, `tsc` and `vp build` green. `migrate:fresh --seed` on SQLite runs twice with identical counts. Not yet clicked through in a browser. **Blocked:** M5.13 (staging) needs Docker and the deploy pipeline (M12.15), and M5.14 (Gate G1) runs on staging. |
| 2026-10-20 | M5.9–M5.12 | Done | Seed pack copied to `database/seeders/data/` with a README. The client confirmed every policy, so the reviewer's `[CONFIRM]` notes were removed (8 policies; "a fifth currency" became UAE dirhams, and the empty Cancellation heading went until M6.8). `CmsSeeder` runs in every environment, before `DemoCatalogSeeder`: 30 brands, 7 types, 6 services, 7 pages, 31 policies published as bundle 1, 4 payment methods. Writes are insert-only or fill-if-empty; policy versions the seeder wrote have `created_by` null, and staff-written versions or drafts are never replaced. The demo seeder now uses the pack's type names. `SetupChecklistService` feeds a "Before launch" card on the dashboard (D26), which is now a controller. The general map URL is a keyless Google Maps embed built from the pack's address; CSP `frame-src` must allow it in M7. |
| 2026-10-20 | M5.4–M5.8 | Done | Policies with integer versions, one draft at most, immutable published versions (model guard), `[CONFIRM]` refused on publish, a bundle per publish and per deletion of a live policy (soft delete). Admin edit page: Text, Changes (jsdiff line diff, `diff` npm package added with npm), Details, History. Payment methods: fixed set (D25), Markdown instructions, `KeyValueField` details, `has_todo` flag. `PaymentMethod` overrides `getRouteKey()` because its key is an enum. Contact messages inbox with free status moves, `handled_by`/`handled_at`, spam hidden by default; public creation waits for M7.15. M5.7 needed no new code: the General tab (M1.9) already covers it, and the seeder fills it. |
| 2026-10-20 | M5.1–M5.3 | Done | Content is Markdown (D23): `App\Support\Markdown` (HTML stripped, unsafe links dropped) and a shared `MarkdownField` with a server-rendered preview (`admin.markdown.preview`). Services copy the brand module (`service.image`). Preset pages (D22): `config/pages.php` defines home, about, services, fleet, rental-policy, contact and privacy; `PageRegistry` builds rules and the blank shape; images sync to `page.image`. Home settings tab: hero via `MediaService::claim` (the old hero is released), section order and visibility, trust figures. Content editors get services, pages, policies (with publish), payment methods and `settings.home.update` on first role creation; operations get contact messages and `payment-methods.view`. |
| 2026-10-20 | M4.4, M4.9 | Changed | Engine capacity is `engine_capacity_litres` (decimal 3,1, e.g. 2.5) instead of cc; the cars migration was edited in place, so local databases need `migrate:fresh --seed`. `DataTable` gained an optional grid view (`grid` + `viewKey`; phones default to it, the choice is remembered per list) used by cars, brands and users. Light/dark/system toggle in the top bar. |
| 2026-10-18 | M4 | Milestone done | All 12 steps. 292 Pest tests (1 skipped, HEIC), 23 Vitest tests; Pint, Larastan 7, `vp check`, `tsc` and `vp build` green. `migrate:fresh --seed` (local env) seeds the demo catalogue, and a second run adds nothing. Not yet clicked through in a browser. |
| 2026-10-18 | M4.10–M4.12 | Done | `car_blackouts` with `CarBlackoutService` (overlaps refused, back-to-back allowed; booking clashes come with M8), nested scoped routes, a Blackouts tab that sends ISO instants. Factories and `DemoCatalogSeeder` (8 brands, 6 types, 12 draft cars). Seeded starting roles get catalog permissions on first creation only: content-editor gets brands, car types, cars (not the value) and `media.upload`; operations gets `cars.view` and `availability.manage`. `RouteGuardTest` samples can now depend on earlier parameters (nested routes). |
| 2026-10-18 | M4.4–M4.9 | Done | Cars soft-delete, and `HasSlug` now counts soft-deleted rows so a slug is never reused. `SaveCarRequest::carData()` converts amounts with `Money::fromMajor` and only reads `car_value` from staff with `cars.view-value`. The car value is excluded from the activity log. `Money::format()` and a `display` key in its JSON. Admin routes bind by slug. The form has tabs for specs, pricing, photos (`<MediaUploader>`), extra info (`<KeyValueField>`) and blackouts. Publishing needs a cover photo. Public resources list fields explicitly and never include `metadata`. |
| 2026-10-18 | M4.1–M4.3 | Done | Brands (`brand.logo`, PNG/WebP only), marquee drag order and an inline switch, car types with drag order. Brands and types are refused deletion while any car, deleted or not, uses them. `Sortable` and `HasSlug` were used by models for the first time, so the phpstan `trait.unused` ignore was removed. |
| 2026-10-18 | M3 | Dropped | Client decision (D20): `metadata` is a misc JSON column, not an admin-defined schema. `fuel_type` and `colour` became car columns; other specs are informal `cars.metadata` keys. |
| 2026-10-18 | M2 | Milestone done | All 16 steps. 245 Pest tests (1 skipped: HEIC needs Imagick, which this machine lacks; CI's test job has it), 17 Vitest tests; Pint, Larastan 7, `vp check`, `tsc` and `vp build` green. Not yet clicked through in a browser: no form hosts the uploader until M4.9. |
| 2026-10-18 | M2.16 | Done | `tests/Feature/Media/*` (upload, sync, endpoints, token uploads, route guard, cleanup and disk migration) plus the registry unit test. Fixtures are built at runtime (GD plus a spliced EXIF/GPS block) instead of committed binaries; the test-only owner is `Tests\Support\Gadget`. New arch rule: only `App\Services\Media` uses `Storage`. |
| 2026-10-18 | M2.15 | Done | `components/media/`: `MediaUploader` (dnd-kit grid, cover, drop zone, client checks), `UploadTile` (one `useHttp` per file: progress, retry, `X-Booking-Token`), `MediaImage` (srcset), `SecureDocumentViewer` (PDFs open in a new tab; CSP `frame-src`, see M12.3). Added `browser-image-compression` with npm; `pnpm-lock.yaml` is now stale. |
| 2026-10-18 | M2.13–M2.14 | Done | `MediaMaintenanceService` behind `media:cleanup` (scheduled hourly, the first scheduler entry) and `media:migrate-disk` (`--collection`, `--dry-run`, `--keep-source`, checksum-verified, refuses public↔private). |
| 2026-10-18 | M2.8–M2.12 | Done | `MediaUrlGenerator`, `routes/media.php` (web + `media.uploader`, no auth) with a `media.signed` stream route for local disks, `MediaController`, three FormRequests, `MediaResource` and TS types. Private views and downloads are logged. `POST /media` takes no owner hint: pending files are owner-less until `sync()`. Domain exceptions can now name a JSON error field, so `MediaException` shows on the uploader's file field. |
| 2026-10-18 | M2.7, M2.9 | Done | `sync()` (null owner = standalone; `claim()`), `attach`, `reorder`, `delete`, `updateMetadata`, plus the `MaxMediaForCollection` and `PendingOrOwnedMedia` rules. `MediaPolicy` delegates to the owner's `update` and `viewMedia`/`view`. `ResolveMediaUploader` and `CurrentUploader` only treat a session as staff if it would pass `EnsureStaff` and `EnsureTwoFactor`, and checks run via `Gate::forUser` so the super admin bypass can't skip 2FA. The `TokenUploaderResolver` null driver is bound for M8. |
| 2026-10-18 | M2.5–M2.6 | Done | `MediaService::upload` (finfo sniffing, extension from MIME, randomized per-uuid directory, checksum, pending + expiry), `ImageProcessor` and `ImageDriver` (Imagick or GD; EXIF/GPS stripped and orientation applied before storing), `ProcessMediaJob` (WebP conversions, no upscaling, failures fall back to the original). `media` added to the morph map: it's an activity-log subject. |
| 2026-10-18 | M2.2 | Done | The env-only disk switch is verified by `StorageMaintenanceTest`: new uploads go to the new disk, and existing rows stay readable from theirs. |
| 2026-10-17 | M2.4 | Done | `MediaCollectionRegistry` (bound in `AppServiceProvider`, not a singleton) resolves `App\Support\Media\MediaCollection` value objects. `MediaSettings::collection_overrides` can only tighten the config ceilings, and HEIC is dropped when the image driver can't decode it (this machine has GD only). The new Uploads settings tab (`settings.media.update`) stores only values below a ceiling. Unit and feature tests added. |
| 2026-10-17 | M2.3 | Done | `media` migration (status and processing split, D18), `Media` model and factory, `MediaVisibility`/`MediaStatus`/`MediaProcessing`, and the `HasMedia` concern. `getMediaCollections()` was left off the trait because models may not use services; use `MediaCollectionRegistry::forOwner()`. `Relation::enforceMorphMap` (user, role) is on (D17). |
| 2026-10-17 | M2.2 | Partial | Disks `media-{public,private}-{local,s3}` are defined, plus a `public/media-files` link. The env-only switch gets verified by the disk-switch test once uploads exist (Slice B/E). |
| 2026-10-17 | M2.1 | Done | `config/media.php` holds disks, TTLs, conversions, the MIME map and the collection registry. Only `site.hero` is registered; each module registers its own collections (D16). Permissions `media.upload`, `media.view-private` and `settings.media.update` added. |
| 2026-10-17 | M2 | In progress | Plan approved: slices A (foundations) → F (React). Decisions D16–D19. |
| 2026-10-17 | M1.6 | Changed | Account self-deletion restored on the profile page (client request). It goes through `UserService::deleteOwnAccount` (password required, activity logged), and the last active super admin is refused. Fixed ordering: sign out before deleting, or `Auth::logout()` re-saves the deleted user. 162 Pest tests green. |
| 2026-10-17 | M1.5, M1.9, M1.14 | Changed | Gating fixes and configurable 2FA (D16): the `/settings/*` account pages now need `staff` + `admin.idle` (they had `auth` only); new global `EnsureActiveAccount` signs out deactivated accounts on any route; local-disk `serve` routes disabled; admin settings routes gated by `can:update-settings,group`. New `SecuritySettings::require_two_factor` (off by default) with a Security tab (`settings.security.update`). 159 Pest tests green. |
| 2026-10-17 | M1.7 | Changed | Roles are now admin-managed data (D15): `App\Models\Role` (+ `description`, `is_system`), `RoleService` CRUD with activity logging, a locked super admin role, and refusal to delete roles that are still assigned. New permissions `roles.create|update|delete`. The `Role` enum is removed. Seeder creates operations and content-editor once as editable starting roles. 154 Pest tests green. |
| 2026-10-17 | M1.1–M1.3 | Changed | Permissions moved from `config/permissions.php` to the `Permission` and `Role` enums (D14), with module helpers (`module()`, `forModule()`, `except()`, `grouped()`). Policies use `can(Permission::X)` and an arch test enforces it. The new `permissions:types` command generates `resources/js/types/permissions.ts`, so frontend `can()` is type-checked; a test fails if the file is stale. The pattern registry was removed. |
| 2026-10-17 | M0 | Done | 9/10; M0.9 is deferred to M12.15. |
| 2026-10-17 | M0.2 | Done (changed) | Sail removed at the client's request (D12). Local development uses `composer dev` on the host against the host MySQL, with database cache/queue/session and the log mailer. `.env.example` and the README are updated. Docker build cache pruned (5.3 GB freed). |
| 2026-10-17 | M1 | Milestone done | All 14 steps complete. 149 Pest tests, 8 Vitest tests; Pint, Larastan 7, `vp check` and `tsc` green. The 2FA enrolment flow was covered by feature tests but not yet clicked through in a browser. |
| 2026-10-17 | M1.14 | Done | SecurityHeaders (CSP baseline, HSTS in production), named limiters `booking-start`/`booking-step`/`media-upload`/`contact`, `Support\Rules\Turnstile` (implicit; always passes with Cloudflare's test secret outside production), `AdminIdleTimeout` (`baines.admin_idle_minutes`). |
| 2026-10-17 | M1.13 | Done | `components/admin/`: DataTable (+ `useTableQuery`), form fields (Text, Textarea, Select, Switch, Money, Date), StringListField, SlugInput (`lib/slug.ts`), RichTextEditor (Tiptap 3), SortableList (dnd-kit), ConfirmDialog, PageHeader. Added `@tailwindcss/typography`. |
| 2026-10-17 | M1.12 | Done | `Support\Money` (integer minor units, `percentBp` with `RoundingMode`; PRD example 66,350 → 16,588 / 49,762 tested), `Support\DateRange` (half-open), `HasSlug`, `Sortable`. |
| 2026-10-17 | M1.11 | Done | The kit's AppLayout/AppSidebar is the admin shell, driven by `config/admin-nav.ts` and per-item permissions. Shared props: `auth.user` (AuthUserResource), `auth.permissions` (`['*']` for super admin), `name` from GeneralSettings. `JsonResource::withoutWrapping()` is on globally. |
| 2026-10-17 | M1.10 | Done | `User` uses `LogsActivity` (name, email, is_staff, is_active only). Viewer at `/admin/activity` with filters and a diff drawer. Note: activitylog v5 stores model diffs in `attribute_changes`. |
| 2026-10-17 | M1.9 | Done | Settings page `/admin/settings/{group}` with tabs filtered by `settings.{group}.update`. General, Notification and Retention forms ship now; the Pricing (M6.14), Booking (M8.8), Fx (M6.4) and Home (M5.3) tabs return 404 until their steps add a FormRequest in `SettingsGroup::requestClass()`. Saves log a before/after diff. |
| 2026-10-17 | M1.8 | Done | 7 spatie settings classes with §9 defaults in `database/settings/`. Gotcha: spatie parses `@var` to choose casts and can't read array shapes, so shaped arrays use `@phpstan-var` only. |
| 2026-10-17 | M1.7 | Done | Read-only roles index and detail pages from `RoleService` and the registry. |
| 2026-10-17 | M1.6 | Done | Invite (StaffInvitation mail with a reset link; following it verifies the email), edit, roles and direct-permission sync, activate/deactivate, delete, resend invite. Rules on yourself and on the last super admin are enforced in `UserService` (super admins bypass policies) via `DomainException`, which renders as an error toast. |
| 2026-10-17 | M1.5 | Done | Registration and passkeys removed (D13). `users.is_staff/is_active/last_login_at`. Inactive users can't sign in. Middleware `staff`, `2fa`, `admin.idle`; admin routes in `routes/admin.php` under `/admin`. Fortify home is `/admin`. |
| 2026-10-17 | M1.4 | Done | `tests/Feature/Admin/RouteGuardTest.php` walks every `admin.*` route as a staff member with no permissions. `admin.dashboard` is the only route open to all staff. |
| 2026-10-17 | M1.1–M1.3 | Done | `config/permissions.php`, `PermissionRegistry` (`*`, `module.*`, `!x`; reports patterns waiting for their module), `permissions:sync {--prune}` (idempotent; DatabaseSeeder runs it), `Gate::before` for `baines.super_admin_role`. Horizon is gated by `system.horizon`. |
| 2026-10-17 | M0 | In progress | 8/10. M0.2 and M0.9 are partial (see their rows). |
| 2026-10-17 | M0.10 | Done | README: Sail setup, commands, conventions, how to add a module. |
| 2026-10-17 | M0.9 | Deferred | Moved to M12.15 (D12): local development only for now. It must be pulled forward before M5.13 / Gate G1. |
| 2026-10-17 | M0.8 | Done | `.github/workflows/tests.yml` split into lint, static, test and build jobs on PHP 8.4. Not yet run on GitHub: no push has been made. |
| 2026-10-17 | M0.7 | Done | Directory skeleton plus `config/baines.php` (super admin role name, booking reference format, token length, upload ceiling, admin idle minutes). |
| 2026-10-17 | M0.6 | Done | `tests/Arch/LayeringTest.php`: controllers don't use DB/Storage; services don't touch request/response/Inertia; models don't use services; FormRequests have `authorize`; policies never check roles; no debug helpers; enums are enums; public resources never mention `car_value`. The kit's settings requests gained `authorize()`. |
| 2026-10-17 | M0.5 | Done | Pest 4 (kit tests converted with drift), Vitest via `vp test` (`vite.config.ts` test block), Playwright scaffold (`playwright.config.ts`, `tests/e2e`), `tests/fixtures/`. Tests use in-memory SQLite and `withoutVite()`. |
| 2026-10-17 | M0.4 | Done | Larastan stays at level 7. `vp check` (oxlint + oxfmt) replaces ESLint/Prettier (D11). Scripts: `composer lint|types|test|ci:check`, `npm run check|types:check|test|e2e`. `docs/**` excluded from oxfmt; the first `check:fix` reformatted these docs (whitespace only). |
| 2026-10-17 | M0.3 | Done | permission v8, settings v3, activitylog v5, intervention/image v4, dompdf, Horizon, Sentry (exception handler registered), flysystem-aws-s3. All install on Laravel 13 (D10). |
| 2026-10-17 | M0.2 | Partial | `compose.yaml` (MySQL 8.4, Redis, Mailpit, RustFS; Sail dropped MinIO) and a full `.env.example`. **Blocked locally:** the Sail app image build fails because Docker build containers can't resolve DNS (`archive.ubuntu.com`), and the disk is 99% full. Migrations and seeding were verified on a file SQLite instead. |
| 2026-10-17 | M0.1 | Done | Starter kit features chosen (2FA, password confirmation, email verification). Chisel removed; project renamed `baines/baines-app`. |
| 2026-10-17 | —    | Plan created | TECHNICAL_PLAN.md and MILESTONES.md drafted from the PRD of 16 Oct 2026 |

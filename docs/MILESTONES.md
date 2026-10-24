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
| M6  | Pricing, currencies and FX                                     | Done        | 16/17    | —                    |
| M7  | Public site                                                    | In review   | 19/20    | **G2**               |
| M8  | Booking engine                                                 | Done        | 22/23    | —                    |
| M9  | Payments                                                       | Blocked     | 10/12    | **G3** (with M6, M8) |
| M10 | Notifications and voucher                                      | Done        | 11/11    | —                    |
| M10B | In-app alerts and renter portal                               | Done        | 10/10    | —                    |
| M11 | Operations desk                                                | In review   | 13/14    | **G4**               |
| M11B | Online payments (Daraja and Paystack) and Resend mail         | In progress | 8/13     | —                    |
| M11C | Finance and dashboard                                         | Not started | 0/7      | —                    |
| M12 | Hardening (security, performance, SEO, a11y)                   | Not started | 0/17     | —                    |
| M12B | Integrations: keys, configuration and live tests              | Not started | 0/12     | —                    |
| M13 | UAT and launch                                                 | Not started | 0/10     | **G5**               |
| P2  | Post-launch backlog                                            | Not started | 0/9      | —                    |

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

Status: **Done** · PRD: §5, Q-1–Q-4, A-3, A-4, G-1, G-6

- [x] M6.1 Currencies table and admin (`currencies.view|create|update`; deactivated, never deleted). Seed KES (base), USD, GBP, EUR, AED (active) and AUD (inactive).
- [x] M6.2 FX rates: migration, history, and manual rates with `override_until` (`fx-rates.view|override`). *(A fresh API rate always wins, D28.)*
- [x] M6.3 `FxRateProvider` interface with `manual` and `open_exchange_rates` drivers, selected by `FxSettings`. *(Changed: one API, written against its HTTP API; `exchangerate_api` dropped, D28.)*
- [x] M6.4 `FxRateService` and the `fx:refresh` command (every 15 minutes, honours `refresh_minutes`), with a jump guard and a staleness alert to staff. *(The alert is on the dashboard; mail joins in M10.)*
- [x] M6.5 Charges table and admin (`charges.*`). Seed chauffeur (USD 50, per day), additional driver (USD 50, counter), fuel service (USD 20, counter), one-way, valeting, admin, full protection (info_only) and excess km (per km, on request). *(Changed: charges are dynamic, `applies` = extra, always or listed, D32.)*
- [x] M6.6 Delivery zones admin (`delivery-zones.*`). Seed Nairobi at KES 2,000 and "Outside Nairobi" as quoted by staff.
- [x] M6.7 Deposit bands admin (`deposit-bands.*`). Seed 23–23 at 3% and 24–70 at 2%, and add an overlap validation rule.
- [x] M6.8 Cancellation tiers admin (`cancellation-tiers.*`), empty by default.
- [x] M6.9 Day counter strategies `calendar_inclusive`, `rolling_24h` and `daytime_blocks`, with unit tests.
- [x] M6.10 `QuoteService` and the `Quote` value object: line items, totals, reservation rounding, balance, deposit band, display conversions.
- [x] M6.11 `tests/fixtures/quotes.json` with at least 40 cases, including the PRD worked example (66,350 / 16,588 / 49,762 / 160,000).
- [~] M6.12 TS port in `resources/js/lib/quote/` plus Vitest parity against the same fixtures. *Dropped (D31): replaced by M6.17.*
- [x] M6.13 `ShareCurrency` middleware, `CurrencyController@update` (cookie) and `lib/money.ts` formatting.
- [x] M6.14 Pricing settings admin tab: reservation %, rounding, day counting, window, hold and expiry, turnaround, mileage defaults.
- [x] M6.15 Admin "Quote sandbox" page: pick a car, dates and extras to see the server quote. Used for client validation (G3).
- [x] M6.16 Tests: FX margin maths, overrides, stale handling, quote edge cases, settings changes not affecting snapshots.
- [x] M6.17 Server quote preview: `POST /quote/preview` (rate limited) returns the quote for the booking form. Replaces M6.12 (D31).

## M7 — Public site

Status: **In review**, all three slices built; waiting for Gate G2 · PRD: G-1–G-6, H-1–H-5, F-1–F-5, O-1, §6 design · **Gate G2:** design and content sign-off on staging, on mobile and desktop.

- [x] M7.1 Design tokens in Tailwind 4 (palette #0B0B0C, #16171A, #C8A45A; Playfair or Cormorant + Inter), with a motion utility that respects `prefers-reduced-motion`.
- [x] M7.2 Public layout: top bar (tap-to-call, socials, currency switcher), header nav, and a mobile menu.
- [x] M7.3 Floating WhatsApp and Call buttons, plus `WhatsAppLinkBuilder` and `lib/whatsapp.ts` (prefilled text naming the car).
- [x] M7.4 Footer: about, brands, types, useful links, contacts, embedded map, socials, payment icons, policy links.
- [x] M7.5 Home: hero (image or video, overlay, slow zoom) with Rent a Car and Enquire CTAs.
- [x] M7.6 Home sections in configurable order: About, Featured Fleet, Car Types rows, Services, Policy highlights, Requirements 01–03, Why Choose Us 01–04, Trust strip, Contact CTA.
- [x] M7.7 Brand marquee (Embla auto-scroll, pause on hover, lazy logos, each logo links to the filtered fleet), positioned by `HomeSettings::marquee_position`.
- [x] M7.8 Car card component: cover, brand badge, name, price per day in the selected currency, specs (any `cars.metadata` key shown here is added to `CarCardResource` explicitly, D20), mileage, and View, Book and WhatsApp buttons.
- [x] M7.9 Fleet page: filters for brand, type, transmission and seats, a price sort, query-string state and pagination.
- [x] M7.10 Brand and car type listing pages, plus filtered views reusing the fleet page.
- [x] M7.11 Car detail: gallery with lightbox and swipe (6 photos, keyboard accessible), info block (F-4), damage excess, and a Book this unit CTA.
- [x] M7.12 Services page, including safaris with WhatsApp enquiry.
- [x] M7.13 About and Privacy pages, rendered from `pages`.
- [x] M7.14 Rental Policy page: grouped sections, an in-page index, cancellation tiers and the fees table.
- [x] M7.15 Contact page: form (Turnstile, rate limited), map, call and WhatsApp CTAs. Stores to `contact_messages` and notifies staff.
- [x] M7.16 Inertia SSR enabled for public routes and verified without hydration warnings.
- [x] M7.17 SEO: per-page meta and OG, canonical URLs, `sitemap.xml`, JSON-LD (`AutoRental`, `Car`, `LocalBusiness`), clean slugs.
- [x] M7.18 Responsive image `srcset` from media conversions, plus lazy loading.
- [x] M7.19 Feature tests for every public route, plus an SSR smoke test.
- [ ] M7.20 **Gate G2** review on staging, mobile and desktop. Record sign-off in the log.

## M8 — Booking engine

Status: **Done** · PRD: B-1–B-8, Q-1–Q-4, §5 lifecycle, §8.4

- [x] M8.1 Migrations and models: bookings, booking_drivers (main driver only, encrypted casts and hash columns), and `car_blackouts` turned into `car_availability_blocks` (hold, booked, blocked). *(Changed: no holds or status history tables, D35–D37.)*
- [x] M8.2 `BookingStatus` enum with the transitions map, with unit tests for every allowed and denied edge. *(Blocking comes from block rows, not the status, D35.)*
- [x] M8.3 `BookingStatusService::transition()`: validation, a `booking-status` activity row (indexed, D36), `BookingStatusChanged` event.
- [x] M8.4 `BookingReferenceGenerator` (`BE-YYYY-000123`, safe under concurrency).
- [x] M8.5 Booking token handling: 64-character token, hash storage, `ResolveBookingToken` middleware, rotation by staff.
- [x] M8.6 `AvailabilityService`: conflicts from block rows (holds, bookings, staff blocks) plus the turnaround buffer, and `lockAndHold()` with `lockForUpdate`. A staff block over a booking is refused.
- [x] M8.7 `DriverEligibilityService`: age bands, licence expiry, licence years, document matrix, flight notice warning.
- [x] M8.8 Booking settings tab: document requirements matrix, licence rules, flight notice days, additional drivers.
- [x] M8.9 `BookingService::startDraft()` with `StartBookingRequest` (Turnstile, rate limit).
- [x] M8.10 Step 1, trip and extras: `SaveTripStepRequest` (dates, points, delivery zone and address, chauffeur, flight).
- [x] M8.11 Step 2, driver and documents: `SaveDriverStepRequest`, with document requirements resolved per id_type and residency.
- [x] M8.12 KYC uploads through `<MediaUploader>` with the booking token into the `booking.*` private collections.
- [x] M8.13 Step 3, review quote: live price from `POST /quote/preview` (M6.17, debounced), with a toggle to view other currencies.
- [x] M8.14 Terms acceptance: policy bundle id, timestamp and IP stored (B-8).
- [x] M8.15 `BookingService::submit()`: re-validate, recalculate, lock and hold, snapshot quote, FX and settings, then Awaiting payment.
- [x] M8.16 Renter booking status page `/bookings/{token}`: status, quote and next action.
- [x] M8.17 `bookings:expire` command (drafts and awaiting payment, releasing holds), scheduled every 5 minutes.
- [x] M8.18 Booking form UI: multi-step wizard, step state restored from the server after a refresh, mobile-first.
- [x] M8.19 Tests: full flow, age block, missing documents, expired licence, concurrent submit race (one wins), expiry job, snapshot immutability.
- [x] M8.20 Playwright E2E: renter completes steps 1–3 and submits with documents.
- [~] M8.21 Delivery and collection address (D33): when the car is delivered or collected, the renter picks the zone (each zone's description, e.g. the areas it covers, is shown with it), then gives the address, an optional landmark and map pin, and a contact phone for the driver. The same fields cover the return collection point. The address is free text: nothing matches it to the zone automatically.
- [x] M8.22 Staff-quoted delivery (D33): a zone without a fee does not stop the booking. The reservation is worked out without delivery, the quote snapshot marks delivery as pending, and the renter is told the fee is added to the balance at handover. Tests: a quoted zone submits, its reservation excludes delivery, and the snapshot shows it pending.
- [x] M8.23 Cancellation terms snapshot (D34): submit stores the cancellation tiers in force with the quote and settings snapshots, so later tier changes never alter an existing booking's refund. The status page and voucher show the booking's own tiers. Test: editing tiers after submit leaves the booking's tiers unchanged.

## M9 — Payments

Status: **Blocked** (M9.9 waits for the client's spreadsheet) · PRD: P-1–P-4 · **Gate G3:** 20 scripted quote cases match the client's spreadsheet to the shilling.

- [x] M9.1 payment_submissions migration and model, and the `PaymentStatus` enum.
- [x] M9.2 Step 4, choose method: instructions revealed only after the renter chooses (P-1).
- [x] M9.3 Step 5, submit the transaction message and an optional proof (`booking.payment_proof`) → Payment submitted. Cash → Awaiting cash.
- [x] M9.4 `PaymentSubmissionService` with retry after a rejection.
- [x] M9.5 `PaymentVerificationService`: verify (reference, amount, partial needs `payments.accept-partial`) → Reserved; reject with a reason → Payment rejected.
- [x] M9.6 Permissions `payments.view|verify|reject|accept-partial`, and the policy.
- [x] M9.7 Firm-block dates on Reserved (the hold block becomes `booked`, `AvailabilityService::confirm`).
- [x] M9.8 Tests: each method path, partial amounts, double verification guard, transitions.
- [ ] M9.9 Add the client's 20 spreadsheet cases to `tests/fixtures/generate_quotes.py` and regenerate `quotes.json`; the PHP calculator and the Vitest money conversions pass. *(TS quote parity was dropped, D31.)*
- [ ] M9.10 **Gate G3** session using the Quote sandbox. Record sign-off in the log.
- [x] M9.11 Admin payments queue (Operations → Payments): pending payments oldest first, search, proof viewer, Verify and Reject dialogs reused by M11.4.
- [x] M9.12 Hold windows (D38): no expiry while a payment is checked, `cash_hold_hours` for cash, a fresh `hold_minutes` after a rejection; `bookings:expire` sweeps cash and rejected bookings too.

## M10 — Notifications and voucher

Status: **Done** · PRD: P-5–P-7

- [x] M10.1 Mail setup: domain sender, Blade mail layout with the brand, Mailpit locally.
- [x] M10.2 `WhatsAppChannel` with a driver interface; the `click_to_chat` driver logs and returns a link; `cloud_api` is a stub.
- [x] M10.3 `BookingReceived` (renter acknowledgement with status link) on submit.
- [x] M10.4 `StaffNewBooking` (mail and WhatsApp to the configured staff recipients).
- [x] M10.5 `PaymentSubmitted` staff alert.
- [x] M10.6 `VoucherService`: dompdf Blade template with the P-6 fields from the snapshot, stored as `booking.voucher` media.
- [x] M10.7 `BookingConfirmed`: mail with the voucher PDF attached, plus a WhatsApp link with the voucher text.
- [x] M10.8 `PaymentRejected`: renter mail with the reason and a retry link.
- [x] M10.9 Notification settings: per-event channel toggles, recipients, copy.
- [x] M10.10 All notifications queued on Horizon and logged to activity.
- [x] M10.11 Tests: each event fires the right notifications, the voucher content matches the snapshot, toggles are respected.

## M10B — In-app alerts and renter portal

Status: **Done** · Added after M10 at the client's request; pulls part of P2.4 forward (D40).

- [x] M10B.1 `notifications` table and the `AdminAlerts` engine: a `StaffAlert` on the database channel to active staff holding the kind's permissions (and super admins); `AlertKind` names each kind's audience and icon.
- [x] M10B.2 First alerts: new booking, payment submitted (or cash), contact message, renter request.
- [x] M10B.3 Bell next to the appearance toggle: unread badge, latest 10, open (marks read and follows the link), mark all read; polled every 60 s with a partial reload of the shared `alerts` prop.
- [x] M10B.4 `alerts:prune` (daily): read alerts older than 90 days are deleted.
- [x] M10B.5 Driver lookup keys `email_normalized` and `phone_digits` (indexed, backfilled; `Support\PhoneNumber`).
- [x] M10B.6 Portal sign-in at `/my-bookings`: email or phone, Turnstile, a 6-digit code emailed to the address on the bookings (10 minutes, 5 tries), the same answer for unknown identifiers, a one-hour portal session.
- [x] M10B.7 Portal list: each submitted booking with status, total and balance; Open (a fresh link, D39), voucher download (logged).
- [x] M10B.8 Cancel or change requests (`booking_requests`), refused once a booking has ended; staff alert and email.
- [x] M10B.9 Admin Requests page under Operations (`booking-requests.view|update`): open and handled, mark handled with a note.
- [x] M10B.10 Tests for both parts; My bookings link in the header and footer, `noindex`, `Disallow: /my-bookings`.

## M11 — Operations desk

Status: **In review**, all steps built; waiting for Gate G4 (M11.12) · PRD: A-5–A-7, P-4 · **Gate G4:** end-to-end booking from submission to voucher takes under 10 minutes of staff time.

- [x] M11.1 Bookings list: status tabs and filters (date range, car, method), search by reference, name or phone, server pagination (`bookings.view`).
- [x] M11.2 Booking detail: drivers, quote snapshot, FX snapshot, status history, payments, documents.
- [x] M11.3 Document viewer: signed URLs and a logged view, requires `bookings.view-documents`.
- [x] M11.4 Verify and reject payments from the detail page, with a confirm dialog and amount check.
- [x] M11.5 Resend voucher (email and WhatsApp link) with `bookings.resend-voucher`, and rotate the renter link.
- [x] M11.6 Record handover: balance and deposit received, odometer, fuel, notes → Active (`bookings.record-handover`).
- [x] M11.7 Record return: deposit refunded or settled, odometer, excess km → Completed (`bookings.record-return`).
- [x] M11.8 Cancel and mark no-show with a reason, and show the cancellation tier where configured.
- [x] M11.9 Availability calendar per car and fleet-wide (bookings, holds, blackouts) using `AvailabilityService`.
- [x] M11.10 Dashboard: today's pickups and returns, pending verifications, stale FX, TODO settings.
- [x] M11.11 `kyc:purge` retention job with legal hold, and the activity log retention schedule.
- [ ] M11.12 **Gate G4** timed run-through with the operations staff. Record the result in the log.
- [x] M11.13 Delivery check (D33): the booking detail shows the delivery address, landmark, pin and zone, with a reminder to confirm the address is in that zone. Staff can set the fee for a staff-quoted zone, or move the booking to the right zone; either recalculates only the balance (the reservation already paid stays), is activity-logged and notifies the renter (`bookings.update-delivery`).
- [x] M11.14 Cancellation refunds (D34): cancelling a booking (M11.8) shows the notice in hours, the tier from the booking's snapshot (the longest notice the renter still met) and the suggested refund, a share of the reservation paid. Less notice than every tier, or a no-show, suggests 0%. Staff can change the amount with a reason; the suggested and final amounts, the tier and the reason are stored and activity-logged. The refund itself is paid back by hand (payments are manual), and staff mark it refunded with a reference. Tests: each tier boundary, below every tier, no-show, override with reason, and tiers edited after booking.

## M11B — Online payments (Daraja and Paystack) and Resend mail

Status: **In progress** · Added before M12 at the client's request; pulls P2.1 and P2.2 forward (D42). Built against the Daraja sandbox and Paystack test keys; live keys come in M12B.5.

- [x] M11B.1 `gateway_transactions` migration and model, the `GatewayTransactionStatus` enum, `payment_submissions.gateway_transaction_id`, `payment_methods.online_enabled`; Daraja and Paystack keys in `config/services.php` and `.env.example`.
- [x] M11B.2 `DarajaClient`: cached OAuth token, STK push (paybill or till, EAT timestamp password, whole-KES amount, 12-character compact reference), STK query, C2B URL registration.
- [x] M11B.3 `PaystackClient`: initialize (KES, card channel, own reference), verify, webhook signature (HMAC-SHA512).
- [x] M11B.4 `OnlinePaymentService` (`startMpesa`, `startCard`, idempotent `settle`) and `PaymentVerificationService::confirmOnline()` sharing the verify path; Payment submitted → Reserved by `system`; gateway copy in the staff `PaymentSubmitted` notification; `needs_review` with the `payment_needs_review` alert.
- [x] M11B.5 Webhooks in `routes/hooks.php`: Paystack (signature, re-verify), STK result (secret path, re-query), C2B confirmation (secret path, IP allowlist, `C2bMatcher`); CSRF-exempt, idempotent by `external_ref`.
- [x] M11B.6 `payments:reconcile` (every 5 minutes) and `mpesa:register-c2b`.
- [x] M11B.7 Renter payment UI: M-Pesa "Pay now" with phone and polling, Paystack inline popup with server confirm, the compact paybill account line, manual flow as fallback; limiter `online-payment`.
- [x] M11B.8 Admin: "Take payments online" switch on M-Pesa and card (disabled without keys); gateway badges and receipts in the payments queue and the booking desk.
- [ ] M11B.9 M-Pesa receipts page (`admin.receipts.*`): unmatched and needs-review, attach to a booking, mark refunded or ignored with a note.
- [ ] M11B.10 Before launch: the payments part asks only for Daraja and Paystack setup (other items stay); the checklist becomes a dashboard dialog, dismissed for the session.
- [ ] M11B.11 Tests: STK success, failure and timeout; duplicate callbacks; late callback on an expired booking; short payment; bad Paystack signature; C2B match, unmatched and staff attach; notifications; hold extension; online switch off; checklist and dismissal; route guard.
- [ ] M11B.12 Sandbox run through a tunnel: a Daraja STK push, a C2B simulate call and a Paystack test card, each landing exactly once. Record it in the log.
- [ ] M11B.13 Resend as the transactional mailer: `resend/resend-php`, the built-in `resend` transport selected with `MAIL_MAILER=resend` and `RESEND_API_KEY` (Mailpit and `log` stay for local and tests), the sending domain verified in Resend (SPF, DKIM, DMARC). Send every M10 notification and the M10B portal code through a Resend test key and check delivery in the Resend dashboard.

## M11C — Finance and dashboard

Status: **Not started** · Added before M12 at the client's request (D43).

- [ ] M11C.1 `FinanceService`: collected, refunds, net, deposits held, outstanding balances and channels, derived from existing rows, Nairobi days; unit tests against a hand-built fixture.
- [ ] M11C.2 Finance → Revenue (`finance.view`): date range, KPI tiles, daily chart, by channel and by car, paginated ledger.
- [ ] M11C.3 CSV export of the ledger (`finance.export`), streamed.
- [ ] M11C.4 `DashboardStatsService`: KPIs, 30-day series, utilisation, top cars; aggregated in PHP and cached for 5 minutes.
- [ ] M11C.5 shadcn `chart` component (recharts), checked in light and dark mode.
- [ ] M11C.6 Dashboard rebuild: permission-gated KPI tiles and charts linking to their menus, today's lists, receipts needing review; the menu shortcut grid removed.
- [ ] M11C.7 Tests: totals and day edges, widget permission matrix, export, route guard; checked in a browser at 390 px and 1440 px.

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
- [ ] M12.16 Webhook review (M11B): Paystack signature, Daraja secret paths and IP allowlist, replayed and out-of-order callbacks, idempotency under concurrent delivery, no PII in logged payloads.
- [ ] M12.17 Payment abuse checks: the `online-payment` limiter, STK push spam to third-party phones, amount tampering on the card confirm.

## M12B — Integrations: keys, configuration and live tests

Status: **Not started** · Runs on staging after M12, before UAT.

Through M12 the code is built end to end against fakes, test keys and manual fallbacks: the manual FX provider, the log mailer, Turnstile's test secret, local media disks and the `click_to_chat` WhatsApp driver. This milestone gets the real accounts and keys from the client, configures them on staging and proves each integration live. M13 then copies the verified configuration to production. For each step, the client creates the account (they own it), keys go into the staging env (never the repo or the settings table), and the live test is recorded in the log.

- [ ] M12B.1 Integration register in `docs/INTEGRATIONS.md`: each service, the account owner, plan and cost, which env keys it needs, where the keys are stored, who can rotate them, and the live test that proves it works. Check `.env.example` lists every key.
- [ ] M12B.2 FX: the client buys an Open Exchange Rates plan. Set `FX_API_KEY` on staging, switch Settings → Exchange rates to Open Exchange Rates, run `fx:refresh`, and compare the stored rates with a bank's quote. Test the failure paths live: a wrong key (dashboard alert), a stale rate (manual fallback) and the jump guard.
- [ ] M12B.3 Mail: the client's Resend account (M11B.13) with a live key. Verify the sending domain (SPF, DKIM, DMARC) on a staging sender, then send every M10 notification to real Gmail, Outlook and Apple inboxes and check that none land in spam and the voucher PDF opens.
- [ ] M12B.4 WhatsApp: confirm the business number and staff recipients. Check that every click-to-chat link (floating button, car enquiry, voucher, staff alerts) opens the right chat with the right prefilled text on Android and iPhone. The Cloud API stays in P2.3 unless the client asks for it.
- [ ] M12B.5 Payments: the client's Daraja production app (go-live approval from Safaricom, shortcode, passkey) and live Paystack keys with the webhook URL set in its dashboard. Set the keys on staging, run `mpesa:register-c2b`, and switch M-Pesa and card online. Enter the paybill or till and bank details in the payment methods. Make one small real payment by each channel (STK push, paybill with the booking reference, Paystack card, bank, cash), confirm the automatic ones land once and the manual ones verify, and refund each by hand.
- [ ] M12B.6 Turnstile: real site and secret keys for the staging and production hostnames. Confirm the public forms reject a failed challenge and the contact and booking limits still hold.
- [ ] M12B.7 Media storage: create the public and private S3 buckets (or the chosen S3-compatible store) with least-privilege keys and CORS for uploads. Switch the staging disks by env, upload through every collection, check that private URLs expire, and dry-run `media:migrate-disk`.
- [ ] M12B.8 Monitoring: Sentry DSNs for PHP and JS with release tracking, a test exception from each, Horizon failure alerts, and the uptime check on the booking endpoint (M12.10) pointing at real alert recipients.
- [ ] M12B.9 Queue and cache: Redis and Horizon on staging, with mail, FX refresh and media jobs running through the real queue and the scheduler entries firing (`fx:refresh`, `media:cleanup`, `bookings:expire`, `kyc:purge`).
- [ ] M12B.10 Maps and embeds: confirm the map embed and the PDF viewer under the production CSP (`frame-src`), on mobile and desktop.
- [ ] M12B.11 Secrets: rotate every key used during testing, record the rotation steps in the runbook (M12.14), and confirm no key is in the repo, logs or Sentry breadcrumbs.
- [ ] M12B.12 End-to-end live run on staging: a booking with documents, a real payment, staff verification, the voucher email and the WhatsApp link, with display prices from live FX. Record the result and any defects in the log.

## M13 — UAT and launch

Status: **Not started** · **Gate G5:** go-live approval.

- [ ] M13.1 UAT scripts written per requirement ID (G, H, F, B, Q, P, O, A).
- [ ] M13.2 Client UAT on staging, with defects triaged and fixed.
- [ ] M13.3 Production content load: real fleet, photos, brand logos, policies with the `[CONFIRM]` markers resolved and published.
- [ ] M13.4 Production settings filled in: no TODO settings remaining, FX provider key, payment details (copied from the configuration verified in M12B).
- [ ] M13.5 Production infrastructure: S3 media disks switched on, CDN, Cloudflare WAF, DNS records.
- [ ] M13.6 Mail domain: SPF, DKIM and DMARC verified for the production sender (staging verified in M12B.3).
- [ ] M13.7 Staff accounts created with 2FA enrolled and roles assigned.
- [ ] M13.8 ODPC registration confirmed by the client.
- [ ] M13.9 DNS cut-over and post-launch smoke test (a booking through to voucher in production with a test car).
- [ ] M13.10 **Gate G5** go-live approval. Record it in the log.

## P2 — Post-launch backlog

Status: **Not started**

- [~] P2.1 M-Pesa Daraja STK push with automatic reconciliation. *Pulled forward to M11B (D42).*
- [~] P2.2 Card gateway. *Pulled forward to M11B as Paystack (D42).*
- [ ] P2.3 WhatsApp Cloud API driver with approved templates.
- [~] P2.4 Renter "Manage booking" page (view, request a change, cancel per tier). *View and requests built in M10B; self-service cancel per tier is still open.*
- [ ] P2.5 Safari package booking.
- [ ] P2.6 Generated TS types from the resources (replacing the hand-maintained types).
- [ ] P2.7 Reporting: revenue, utilisation per car, conversion funnel.
- [ ] P2.8 Multi-language content (EN plus another language if requested).
- [ ] P2.9 Map-drawn delivery zones: staff draw each zone on a map, the renter drops a pin and the zone (and fee) is picked automatically. Needs a paid maps API key (M12B). Only if wrong zone picks become a problem (D33).

---

## Progress log

Newest first. Add one row per completed step, status change, block or gate.

| Date       | Step | Change       | Notes                                                                   |
| ---------- | ---- | ------------ | ----------------------------------------------------------------------- |
| 2026-10-24 | M11B.8 | Done | Payment method edit (M-Pesa and card only, `PaymentMethodCode::supportsOnline()`): an "Online payments" card with "Take payments online" (`online_enabled`, sent only when present; ignored for bank and cash). The switch can be set before the keys exist: a hint says the Daraja or Paystack keys are not set yet and renters still pay by hand (`gateway_configured`, `OnlinePaymentService::gatewayConfigured()`). Admin `PaymentSubmissionResource` gains `online` (gateway and label) and shows `verified_by` "Verified automatically" for gateway payments; the payments queue and the booking desk show the label as a badge next to the method (gateway transaction eager-loaded in both). 911 Pest tests (1 skipped, HEIC); Pint, Larastan, `vp check`, `tsc` and `vp build` green. Not yet checked in a browser. |
| 2026-10-24 | M11B.7 | Done | Renter routes under the booking token: `POST bookings/{token}/payment/mpesa` (phone; redirect with "Check your phone"), `.../payment/card` (JSON access code, reference, public key), `.../payment/card/confirm` (reference, verified with Paystack) in `OnlinePaymentController`, behind the new limiter `online-payment` (10/hour per booking, 30/hour per IP). Status page props: `payment_methods.*.online`, `online_payment` (latest STK or card attempt of the last 30 minutes: gateway, status, failure reason, start) and `mpesa_pay_to` (paybill with the compact account number, or the till). `PaymentForm`: an online M-Pesa or card method shows `MpesaOnline` (phone pre-filled from the driver, "Pay KES … now", then "Check your phone…" with a resend after 90 s; a failure says why; the paybill and account line) or `CardOnline` (Paystack inline popup from `@paystack/inline-js`, loaded only on click; success posts the reference to confirm; cancel says nothing was charged); "Already paid another way?" opens the existing manual form. The status page polls every 3 s (partial reload) while an attempt is initiated, for at most 5 minutes. CSP: `js.paystack.co` (script), `checkout.paystack.com` (frame), `api.paystack.co` (connect), to confirm in the M11B.12 sandbox run. 908 Pest tests (1 skipped, HEIC), 87 Vitest tests; Pint, Larastan, `vp check`, `tsc`, `vp build` and `build:ssr` green. Not yet checked in a browser: the online flow needs sandbox keys (M11B.12). Note: run `php artisan wayfinder:generate --with-form` (not without the flag) when generating by hand, as the Vite plugin uses form variants. |
| 2026-10-24 | M11B.6 | Done | `payments:reconcile` (every 5 minutes, without overlapping, one server; `OnlinePaymentService::reconcile()`): initiated STK pushes and card payments older than 2 minutes are asked about; still unanswered after 30 minutes they are marked failed; an unreachable gateway is counted and retried next run. A failed attempt is asked again when a callback or the popup reports it, and a paid answer still settles it (Failed → Paid), so a late genuine payment is never dropped. `mpesa:register-c2b` (`registerC2b()`): registers the confirmation and validation URLs, then records the time and the paybill or till in the new system-owned `GatewaySettings` (`gateways` group, no admin tab); `c2bRegistered()` is false again when the configured paybill or till changes. A refused registration prints Safaricom's reason and records nothing. 897 Pest tests (1 skipped, HEIC); Pint and Larastan green. |
| 2026-10-24 | M11B.5 | Done | `routes/hooks.php` under `/hooks`, outside the web group (no session, cookies or CSRF), limiter `hooks` (120/min per IP). `POST hooks/paystack` (`PaystackWebhookController`): 401 for an unsigned or mis-signed body, nothing read; only `charge.success` for one of our references, then `refreshPaystack()`. `POST hooks/mobile/stk/{secret}`, `.../c2b/{secret}/confirm` and `.../validate` (`MpesaCallbackController`) behind `VerifyDarajaCallback` (`daraja.callback`: `hash_equals` on the secret, `DARAJA_ALLOWED_IPS` when set; 404 otherwise, and always 404 with no secret configured); they always answer `{"ResultCode":0,"ResultDesc":"Accepted"}`. `GatewayWebhookService::stkResult()` keeps the reported outcome on the transaction (never the phone) and confirms with `refreshStk()`: a forged "paid" callback that the query calls cancelled ends failed; when Daraja cannot be asked, the push stays initiated for reconcile. `C2bMatcher::record()`: ignores another shortcode (`DarajaClient::ownsShortcode()`), a missing TransID or amount; one row per TransID; account number matched as BE + 4 + 6 digits however typed (`bookingFor()`), payer name kept, then `settle()` (unknown reference unmatched, short payment to review). Validation URL accepts everything. The STK callback URL is now `route('hooks.mobile.stk')`. 890 Pest tests (1 skipped, HEIC); Pint and Larastan green. |
| 2026-10-24 | M11B.4 | Done | `OnlinePaymentService`: `isOnline(method)` (keys present, method active and `online_enabled`; bank and cash never), `startMpesa()` (Kenyan mobile only; refused while a manual payment is pending, the booking takes no payment, or an STK push went out in the last 60 s; the hold is raised to at least 10 minutes; a Daraja failure is reported and the renter told to try again, with nothing recorded), `startCard()` (reference `BE2026000123-XXXXXXXX`, receipt to the driver's email), `confirmCard()` (only the booking's own reference; always verified with Paystack), `refreshStk()` / `refreshPaystack()` (the paid, failed or still-waiting state goes from `initiated` to `paid` or `failed` by a guarded update, so callback, reconcile and popup racing settle once), `settle()` (idempotent under a row lock; the full reservation on a booking that still takes a payment, with no manual payment pending and the car free, is confirmed; otherwise `needs_review` with the reason, or `unmatched` without a booking, and an alert). A Paystack charge for another amount or currency goes to review. `PaymentVerificationService::confirmOnline()` creates the submission (`M-Pesa STK: <receipt>`), walks Payment submitted → Reserved as `system` (`verified_by` null) and shares `markVerified()` with staff verification; the `payments` activity row now records `source` (staff, mpesa_stk, mpesa_c2b, paystack). Staff notification: `BookingNotifier::paymentSubmitted()` takes the newest submission (an online one is verified by the time the queued job runs); `PaymentSubmitted` mail and WhatsApp say "paid online … confirmed automatically, nothing to check" and link to the booking; the bell says "Paid online for …". New `AlertKind::PaymentNeedsReview` (to `payments.verify`, `circle-alert` icon); it links to the payments queue until the receipts page (M11B.9). 872 Pest tests (1 skipped, HEIC); Pint, Larastan and `tsc` green. |
| 2026-10-24 | M11B.3 | Done | `App\Services\Payments\Gateways\PaystackClient` (bound per resolution from `config('services.paystack')`): `initialize()` (KES, `channels: ['card']`, our reference and metadata; returns the popup's access code; never retried), `verify()` (retried reads; refuses an answer whose reference differs from the one asked) returning `PaystackTransaction` (`isSuccessful()` only for `success`, `isPending()` for ongoing, pending, processing, queued), `validSignature()` (HMAC-SHA512 of the raw body, `hash_equals`, false without a key or header). 848 Pest tests (1 skipped, HEIC); Pint and Larastan green. |
| 2026-10-24 | M11B.2 | Done | `App\Services\Payments\Gateways\DarajaClient` (bound per resolution from `config('services.daraja')`): `configured()` (the till number too when `type` is till), `payTo()`, `stkPush()` (EAT timestamp password; paybill `CustomerPayBillOnline` to the shortcode, till `CustomerBuyGoodsOnline` with the store number as shortcode and the till as PartyB; whole shillings only; reference at most 12 characters; description cut to 13; never retried, so the renter is never prompted twice), `stkQuery()` (`500.001.1001` is pending, ResultCode 0 paid, anything else failed; other errors throw), `registerC2bUrls()` (v2 endpoint, `ResponseType` Completed) and a sandbox-only `simulateC2b()` for M11B.12. OAuth token cached until a minute before expiry, keyed by host and consumer key, and dropped on a 401. `StkPushResult`, `StkQueryResult`, `GatewayException` (messages never carry keys or tokens). `PhoneNumber::kenyanMobile()` (2547/2541 form, or null). The v2 C2B registration path is to be confirmed in the M11B.12 sandbox run. 834 Pest tests (1 skipped, HEIC); Pint and Larastan green. |
| 2026-10-24 | M11B.1 | Done | `gateway_transactions` (`GatewayTransaction`, factory), enums `PaymentGateway` (mpesa_stk, mpesa_c2b, paystack; each maps to its `PaymentMethodCode`) and `GatewayTransactionStatus` (`awaitsStaff()`), `payment_submissions.gateway_transaction_id` (`isOnline()`), `payment_methods.online_enabled`, `Booking::gatewayTransactions()` and `compactReference()` (BE2026000123, Daraja's 12 characters). `payer_phone` is encrypted and hidden; no lookup hash, since nothing searches by it. A `resolution_note` column holds the staff note when a receipt is resolved. `daraja` and `paystack` in `config/services.php`, their keys and `RESEND_API_KEY` in `.env.example`; `resend/resend-php` installed for M11B.13. Migrate and rollback checked. 803 Pest tests (1 skipped, HEIC); Pint and Larastan green. |
| 2026-10-24 | M11B, M11C | Added | Two milestones before M12 at the client's request. M11B: Daraja (STK push and C2B paybill matching) and Paystack (cards) collect the reservation and verify the booking automatically; balance and refunds stay manual (D42). Pulls P2.1 and P2.2 forward; M12B.5 now covers the live gateway keys; M12.16 and M12.17 harden the webhooks. M11C: Finance → Revenue and a rebuilt dashboard with KPIs and charts in place of the menu shortcuts; the before-launch checklist's payments part asks only for Daraja and Paystack and the checklist becomes a session-dismissed dialog (D43). |
| 2026-10-24 | M11 | In review | 13/14; M11.12 (Gate G4, the timed run with the operations staff) is left. Decision D41. **Desk:** Operations → Bookings (`BookingController`, `BookingDeskService`): tabs Unpaid, Upcoming, On hire, Closed, All (`BookingBucket`; drafts never shown) with counts, search by reference, name or phone, local pickup dates, car and payment method. The booking page has trip, delivery (address, landmark, pin, zone and its description, the check reminder), money, the frozen quote and its other-currency table (`QuoteResult`, now shared with the quote sandbox), payments with the M9 Verify and Reject dialogs, handover and return records, history, driver (identity numbers masked without `bookings.view-documents`), documents, voucher, cancellation, renter requests and the legal hold. **Actions** (each needs `bookings.view` plus its own permission): handover (Reserved → Active; refused while a staff-quoted delivery has no fee; a note when the balance taken differs) and return (Active → Completed; excess km from the snapshot's allowance, a note when deposit is kept) in `handover_records`; cancel or no-show with the refund from the booking's own tiers (`RefundCalculator`, D34 as written), an override needs a reason, then "mark refunded" with a reference; delivery fee or zone change (balance only, the quote snapshot unchanged, renter mailed by the new `DeliveryUpdated`, toggle `delivery_updated`); resend the voucher (stored one, fresh link) and replace the renter link (shown once in the toast, with a WhatsApp action). New edges: Awaiting payment, Payment submitted and Awaiting cash → Cancelled. New permissions `bookings.resend-voucher`, `.record-handover`, `.record-return`, `.cancel`, `.update-delivery`, `.legal-hold`; the operations starting role gets all but the legal hold on first creation only, so existing local roles need them added by hand. **Calendar:** Operations → Availability (`bookings.view` or `availability.manage`): cars by days, 2 or 4 weeks from local midnight, bars for booked, held and staff-blocked, a booking bar opens it; the car's Blackouts tab links to it. **Dashboard:** pickups and returns today, pickup times passed, overdue returns, payments to check, then the existing checklist (stale FX, TODO settings). **Retention:** `kyc:purge` daily at 02:30 (`closed_at` + `kyc_retention_months`, legal hold respected, voucher and proofs kept, identity numbers blanked, never-submitted drafts deleted after `draft_retention_days`) and `activity:prune` weekly (`activitylog:clean` with `activity_log_retention_months`). Refused desk actions show as an error toast, like payments. 795 Pest tests (1 skipped, HEIC); Pint, Larastan, `vp check`, `tsc`, `vp build` and `build:ssr` green; migrate and rollback checked. The parallel suite needs `--processes=4` here (the default ran out of memory). Not yet clicked through in a browser. |
| 2026-10-23 | M10B | Milestone done | 10/10. Decision D40. Staff alerts: Laravel `notifications` table; `AdminAlerts::send(kind, title, body, url)` goes to active staff with any of the kind's permissions (direct or by role) and to super admins; `AlertKind` (`new_booking` to bookings.view or payments.view, `payment_submitted`, `contact_message`, `booking_request`). Bell (`components/admin/notification-bell.tsx`) before the appearance toggle in both admin headers; shared `alerts` prop on admin routes only, `usePoll(60 s)` with `only: ['alerts']`. `admin.alerts.open` and `admin.alerts.read-all` are scoped to the user's own alerts and listed as own-record routes in `RouteGuardTest`. Portal: `/my-bookings` (`BookingPortalController`, `BookingPortalService`, `portal` middleware). The code always goes by email (no SMS provider); it is stored HMAC-hashed in the cache (10 minutes, 5 tries); the session is regenerated and lasts an hour; limiters `portal` (10/hour per IP, 5/hour per identifier) and `portal-verify` (20/hour per IP). Open rotates the token; the voucher streams through `MediaService` and is logged; requests (`booking_requests`, `BookingRequestType` cancel or change) send a staff email and an alert. The operations starting role gets `booking-requests.*` on first creation only. 738 Pest tests (1 skipped, HEIC), 87 Vitest tests; Pint, Larastan, `vp check`, `tsc` and `vp build` green. Not yet checked in a browser. |
| 2026-10-23 | M10 | Milestone done | 11/11. Decision D39 (rotate-on-send renter links). `BookingNotifier` plus the queued `SendBookingNotifications` listener on `BookingStatusChanged`: `BookingReceived` (renter, from `BookingService::submit`, which now takes the request's token), `StaffNewBooking`, `PaymentSubmitted` (also for cash), `BookingConfirmed` (voucher attached) and `PaymentRejected` (reason, retry link). All extend `BookingNotification`: queued, encrypted on the queue, after commit, channels from the toggles in Notification settings. The confirmation and rejection mails rotate the token and carry the new link; with the mail off, the token is left alone. Voucher: `VoucherService` (dompdf, `pdf/voucher.blade.php`, P-6 fields and the booking's own tiers from the snapshot), stored as `booking.voucher` by the new `MediaService::storeGenerated` (replaces the previous one); staff with `bookings.view` or `payments.view` can open it. WhatsApp: `WhatsAppChannel` with `click_to_chat` (builds and logs the wa.me link) and a `cloud_api` stub that throws. Under click-to-chat nothing reaches the staff number (logged only); staff mails link to a chat with the renter, and the verify toast gains a "Send on WhatsApp" action with the confirmation text (new setting `voucher_whatsapp_text`). `WhatsAppLinkBuilder::linkTo()` (and `lib/whatsapp.ts`) read a local `07…` number as Kenyan. Each notification sent is logged as `notifications` activity. Mail: published `vendor/mail` message view and theme (charcoal header, gold buttons, company contacts in the footer); `.env.example` notes Mailpit and that Horizon needs `QUEUE_CONNECTION=redis`. 716 Pest tests (1 skipped, HEIC), 87 Vitest tests; Pint, Larastan, `vp check`, `tsc` and `vp build` green. Voucher PDF and confirmation mail rendered from a local booking and checked by eye; not yet run through a browser with Mailpit. The FX stale alert mail stays dashboard-only (not in M10). |
| 2026-10-23 | M9.1–M9.8, M9.11, M9.12 | Done; M9 Blocked | 10/12. **Blocked:** M9.9 needs the client's 20 spreadsheet cases, then Gate G3 (M9.10). Decision D38 (hold windows, part payments). `payment_submissions` (method, message, proof, then reference, amount and note on verify, or the reason on reject); `bookings.reservation_paid_minor` and `paid_at`. Renter: `POST bookings/{token}/payment`; the status page lists active methods, shows a method's instructions once chosen, takes the message and an optional proof (`booking.payment_proof`, token upload while Awaiting payment or Payment rejected; the resolver contract gained `canUpload()`, KYC stays drafts only), and shows the rejection reason, "checking", cash and confirmed states. Hold: no expiry while being checked (a hold with null `expires_at` now counts as active), `cash_hold_hours` (24, Pricing tab) for cash, a fresh `hold_minutes` after a rejection; new edges Payment rejected → Awaiting cash and → Expired. Staff: Operations → Payments (`payments.*`; the operations starting role gets them on first creation only, so existing local roles need them added by hand). Verify locks the submission (a second click is refused), re-checks the car is free (a lapsed cash hold may have lost it), stores the amount and moves a shortfall to the balance; Reserved confirms the hold as `booked` in `BookingStatusService`. Cash is never rejected. Payment-staff can open proofs (`BookingPolicy::viewMedia` now gets the file), not KYC. 705 Pest tests (1 skipped, HEIC); Pint, Larastan, `vp check`, `tsc`, `vp build` and `build:ssr` green. Playwright booking spec now pays by M-Pesa with a proof: green on desktop and Pixel 7 twice running (one earlier run failed on both before passing; cause not found). Checked in a browser: payments queue at 1440 and 390 px (no horizontal scroll), the part-payment note, a real verify (booking Reserved, block `booked`), and the renter's rejected and confirmed pages at 390 px. Notifications (payment submitted, rejected, voucher) are M10. |
| 2026-10-23 | M8 | Milestone done | 22/23; M8.21 partial: the map pin has columns (`delivery_lat`/`lng`, `return_lat`/`lng`) and is validated if sent, but the form has no pin picker, which needs a maps API key (M12B, P2.9). Address, landmark and driver phone are in. 675 Pest tests (1 skipped, HEIC), 86 Vitest tests, Playwright booking spec green on desktop and Pixel 7; Pint, Larastan, `vp check`, `tsc`, `vp build` and `build:ssr` green. Checked in Chrome at 390px and 1440px: no horizontal scroll. The E2E books the seeded Mazda Demio on random dates against `composer dev`, so it adds bookings to the local database (they expire with `bookings:expire`); it is not in CI yet, which would need a seeded app server. |
| 2026-10-23 | M8.9–M8.23 | Done | Slices B and C: the renter flow. Routes: `GET`/`POST cars/{car}/book` (start, Turnstile, `booking-start`), `GET bookings/{token}` (wizard while a draft, status page after), `PUT bookings/{token}/trip`, `PUT bookings/{token}/driver`, `POST bookings/{token}/submit` (`booking.token`, `booking-step`). `BookingService`: `startDraft`, `saveTrip` (prices it and checks the car is free, no hold yet), `saveDriver` (keeps the details, attaches the required documents and removes the others, then reports eligibility and age problems as field errors), `submit` (re-locks the booking row against double clicks, re-checks, holds the car, assigns the reference, freezes the quote, tiers and terms bundle with time and IP, then Awaiting payment), `expireStale`. `bookings:expire` every 5 minutes: drafts 24 h after their last save, unpaid bookings when the hold ends. Identity numbers and the KRA PIN never go back to the browser (masked hints; blank keeps the saved value). Times arrive as Nairobi instants and are stored in UTC (a first version stored them 3 h out). Pages `public/booking/{start,wizard,status}` with a live price on step 1 and a currency switch (KES plus every rated currency); the car page and car cards link to the booking page; `Disallow: /bookings` in robots.txt and `noindex` on token pages. The E2E found a real bug: documents finishing together overwrote each other in the form (now merged into the latest data). Public `Field` moved to `components/public/form-field.tsx`. |
| 2026-10-22 | M8.1–M8.8 | Done | Slice A of 3: data and domain. `bookings` (reference set on submit, token hash, `HandoverMode` office or address for each leg, D33 address fields, extras by code, `quote` and `cancellation_tiers` snapshots, money columns), `booking_drivers` (one row; id number, KRA PIN and licence number encrypted, id number with an app-keyed HMAC), `booking_reference_sequences`. `car_blackouts` renamed to `car_availability_blocks` by a new migration (existing rows become `blocked`; the old activity subject type is repointed), model `CarBlackout` → `AvailabilityBlock`; `Car::blackouts()` returns staff blocks only, so the admin tab is unchanged, and a staff block over a hold or booking is refused, naming the reference. `BookingStatus` (PRD table), `BookingStatusService` (one `booking-status` activity row per change, frees the hold on Expired, Cancelled and No-show, `BookingStatusChanged` after commit, `timeline()`), `BookingReferenceGenerator` (per year in Nairobi time, row-locked), `BookingTokenService` and the `booking.token` middleware, `BookingTokenResolver` (drafts only) replacing the null resolver, `AvailabilityService` (blocks + turnaround, `lockAndHold` locks the car row, `confirm` for M9.7), `DriverEligibilityService` (licence expiry by local date, licence years, KRA PIN and documents per the matrix; flight notice is a warning; age stays with the deposit bands in the quote). Five private `booking.*` collections, token upload only. Booking settings tab (licence rules, flight notice, the document matrix); every settings tab now exists, so `SettingsGroup::isEditable()` is gone. Permissions `bookings.view`, `bookings.view-documents` and `BookingPolicy::viewMedia`. 644 Pest tests (1 skipped, HEIC); Pint, Larastan, `vp check` and `tsc` green; migrate and rollback checked on MySQL, `migrate:fresh --seed` twice on SQLite. Next: slice B, the renter flow on the server. |
| 2026-10-22 | M8 | In progress | Plan approved, in three slices: A data and domain, B renter flow on the server, C wizard UI and tests. Decisions D35–D37: one `car_availability_blocks` table (hold, booked, blocked) replaces `car_blackouts` and the planned holds table; status changes go to the activity log only (`booking-status`, indexed); main driver only, office or delivery for pickup and return, one `quote` snapshot. |
| 2026-10-22 | M7 | Changed | Images managed in the admin with upload guidance. Each media collection (`config/media.php`) and page image field (`config/pages.php`) can give `guidance` (shape, recommended pixels, framing tip); the uploader shows it with the shape drawn to scale, and warns when an uploaded image is under 75% of the recommended size. New: an optional hero video (`site.hero_video`, MP4 or WebM up to 20 MB, `HomeSettings::hero_video_uuid`) that plays muted over the hero image (its poster, and the fallback with reduced motion); a header image on the Services, Fleet (also the brand and type pages), Rental policy, Contact and About pages, shown behind the title. Service photos (Content → Services) feed both the home cards and the Services page, now both upright 4:5. `baines.uploads.max_kb_ceiling` is 20 MB, so PHP's `upload_max_filesize` and `post_max_size` must allow that on the server (M12); locally they are 2 MB and 8 MB. `PageContent` service resolves preset page content for public controllers. |
| 2026-10-22 | M7.9–M7.19 | Done | Slices 2 and 3. Fleet (`FleetController`): brand, type, gearbox and seat filters, price sort, query-string state, 12 per page; an unknown, inactive or hidden brand or type in the query is ignored. `/car-brands/{brand}` and `/car-types/{type}` are the same page with the filter fixed; inactive or hidden ones are not found. Car page: Embla gallery with thumbnails, arrows, keys and a full-screen lightbox; the F-4 information block; "Book this car" opens the contact form with the car chosen until M8; related cars. Services (anchored sections, CTA per `cta_type`), About and Privacy (preset pages; About falls back to the home story), Rental Policy (grouped policies, index, live fees, deposit bands, cancellation tiers, delivery zones), Contact (Turnstile, `contact` limiter, stores to `contact_messages`, mails the staff emails; one "email or phone" error). SEO: `Seo` component (title, description, canonical without the query, Open Graph), JSON-LD `AutoRental` (home, contact) and `Car`, `sitemap.xml`, `robots.txt` route. SSR: Inertia v3 uses `app.tsx` as the SSR entry; public pages only (`$withoutSsr`); production bundle `bootstrap/ssr/app.js` from `npm run build:ssr`, served by `php artisan inertia:start-ssr` (to add to the deploy in M12). `SsrSmokeTest` renders all 10 public pages through the built bundle; no hydration warnings on any page in a browser. Local media URLs are now root-relative (`/media-files/...`), so images load on any host. New scope `hasPublishedCars()` on brands and car types. 599 Pest tests, 85 Vitest tests; Pint, Larastan, `vp check`, `tsc`, `vp build` and `build:ssr` green. Next: M7.20, Gate G2 review on staging. |
| 2026-10-22 | M7.5–M7.6 | Changed | Hero fills the first screen. Services are a continuously gliding row of photo cards (Embla auto-scroll, like the brand strip; stops on hover or focus; still with reduced motion); the text sits on a blurred, darkened copy of the photo. Interim Unsplash photos for the hero and the six services, cropped with plates blurred, credits in `data/media/stock.json`, added by `SiteMediaSeeder` only where none is set. A photo brief for the client covers fleet, banner, services and About shots. Section tones per band (sand, olive, gold, charcoal) and icons on the Before you book cards. |
| 2026-10-22 | M7.1, M7.6, M4 | Changed | Design refinement. Type is now Manrope only (replaces Playfair Display + Inter): semibold, tight-set headings read automotive rather than editorial; prices use tabular figures. Removed template chrome: gold all-caps labels above headings, the gold rule under each heading, middle-dot strings, arrows on text links, and numbering on Why choose us and services (Requirements keep 01–03 as steps). Home: the services overview now comes straight after the hero, listing the real services from the admin with a "Learn more" link each (to `/services#slug`) and "Explore all services"; the settings migration `2026_10_22_100000` moves this order only where staff never changed it. Car types: `is_active` already was the public visibility flag (default true), so no new column; it is relabelled "Shown on the website", gets an inline switch in the car type list (`PUT admin/car-types/{car_type}/visibility`, like the Featured and In marquee switches), and a hidden type's public page is a 404. Hiding a type never hides its cars. |
| 2026-10-21 | M7.1–M7.8 | Done | Slice 1 of 3: tokens, layout, card and home. `.public` scope on top of `.dark` gives the public site the brand palette while the admin keeps its appearance toggle; `app.css` now uses `@theme inline` so utilities follow the scoped variables. Inter + Playfair Display (Bunny). New `site` middleware (`ShareSite`) next to `currency`. `WhatsAppLinkBuilder` and `lib/whatsapp.ts` share fill rules and encoding (same test cases on both sides). `HomeController` sends only visible sections; car type rows hold 4 published cars. The other public routes are named and render `public/coming-soon` until slices 2–3. Seed pack from the client's info sheet: the client's 7 car types and 6 marquee brands, home headline and trust figures, home page copy (`pages.json`, fills empty fields only), and `FleetSeeder` with the real Mazda Demio and its 6 photos (plates blurred). Demo cars get a placeholder photo and are published. Run `migrate:fresh --seed` and `php artisan storage:link` locally. Screenshots checked at 390px and 1440px. Pending: the map embed, which rendered blank in headless Chrome, so check it in a real browser; the existing inline appearance script in `app.blade.php` is blocked by the CSP outside `vp dev`. |
| 2026-10-21 | M8, M11 | Added | Cancellation refunds (D34): M8.23 snapshots the tiers on submit; M11.14 suggests the refund from the booking's own tiers (reservation only; below every tier or a no-show is 0%), lets staff override it with a reason, and records the manual refund. The 0% default and the reservation-only base need client confirmation before M11. |
| 2026-10-21 | M8, M11, P2 | Added | Home delivery (D33): renters pick the zone and type their address; staff check the address against the zone. New steps M8.21 (address, landmark, pin, driver phone; same for return collection), M8.22 (a staff-quoted zone books normally, with delivery added to the balance at handover), M11.13 (staff set the quoted fee or correct the zone, balance only) and P2.9 (map-drawn zones, later). |
| 2026-10-21 | M6.5 | Changed | Dynamic charges (D32): the fixed `ChargeTrigger` enum and `is_optional` are replaced by `applies` (`extra`, `always`, `listed`), and renters pick extras by charge code, so staff can add a bookable extra without a code change. Seeded valeting, admin fee and excess km are listed only. The pricing-rules migration was edited in place: run `php artisan migrate:rollback --step=1 && php artisan migrate` (or `migrate:fresh --seed`) on local databases. Fixtures regenerated (57 cases). |
| 2026-10-21 | M12B | Added | New milestone after M12: get the client's accounts and keys for every integration (FX, mail, WhatsApp, payments, Turnstile, S3, monitoring), configure them on staging and test each live. Until then code is built end to end against fakes and test keys. Named M12B so M13 keeps its number. |
| 2026-10-20 | M6 | Milestone done | 16/17; M6.12 dropped for M6.17 (D31). 541 Pest tests (1 skipped, HEIC), 79 Vitest tests; Pint, Larastan 7, `vp check`, `tsc` and `vp build` green. `migrate:fresh --seed` on SQLite runs twice with identical counts. Not yet clicked through in a browser. |
| 2026-10-20 | M6.9–M6.17 | Done | Day counters work in Africa/Nairobi (D30). `QuoteService` loads the rules; `Quote\QuoteCalculator` has no database access. `tests/fixtures/quotes.json` (57 cases) is generated by `tests/fixtures/generate_quotes.py`, an independent Python implementation, and the PHP calculator matches every case; `lib/money.ts` (exact BigInt conversion, half up) matches every display conversion in Vitest. Foreign charges are multiplied out, then converted once at the market rate. A mandatory charge (not optional, no trigger) always applies. Age is taken on the local pickup date. Public: `currency` middleware on public routes, `POST /currency` (cookie), `POST /quote/preview` (`quote-preview` limiter, 30/min; no band percentage, so the car value stays hidden). Admin: Pricing settings tab, Quote sandbox (`quotes.sandbox`; scenario in the query string, local times; deposit only with `cars.view-value`). Operations gets the pricing `.view` permissions, `fx-rates.view` and `quotes.sandbox` on first role creation. |
| 2026-10-20 | M6.1–M6.8 | Done | Currencies, FX and the four pricing tables. Rates are bcmath strings (`Support\Decimal`), never floats. Open Exchange Rates is called directly (USD-based, KES cross rates at 12 places, rounded to 6); a wrong base, missing or non-positive rate, or future timestamp rejects the whole fetch; a move over `max_jump_percent` (new setting, 10) is held back until staff accept it. The provider's timestamp is stored as `fetched_at`, and unchanged fetches are not stored again. The API key is env-only (`FX_API_KEY`; `FX_PROVIDER` removed: the admin picks the provider). `config('baines.currencies')` is gone: car excess currencies come from the table. `PricingSeeder` runs after `CmsSeeder` (insert-only; bands only into an empty table); `DemoCatalogSeeder` adds manual demo rates and car values for local and staging. Currencies can't be deactivated while active charges use them. Dashboard: missing key, missing, stale, failed and held-back rates. |
| 2026-10-20 | M6 | In progress | Plan approved. Decisions D27–D31: storage vs display decimals, FX precedence (API always wins), delivery by zone only, business time zone, server-only quote. |
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

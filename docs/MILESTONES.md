# Baines Executives — Engineering Milestones

Living tracker for the build. The design behind each step is in [TECHNICAL_PLAN.md](TECHNICAL_PLAN.md), and the requirement IDs (G-, H-, F-, B-, Q-, P-, O-, A-) come from the PRD.

## How to update this file

1. **After every step:**
   - Tick the step box.
   - Set the milestone's status.
   - Update the **Progress** column in the summary table.
   - Add a row to the [Progress log](#progress-log).
2. **Status values:** `Not started` · `In progress` · `Blocked` · `Done`. If a milestone is Blocked, write the reason in the log.
3. **Step state:** `[ ]` is open, `[x]` is done, and `[~]` is partially done or deferred. Explain any `[~]` in the log.
4. **Every module step includes the standard checklist below.** Do not tick a step until all of it is true.
5. **Scope changes:** new steps get the next free ID inside the milestone, for example `M3.12`. Never renumber existing steps.

**Standard definition of done for a module step:**

- Migration and model, with `metadata` where the plan says so
- Factory
- Permissions added to `config/permissions.php`, then `permissions:sync` run
- Policy
- Form Requests
- Service
- Resources (admin and public)
- Controller and routes
- Inertia page(s)
- Pest feature tests covering guest, 403, validation and happy path
- Pint, Larastan, ESLint and tsc all green

---

## Summary

| # | Milestone | Status | Progress | Gate |
| --- | --- | --- | --- | --- |
| M0 | Project bootstrap and conventions | Not started | 0/10 | — |
| M1 | Platform foundations (auth, IAM, settings, audit, admin shell) | Not started | 0/14 | — |
| M2 | Media module (shared) | Not started | 0/16 | — |
| M3 | Metadata fields | Not started | 0/7 | — |
| M4 | Catalog (brands, car types, cars) | Not started | 0/12 | **G1** (with M5) |
| M5 | Content CMS and seed pack | Not started | 0/14 | **G1** |
| M6 | Pricing, currencies and FX | Not started | 0/16 | — |
| M7 | Public site | Not started | 0/20 | **G2** |
| M8 | Booking engine | Not started | 0/20 | — |
| M9 | Payments | Not started | 0/10 | **G3** (with M6, M8) |
| M10 | Notifications and voucher | Not started | 0/11 | — |
| M11 | Operations desk | Not started | 0/12 | **G4** |
| M12 | Hardening (security, performance, SEO, a11y) | Not started | 0/14 | — |
| M13 | UAT and launch | Not started | 0/10 | **G5** |
| P2 | Post-launch backlog | Not started | 0/8 | — |

---

## M0 — Project bootstrap and conventions

Status: **Not started**

- [ ] M0.1 Create the Laravel 12 app from the React starter kit (Inertia 2, React 19, TS, Tailwind 4, shadcn, Wayfinder) and initialize the git repo.
- [ ] M0.2 Set up Sail services: MySQL 8, Redis, Mailpit and MinIO, plus `.env.example` with every `MEDIA_*`, `FX_*` and `WHATSAPP_*` key.
- [ ] M0.3 Install core packages: spatie/laravel-permission, spatie/laravel-settings, spatie/laravel-activitylog, intervention/image, barryvdh/laravel-dompdf, laravel/horizon, sentry/sentry-laravel.
- [ ] M0.4 Set up quality tooling: Pint config, Larastan (level 6), ESLint and Prettier, `tsc --noEmit`, and composer and npm scripts (`lint`, `test`, `types`).
- [ ] M0.5 Set up testing: Pest 3 with the arch plugin, Vitest, a Playwright scaffold, and a `tests/fixtures/` folder.
- [ ] M0.6 Add the Pest arch tests that enforce the layering rules (TECHNICAL_PLAN §2, §16).
- [ ] M0.7 Create the directory skeleton (`Services/*`, `Http/{Controllers,Requests,Resources}/{Admin,Public,Media}`, `Settings`, `Support`, `Enums`) and add `config/baines.php`.
- [ ] M0.8 Set up the CI workflow in GitHub Actions: lint, static analysis, types, Pest, Vitest and build.
- [ ] M0.9 Add Dockerfiles (php-fpm + nginx, SSR node, Horizon, scheduler) and the staging deploy pipeline.
- [ ] M0.10 Write the README covering local setup, the conventions and how to add a module, with a link to this file.

## M1 — Platform foundations

Status: **Not started** · PRD: §3 roles, A-7, §7 security

- [ ] M1.1 Add the `PermissionRegistry`, with `*` and `!` pattern expansion, plus unit tests.
- [ ] M1.2 Add the `permissions:sync` command (idempotent, `--prune`) and wire it into `DatabaseSeeder` and the deploy step.
- [ ] M1.3 Seed the roles super-admin, operations and content-editor from config, and add the `Gate::before` bypass for super-admin.
- [ ] M1.4 Add the route guard test that checks every `admin.*` route returns 403 for a user with no permissions.
- [ ] M1.5 Staff auth: Fortify login, password reset and TOTP 2FA. Add the `EnsureTwoFactor` and `EnsureStaff` middleware and disable public registration.
- [ ] M1.6 Users admin (CRUD, assign roles, extra permissions, deactivate), with permissions `users.*`.
- [ ] M1.7 Roles admin, read-only view of the grants defined in config (`roles.view`).
- [ ] M1.8 Settings infrastructure: base settings classes and migrations for General, Pricing, Booking, Fx, Notification, Home and Retention, with defaults from TECHNICAL_PLAN §9.
- [ ] M1.9 Settings admin UI: one tabbed page with a FormRequest per group and permissions `settings.{group}.update`.
- [ ] M1.10 Activity log: the `LogsActivity` setup convention and an admin viewer with filters (`activity.view`).
- [ ] M1.11 Admin shell: layout, sidebar driven by permissions, flash toasts, confirm dialogs, and shared Inertia props (auth user, abilities, settings subset).
- [ ] M1.12 Shared `Support` code: `Money` (minor units, rounding modes), `DateRange` and `HasSlug` / `Sortable` concerns, with unit tests.
- [ ] M1.13 Shared admin React components: DataTable (server pagination, filters, sort), FormField set, slug input, rich text editor, sortable list.
- [ ] M1.14 Security baseline: security headers middleware, named rate limiters, the Turnstile rule and session timeout.

## M2 — Media module (shared)

Status: **Not started** · PRD: A-1, B-4–B-6, P-2, §7 documents and uploads

- [ ] M2.1 Write `config/media.php`: the public and private disks from env, conversions, TTLs and the collection registry skeleton.
- [ ] M2.2 Define the filesystem disks `media-{public,private}-{local,s3}` and verify the switch works with an env change only.
- [ ] M2.3 Add the `media` migration and model, the `MediaVisibility` and `MediaStatus` enums, and the `HasMedia` concern.
- [ ] M2.4 Add `MediaCollectionRegistry` (config plus admin-setting overrides, with hard ceilings), with unit tests.
- [ ] M2.5 `MediaService::upload`: MIME sniffing, whitelist check, randomized path, checksum, pending status and expiry.
- [ ] M2.6 `ImageProcessor` and `ProcessMediaJob`: EXIF strip and re-encode, orientation fix, HEIC→JPEG, WebP conversions.
- [ ] M2.7 `MediaService` sync, attach, detach, reorder, delete and cover handling, plus the `MaxMediaForCollection` and `PendingOrOwnedMedia` rules.
- [ ] M2.8 Add `MediaUrlGenerator`: public redirect or stream, private temporary URLs (S3) and signed stream route (local).
- [ ] M2.9 Add `MediaPolicy`, which delegates to the owner policy and supports booking-token uploaders. Permissions `media.upload` and `media.view-private`.
- [ ] M2.10 Add `MediaController` with the single endpoint set: `POST /media`, `GET /media/{uuid}/{conversion?}`, `GET /media/{uuid}/download`, `PATCH /media/{uuid}`, `DELETE /media/{uuid}` and `POST /media/reorder`.
- [ ] M2.11 Add `MediaResource` (uuid, urls per conversion, name, size, mime, dimensions, alt) and the TS type.
- [ ] M2.12 Log every private-media view and download to the activity log.
- [ ] M2.13 Add the `media:cleanup` command (expired pending files, soft-deleted purge) and schedule it.
- [ ] M2.14 Add the `media:migrate-disk {from} {to} --collection=` command, with a dry-run flag.
- [ ] M2.15 React components: `<MediaUploader>` (progress, retry, drag-reorder, cover, client compression, `X-Booking-Token` support), `<MediaImage>` and `<SecureDocumentViewer>`.
- [ ] M2.16 Tests: upload per collection type, rejected MIME and size, private access denied or allowed, token uploads, disk switch with `Storage::fake`, cleanup.

## M3 — Metadata fields

Status: **Not started**

- [ ] M3.1 Add the `metadata_fields` migration and model, and the `MetadataFieldType` enum.
- [ ] M3.2 Add the `HasMetadata` concern (`AsArrayObject` cast, unknown keys stripped on save).
- [ ] M3.3 Add `MetadataService`: `rulesFor(entity)`, `publicView(model)` and `cardView(model)`.
- [ ] M3.4 Metadata fields admin (CRUD per entity, reorder), with permissions `metadata-fields.*`.
- [ ] M3.5 Add the `<MetadataFields entity=...>` dynamic form renderer and a display component.
- [ ] M3.6 Seed the default car metadata fields: fuel_type, colour, zero_to_hundred_s, top_speed_kmh, drive, luggage_bags, features.
- [ ] M3.7 Tests: validation from definitions, public versus hidden exposure, stripping of unknown keys.

## M4 — Catalog

Status: **Not started** · PRD: A-1, A-2, F-1–F-5

- [ ] M4.1 Brands: migration, model and metadata, `brand.logo` media, permissions `brands.*`, service, requests, resources, controller and pages.
- [ ] M4.2 Brand marquee ordering and the `show_in_marquee` toggle in the admin list (drag sort).
- [ ] M4.3 Car types: full module (`car-types.*`).
- [ ] M4.4 Cars: migration and model, covering all spec columns, money in minor units, `mileage_limit_km` nullable, damage excess, publish flags and metadata.
- [ ] M4.5 Car permissions `cars.view|create|update|delete|view-value|publish`, and `CarPolicy`.
- [ ] M4.6 `CarService`: create and update with gallery sync, publish and unpublish, toggle availability and toggle featured.
- [ ] M4.7 Car form requests, including gallery UUIDs (max from the collection), cover, metadata rules and major→minor money conversion.
- [ ] M4.8 Car resources: `Admin\CarResource` (value only with `cars.view-value`), `Public\CarCardResource` and `Public\CarDetailResource`.
- [ ] M4.9 Car admin pages: list (filters, availability and featured toggles) and an edit form with tabs for specs, pricing, gallery and metadata.
- [ ] M4.10 Car blackouts (maintenance periods): CRUD nested under the car (`availability.manage`).
- [ ] M4.11 Factories plus a demo seeder with sample cars for staging.
- [ ] M4.12 Tests: car value never present in public resources, the 6-photo cap, permission matrix per role.

## M5 — Content CMS and seed pack

Status: **Not started** · PRD: A-2, H-5, O-1, §11 · **Gate G1:** the client adds a car with 6 photos and edits a policy on staging.

- [ ] M5.1 Services module (`services.*`): cta_type (book, whatsapp, link), image media, metadata.
- [ ] M5.2 Pages and page sections (`pages.*`): section types hero, rich_text, cards, stats, cta and faq, plus a section editor with reorder and visibility.
- [ ] M5.3 Home page sections bound to `HomeSettings` (hero media, CTAs, featured count, `marquee_position`, trust figures).
- [ ] M5.4 Policies and versions (`policies.*`, `policies.publish`): draft, publish, diff against the previous version, and the `show_on_home` summary.
- [ ] M5.5 Policy bundles: publishing creates a bundle snapshot of every currently published version, which is what renters accept.
- [ ] M5.6 Payment methods admin (`payment-methods.*`): rich instructions, metadata (paybill, till, bank, card link), flags.
- [ ] M5.7 Contacts, socials, location and WhatsApp templates edited through `GeneralSettings`.
- [ ] M5.8 Contact messages inbox (`contact-messages.*`) with a status workflow.
- [ ] M5.9 Seed pack JSON under `database/seeders/data/`: site_settings, policies, catalog, pricing.
- [ ] M5.10 `CmsSeeder`: upserts by slug or code, creates a new policy version only when the body changes, and leaves `[CONFIRM]` versions unpublished.
- [ ] M5.11 Mark `TODO` values (paybill, bank, card link, hours, legal name) and add an admin dashboard widget that lists the unfilled TODO settings.
- [ ] M5.12 Tests: seeder idempotency (run twice, same counts), policy versioning, bundle creation.
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
- [ ] M7.8 Car card component: cover, brand badge, name, price per day in the selected currency, specs, `show_on_card` metadata, mileage, and View, Book and WhatsApp buttons.
- [ ] M7.9 Fleet page: filters for brand, type, transmission and seats, a price sort, query-string state and pagination.
- [ ] M7.10 Brand and car type listing pages, plus filtered views reusing the fleet page.
- [ ] M7.11 Car detail: gallery with lightbox and swipe (6 photos, keyboard accessible), info block (F-4), public metadata, damage excess, and a Book this unit CTA.
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

| Date | Step | Change | Notes |
| --- | --- | --- | --- |
| 2026-10-17 | — | Plan created | TECHNICAL_PLAN.md and MILESTONES.md drafted from the PRD of 16 Oct 2026 |

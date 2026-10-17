# Baines Executives — PRD, Architecture & Delivery Plan

Oct 16, 2026 · @vyleBistro

**CONFIDENTIAL — Bistro Tech Developers / Baines Executives**

## 1. Product summary

Baines Executives gets a premium public website plus an online booking engine that takes a renter from browsing the fleet to a paid 25% reservation and a confirmation voucher, all managed from one admin CMS.

| Item                  | Detail                                                                                          |
| --------------------- | ----------------------------------------------------------------------------------------------- |
| **Client**            | Baines Executives — car rental and safari company, Nairobi, Kenya                               |
| **Product**           | Marketing website, fleet catalogue, booking and pricing engine, payment verification, admin CMS |
| **Stack**             | Laravel 12, Inertia.js 2 + React 19 (TypeScript), MySQL 8                                       |
| **Design reference**  | [luxurysupercarsdubai.com](https://luxurysupercarsdubai.com/)                                   |
| **Physical location** | Horizon Flats, Dennis Pritt Road, Kilimani, Nairobi                                             |
| **Phone / WhatsApp**  | +254 724 422 051                                                                                |
| **Email**             | bainesexecutives@gmail.com                                                                      |
| **Socials**           | Instagram and TikTok: @bainesexecutives                                                         |
| **Source**            | Client brief (PDF), received 16 Oct 2026                                                        |

The client's policies and contacts are extracted into seed files (see section 11) so they load straight into the CMS instead of being hard-coded.

## 2. Problem, goals and scope

Today bookings run over calls and WhatsApp, with prices, deposits and documents worked out by hand; the site must turn that into a self-serve flow that collects KYC documents and a 25% reservation before staff get involved.

**Goals**

1. Present the fleet and brand at a luxury standard comparable to the Dubai reference.
2. Let a renter pick a car, see an exact quote in five currencies and submit a complete booking with documents.
3. Collect the 25% reservation by cash, M-Pesa, bank transfer or card, verified by staff from the pasted transaction message.
4. Send a booking voucher by email and WhatsApp once payment is confirmed.
5. Let staff manage cars (6 photos each), brands, types, services, policies and contacts without a developer.

**Non-goals for v1**

- Automated payment capture (M-Pesa STK push, card gateway) — manual verification first, gateways in phase 2.
- Safari booking engine — safaris are presented as services with WhatsApp enquiry.
- Customer accounts and "Manage booking" self-service — changes go through phone/WhatsApp as the policy already states.
- Online signing of the rental agreement — signed at handover.

**Success metrics (first 90 days after launch)**

| Metric                                                | Target                                         |
| ----------------------------------------------------- | ---------------------------------------------- |
| Bookings submitted online with all documents attached | ≥ 70% of all bookings                          |
| Median time from payment submission to confirmation   | < 2 business hours                             |
| Quote disputes at handover                            | 0 (quote is server-calculated and snapshotted) |
| Mobile Lighthouse performance, home and fleet         | ≥ 85                                           |
| WhatsApp enquiries tagged with car name               | 100% (prefilled messages)                      |

## 3. Users and roles

Four roles cover v1: one public persona and three staff roles with scoped admin access.

| Role               | Who                                                       | Needs                                                                                               |
| ------------------ | --------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| **Renter**         | Tourists, corporates, Nairobi residents; mostly on mobile | Browse fleet, see photos and specs, get a clear quote in their currency, book, pay, receive voucher |
| **Super admin**    | Baines owner                                              | Everything, including car values, pricing rules, FX margin, user management                         |
| **Operations**     | Booking desk staff                                        | View and verify bookings and payments, confirm, send vouchers, record handover and return           |
| **Content editor** | Marketing                                                 | Cars, photos, brands, types, services, policies, homepage content, socials                          |

## 4. Functional requirements

Requirements are grouped by page; IDs are referenced in the delivery plan and UAT scripts.

### 4.1 Global layout

| ID  | Requirement                                                                                                                                                                                                                                          |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| G-1 | Top bar: phone (tap-to-call), Instagram and TikTok icons, currency switcher (KES, USD, GBP, EUR, plus the fifth currency — see open question Q1).                                                                                                    |
| G-2 | Header: logo, nav — Home, About Us, Car Types, Car Brands, Our Fleet, Services, Rental Policy, Contact Us.                                                                                                                                           |
| G-3 | Floating WhatsApp and Call buttons on every page, bottom-right on mobile.                                                                                                                                                                            |
| G-4 | WhatsApp links use `wa.me/254724422051` with a prefilled message; car-specific buttons name the car.                                                                                                                                                 |
| G-5 | Footer: about blurb, brand links, car-type links, useful links, contacts, physical location with embedded map (Horizon Flats, Dennis Pritt Road, Kilimani), Instagram and TikTok, accepted payment icons, links to Rental Policy and Privacy Policy. |
| G-6 | Selected currency persists across pages (cookie) and drives every displayed price.                                                                                                                                                                   |

### 4.2 Home page

| ID  | Requirement                                                                                                                                                                                                              |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| H-1 | Full-screen hero with a background image (or short video) of high-end vehicles such as a G-Wagon and Range Rover, dark overlay, headline, subline.                                                                       |
| H-2 | Two hero CTAs: **Rent a Car** → Fleet page; **Enquire** → WhatsApp chat.                                                                                                                                                 |
| H-3 | Sections in order: About Us, Featured Fleet (car cards), Car Types, Car Brands, Services (car rental, chauffeur, airport transfer, safaris), Rental Policy highlights, Why Choose Us, Contact Us (Call + WhatsApp CTAs). |
| H-4 | Car-brand logo marquee with about 30 logos, continuously sliding, pause on hover, each logo links to the filtered fleet. Placed at the bottom of the home page, above the footer.                                        |
| H-5 | All section copy is editable in the CMS.                                                                                                                                                                                 |

### 4.3 Fleet

| ID  | Requirement                                                                                                                                                                                             |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| F-1 | Grid of car cards; filters for brand, type, transmission, seats; sort by price. Brand and type pages are filtered views of this page (`/fleet?brand=toyota`, `/car-types/suv`).                         |
| F-2 | Card: cover photo, brand logo, car name, price per day in selected currency, key specs, **View** and **Book** buttons, WhatsApp shortcut.                                                               |
| F-3 | Car detail page: gallery of up to **6 photos** with lightbox and swipe.                                                                                                                                 |
| F-4 | Car information block: car name, engine type, engine capacity, transmission, number of doors, number of seats, rental rate per day, mileage limit per day (default **300 km/day**, shown on every car). |
| F-5 | **Book this unit** button under the info block → booking page for that car.                                                                                                                             |

### 4.4 Booking form

| ID  | Requirement                                                                                                                                                                                                         |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| B-1 | Multi-step form: (1) trip and extras, (2) driver details and documents, (3) review quote, (4) choose payment method, (5) submit transaction message. Progress saved server-side so a refresh does not lose uploads. |
| B-2 | Trip: pick-up date and time, return date and time, pick-up point, return point, delivery to a location (optional, KES 2,000), chauffeur required (yes/no), flight number (optional).                                |
| B-3 | Driver: surname, first name, second name; date of birth; email; phone/WhatsApp number.                                                                                                                              |
| B-4 | Identity: ID or passport number; ID requires front **and** back photo, passport requires the photo page.                                                                                                            |
| B-5 | KRA PIN number plus KRA PIN certificate upload (see Q6 for foreign renters).                                                                                                                                        |
| B-6 | Driving licence number, expiry date, licence photo upload. Expiry must be after the return date; licence held ≥ 3 years (declared checkbox).                                                                        |
| B-7 | Age is computed from date of birth at pick-up date and drives the deposit band; under the minimum age the booking is blocked with a message.                                                                        |
| B-8 | Acceptance checkbox for Rental Policy and Terms, with link; stored with timestamp and policy version.                                                                                                               |

### 4.5 Quote calculator

| ID  | Requirement                                                                                                                                                                                                                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Q-1 | Live calculator on the booking page showing: number of days, daily rate, rental subtotal, chauffeur, delivery, **total rental**, **25% reservation due now**, **75% balance due at handover**, **security deposit due at handover**. |
| Q-2 | Displays all amounts in the selected currency, with a toggle to view the other four.                                                                                                                                                 |
| Q-3 | The browser calculation is a preview; the server recalculates on submit and the server figure is what is stored and charged.                                                                                                         |
| Q-4 | Quote, FX rates and policy version are snapshotted on the booking so later rate or price changes never alter it.                                                                                                                     |

### 4.6 Payment and confirmation

| ID  | Requirement                                                                                                                                                                                                                                      |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| P-1 | Methods: Cash, M-Pesa, Bank transfer, Card (Visa, Mastercard). Payment instructions per method (paybill/till, bank details, card payment link) are shown only after the renter chooses to continue, and are CMS-managed.                         |
| P-2 | Renter pastes the transaction message (e.g. M-Pesa SMS) and may attach a screenshot; booking moves to _Payment submitted_.                                                                                                                       |
| P-3 | Cash: booking moves to _Awaiting cash_ and staff confirm on receipt.                                                                                                                                                                             |
| P-4 | Staff verify the payment in admin, record the reference and amount, then confirm or reject with a reason.                                                                                                                                        |
| P-5 | On confirmation the system sends a booking voucher by email (PDF attached) and by WhatsApp.                                                                                                                                                      |
| P-6 | Voucher shows: booking reference, client full name, car (e.g. Toyota Prado), number of days, total rental, deposit (reservation) paid, balance due, security deposit due at handover, pick-up date, point and time, return date, point and time. |
| P-7 | Renter receives an acknowledgement email immediately on submission; staff receive a new-booking alert (email + WhatsApp to the main number).                                                                                                     |

### 4.7 Other public pages

| ID  | Requirement                                                                                                                                                                                                           |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| O-1 | About Us, Services (incl. safaris with WhatsApp enquiry), Car Types, Car Brands, Contact Us (form + map + call/WhatsApp), Rental Policy (all policy sections from section 11, with an in-page index), Privacy Policy. |

### 4.8 Admin CMS

| ID  | Requirement                                                                                                                                                                                                                 |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A-1 | Cars: CRUD, up to 6 photos with drag-to-reorder and cover selection, specs, daily rate (KES), car value (KES, admin-only, never public), mileage limit, availability toggle, featured flag.                                 |
| A-2 | Brands (name, logo, order, show in marquee), Car types, Services, Homepage sections, Policies (rich text, versioned), Contacts and socials, Payment method instructions.                                                    |
| A-3 | Pricing settings: deposit percentages by age band, reservation %, chauffeur rate, delivery fee, mileage default, FX margin (KES 3).                                                                                         |
| A-4 | FX: daily automatic rate pull, manual override, history.                                                                                                                                                                    |
| A-5 | Bookings: list with status filters, detail view with documents (secure viewer), quote snapshot, payment submissions, verify/reject, resend voucher, record handover (security deposit and 75% balance received) and return. |
| A-6 | Availability calendar per car; overlapping confirmed bookings are blocked.                                                                                                                                                  |
| A-7 | Audit log of every status change and document view.                                                                                                                                                                         |

## 5. Business rules and pricing

All prices are held in KES; the renter pays 25% of the total rental to reserve, and the 75% balance plus a refundable security deposit at handover. Every value below is a CMS setting, not code.

| Rule                            | Value                              | Notes                                                                                                            |
| ------------------------------- | ---------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| **Base currency**               | KES                                | Car daily rates and delivery fee are entered in KES.                                                             |
| **Billable day**                | One 12-hour daytime block          | Days = calendar dates from pick-up to return, inclusive (see Q3). Daytime window configurable, e.g. 07:00–19:00. |
| **Chauffeur**                   | USD 50 per day, tax inclusive      | Optional. Converted to KES at the market rate. Per-day basis assumed (Q4).                                       |
| **Delivery / pick-up**          | KES 2,000                          | Optional, flat per booking.                                                                                      |
| **Reservation**                 | 25% of total rental                | Paid online to reserve. Rounded up to the whole shilling.                                                        |
| **Balance**                     | 75% of total rental                | Paid at handover.                                                                                                |
| **Security deposit, age 24–70** | 2% of car value                    | Paid at handover, 100% refundable if the car returns in the same condition.                                      |
| **Security deposit, under 24**  | 3% of car value                    | Minimum age per policy is 23, so this band is age 23 only (Q2).                                                  |
| **Age limits**                  | Min 23, max 70                     | Applies to main and additional drivers.                                                                          |
| **Mileage**                     | 300 km per day, shown on every car | Conflicts with policy text (250 km/day, unlimited for 7+ days) — Q5.                                             |
| **Additional driver**           | USD 50 per day, tax inclusive      | Payable at counter; shown as information only in v1.                                                             |
| **FX display rate**             | Market rate − KES 3                | Foreign price = KES amount ÷ (market KES per unit − 3).                                                          |

**Formulas**

```latex
\text{total} = d \cdot r + d \cdot c_{\text{USD}} \cdot m_{\text{USD}} + f
```

where d is billable days, r is the daily rate in KES, c is the chauffeur rate in USD (0 if not selected), m is the USD market rate and f is the delivery fee (0 if not selected).

```latex
\text{reservation} = \lceil 0.25 \cdot \text{total} \rceil,\quad \text{balance} = \text{total} - \text{reservation},\quad \text{deposit} = p_{\text{age}} \cdot V
```

where p is 2% or 3% by age band and V is the car value. Display in currency X = KES amount ÷ (m\_X − 3).

**Worked example (illustrative figures only)** — Toyota Prado at KES 15,000/day, car value KES 8,000,000, 3 days, chauffeur and delivery selected, driver aged 35, USD market rate 129.00:

| Line                              | KES        | USD at 126.00 |
| --------------------------------- | ---------- | ------------- |
| Rental, 3 × 15,000                | 45,000     | 357.14        |
| Chauffeur, 3 × USD 50 at 129.00   | 19,350     | 153.57        |
| Delivery                          | 2,000      | 15.87         |
| **Total rental**                  | **66,350** | **526.59**    |
| **Reservation due now (25%)**     | **16,588** | **131.65**    |
| Balance at handover (75%)         | 49,762     | 394.94        |
| Security deposit at handover (2%) | 160,000    | 1,269.84      |

**Booking lifecycle**

| Status              | Entered when                                | Next                                      |
| ------------------- | ------------------------------------------- | ----------------------------------------- |
| Draft               | Form started, uploads in progress           | Submitted, expired after 24 h             |
| Awaiting payment    | Form submitted, quote snapshotted           | Payment submitted, Awaiting cash, Expired |
| Payment submitted   | Renter pasted transaction message           | Reserved, Payment rejected                |
| Awaiting cash       | Renter chose cash                           | Reserved, Expired                         |
| Payment rejected    | Staff could not match payment               | Payment submitted (retry), Cancelled      |
| Reserved            | Staff confirmed 25%; voucher sent           | Active, Cancelled, No-show                |
| Active              | Handover done; balance and deposit received | Completed                                 |
| Completed           | Car returned, deposit refunded or settled   | —                                         |
| Cancelled / No-show | Per cancellation policy                     | —                                         |

A car is held against overlapping dates from _Awaiting payment_ (for a configurable hold, default 2 hours) and blocked firmly from _Reserved_.

## 6. Design direction

The public site follows the structure and tone of [luxurysupercarsdubai.com](https://luxurysupercarsdubai.com/) — dark, cinematic and photo-led — rebuilt with Baines' own brand, imagery and Kenyan context rather than copied assets.

| Element           | Reference pattern                                                                         | Baines adaptation                                                                                           |
| ----------------- | ----------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| **Top bar**       | Socials, phone, currency switcher (AED, USD, GBP, AUD, EUR)                               | Instagram, TikTok, +254 724 422 051, switcher KES / USD / GBP / EUR / fifth currency                        |
| **Hero**          | Full-bleed video, "Looking to rent a luxury car…" headline, Rent A Car + Contact Us       | G-Wagon / Range Rover image or loop, Rent a Car + Enquire (WhatsApp)                                        |
| **Brand strip**   | Logo marquee directly under the hero                                                      | \~30 logos; client wants it at the bottom of the home page (Q8 offers both)                                 |
| **Category rows** | Sports, Convertible, Luxury, SUV rows of 4 cards with "View all"                          | Rows per car type: SUV, 4x4 Safari, Luxury Sedan, Executive, Economy, Van                                   |
| **Car card**      | Photo, brand badge, name, price/day, engine, 0–100, doors, seats, View Details + WhatsApp | Photo, brand badge, name, price/day, engine, transmission, doors, seats, 300 km/day, View + Book + WhatsApp |
| **Trust strip**   | Stats: brands, Google rating, reviews, 24/7 support                                       | Brands count, years in business, safaris done, 24/7 support (client to supply real figures)                 |
| **Requirements**  | Numbered 01–03 cards: age, licence, delivery                                              | Age 23–70, licence held 3+ years, ID/passport + KRA PIN, delivery KES 2,000                                 |
| **Why choose us** | Numbered 01–04 cards beside a video                                                       | Same pattern with Baines selling points                                                                     |
| **Contact block** | Phone cards, address, enquiry form with brand and dates                                   | Call, WhatsApp, email, Kilimani address with map, enquiry form                                              |
| **Footer**        | Brand, rent, useful links, contacts, payment icons                                        | Same columns; Visa, Mastercard, M-Pesa, bank, cash icons                                                    |

**Visual tokens (proposed)**

- Palette: near-black background (#0B0B0C), charcoal surfaces (#16171A), warm gold accent (#C8A45A) for CTAs and prices, white text, muted grey secondary.
- Type: a high-contrast display face for headings (e.g. _Playfair Display_ or _Cormorant_) with a clean sans for body (e.g. _Inter_).
- Motion: slow hero zoom, card hover lift, marquee scroll; respect `prefers-reduced-motion`.
- Imagery: real photos of the Baines fleet; stock only as hero placeholder until the client shoot.

Do not reuse the reference site's photos, logos, video or copy; brand logos in the marquee must be sourced from official brand-asset pages and used only to indicate brands in the fleet.

## 7. Non-functional requirements

The booking flow handles identity documents of foreign and Kenyan nationals, so data protection drives most of these requirements.

| Area                 | Requirement                                                                                                                                                                       |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Data protection**  | Comply with the Kenya Data Protection Act 2019; Baines registers with the ODPC as a data controller. Privacy notice and consent captured at booking.                              |
| **Document storage** | ID, passport, licence and KRA files on a private S3-compatible bucket, never the public disk; served only through short-lived signed URLs to authorised staff; every view logged. |
| **Retention**        | KYC files purged a configurable period after booking completion (proposed 12 months), unless held for a claim or fine.                                                            |
| **Uploads**          | JPG, PNG, HEIC, PDF; max 8 MB each; MIME sniffing, image re-encoding to strip EXIF; client-side compression on mobile.                                                            |
| **Security**         | HTTPS only, CSRF, rate-limited booking and contact endpoints, Cloudflare Turnstile on forms, 2FA for admin, role-based permissions, signed booking links for renters.             |
| **Money integrity**  | Integer minor units (cents) in KES; quote recalculated server-side; immutable quote and FX snapshot per booking.                                                                  |
| **Performance**      | Inertia SSR for public pages; responsive images (WebP/AVIF) via conversions; LCP < 2.5 s on 4G; lazy-loaded galleries and marquee.                                                |
| **SEO**              | SSR HTML, per-page meta and Open Graph, sitemap.xml, JSON-LD (`AutoRental`, `Car`, `LocalBusiness`), clean URLs (`/fleet/toyota-land-cruiser-v8`).                                |
| **Accessibility**    | WCAG 2.1 AA: contrast on dark theme, keyboard-navigable gallery and forms, alt text on all images.                                                                                |
| **Availability**     | 99.5% monthly; daily DB and media backups with 30-day retention; restore tested before launch.                                                                                    |
| **Observability**    | Error tracking (Sentry or equivalent), queue monitoring (Horizon), uptime check on booking endpoint.                                                                              |

## 8. Architecture

A single Laravel modular monolith serves both the public site and the admin through Inertia React, with SSR on public routes, MySQL for data, Redis for queues and cache, and private S3-compatible storage for media and KYC files.

&#91;embedded content: system architecture · 1 app, 6 modules, 4 data stores, 3 external services\]

Booking is the core module: it pulls quotes from Pricing, documents into the private bucket, and hands confirmed bookings to Notifications.

### 8.1 Stack and packages

| Concern                     | Choice                                                                                                                                                     |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Framework**               | Laravel 12, PHP 8.3                                                                                                                                        |
| **Frontend**                | Inertia.js 2 + React 19 + TypeScript, Vite, Tailwind CSS 4, shadcn/ui for admin, Embla Carousel for gallery and marquee                                    |
| **SSR**                     | Inertia SSR (Node sidecar) for public routes                                                                                                               |
| **Database**                | MySQL 8, InnoDB, utf8mb4                                                                                                                                   |
| **Queue / cache / session** | Redis + Laravel Horizon                                                                                                                                    |
| **Media**                   | spatie/laravel-medialibrary (collections: `gallery` max 6, `brand_logo`, `kyc_*`), image conversions to WebP                                               |
| **Settings**                | spatie/laravel-settings (pricing, contacts, socials, FX margin)                                                                                            |
| **Roles**                   | spatie/laravel-permission                                                                                                                                  |
| **Audit**                   | spatie/laravel-activitylog                                                                                                                                 |
| **State machine**           | spatie/laravel-model-states for booking status                                                                                                             |
| **PDF voucher**             | spatie/laravel-pdf (Browsershot) or barryvdh/laravel-dompdf                                                                                                |
| **Typed routes / DTOs**     | Ziggy for routes; spatie/laravel-data + typescript-transformer so React types match PHP DTOs                                                               |
| **Email**                   | Laravel Mail via SMTP provider (Resend, Mailgun or Brevo) from a Baines domain address                                                                     |
| **WhatsApp**                | v1: click-to-chat links with prefilled text and staff "Send on WhatsApp" button; v1.1: WhatsApp Cloud API approved template for vouchers                   |
| **FX rates**                | `FxRateProvider` interface; adapter for a paid API (e.g. Open Exchange Rates or ExchangeRate-API) pulled hourly by the scheduler; manual override in admin |
| **Testing**                 | Pest (unit + feature), Laravel Dusk or Playwright for the booking flow                                                                                     |

### 8.2 Modules

Code is organised by domain under `app/Domain/*`, each with Models, Actions, Data (DTOs), Policies and Http controllers; React pages mirror it under `resources/js/Pages/{Public,Admin}`.

| Module            | Owns                                                                        | Key classes                                                                              |
| ----------------- | --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| **Catalog**       | Brands, car types, cars, specs, photos                                      | `Car`, `Brand`, `CarType`, `PublishCarAction`                                            |
| **Content**       | Pages, homepage sections, services, policies (versioned), contacts, socials | `Page`, `PageSection`, `Policy`, `PolicyVersion`, `SiteSettings`                         |
| **Pricing**       | Pricing rules, quote engine, currencies, FX rates                           | `QuoteCalculator` (pure, unit-tested), `FxRateService`, `PricingSettings`                |
| **Booking**       | Bookings, drivers, documents, availability holds, lifecycle                 | `Booking`, `BookingDriver`, `CreateBookingAction`, `AvailabilityService`, `BookingState` |
| **Payments**      | Payment methods, instructions, payment submissions, verification            | `PaymentMethod`, `PaymentSubmission`, `VerifyPaymentAction`                              |
| **Notifications** | Acknowledgement, staff alert, voucher (email, WhatsApp)                     | `BookingReceived`, `BookingConfirmed`, `VoucherPdf`                                      |
| **Admin / IAM**   | Staff users, roles, 2FA, audit log                                          | `User`, policies, activity log                                                           |

The quote engine is the one piece of logic that runs in two places: `QuoteCalculator` in PHP is authoritative, and a TypeScript port in `resources/js/lib/quote.ts` gives the live preview. Both are tested against the same JSON fixture of cases so they cannot drift.

### 8.3 Data model

| Table                           | Key columns                                                                                                                                                                                                                                                                                                                                                                              |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **brands**                      | id, name, slug, logo (media), sort\_order, show\_in\_marquee, is\_active                                                                                                                                                                                                                                                                                                                 |
| **car\_types**                  | id, name, slug, description, sort\_order                                                                                                                                                                                                                                                                                                                                                 |
| **cars**                        | id, brand\_id, car\_type\_id, name, slug, model\_year, engine\_type, engine\_capacity\_cc, transmission, doors, seats, daily\_rate\_kes, car\_value\_kes, mileage\_limit\_km (default 300), is\_featured, is\_available, description                                                                                                                                                     |
| **media**                       | spatie media table; `gallery` collection capped at 6 per car                                                                                                                                                                                                                                                                                                                             |
| **services**                    | id, title, slug, summary, body, icon, cta\_type (book / whatsapp), sort\_order                                                                                                                                                                                                                                                                                                           |
| **pages / page\_sections**      | id, slug, title, meta; section key, content JSON, sort\_order                                                                                                                                                                                                                                                                                                                            |
| **policies / policy\_versions** | id, slug, title, sort\_order; version, body (markdown), published\_at                                                                                                                                                                                                                                                                                                                    |
| **settings**                    | spatie settings: contacts, socials, location, pricing, FX margin                                                                                                                                                                                                                                                                                                                         |
| **currencies**                  | code, name, symbol, decimals, is\_active, sort\_order                                                                                                                                                                                                                                                                                                                                    |
| **fx\_rates**                   | currency\_code, market\_rate\_kes, margin\_kes, effective\_rate\_kes, source, fetched\_at, is\_override                                                                                                                                                                                                                                                                                  |
| **bookings**                    | id, reference (BE-2026-000123), car\_id, status, pickup\_at, return\_at, pickup\_point, return\_point, delivery\_required, delivery\_address, chauffeur\_required, flight\_number, billable\_days, quote JSON snapshot, fx JSON snapshot, total\_kes, reservation\_kes, balance\_kes, security\_deposit\_kes, display\_currency, policy\_version\_id, accepted\_terms\_at, access\_token |
| **booking\_drivers**            | booking\_id, surname, first\_name, second\_name, dob, email, phone, id\_type (national\_id / passport), id\_number (encrypted), kra\_pin (encrypted), licence\_number (encrypted), licence\_expiry, is\_main                                                                                                                                                                             |
| **booking\_documents**          | via media: `id_front`, `id_back`, `passport`, `kra_certificate`, `licence`, `payment_proof` on a private disk                                                                                                                                                                                                                                                                            |
| **payment\_methods**            | code (cash, mpesa, bank, card), name, instructions (rich text), is\_active                                                                                                                                                                                                                                                                                                               |
| **payment\_submissions**        | booking\_id, method, amount\_kes, transaction\_message, reference, status, verified\_by, verified\_at, rejection\_reason                                                                                                                                                                                                                                                                 |
| **availability\_holds**         | car\_id, booking\_id, starts\_at, ends\_at, expires\_at                                                                                                                                                                                                                                                                                                                                  |
| **activity\_log**               | spatie activity log                                                                                                                                                                                                                                                                                                                                                                      |

Sensitive identifiers use Laravel encrypted casts; a separate `id_number_hash` column allows lookups without decrypting.

### 8.4 Request flow for a booking

1. `GET /fleet/{car}/book` renders the booking page with the car, settings and current FX rates as props.
2. Step data posts to `POST /bookings/draft`, which creates a Draft and returns a signed token; uploads go to `POST /bookings/{token}/documents` one file at a time.
3. `POST /bookings/{token}/submit` validates (age, licence expiry, documents present), recalculates the quote, checks availability in a DB transaction with a row lock on the car, snapshots quote/FX/policy and moves to Awaiting payment.
4. The renter picks a method; `POST /bookings/{token}/payments` stores the transaction message → Payment submitted; staff are alerted on a queued job.
5. Staff verify in admin → `VerifyPaymentAction` moves to Reserved, firm-blocks the dates, queues the voucher email and WhatsApp.

### 8.5 Deployment

| Component        | Proposal                                                                                                                           |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| **Runtime**      | Docker images: php-fpm + nginx, Inertia SSR node, Horizon worker, scheduler                                                        |
| **Hosting**      | A single VPS (4 vCPU, 8 GB) is enough at launch; the same images run on the existing k3s / Argo CD setup if Baines is hosted there |
| **Data**         | Managed or self-hosted MySQL 8 and Redis; S3-compatible bucket (MinIO or cloud) for media, private bucket for KYC                  |
| **Edge**         | Cloudflare DNS, CDN for public media, Turnstile, WAF                                                                               |
| **CI/CD**        | GitHub Actions: Pest, Pint, ESLint, type-check, build, push image, deploy to staging then production                               |
| **Environments** | Local (Sail), staging (client UAT), production                                                                                     |

## 9. Delivery plan

Launch takes about 10 weeks in six phases, each closing on a stop gate the client signs before the next starts; payment-gateway automation follows as phase 2 after launch.

&#91;embedded content: delivery roadmap · 6 phases, 6 gates, 10 weeks\]

Phases overlap by a week where design and build hand over; no phase starts until the previous gate is signed.

1. **Phase 0 — Discovery and sign-off (week 1).** Resolve the open questions in section 10, collect fleet list with values and photos, brand assets, payment details. _Gate G0: signed PRD and pricing rules._
2. **Phase 1 — Foundations (weeks 2–3).** Repo, CI/CD, staging, auth with 2FA and roles, settings, media library, Catalog and Content modules, CMS seeders loaded from section 11. _Gate G1: client can add a car with 6 photos and edit a policy on staging._
3. **Phase 2 — Public site (weeks 3–5).** Design system, home page with hero, sections and brand marquee, fleet grid and filters, car detail with gallery, brand and type pages, policy, about, services, contact, SSR and SEO. _Gate G2: design and content sign-off on staging, mobile and desktop._
4. **Phase 3 — Booking and pricing (weeks 5–7).** Multi-step form, secure uploads, `QuoteCalculator` in PHP and TS with shared fixtures, FX provider and margin, availability holds, payment method instructions, transaction-message submission. _Gate G3: 20 scripted quote cases match the client's own spreadsheet to the shilling._
5. **Phase 4 — Operations (weeks 7–9).** Admin bookings desk, document viewer, payment verification, voucher PDF, emails, WhatsApp hand-off, handover and return recording, audit log, retention job. _Gate G4: end-to-end booking from submission to voucher in under 10 minutes of staff time._
6. **Phase 5 — UAT and launch (weeks 9–10).** Client UAT, content load, penetration-style review of upload and document endpoints, backups and restore test, DNS cut-over, ODPC registration confirmed. _Gate G5: go-live approval._

**After launch (phase 2 scope):** M-Pesa Daraja STK push with automatic reconciliation, card gateway (e.g. Pesapal or Flutterwave), WhatsApp Cloud API templates, renter "Manage booking" page, safari package booking.

**Estimate assumptions:** one senior full-stack developer plus part-time designer and QA; client delivers content and photos by end of week 3; open questions closed at G0.

## 10. Open questions and conflicts

The brief contradicts its own rental policy in several places; each needs a client answer at gate G0, and the PRD uses the working assumption until then.

| #   | Question                                                                       | Conflict in the brief                                                   | Working assumption                                                                               |
| --- | ------------------------------------------------------------------------------ | ----------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Q1  | Is "UAD" the UAE dirham (AED) or Australian dollar (AUD)?                      | Listed as "uad"; the reference site offers both                         | Currency table supports both; AED enabled until confirmed                                        |
| Q2  | What is the minimum age, and who pays 3%?                                      | Booking rules: 2% for 24–70, 3% below; policy: minimum 23               | Allowed 23–70; 3% applies to age 23 only                                                         |
| Q3  | How are days counted for "12 hours per day, daytime only"?                     | No rule for partial days or overnight returns                           | Billable days = calendar dates from pick-up to return inclusive; one day = 12-hour daytime block |
| Q4  | Is the chauffeur USD 50 per day or per booking?                                | "Paid 50 dollars inclusive of tax"                                      | Per day                                                                                          |
| Q5  | Mileage: 300 km/day, or 250 km/day with unlimited for 7+ days? Excess km rate? | Brief says 300 km on every car; policy says 250 km and unlimited weekly | 300 km/day shown on every car; excess rate to be set by client                                   |
| Q6  | Is KRA PIN required for foreign tourists?                                      | Mandatory in brief; most tourists have none                             | Required for Kenyan residents; optional for passport holders                                     |
| Q7  | Is a credit card mandatory at pick-up?                                         | Policy: credit card required; same section: cash and debit accepted     | Card optional; deposit can be paid by any accepted method                                        |
| Q8  | Brand marquee placement                                                        | Brief: bottom of home page; reference: under the hero                   | Bottom of home page as requested                                                                 |
| Q9  | Flight details notice                                                          | Policy says 2 days in one place and 3 days in another                   | 2 days                                                                                           |
| Q10 | Is delivery always KES 2,000?                                                  | Policy says delivery cost is confirmed after booking                    | Flat KES 2,000 in Nairobi; other areas quoted by staff                                           |
| Q11 | Which FX source?                                                               | "Rates on Google" has no official API                                   | Paid FX API, hourly, minus KES 3, with admin override                                            |
| Q12 | Damage excess per vehicle                                                      | Policy gives a US$1,700–4,000 range                                     | Store excess per car; display on car detail page                                                 |
| Q13 | One-way fee, valeting fee, admin fee, Full Protection price                    | Fees mentioned, no amounts                                              | Shown as text only until amounts are supplied                                                    |
| Q14 | Cancellation refunds before pick-up                                            | Policy covers no-shows and amendments, not normal cancellation          | Client to define tiers                                                                           |
| Q15 | Safari services scope                                                          | Company offers safaris; brief has no safari detail                      | Services page with packages and WhatsApp enquiry                                                 |
| Q16 | Domain and business email                                                      | Only a Gmail address given                                              | Register a domain (e.g. bainesexecutives.co.ke) and send from it                                 |

## 11. Appendix: CMS seed data

All policies, contacts and pricing rules from the brief are extracted into an idempotent seed pack (`baines-seed.zip`) that loads into the CMS with `php artisan db:seed --class=CmsSeeder`.

| File                 | Contents                                                                                                                              | Records                         |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------- |
| `site_settings.json` | Company profile, phone/WhatsApp, email, location, socials, WhatsApp message templates                                                 | 1 settings group                |
| `policies.json`      | Rental policy sections, Markdown bodies, grouped (booking, rental, pickup, deposit, cover, charges, general) with home-page summaries | 31 sections                     |
| `catalog.json`       | Car brands for the marquee, car types, services, sample car shape                                                                     | 30 brands, 7 types, 6 services  |
| `pricing.json`       | Pricing rules, deposit bands, FX margin, currencies, payment methods, voucher fields                                                  | 6 currencies, 4 payment methods |
| `CmsSeeder.php`      | Upserts by slug or code; new policy version only when the body changes                                                                | —                               |

**Handling of conflicts in the seed data**

- Every place where the policy contradicts the booking brief carries a `[CONFIRM: …]` marker in the policy body.
- The seeder creates those policy versions **unpublished**, so nothing contradictory goes live before G0.
- Missing business data (paybill, bank details, card link, opening hours, legal name, FX provider) is marked `TODO`.
- Brand logos are not bundled; they are uploaded per brand from official brand-asset sources.

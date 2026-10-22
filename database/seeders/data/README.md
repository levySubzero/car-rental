# Seed pack

Content from the client brief (rental policy PDF, received 16 Oct 2026), loaded by `database/seeders/CmsSeeder.php` (M5.9–M5.10). The original pack lives outside the repo in `baines-seed/`; its bundled `CmsSeeder.php` targeted an older structure and was rewritten.

| File                 | Used by                                                                                                     | Contents                                                                                                                                                                                                                                   |
| -------------------- | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `site_settings.json` | `CmsSeeder` (M5)                                                                                            | Company profile, phone and WhatsApp, email, location, socials                                                                                                                                                                              |
| `policies.json`      | `CmsSeeder` (M5)                                                                                            | 31 rental policy sections, Markdown bodies                                                                                                                                                                                                 |
| `catalog.json`       | `CmsSeeder` (M5)                                                                                            | 30 brands, 7 car types, 6 services. `sample_cars` is a shape reference only                                                                                                                                                                |
| `media/`             | `FleetSeeder` (M7), `DemoCatalogSeeder`, `SiteMediaSeeder`                                                  | The client's Mazda Demio photos (cropped, plates blurred), the demo "photo coming soon" placeholder, and interim stock photos for the home hero and the six service cards (`stock.json` lists each one's source, photographer and licence) |
| `pricing.json`       | `CmsSeeder` (M5): `payment_methods`; `PricingSeeder` (M6): currencies, charges, delivery fee, deposit bands | `voucher_fields` waits for M10. Pricing settings defaults already match `pricing`                                                                                                                                                          |

## Changes from the original pack

- `policies.json`: the client confirmed every policy on 2026-10-20 (Gate G1 prep). The reviewer's `[CONFIRM: …]` notes were removed and the client's wording kept. The fifth display currency is UAE dirhams (AED), and the empty "Cancellation" heading was removed until cancellation tiers are set (M6.8).
- `catalog.json` (M7, from the client's information sheet, 21 Oct 2026): the 7 car types are now the client's (Executive Sedans, Executive SUVs, SUVs, Medium SUVs, Safari Landcruiser, Sports Cars, Economy). The marquee shows only the client's six brands, in their order: Mercedes-Benz, BMW, Toyota, Nissan, Honda, Lexus. The other brands stay available for cars.
- `media/mazda-demio/`: the six photos the client sent (`attachments/IMG_2690–2698`), cropped to 4:3, resized to 1600×1200, with every number plate blurred. The 640px "Serving Luxury" graphic was too small to use.
- The other files are unchanged.

## How the seeder treats this data

- **Safe to re-run.** Rows are matched by slug or code and only missing rows are inserted, so edits made in the admin are never overwritten.
- **Settings** are filled only while a field is still empty or at its default. `TODO` values are left empty: an empty setting is how the dashboard's "Before launch" list knows it is missing (D26).
- **Policies** get a new version only when the text here differs from the last version the seeder wrote. A new version is published, unless a staff member has a draft open. Each run that publishes anything creates one policy bundle (D24).
- **Payment methods** keep the `TODO` text in their instructions until staff replace it. The dashboard lists them until then.
- **Cars**: `FleetSeeder` adds the client's real cars (so far the Mazda Demio) with their photos and publishes them, in every environment. A car that already exists, even deleted, is skipped. Photos are uploaded as the first staff user.
- **Site photos**: `SiteMediaSeeder` adds the interim hero and service photos in every environment, only where none is set. Uploading the client's own photo in the admin replaces one for good. They are Unsplash photos (free commercial use, no attribution needed), cropped, with plates blurred; never use stock for fleet cars.
- **Pricing** rows are matched by code (currencies, charges) or name (delivery zones). Deposit bands are only seeded into an empty table, since bands must not overlap. No exchange rates are seeded: they come from the provider, or staff enter manual ones. Local and staging get demo rates from `DemoCatalogSeeder`.
- The pack's wording maps to the app's values: `calendar_dates_inclusive` is `calendar_inclusive`, `ceil_whole_shilling` is `ceil_major`, and decimal percentages become basis points (0.25 → 2500).

## Still to supply before launch

Legal name, opening hours, M-Pesa paybill or till, bank details, card payment link, FX API key (`FX_API_KEY`, then pick Open Exchange Rates in Settings → Exchange rates), excess km rate, the fleet list (cars, values, plates, photos), hero photo or video, the vector logo, brand logos (uploaded in the admin), and the copy for the preset pages (Content → Pages).

# Seed pack

Content from the client brief (rental policy PDF, received 16 Oct 2026), loaded by `database/seeders/CmsSeeder.php` (M5.9–M5.10). The original pack lives outside the repo in `baines-seed/`; its bundled `CmsSeeder.php` targeted an older structure and was rewritten.

| File                 | Used by                                  | Contents                                                                    |
| -------------------- | ---------------------------------------- | --------------------------------------------------------------------------- |
| `site_settings.json` | `CmsSeeder` (M5)                         | Company profile, phone and WhatsApp, email, location, socials               |
| `policies.json`      | `CmsSeeder` (M5)                         | 31 rental policy sections, Markdown bodies                                  |
| `catalog.json`       | `CmsSeeder` (M5)                         | 30 brands, 7 car types, 6 services. `sample_cars` is a shape reference only |
| `pricing.json`       | `CmsSeeder` (M5): `payment_methods` only | Pricing rules, deposit bands, FX, currencies and voucher fields wait for M6 |

## Changes from the original pack

- `policies.json`: the client confirmed every policy on 2026-10-20 (Gate G1 prep). The reviewer's `[CONFIRM: …]` notes were removed and the client's wording kept. The fifth display currency is UAE dirhams (AED), and the empty "Cancellation" heading was removed until cancellation tiers are set (M6.8).
- The other files are unchanged.

## How the seeder treats this data

- **Safe to re-run.** Rows are matched by slug or code and only missing rows are inserted, so edits made in the admin are never overwritten.
- **Settings** are filled only while a field is still empty or at its default. `TODO` values are left empty: an empty setting is how the dashboard's "Before launch" list knows it is missing (D26).
- **Policies** get a new version only when the text here differs from the last version the seeder wrote. A new version is published, unless a staff member has a draft open. Each run that publishes anything creates one policy bundle (D24).
- **Payment methods** keep the `TODO` text in their instructions until staff replace it. The dashboard lists them until then.

## Still to supply before launch

Legal name, opening hours, M-Pesa paybill or till, bank details, card payment link, FX provider (M6), brand logos (uploaded in the admin), and the copy for the preset pages (Content → Pages).

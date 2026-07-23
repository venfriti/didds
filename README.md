# DIIDS

DIIDS is a premium underwear brand for people who value confidence, comfort, and style every day. The storefront targets Nigeria and the wider African market, with pricing in NGN and payments handled by Paystack.

This repository is the DIIDS e-commerce platform, built on [Bagisto](https://bagisto.com/) 2.4.x (Laravel 12 + Vue 3). It's a customized, branded fork of Bagisto's storefront and admin panel — not the stock Bagisto demo.

For local setup, see **[SETUP.md](SETUP.md)**.

## Stack

- **Platform:** Bagisto (Laravel 12, PHP 8.3+), chosen over a headless Medusa + Next.js build for the complete admin panel, storefront, and variant system it ships out of the box.
- **Frontend:** Vue 3 (inline `x-template` components inside Blade, no SFC compilation), Tailwind CSS 3, Vite 5. Admin and Shop each have independent Vite builds.
- **Payments:** Paystack, via `wontonee/paystack` — supports Nigeria, Kenya, and the wider African region.
- **Currency:** NGN.
- **Queue:** `database` driver (not Redis) — sufficient at current scale.
- **Email:** Resend, via Laravel's SMTP mailer.
- **File storage:** local disk (via the `public` filesystem disk and `storage:link`).

## Brand system

- **Colors:** `navyBlue` (#1F2A44 / #141414 depending on package), `diidsSurface` (#F5F5F3, near-white), `diidsInk`, `diidsBorder`, `diidsBlush` (#C98B7A, used sparingly for sale tags/accents).
- **Type:** Manrope for body and headings (`font-poppins`/`font-dmserif` Tailwind tokens both resolve to Manrope — the names are historical, not literal), IBM Plex Mono for SKU/size/price labels and section eyebrows.
- **Layout direction:** full-bleed hero photography, centered/left-aligned nav, pill-shaped buttons, a garment-care-tag styled variant selector on the product page (`.diids-variant-tag`) instead of a generic swatch picker.

Both the Shop storefront and the Admin panel share this token system — Admin was restyled to match the storefront rather than using Bagisto's stock blue theme.

## Repository layout

All application code lives in `packages/Webkul/` (~40 packages, Bagisto's modular structure). The ones most relevant to day-to-day DIIDS work:

| Package | What it is |
|---|---|
| `Shop` | Customer-facing storefront — theme, product pages, cart, checkout |
| `Admin` | Store owner's admin panel — catalog, orders, settings, roles |
| `Theme` | Homepage content blocks (hero carousel, product carousels, footer links) — data lives in `theme_customizations` DB rows; the baseline version is seeded from `database/seeders/diids/theme-customizations.json` |
| `CMS` | Static pages (About Us, policies) — data lives in `cms_page_translations`; the baseline version is seeded from `database/seeders/diids/cms-pages.json` |
| `Paypal`, `Razorpay`, `Stripe`, `PayU`, `PhonePe` | Other payment gateways bundled with Bagisto; Paystack is the one actually wired up for this store, via the separate `wontonee/paystack` package |

See `CLAUDE.md` for the fuller architecture notes (repository pattern, proxy models, event-driven extensibility) if you're working on core package code rather than just theme/content.

## Common commands

```bash
php artisan serve --port=8010     # Local dev server
php artisan optimize:clear        # Clear all caches after config/code changes
vendor/bin/pest                   # Run the test suite
vendor/bin/pint                   # Fix PHP code style
```

Full test/lint/build commands are in `CLAUDE.md`.

## Content vs. code

Homepage sections, footer links, CMS page copy, and site branding (logo, currency, name) live in the **database**, not as Blade files — that's how Bagisto's admin panel is able to let a non-developer edit them without touching code. To make sure a fresh clone still looks like DIIDS rather than a generic Bagisto demo, those specific rows are seeded automatically via `database/seeders/DiidsBaselineSeeder.php`, which runs as part of `php artisan migrate --seed`. Its source data lives in `database/seeders/diids/*.json` — edit those files and re-run the seeder to change the baseline.

**The product catalog is the one thing intentionally left out of this baseline.** It stays purely database-managed, since it changes constantly as real inventory gets added — a fresh install starts with zero products. See [SETUP.md](SETUP.md) for the full setup sequence, including admin logins.

## License

Bagisto itself is MIT-licensed. DIIDS-specific branding, copy, and product data are not for redistribution.

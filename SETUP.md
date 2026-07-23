# Local Setup

Steps to get DIIDS running on your own machine from a fresh clone.

## 1. Prerequisites

- **PHP 8.3 or 8.4** (not 8.5+) with extensions: `calendar`, `curl`, `intl`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, plus the usual `gd`/`zip`/`xml`/`mysqli` that most PHP installs already ship with.
- **Composer**
- **MySQL 8+** (or MariaDB equivalent)
- **Node.js 18+** and npm

## 2. Clone and install PHP dependencies

```bash
git clone https://github.com/venfriti/didds.git
cd didds
composer install
```

## 3. Environment file

`.env` is gitignored on purpose — it holds real database credentials and mail secrets, so it's never in git. There are two ways to get one, depending on what you were given:

### Option A — you were handed a working `.env` file

If the repo owner gave you their actual `.env` (outside of git, e.g. dropped directly into your project folder after cloning), just place it at the project root as `.env` and run:

```bash
php artisan key:generate
```

Then still check/update these for your own machine, since a `.env` written for someone else's setup won't automatically match yours:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — must point at a database that actually exists on **your** MySQL install, not theirs. Create one matching these values before moving to step 4.
- `APP_URL` — must match the host/port you'll actually run the server on (e.g. `http://127.0.0.1:8010`). A mismatch here is a common cause of assets/logo appearing to "not load" even though the file is served correctly — the browser and the app disagree on the site's own origin.

### Option B — starting from `.env.example`

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` already has DIIDS's real defaults filled in (NGN currency, `127.0.0.1:8010`, database-driven queue, Resend SMTP host). You still need to set for your own machine:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — your local MySQL credentials
- `MAIL_PASSWORD` / `RESEND_KEY` — only needed if you want outgoing email (order confirmations, password resets) to actually send. Leave as placeholders otherwise; the app runs fine without it, emails just won't deliver.

Either way, `APP_KEY` must be freshly generated per machine via `php artisan key:generate` — never copy this specific value from someone else's `.env`, even if you copy everything else. It's the encryption key behind sessions and cookies.

## 4. Database

Create an empty database matching what you put in `.env` (e.g. `diids`), then run migrations:

```bash
php artisan migrate --seed
```

This gives you Bagisto's own seed data plus the **DIIDS baseline seeder** (`database/seeders/DiidsBaselineSeeder.php`), which runs automatically and sets up:

- The DIIDS logo, site name, and NGN currency
- The real homepage sections (hero carousel, lookbook, product carousels, footer links, services strip)
- Real copy on every CMS page (About Us, Privacy Policy, Shipping Policy, etc.)
- Two working admin logins — see step 8

So a fresh install already looks like DIIDS out of the box, not a generic Bagisto demo. What it does **not** include is the actual product catalog — that stays purely database-managed and grows independently as real inventory gets added through the admin panel (see [README.md](README.md#content-vs-code)). A fresh install will have zero products until some are added or imported.

If you need the site to show the same products as someone else's working local build, ask them for a `mysqldump` export of just the catalog tables (or the whole database) rather than relying on the seeder for that part.

## 5. Storage symlink

Needed for uploaded images (product photos, logos, theme assets) to actually be reachable over HTTP:

```bash
php artisan storage:link
```

## 6. Frontend assets

Shop, Admin, and Installer each have independent Vite builds. Built assets are already committed to `public/themes/`, so the site will render without this step — but you'll need it the moment you touch any Blade, CSS, or JS file, since nothing recompiles automatically outside `npm run dev`.

```bash
cd packages/Webkul/Shop
npm install
npm run build      # or `npm run dev` while actively working on Shop templates
cd ../../..

cd packages/Webkul/Admin
npm install
npm run build      # or `npm run dev` while actively working on Admin templates
cd ../../..
```

## 7. Run it

```bash
php artisan serve --port=8010
```

- Storefront: `http://127.0.0.1:8010`
- Admin panel: `http://127.0.0.1:8010/admin`

## 8. Admin logins

`migrate --seed` creates two accounts via `DiidsBaselineSeeder`:

| Email | Password | Access |
|---|---|---|
| `admin@diids.com` | `Diids@Admin1` | Unrestricted ("Administrator" role) |
| `orders@diids.com` | `Diids@Orders1` | Dashboard + Sales only ("Order Manager" role) — a working demo of the permissions system below |

**Change both passwords after first login** — these are placeholder credentials checked into git, not meant to be real production secrets.

### Admin roles

Bagisto's own role system already covers per-admin permission restriction — no custom code needed. **Admin → Settings → Roles** gives you a checklist tree of every section/sub-option in the panel; assign a role to any admin user and their sidebar (and route access) is limited to exactly what's checked. Only a role with `permission_type: all` (the default "Administrator" role) sees everything. The seeded "Order Manager" account is a ready-made example — log in as it to see the restriction in action.

## Restarting from scratch

If your local setup has drifted (your own experiments, a half-finished migration, content that doesn't match what's expected) and you'd rather reset than debug, do a real from-scratch reinstall rather than patching pieces individually — it's more reliable than guessing at what's inconsistent.

1. **Drop and recreate your local database** (empty). Anything in it — product data, admin edits, local changes — will be lost. Export first if any of it is worth keeping.
2. **Pull the latest `main`.** You specifically need commit `38d195d` or later — that's the one that added `DiidsBaselineSeeder` and its JSON fixtures. If you're on an older commit, none of this exists yet regardless of what you run.
3. **Re-run `composer install`** if `composer.json` or anything under `packages/` changed since you last installed.
4. **Environment file** — see step 3 above. If you're resetting because your `.env` itself might be part of the problem (wrong `APP_URL`, stale port, etc.), the safest move is to get a fresh copy — either a fresh `.env.example` copy with your own DB credentials re-entered, or a fresh copy of the repo owner's working `.env` — rather than trying to patch your existing one in place.
5. **`php artisan migrate:fresh --seed`** (not plain `migrate --seed`) — `migrate:fresh` drops every table and rebuilds cleanly, which avoids leftover state conflicting with the seeder's update-in-place logic.
6. **Storage symlink.** If `public/storage` already exists as a plain folder rather than a symlink (common failure mode on Windows), delete it first, then run `php artisan storage:link`.
7. **Confirm the baseline image files actually exist** at `storage/app/public/channel/1/diids-logo.svg` and `storage/app/public/theme/home/*.webp` before assuming anything's broken. If your `git pull` didn't bring these in, the seeder will still set the correct *path* in the database, but there'll be nothing on disk for it to point at — which shows up as a broken logo/images even though the database itself looks correct.
8. **`php artisan optimize:clear`** — this is the step most likely to get skipped and the one most likely to leave you staring at stale content even after everything above succeeded. See the caching note under Troubleshooting.

## Troubleshooting

- **Already had a database before this seeder existed and it still shows old/generic content:** run `php artisan db:seed --class=DiidsBaselineSeeder` on its own — it's safe to re-run any time (it updates existing rows in place rather than duplicating them) and will bring an older database up to the current DIIDS baseline without needing a full `migrate:fresh`.
- **Blank/unstyled pages after a git pull:** you likely need to rebuild frontend assets (step 6) — someone changed a Blade/CSS/JS file and the compiled output doesn't match yet.
- **"Class not found" errors after pulling package changes:** run `composer dump-autoload`.
- **Config changes (e.g. to `.env` or `config/*.php`) not taking effect:** run `php artisan config:clear`. Bagisto also caches admin settings saved through the UI (like the site logo) at the repository level — if a value you know is correct in the database still isn't showing up, run `php artisan cache:clear` too.
- **Dev server won't start / port already in use:** something else is bound to port 8010, or a previous `php artisan serve` process didn't exit cleanly. Pick a different `--port` or free up the existing one.

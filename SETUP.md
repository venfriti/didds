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

`.env` is gitignored on purpose — it holds real database credentials and mail secrets, so everyone needs their own.

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` already has DIIDS's real defaults filled in (NGN currency, `127.0.0.1:8010`, database-driven queue, Resend SMTP host). You still need to set for your own machine:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — your local MySQL credentials
- `MAIL_PASSWORD` / `RESEND_KEY` — only needed if you want outgoing email (order confirmations, password resets) to actually send. Leave as placeholders otherwise; the app runs fine without it, emails just won't deliver.

## 4. Database

Create an empty database matching what you put in `.env` (e.g. `diids`), then run migrations:

```bash
php artisan migrate --seed
```

This gives you Bagisto's own seed data — a working schema with default settings, but **not** the actual DIIDS catalog, homepage content, footer links, CMS page copy, or admin roles. Those all live in database rows that were built up locally during development and are not tracked in git (see [README.md](README.md#content-vs-code)).

If you need the site to actually look like the live local build (real products, homepage sections, policy pages), ask whoever has the working local database for a `mysqldump` export instead of relying on the seeder alone.

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

## 8. Getting an admin login

`php artisan migrate --seed` does not create an admin account. Either:

- Ask for the credentials to an existing admin user from the working local database, **or**
- Create one directly:
  ```bash
  php artisan tinker --execute="
  \$admin = new \Webkul\User\Models\Admin();
  \$admin->name = 'Your Name';
  \$admin->email = 'you@example.com';
  \$admin->password = bcrypt('choose-a-password');
  \$admin->role_id = 1; // Administrator (unrestricted) — see Roles below
  \$admin->status = 1;
  \$admin->save();
  "
  ```

### Admin roles

Bagisto's own role system already covers per-admin permission restriction — no custom code needed. **Admin → Settings → Roles** gives you a checklist tree of every section/sub-option in the panel; assign a role to any admin user and their sidebar (and route access) is limited to exactly what's checked. Only a role with `permission_type: all` (the default "Administrator" role) sees everything.

## Troubleshooting

- **Blank/unstyled pages after a git pull:** you likely need to rebuild frontend assets (step 6) — someone changed a Blade/CSS/JS file and the compiled output doesn't match yet.
- **"Class not found" errors after pulling package changes:** run `composer dump-autoload`.
- **Config changes (e.g. to `.env` or `config/*.php`) not taking effect:** run `php artisan config:clear`. Bagisto also caches admin settings saved through the UI (like the site logo) at the repository level — if a value you know is correct in the database still isn't showing up, run `php artisan cache:clear` too.
- **Dev server won't start / port already in use:** something else is bound to port 8010, or a previous `php artisan serve` process didn't exit cleanly. Pick a different `--port` or free up the existing one.

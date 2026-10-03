# Workora

Upload documents and images, share them with a link, and move files to and from
Google Drive. Built on Laravel 13 with Tailwind CSS, Alpine.js and Bootstrap Icons.

## What works

- **Accounts and workspaces.** Registering creates a private workspace with you as owner.
  Everything is isolated per workspace (see `docs/TENANCY.md`).
- **Upload.** Drag and drop or browse, many files at once, per-file progress, size limit
  and a blocklist of executable extensions. Search, filter by images / documents / folder,
  rename, delete. Images and PDFs preview in the browser.
- **Share.** One click makes a public link. Optional password, expiry (1, 7 or 30 days) and
  download limit. Links can be turned off at any time from *Shared links*, and stop working
  when the file is deleted.
- **Google Drive.** Connect with OAuth, browse folders, search, import files (Google Docs,
  Sheets and Slides arrive as .docx / .xlsx / .pptx), and save any workspace file to Drive.

## Run it locally

```bash
composer install          # needs PHP 8.4 for the committed composer.lock
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
php artisan serve
```

Open http://localhost:8000 and create an account. For hot reload use `npm run dev`.

PHP's own limits apply before the app's. To accept the default 100 MB uploads set
`upload_max_filesize=100M` and `post_max_size=110M` in `php.ini`.

## Turning on Google Drive

1. Google Cloud Console: enable the **Google Drive API**.
2. Create an **OAuth client ID** (type *Web application*) and add
   `https://your-domain/drive/callback` as an authorised redirect URI.
3. Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`.

The app requests `drive.readonly` (browse and import) and `drive.file` (save copies).
Both are Google "restricted/sensitive" scopes: while your OAuth consent screen is in
*Testing* mode only listed test users can connect; going public requires Google's
verification review. Tokens are stored encrypted.

## Tests

```bash
php artisan test
```

Drive calls are faked with `Http::fake()`; the suite never contacts Google.

---

# Foundation notes

The sections below describe the original schema-first groundwork the app is built on.

Phase 0/1 groundwork for a multi-tenant SaaS where companies manage the freelancers
they already work with. Laravel 11+ and PostgreSQL 15+.

This is the schema and the tenancy/permission spine, not a running application. It
is meant to be dropped into a fresh Laravel install so the parts that are expensive
to get wrong later are settled before any UI exists.

## What is here

```
database/migrations/    25 tables covering the MVP, plus RLS policies
app/Support/            Tenancy — resolves and holds the current company
app/Scopes/             OrganizationScope — the Eloquent global scope
app/Models/Concerns/    BelongsToOrganization — one trait per tenant model
app/Http/Middleware/    SetCurrentOrganization — resolves tenant per request
app/Enums/              OrganizationRole — the six staff roles plus freelancer
app/Policies/           ProjectPolicy, InvoicePolicy — the pattern to copy
docs/TENANCY.md         How isolation works and how to test it
docs/MVP-SCOPE.md       What ships in v1 and what is deliberately cut
```

## Setup

```bash
composer create-project laravel/laravel freelance-ops
cd freelance-ops
# copy database/, app/ and docs/ from this package over the fresh install
```

`.env`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=freelance_ops
DB_USERNAME=app_user     # NOT the role that owns the tables — see below
DB_PASSWORD=
```

Create two Postgres roles. This is not optional:

```sql
CREATE ROLE migrator LOGIN PASSWORD 'redacted';
CREATE ROLE app_user LOGIN PASSWORD 'redacted';
CREATE DATABASE freelance_ops OWNER migrator;

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user;
ALTER DEFAULT PRIVILEGES FOR ROLE migrator IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_user;
```

Run migrations as `migrator`, run the application as `app_user`. Postgres lets a
table owner bypass row level security, so if the app connects as the owner the
RLS policies do nothing and you will not notice until it matters.

Register the middleware in `bootstrap/app.php`:

```php
$middleware->web(append: [
    \App\Http\Middleware\SetCurrentOrganization::class,
]);
```

Bind Tenancy as a singleton in `AppServiceProvider::register()`:

```php
$this->app->singleton(\App\Support\Tenancy::class);
```

## Conventions worth keeping

**Money is stored as integer minor units.** Every amount column ends in `_minor`
and holds paise, cents or equivalent. No floats anywhere near a currency value.
Every money column has a `currency` alongside it, because a freelancer in Berlin
and a company in Ahmedabad do not share one.

**UUID primary keys.** Sequential integers leak volume across tenants and make
ID-guessing attacks trivial in a shared-schema design.

**Financial records are never hard deleted.** Invoices are voided, payments are
cancelled, audit log rows cannot be updated or deleted at all — a database trigger
enforces that, not a code convention.

**Every tenant model gets the trait.** If a table has `organization_id`, its model
gets `BelongsToOrganization`. There is a test in docs/TENANCY.md that will fail if
someone forgets.

## Deploying

Git-based deploys, not File Manager uploads. This application has migrations, a
queue worker and a scheduler; it needs a VPS or equivalent, not shared hosting.

Minimum on the server: PHP 8.3, PostgreSQL 15, Redis for queues and cache, Nginx,
`php artisan queue:work` under Supervisor, and `php artisan schedule:run` on cron
every minute. The scheduler and queue are what make deadline reminders, recurring
invoices and the automation rules possible at all.

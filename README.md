# Freelancer Operations Platform — Foundation

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

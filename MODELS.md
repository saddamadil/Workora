# Models

Thirty Eloquent models plus two config files. Drop `app/` and `bootstrap/` over
the existing directories — `AppServiceProvider.php` and `bootstrap/app.php`
replace Laravel's defaults, everything else is new.

## Two things to know before reading the code

**Tenant models carry the trait, the tenant itself does not.** Every model with
an `organization_id` uses `BelongsToOrganization`. `Organization` does not — it
is the thing being scoped to. `User`, `FreelancerProfile`, `Skill`,
`PayoutMethod` and `Plan` are also untenanted: a person and their profile exist
across every company they work with, and the per-company rate, role and category
live on `OrganizationMember` instead.

**Money is integers.** Every amount is `*_minor` and holds paise or cents, cast
to `integer`, with a `currency` column beside it. There is no float anywhere
near a currency value. Totals are always derived from their parts
(`Invoice::recalculate()`, `Timesheet::recalculate()`) rather than trusted from
whatever was submitted.

## Where the rules live

Some invariants are enforced in the model rather than left to controllers, on
the grounds that a controller can be bypassed and a boot hook cannot:

- `TimeEntry` refuses to update or delete once `locked_at` is set. An invoiced
  hour is a financial record.
- `AuditLog` throws on update and delete, matching the database trigger.
- `InvoiceItem` recomputes `amount_minor` on every save, so a line can never
  disagree with its own quantity times rate.
- `TaskSubmission` and `TaskRevision` assign their own sequence numbers, so
  attempt 2 is always attempt 2 regardless of what the caller passes.
- `BelongsToOrganization` refuses to let `organization_id` change on update.

## Worth a look

`WorkRequest::currentAmountMinor()` returns the latest counter-offer from the
negotiation thread, falling back to the freelancer's opening figure. That method
is the shape of the whole two-way workflow in miniature.

`Invoice::nextNumber()` scopes numbering per freelancer per company per year, so
two freelancers working for the same client never collide.

`Model::shouldBeStrict()` is on in local. It will throw on lazy-loaded
relationships and on setting non-fillable attributes. That is deliberate: N+1
queries are much cheaper to fix on the day you write them.

## After installing

```bash
php artisan config:clear
php artisan about
```

Then check a model resolves:

```bash
php artisan tinker
>>> App\Models\Organization::count()
```

Expect `0`, not an error. An error means a namespace or file path is wrong.

Creating a record needs a tenant context, because the trait stamps
`organization_id` from it:

```php
$org = App\Models\Organization::create(['name' => 'Test Co', 'slug' => 'test-co']);

app(App\Support\Tenancy::class)->forOrganization($org, function () use ($org) {
    App\Models\Client::create(['name' => 'First client']);
});
```

Outside a tenant context that same `Client::create` throws — which is the
intended behaviour, not a bug.

## Still missing

Factories, seeders, controllers, routes, views, and the two tenancy tests in
`docs/TENANCY.md`. Those tests should come before the first controller: they
are much harder to retrofit once there is UI depending on the current shape.

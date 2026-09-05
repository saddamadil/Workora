# Tenant isolation

Company A must never see Company B's projects, rates, invoices or payments. In a
shared-schema multi-tenant design that guarantee has to be built in three layers,
because any single layer will eventually be bypassed by a mistake.

## Layer 1 — the Eloquent global scope

`BelongsToOrganization` adds `OrganizationScope` to the model. Every query gets
`where organization_id = <current>` automatically, and every insert gets stamped.

This is the layer that does the real work day to day. It is also the layer that
fails silently the moment someone writes raw SQL, uses a query builder directly,
or forgets the trait on a new model.

It fails closed: with no tenant context, the scope filters on a zero UUID and
returns nothing. An empty screen is a bug report. A screen full of another
company's invoices is a breach.

## Layer 2 — Postgres row level security

Migration `000013` enables RLS on all 25 tenant tables with `FORCE`, so even the
table owner is subject to it. The policy compares `organization_id` against the
`app.current_organization_id` session variable, which `Tenancy::set()` writes on
every request.

This catches the cases layer 1 misses: raw queries, a model missing the trait, a
console command that forgot to set context.

Two operational consequences:

- The application role must not own the tables. See README setup.
- Queued jobs run outside the HTTP request, so the session variable is not set.
  Every job touching tenant data must call `Tenancy::forOrganization()` first, or
  it will find zero rows.

## Layer 3 — policies

Scoping answers "which company"; policies answer "which person, and how much".
A freelancer and a finance manager are both inside the same tenant and must see
very different things. `ProjectPolicy` and `InvoicePolicy` show the pattern:
resolve the role from `Tenancy`, branch freelancers separately from staff, and
gate money behind `seesMoney()` rather than sprinkling role checks in views.

## Testing it

Two tests. Write them before the first controller.

```php
it('never returns another tenant\'s rows', function () {
    $a = Organization::factory()->create();
    $b = Organization::factory()->create();

    tenancy()->forOrganization($a, fn () => Project::factory()->count(3)->create());
    tenancy()->forOrganization($b, fn () => Project::factory()->count(2)->create());

    tenancy()->forOrganization($a, function () {
        expect(Project::count())->toBe(3);
    });
});

it('scopes every model that has an organization_id column', function () {
    $missing = collect(File::files(app_path('Models')))
        ->map(fn ($f) => 'App\\Models\\' . $f->getFilenameWithoutExtension())
        ->filter(fn ($class) => Schema::hasColumn((new $class)->getTable(), 'organization_id'))
        ->reject(fn ($class) => in_array(
            BelongsToOrganization::class,
            class_uses_recursive($class),
            true
        ));

    expect($missing)->toBeEmpty();
});
```

The second one is the important one. It turns "remember to add the trait" from a
code review habit into a build failure.

## Known limits

Shared schema with RLS is the right trade for hundreds or low thousands of small
tenants. It is not the right trade if a single enterprise customer eventually
demands their data in a separate database or region. If that becomes a real sales
requirement, the migration path is schema-per-tenant or database-per-tenant, and
it is a significant piece of work. Worth knowing now, not worth building for now.

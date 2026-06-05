# Factory and Seeder Rules

This document is the project standard for model factories, seeders, and demo
data. It exists to keep test data, development demo data, and fixed reference
data predictable, repeatable, and safe for the shared-hosting SQLite baseline.

Seeders orchestrate order. Factories create valid model graphs on request.
Neither seeders nor factories may add restaurant product behavior.

## Scope

These rules apply to:

- `database/factories`;
- `database/seeders`;
- tests that create domain records;
- local development demo data.

They do not define a training mode, restaurant checklist, issue module, or
production bootstrap feature.

## Factory Rules

- Every important domain model must have a factory before it is used broadly in
  tests or demo data.
- Factory defaults must create valid records that satisfy model casts, enum
  values, required columns, unique constraints, and minimum relationship needs.
- A factory may create required parent records when the model cannot exist
  without them, but it must not create optional child records or wide domain
  graphs unless the caller explicitly asks for them.
- Complex relationships must be expressed with named states, `for(...)`,
  `has(...)`, or dedicated builder methods. Do not hide a full restaurant setup
  inside a default factory definition.
- Factories must use safe fake data. Never use real customer, staff, owner,
  email, phone, password, payment, or production restaurant data.
- Factories must work for both tests and demo seed. Tests should be able to
  create the records they need without running the full demo seeder.
- Factory states should describe business-valid situations, such as active,
  closed, pending, approved, unavailable, paid, or cancelled, using centralized
  enums/status helpers where those exist.
- Factories must avoid hardcoded IDs. Link records through relationships,
  factory states, or explicitly passed model instances.
- Factories must not use external services, S3, queues, webhooks, payment
  gateways, or network calls.

Recommended shape:

```php
Order::factory()
    ->for($tableSession)
    ->confirmed()
    ->hasItems(3)
    ->create();
```

Avoid this shape in a default factory:

```php
Order::factory()->create(); // silently creates organization, branch, full menu, staff, QR, tickets, and payments
```

## Seeder Rules

- Seeders must be idempotent. Running the same seeder twice must not create
  duplicate fixed data or duplicate demo restaurant records.
- `DatabaseSeeder` should control seeding order and call small focused seeders.
  It must not become a large manual restaurant script.
- Fixed reference seeders may use Eloquent `upsert`, `firstOrCreate`,
  `updateOrCreate`, or small relationship sync operations when unique keys are
  clear and indexed.
- Fixed reference data includes:
  - roles;
  - permissions;
  - default departments;
  - statuses, if stored in tables;
  - languages and currencies, if stored in tables.
- Demo data seeders must build demo records through factories or small dedicated
  demo builders that call factories. Manual arrays are acceptable only for small
  fixed reference rows or tiny scenario labels.
- Seeders must not break, overwrite, or delete real production data. They must
  avoid destructive cleanup unless the command is explicitly local/test only and
  documented beside the seeder.
- Seeders must not create production users by default. Production bootstrap
  users require explicit config, documented env values, and no default password.
- Seeders should use model actions/services only when they are required to keep
  invariants intact. Otherwise, use factories and Eloquent relationships.
- Seeders must stay compatible with SQLite and shared hosting. Avoid parallel
  writes and keep seeding order deterministic.
- Seeders must not rely on external services, Redis, WebSockets, S3, payment
  providers, or paid infrastructure.

## Demo Seed Rules

- Demo seed is development-only. It must be blocked in `production` and must not
  be called automatically from production deploy steps.
- Demo seed is not training mode.
- Demo seed is not a restaurant feature.
- Demo seed must be built through factories and explicit states/builders.
- Demo seed may create fake organizations, branches, staff, service points,
  menus, QR records, draft/order examples, and payment examples only when a
  prompt explicitly asks for that scenario.
- Demo seed must use clearly fake emails and generated passwords suitable only
  for local development.
- Demo seed must not create real owners, real staff accounts, real customer
  identities, or any account that could be mistaken for production access.
- Demo seed must not create training orders, checklist flows, issue logs,
  sandbox restaurant modes, or fake operational modules.

## Prohibited Patterns

- Do not write large chaotic `DB::table()->insert(...)` arrays for a demo
  restaurant.
- Do not create records without valid relationships.
- Do not seed orphaned menu items, order items, payments, tickets, QR records,
  guests, or branch assignments.
- Do not use real emails, real passwords, real phone numbers, real payment data,
  or real restaurant/customer names.
- Do not create superadmin, owner, staff, or guest accounts accidentally in
  production.
- Do not seed training orders or training table sessions.
- Do not store uploaded files, generated images, or blobs in seeders.
- Do not hide business behavior in seeders. A seeder may create data; it must
  not become a second implementation of ordering, payments, QR, permissions, or
  kitchen/bar workflows.
- Do not depend on `Model::all()` or unbounded loops when a scoped query,
  cursor, `lazyById`, or explicit list is safer.
- Do not bypass model fillable/casts/enums unless the seeder is maintaining a
  small fixed reference table and documents why `forceFill` is needed.

## Verification Checklist

Before accepting a new or changed factory/seeder:

- Run the target factory in at least one focused test.
- Run the target seeder twice locally and confirm fixed/demo data is not
  duplicated.
- Confirm tests can create their required model graph with factories without
  running the full demo seed.
- Confirm all required relationships exist and no orphaned domain records are
  created.
- Confirm enum/status values use centralized enums or status helpers.
- Confirm production safety guards prevent demo seed from running in
  `production`.
- Confirm no real credentials, secrets, full server paths, or real customer
  data appear in seeded rows.
- Confirm no external service or unsupported infrastructure is required.

Useful local checks:

```shell
php artisan migrate:fresh --seed --env=local
php artisan db:seed --class=DemoRestaurantSeeder --env=local
php artisan db:seed --class=DemoRestaurantSeeder --env=local
php artisan test --compact
```

Use focused tests instead of the full suite when only one factory or seeder is
changed. Run the full suite before merging larger data-model changes.

## Incremental Refactor Rule

Existing seeders should be improved gradually. If a seeder currently contains
large manual demo arrays, do not rewrite it wholesale during unrelated work.
When touching that area, extract one small factory state or builder at a time
and record any remaining cleanup in `docs/NEXT_STEPS.md`.

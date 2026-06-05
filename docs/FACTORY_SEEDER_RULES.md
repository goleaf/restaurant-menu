# Factory and Seeder Rules

This document is the project standard for model factories, seeders, and demo
data. It exists to keep test data, development demo data, and fixed reference
data predictable, repeatable, and safe for the shared-hosting SQLite baseline.

Seeders orchestrate order. Factories create valid model graphs on request.
Neither seeders nor factories may add restaurant product behavior.

For the complete layer plan, read `docs/SEED_ARCHITECTURE.md`. For operating
commands and current seeder ownership, read `docs/SEEDERS.md`.

## Scope

These rules apply to:

- `database/factories`;
- `database/seeders`;
- tests that create domain records;
- local development demo data.

They do not define a training mode, restaurant checklist, issue module, or
production bootstrap feature.

## Core Seed Law

- All seed data must be created through factories except fixed reference data.
- Fixed reference data may use short explicit rows when those rows come from
  enums, config, or canonical dictionaries.
- Demo and scenario seeders must not contain large manual arrays of restaurants,
  users, tables, menu items, QR records, orders, or payments.
- When a demo row needs a human-readable name, pass it as a factory state or
  explicit `state([...])` override.
- Seeders may call Actions only when an Action protects existing domain
  invariants, such as QR token uniqueness or branch settings defaults.
- No seeder may create training mode, training sessions, or training orders.

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
- Factories should expose named states for demo-specific records instead of
  making demo behavior the default.
- Factories must create the narrowest valid graph by default. Wide restaurant
  graphs belong in demo builders/seeders that compose factories explicitly.
- Factories that create rows with unique columns must provide a way for seeders
  to pass stable values.

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
- Reference seeders may run in production. Demo and scenario seeders are
  local/dev only.
- `DatabaseSeeder` must not call scenario seeders.
- Demo seeders must be explicit commands and must be blocked in production.
- Repeated demo seed runs must not duplicate roles, permissions, users,
  memberships, branch assignments, service points, QR records, menu records, or
  translations.

## Seed Layer Rules

Seeders must follow these layers:

1. Reference seed layer:
   - roles;
   - permissions;
   - role permissions;
   - default departments;
   - default allergens when tables exist;
   - default tags when tables exist;
   - languages when tables exist;
   - currencies when tables exist;
   - service point types when tables exist;
   - area types when tables exist;
   - statuses if stored.
2. Platform seed layer:
   - superadmin;
   - platform settings if any.
3. Demo organization seed layer:
   - organization;
   - brand;
   - branches;
   - branch settings;
   - branch profiles.
4. Restaurant structure seed layer:
   - `area_nodes`;
   - `service_points`;
   - QR codes;
   - QR image files if implemented locally.
5. Staff seed layer:
   - owner;
   - director;
   - restaurant admin;
   - shift manager;
   - waiter;
   - head chef;
   - cook;
   - bartender;
   - cashier;
   - accountant;
   - marketer.
6. Menu seed layer:
   - menus;
   - categories;
   - menu items;
   - menu item translations;
   - variants when tables exist;
   - modifiers;
   - allergens when tables exist;
   - tags when tables exist;
   - nutrition when columns or tables exist;
   - images if local assets are available.
7. Functional scenario seed layer:
   - optional local/dev only;
   - active session examples;
   - draft examples;
   - order examples;
   - payment examples;
   - never production;
   - never training mode.

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
- Demo seed must create the complete local/dev restaurant through factories:
  organization, brand, branches, branch settings, profiles, areas, service
  points, QR records, menu, departments, staff, and users.
- Demo staff must cover every restaurant role defined in `SystemRole`, except
  superadmin, which belongs to platform bootstrap.
- Demo seed must not silently run optional functional scenarios. Scenario seed
  must be a separate explicit command.

## Idempotency Keys

Use stable natural keys or metadata keys for repeatable seed:

| Data | Preferred key |
| --- | --- |
| Roles | `code` |
| Permissions | `code` |
| Role permissions | `role_id` plus `permission_id` |
| Users | `email` |
| Organizations | demo key or stable demo name |
| Brands | `organization_id` plus demo key or name |
| Branches | `brand_id` plus demo key or name |
| Branch settings | `branch_id` |
| Branch opening hours | `branch_id` plus `day_of_week` |
| Organization memberships | `organization_id` plus `user_id` |
| Branch assignments | `branch_id` plus `user_id` |
| Areas | `branch_id` plus demo key, or stable parent/name/type |
| Service points | stable `internal_code` |
| QR codes | `service_point_id` for active permanent QR |
| Menus | `branch_id` plus demo key or name |
| Categories | `menu_id` plus demo key or name |
| Category translations | `menu_category_id` plus `language_code` |
| Menu items | `menu_id` plus demo key or name |
| Item translations | `menu_item_id` plus `language_code` |
| Modifier groups | `branch_id` plus demo key or name |
| Modifier options | `modifier_group_id` plus demo key or name |
| Scenario rows | stable `demo_key` in metadata where available |

If a table has no natural unique key, prefer adding a documented demo metadata
key when the model already supports metadata. Do not introduce hardcoded numeric
IDs to make seeders repeatable.

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
- Do not call external QR generation services from seeders.
- Do not add QR image files with non-deterministic names on every repeat run.
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
- Confirm optional scenario seeders are local/dev only and are not called by
  `DatabaseSeeder`.
- Confirm no real credentials, secrets, full server paths, or real customer
  data appear in seeded rows.
- Confirm no external service or unsupported infrastructure is required.
- Confirm no duplicate active QR record exists for a service point after running
  demo seed twice.
- Confirm all demo staff roles are represented when the prompt changes complete
  demo seed coverage.

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

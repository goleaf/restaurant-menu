# Seeders

This document is the operational guide for running and maintaining project
seeders. For the architecture rules behind these commands, read
`docs/SEED_ARCHITECTURE.md` and `docs/FACTORY_SEEDER_RULES.md`.

## Current Seeder Classes

| Seeder | Layer | Responsibility |
| --- | --- | --- |
| `DatabaseSeeder` | Orchestration | Runs production-safe base seeders in order. |
| `SystemRolesSeeder` | Reference | Creates or updates roles from `SystemRole`. |
| `SystemPermissionsSeeder` | Reference | Creates or updates permissions and syncs the fixed role baseline matrix in `permission_role`. |
| `FirstSuperadminSeeder` | Platform | Creates or links one configured superadmin when config is present. |
| `KitchenDepartmentsSeeder` | Reference per branch | Ensures default kitchen department rows exist for existing branches. |
| `DemoRestaurantSeeder` | Demo | Creates the local/dev demo restaurant. Must remain blocked in production. |

## Default Seed Command

For a clean local database:

```shell
php artisan migrate:fresh --seed --env=local
```

This runs `DatabaseSeeder`, which should stay production-safe. It must not
create demo restaurants, demo staff, table sessions, orders, payments, or QR
examples by default.

## Demo Restaurant Command

For local/demo restaurant data:

```shell
php artisan db:seed --class=DemoRestaurantSeeder --env=local
```

Run it a second time before accepting seeder changes:

```shell
php artisan db:seed --class=DemoRestaurantSeeder --env=local
```

The second run must not duplicate users, roles, branch assignments, service
points, QR codes, menus, translations, or modifiers.

## Production Rules

Production seed runs are limited to reference and explicit platform bootstrap
data.

Allowed in production:

- roles;
- permissions;
- role/permission pivot rows with the fixed baseline `enabled` state;
- configured first superadmin when required config exists;
- default per-branch kitchen departments when branches already exist.

Forbidden in production:

- `DemoRestaurantSeeder`;
- scenario seeders;
- fake users;
- fake organizations, brands, and branches;
- fake QR records;
- fake sessions, orders, and payments;
- local passwords or demo credentials.

`DemoRestaurantSeeder` must throw or return safely when `APP_ENV=production`.
Do not bypass that guard with `--force`.

## Required Seeding Order

Use this order whenever the complete local demo data set is built:

1. Reference roles.
2. Reference permissions.
3. Role permission matrix with baseline enabled/disabled states.
4. Platform superadmin from config, if present.
5. Demo organization owner through `UserFactory`.
6. Demo organization through `OrganizationFactory`.
7. Demo brand through `BrandFactory`.
8. Demo branches through `BranchFactory`.
9. Branch settings, profiles, and opening hours through factories/actions.
10. Default branch departments.
11. Areas through `AreaNodeFactory`.
12. Service points through `ServicePointFactory`.
13. QR records through `QrCodeFactory` or the QR action when it protects token
    invariants.
14. Demo staff users through `UserFactory`.
15. Organization memberships and branch assignments through factories.
16. Menus, categories, translations, items, modifiers, and local assets through
    factories.
17. Optional local-only scenarios through a dedicated scenario seeder.

## Demo Staff Coverage

The complete demo restaurant must include fake users for these roles:

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

The superadmin is configured separately by `FirstSuperadminSeeder`.

All staff emails must be obviously fake and stable. All role links must resolve
the `roles` table by `code`, never by hardcoded IDs.

## Functional Scenario Seeders

Functional scenario data is optional and local/dev only.

Allowed scenario examples:

- active table sessions;
- table session guests;
- draft orders;
- waiter review records;
- confirmed orders;
- kitchen/bar tickets;
- manual payments.

Rules:

- do not call scenario seeders from `DatabaseSeeder`;
- do not treat scenarios as training mode;
- do not create training orders;
- do not add restaurant product features from a seeder;
- make repeated runs idempotent with stable demo keys;
- keep scenario data small enough for local SQLite and shared hosting.

## QR and File Rules

QR records must be stable and repeatable:

- one active QR per service point;
- no QR regeneration during ordinary repeat seed;
- no duplicate active QR rows;
- no public URLs based on visible table numbers or database IDs.

QR image files, when implemented, must be local deterministic artifacts. Use a
stable path derived from the QR token or short code, and regenerate the same file
instead of creating a new file on each seed run.

Do not use S3 or external QR APIs.

## Verification

For documentation-only changes:

```shell
git diff --check
```

For seeder or factory implementation changes:

```shell
php artisan migrate:fresh --seed --env=local
php artisan db:seed --class=DemoRestaurantSeeder --env=local
php artisan db:seed --class=DemoRestaurantSeeder --env=local
php artisan test --compact
```

Use focused tests when changing one factory or one seeder. Run the full suite
before merging broad seed architecture changes.

## Maintenance Checklist

Before accepting seeder changes, confirm:

- reference data is idempotent;
- demo and scenario data are local/dev only;
- demo/scenario data is created through factories;
- no training mode or training orders were added;
- no raw SQL or manual demo arrays were introduced;
- no hardcoded IDs are used;
- no seed path requires Redis, WebSockets, S3, external APIs, or paid services;
- repeated seed runs do not duplicate users or QR records.

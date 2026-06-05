# Seed Architecture

This document defines the target seed architecture for the restaurant menu
project. It is a documentation plan only: it does not add product features,
training mode, training orders, or runtime restaurant behavior.

The seed system must produce a complete local demo/dev restaurant after a seed
command, while keeping production bootstrap safe and reference data repeatable.

## Core Principle

All seed data must be created through model factories except fixed reference
data.

Fixed reference data is the small, canonical data set the application needs to
understand roles, permissions, enum-backed choices, and default dictionaries.
Reference seeders may use short explicit rows sourced from enums or config.
Everything else - demo organizations, branches, staff, QR, menu, and optional
scenarios - must be built with factories and named factory states.

The project uses Laravel 13 with SQLite as the default local/shared-hosting
database. Seeders must remain deterministic, single-process friendly, and free
of external services such as Redis, WebSockets, S3, payment gateways, or paid
infrastructure.

## Seed Layers

Seeders must be organized in layers. A layer may depend only on earlier layers.

| Order | Layer | Purpose | Production |
| --- | --- | --- | --- |
| 1 | Reference | Fixed dictionaries and permission matrix | Allowed |
| 2 | Platform | Superadmin and platform settings | Allowed only from explicit config |
| 3 | Demo organization | Fake organization, brand, branches, branch settings, profiles | Local/dev only |
| 4 | Restaurant structure | Area tree, service points, permanent QR records, QR files | Local/dev only |
| 5 | Staff | Fake staff users and assignments for every role | Local/dev only |
| 6 | Menu | Fake menus, categories, items, translations, modifiers, metadata | Local/dev only |
| 7 | Functional scenarios | Optional active sessions, drafts, orders, payments | Local/dev only and explicit |

## Layer 1: Reference Seed Layer

Reference seeders create fixed data that is part of the application vocabulary.
They must be idempotent and may run in production.

Required reference groups:

- roles from `App\Enums\SystemRole`;
- permissions from `App\Enums\SystemPermission`;
- fixed role permission baseline in `permission_role`;
- default kitchen departments from `App\Enums\KitchenDepartmentType`;
- default allergens when an allergen table exists;
- default tags when a tag table exists;
- supported languages when a language table exists;
- supported currencies when a currency table exists;
- service point types when a service point type table exists;
- area types when an area type table exists;
- statuses only if they are stored in database tables.

Current schema note:

- roles and permissions are stored in `roles`, `permissions`, and
  `permission_role`; `SystemPermissionsSeeder` owns the baseline enabled state
  for fixed roles;
- default kitchen departments are stored per branch in `kitchen_departments`;
- languages, currencies, service point types, area types, and most statuses are
  currently enum-backed and do not have dedicated tables;
- allergen and tag tables are not present in the current schema.

Reference seeders must not create demo users, demo restaurants, active sessions,
orders, payments, or QR records.

## Layer 2: Platform Seed Layer

The platform layer contains only the minimum local platform bootstrap:

- configured superadmin user;
- superadmin role assignment;
- platform settings if such a table exists later.

The existing `FirstSuperadminSeeder` is the right pattern: it reads from config,
does nothing when the required email/password are absent, and does not create a
default production credential.

Platform seed rules:

- never hardcode production credentials;
- never create a superadmin from demo seed;
- never require demo seed for platform setup;
- keep platform settings separate from restaurant settings.

## Layer 3: Demo Organization Seed Layer

The demo organization layer creates the main fake restaurant tenant for local
development and QA.

Target records:

- one demo `organization`;
- one demo `brand`;
- one or more demo `branches`;
- one `branch_settings` row per branch;
- branch public profile fields;
- branch opening hours when needed by UI flows.

Factory requirements:

- `OrganizationFactory` must create a valid organization with a fake owner when
  the caller does not pass one;
- `BrandFactory` must be able to attach to an existing organization via
  `for($organization)`;
- `BranchFactory` must be able to attach to an existing brand and organization;
- `BranchSettingFactory` must be able to use `for($branch)`;
- profile/opening-hour factories must avoid external file services.

Idempotency keys:

- demo owner user email;
- organization demo key or stable demo name;
- brand demo key or `organization_id` plus name;
- branch demo key or `brand_id` plus name.

## Layer 4: Restaurant Structure Seed Layer

The structure layer creates the physical service model for the demo branch.

Target records:

- `area_nodes` for floor, hall, terrace, bar, VIP room, pickup area, or other
  relevant layout nodes;
- `service_points` for tables, bar seats, pickup windows, rooms, or other
  service points;
- one active permanent `qr_codes` record per service point;
- QR image files when the application has a local QR image generation path.

Factory requirements:

- `AreaNodeFactory` must create valid nodes under a branch and support parent
  nodes through `for(...)` or a named state;
- `ServicePointFactory` must support stable internal codes, coordinates,
  capacity, type, area, and status;
- `QrCodeFactory` must create one active QR for a service point and must not
  regenerate or duplicate active QR codes on repeat seed.

QR rules:

- QR public tokens and short codes must be stable across repeated seed runs;
- a service point must not receive a second active QR during repeat seed;
- QR files, if generated, must use local storage and deterministic paths;
- seeders must not upload QR images to S3 or call external QR services.

## Layer 5: Staff Seed Layer

The staff layer creates fake users and attaches them to the demo organization
and branch.

Required demo roles:

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

The superadmin belongs to the platform layer, not demo staff.

Factory requirements:

- `UserFactory` must support verified local demo users with fake emails and a
  known local-only password;
- membership and assignment factories must create valid `organization_users`
  and `branch_users` records;
- role assignment must use seeded `roles` by code, not hardcoded IDs;
- permission overrides, when needed, must use seeded `permissions` by code.

Idempotency keys:

- user email;
- `organization_users`: `organization_id` plus `user_id`;
- `branch_users`: `branch_id` plus `user_id`;
- `role_user`: `user_id` plus `role_id`;
- `permission_user_overrides`: `user_id` plus `permission_id`.

## Layer 6: Menu Seed Layer

The menu layer creates a realistic fake menu for the demo branch.

Target records:

- `menus`;
- `menu_categories`;
- `menu_category_translations`;
- `menu_items`;
- `menu_item_translations`;
- modifier groups;
- modifier options;
- menu item to modifier group links;
- allergens when allergen tables exist;
- tags when tag tables exist;
- nutrition data where columns or tables exist;
- local images when assets are available.

Current schema note:

- menu item nutrition is currently represented by columns such as `weight`,
  `volume`, and `calories`;
- modifier groups and options exist;
- menu item variants, allergen tables, tag tables, and separate nutrition tables
  are not present in the current schema.

Factory requirements:

- menu factories must attach to an existing branch;
- category factories must attach to an existing menu;
- item factories must attach to an existing menu, category, and optional
  kitchen department;
- translation factories must cover all supported demo locales;
- modifier factories must attach through the existing pivot table;
- local image fields may point only to committed fixture assets or generated
  local files documented in the seeder.

## Layer 7: Functional Scenario Seed Layer

Functional scenarios are optional and explicit. They are for local development
and QA only.

Allowed examples:

- active table session examples;
- table session guest examples;
- draft order examples;
- waiter review examples;
- confirmed order examples;
- kitchen/bar ticket examples;
- manual payment examples.

Forbidden examples:

- training mode;
- training orders;
- training sessions;
- production demo scenarios;
- hidden product behavior implemented only in seeders.

Scenario seeders must not run from `DatabaseSeeder` by default. They must be
called explicitly, for example through a dedicated local-only seeder class.
Repeated scenario seed runs must be idempotent by using stable demo keys in
natural unique columns or metadata.

## Target Seeder Flow

`DatabaseSeeder` should stay small and call focused layer seeders:

```php
$this->call([
    SystemPermissionsSeeder::class,
    FirstSuperadminSeeder::class,
    KitchenDepartmentsSeeder::class,
]);
```

The complete local demo/dev flow should be explicit:

```shell
php artisan migrate:fresh --seed --env=local
php artisan db:seed --class=DemoRestaurantSeeder --env=local
```

Optional scenario examples must be separate:

```shell
php artisan db:seed --class=DemoRestaurantScenarioSeeder --env=local
```

The scenario class is a target architecture placeholder. It should be created
only when scenario data is implemented.

## Idempotency Contract

Running the same seed command repeatedly must not duplicate:

- roles;
- permissions;
- role permission rows and their baseline `enabled` state;
- superadmin users;
- demo users;
- organization memberships;
- branch assignments;
- demo organizations, brands, and branches;
- branch settings and profiles;
- area nodes;
- service points;
- active QR codes;
- menus, categories, translations, items, modifiers, allergens, and tags;
- scenario records when scenarios are explicitly seeded.

When a table has no natural unique key, the demo layer should store a stable
`demo_key` in metadata if the model supports metadata, or use an existing stable
business key such as `internal_code`, email, code, or name within a parent.

## Current Gaps To Resolve When Implementing

The current seed architecture already has roles, permissions, first superadmin,
kitchen departments, factories for many domain models, and a local-only
`DemoRestaurantSeeder`.

Known target gaps:

- the demo restaurant seeder should be refactored over time so demo data is
  created through factories and named factory states instead of manual demo
  arrays;
- the demo staff list should cover director, shift manager, cook, accountant,
  and marketer in addition to the currently seeded roles;
- menu variants, allergens, tags, and separate nutrition records should not be
  documented as seeded tables until migrations/models exist;
- functional scenarios should stay in a separate local-only seeder, not in the
  default database seed path.

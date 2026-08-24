# Factories and seeding

## Inventory and contract

There are 45 first-party Eloquent models and 45 model factories. There are no independent-model exemptions. Every default factory must satisfy required foreign keys, enums, ownership, uniqueness, date ordering and money invariants. Optional large graphs are explicit factory states/helpers rather than hidden callbacks. The `MenuItemImageFactory` creates one valid generated test path and parent item; multi-image graphs remain explicit so ordinary item factories stay small. `RestaurantOnboardingFactory` defaults to a valid user-owned empty checkpoint; complete restaurant graphs stay opt-in through the workflow tests.

Meaningful states cover workflow values actually used by each model: active/inactive/pending/approved/rejected/archived/expired/verified/public/private/deleted/failed/completed only where that model owns such a concept. Edge data includes empty optionals, complete optionals, Unicode/long values and historical/future dates where valid.

## Seeder layers

| Seeder | Class | Contract |
|---|---|---|
| Fixed roles | `SystemRolesSeeder` | Idempotent natural role code; all closed system roles |
| Fixed permissions | `SystemPermissionsSeeder` | Idempotent permission code and deterministic role grants |
| Fixed departments | `KitchenDepartmentsSeeder` | Stable defaults attached only in an explicit restaurant graph |
| First superadmin | `FirstSuperadminSeeder` | Explicit environment/config input; never a committed production password |
| Demo | `DemoRestaurantSeeder` | All roles plus a realistic four-branch restaurant/menu/QR/staff graph; writes deterministic QR and representative menu-gallery files after the database transaction; restores owned soft-deleted menu records; refuses production |
| Operational demo | `DemoOperationalStateSeeder` | Factory-backed, idempotent live, payment-requested, completed, kitchen/bar ticket, waiter-call, payment, order-status and audit histories; every branch has a paid order and every bar department has new, in-progress and ready work |
| Orchestrator | `DatabaseSeeder` | Safe dependency order; no truncation; deterministic option |

Demo data is fictitious and covers every meaningful staff role, ownership/non-ownership, current/historical workflow states, localized menu data, empty/normal/heavy presentation cases and local file fixtures. Every seeded branch has staff assignments, a localized menu with bar work, physical service points, permanent QR identities, table sessions, orders, an immutable payment and both audit and order-status history. The current local deterministic graph contains 12 roles, 15 users, 1 organization, 3 brands, 4 branches, 10 areas, 20 service points, 19 QR identities and ready SVG files, 4 menus, 9 categories, 22 dishes, 8 ordered secondary gallery records, 33 variants, 3 invitations, 10 orders, 5 department tickets with 15 ticket items, 5 immutable payments, 4 order-status logs and 4 audit logs. Every branch representative dish retains its legacy primary image and has two factory-backed secondary gallery images. Every bar department includes new, in-progress and ready ticket items. QR images live under `storage/app/public/demo/qr/`; representative media live under `storage/app/public/demo/media/`. Filenames contain no bearer token, repeated seeding overwrites only the same owned deterministic paths, and `php artisan storage:link` exposes them through the configured public disk. No seeded capability depends on the internet.

## Canonical demo identities

`App\Support\DemoLogin\DemoAccountCatalog` is the single identity map shared by `DemoRestaurantSeeder` and the opt-in demo-login surface. It defines one deterministic fictitious name and email for each of the 12 `SystemRole` cases in canonical enum order; it contains no password or persistence logic. Seeder parity coverage proves that every generated demo account matches this catalogue and its assigned role.

`DemoRestaurantSeeder` remains idempotent, refuses production and uses natural keys without truncating unrestricted data. `DatabaseSeeder` wiring is unchanged: demo restaurant data is still an explicit operator action, not an implicit default seed. The shared seed password remains a non-production operator/testing detail and is never exposed by the role-selection page.

## Coverage matrix

| Model group | Factory | Meaningful state examples | Seeder coverage | Tests |
|---|---|---|---|---|
| User/access | one per model | role/status/override/2FA/passkey relations | roles, permissions, staff/demo | `ModelFactoryAuditTest`, `FactoryStatesTest`, auth/permission tests |
| Organization/branch | one per model | active/suspended, nested ownership, soft-deleted | demo restaurant | factory, management and schema tests |
| Areas/service points/QR | one per model | node types, operational/QR states, assigned waiter | demo floor plan | factory, CRUD/schema/QR tests |
| Menu graph | one per model | draft/active/archived, localized, available/unavailable, ordered primary/gallery image states | demo menu | factory/menu/schedule/gallery tests |
| Session/guest/draft | one per model | pending/active/closed, guest/join/draft states | demo active and historical tables | factory/session/draft tests |
| Order/tickets/calls/payment | one per model | all valid workflow states, successful/corrected settlement | demo live and completed service | factory/order/kitchen/payment tests |
| Audit/subscription | one per model | action/status/payment states | demo governance | factory/audit/subscription tests |

## Final evidence

- 45 first-party Eloquent models and 45 factories; no exemptions.
- Seven seeders including the orchestrator and operational demo layer.
- `ModelFactoryAuditTest`, `FactoryStatesTest` and demo/seeder safeguards cover the complete graph, catalogue parity, ready QR SVG contents and repeated-run file hashes.
- Fresh isolated SQLite includes all 76 migrations; the onboarding pair and gallery migration were separately verified forward, rollback and forward again. Repeated `DemoRestaurantSeeder` runs preserve graph counts, IDs, 19 QR SVGs, eight secondary gallery rows and every deterministic file hash. A forced production run refuses before changing data. The same seeder then completed twice against the local non-production database without refresh, truncation or deletion. Demo area/service-point/menu-category icons are restricted to supported Flux names, while presentation safely falls back for historical invalid values.
- Fixed natural keys, FK/unique constraints and production refusal remain enabled; seeders do not truncate unrestricted data. QR files are written only after the core demo transaction commits, and a failed filesystem write raises an exception instead of silently reporting a complete seed.

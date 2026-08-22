# Factories and seeding

## Inventory and contract

There are 41 first-party Eloquent models and 41 model factories. There are no independent-model exemptions. Every default factory must satisfy required foreign keys, enums, ownership, uniqueness, date ordering and money invariants. Optional large graphs are explicit factory states/helpers rather than hidden callbacks.

Meaningful states cover workflow values actually used by each model: active/inactive/pending/approved/rejected/archived/expired/verified/public/private/deleted/failed/completed only where that model owns such a concept. Edge data includes empty optionals, complete optionals, Unicode/long values and historical/future dates where valid.

## Seeder layers

| Seeder | Class | Contract |
|---|---|---|
| Fixed roles | `SystemRolesSeeder` | Idempotent natural role code; all closed system roles |
| Fixed permissions | `SystemPermissionsSeeder` | Idempotent permission code and deterministic role grants |
| Fixed departments | `KitchenDepartmentsSeeder` | Stable defaults attached only in an explicit restaurant graph |
| First superadmin | `FirstSuperadminSeeder` | Explicit environment/config input; never a committed production password |
| Demo | `DemoRestaurantSeeder` | Realistic organization/branch/menu/QR/session/staff states; refuses production |
| Orchestrator | `DatabaseSeeder` | Safe dependency order; no truncation; deterministic option |

Demo data is fictitious and covers every meaningful staff role, ownership/non-ownership, current/historical workflow states, localized menu data, empty/normal/heavy presentation cases and local file fixtures. No seeded capability depends on the internet.

## Coverage matrix

| Model group | Factory | Meaningful state examples | Seeder coverage | Tests |
|---|---|---|---|---|
| User/access | one per model | role/status/override/2FA/passkey relations | roles, permissions, staff/demo | `ModelFactoryAuditTest`, `FactoryStatesTest`, auth/permission tests |
| Organization/branch | one per model | active/suspended, nested ownership, soft-deleted | demo restaurant | factory, management and schema tests |
| Areas/service points/QR | one per model | node types, operational/QR states, assigned waiter | demo floor plan | factory, CRUD/schema/QR tests |
| Menu graph | one per model | draft/active/archived, localized, available/unavailable | demo menu | factory/menu/schedule tests |
| Session/guest/draft | one per model | pending/active/closed, guest/join/draft states | demo active and historical tables | factory/session/draft tests |
| Order/tickets/calls/payment | one per model | all valid workflow states, successful/corrected settlement | demo live and completed service | factory/order/kitchen/payment tests |
| Audit/subscription | one per model | action/status/payment states | demo governance | factory/audit/subscription tests |

Required verification: every factory and meaningful state persists; `DatabaseSeeder` finishes on fresh test SQLite; fixed seeders are idempotent; FK/unique checks remain enabled; production rejects demo accounts; seeded principal pages render and seeded files exist on the fake/configured disk.

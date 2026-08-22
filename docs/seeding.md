# Factories and seeding

## Inventory and contract

There are 41 first-party Eloquent models and 41 model factories. There are no independent-model exemptions. The factories expose 105 explicit state/relationship helper methods in addition to their 41 `definition()` methods. Every default factory must satisfy required foreign keys, enums, ownership, uniqueness, date ordering and money invariants. Optional large graphs are explicit factory states/helpers rather than hidden callbacks.

Meaningful states cover workflow values actually used by each model: active/inactive/pending/approved/rejected/archived/expired/verified/public/private/deleted/failed/completed only where that model owns such a concept. Edge data includes empty optionals, complete optionals, Unicode/long values and historical/future dates where valid.

## Seeder layers

| Seeder | Class | Contract |
|---|---|---|
| Fixed roles | `SystemRolesSeeder` | Idempotent natural role code; all closed system roles |
| Fixed permissions | `SystemPermissionsSeeder` | Idempotent permission code and deterministic role grants |
| Fixed departments | `KitchenDepartmentsSeeder` | Stable defaults attached only in an explicit restaurant graph |
| First superadmin | `FirstSuperadminSeeder` | Explicit environment/config input; never a committed production password |
| Demo | `DemoRestaurantSeeder` | Realistic organization/branch/menu/QR/session/staff states; refuses production |
| Operational demo | `DemoOperationalStateSeeder` | Idempotent live, payment-requested, completed, ticket, waiter-call, payment and audit histories |
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

## Final evidence

- 41 first-party Eloquent models, 41 factories and 105 explicit state/relationship helpers; no exemptions.
- Seven seeders including the orchestrator and operational demo layer.
- `ModelFactoryAuditTest`, `FactoryStatesTest` and demo/seeder safeguards pass in the 694-test suite.
- Fresh isolated SQLite completed all 66 migrations and `DatabaseSeeder`; `DemoRestaurantSeeder` then passed twice in 3.62 s and 7.06 s. Demo area/service-point/menu-category icons are restricted to supported Flux names, while presentation safely falls back for historical invalid values.
- Fixed natural keys, FK/unique constraints and production refusal remain enabled; seeders do not truncate unrestricted data.

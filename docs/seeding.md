# Factories and seeding

## Inventory and contract

There are 48 first-party Eloquent models and 48 model factories. There are no independent-model exemptions. Every default factory must satisfy required foreign keys, enums, ownership, uniqueness, date ordering and money invariants. Optional large graphs are explicit factory states/helpers rather than hidden callbacks. The `MenuItemImageFactory` creates one valid generated test path and parent item; multi-image graphs remain explicit so ordinary item factories stay small. Menu, category, item, variant, modifier-group and modifier-option factories expose explicit complete-translation states; the three newly persisted translation entity types have their own factories. `RestaurantOnboardingFactory` defaults to a valid user-owned empty checkpoint; complete restaurant graphs stay opt-in through the workflow tests.

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
| Management lifecycle demo | `DemoOrganizationCrudSeeder` | Factory-backed archived/restored management examples, ordered gallery media and active, accepted, expired, cancelled and rejected invitations |
| Tenant portfolio demo | `DemoTenantPortfolioSeeder` | Two additional factory-backed organizations with isolated owners, brands, branches, rooms, tables, QR identities and complete EN/LT/RU menus |
| Orchestrator | `DatabaseSeeder` | Safe dependency order; no truncation; deterministic option |

Demo data is fictitious and covers every meaningful staff role, three independent tenants, ownership/non-ownership, all canonical order/draft/invitation states, localized menu data, empty/normal/heavy presentation cases and local file fixtures. The verified graph contains 12 roles, 17 users, 3 organizations, 5 brands, 6 branches, 12 areas, 24 service points, 24 permanent QR identities and 24 ready SVG files, 6 menus with 18 translations, 11 categories with 33 translations, 24 dishes with 72 translations, 8 ordered secondary gallery records, 35 variants with 105 translations, 8 modifier groups with 24 translations, 24 modifier options with 72 translations, 5 invitations, 20 table sessions, 24 drafts, 19 orders, 13 department tickets with 23 ticket items, 5 immutable payments, 12 order-status logs and 4 audit logs. The primary four-branch tenant supplies rich kitchen/bar/payment/audit histories; the two additional tenants provide realistic isolation data and complete EN/LT/RU guest menus. Every branch representative dish retains its legacy primary image and has two factory-backed secondary gallery images. Every bar department includes new, in-progress and ready ticket items. QR images use the production path contract under `storage/app/public/qr/{digest-prefix}/{sha256-token}.svg`; representative media live under `storage/app/public/demo/media/`. Filenames contain neither the bearer nor a sequential database ID, repeated seeding verifies and reuses exactly one path per QR, and `php artisan storage:link` exposes them through the configured public disk. No seeded capability depends on the internet.

## Canonical demo identities

`App\Support\DemoLogin\DemoAccountCatalog` is the single identity map shared by `DemoRestaurantSeeder` and the opt-in demo-login surface. It defines one deterministic fictitious name and email for each of the 12 `SystemRole` cases in canonical enum order; it contains no password or persistence logic. Seeder parity coverage proves that every generated demo account matches this catalogue and its assigned role.

`DemoRestaurantSeeder` remains idempotent, refuses production and uses natural keys without truncating unrestricted data. `DatabaseSeeder` includes it only when the resolved environment is non-production, `DEMO_LOGIN_ENABLED=true`, and the normalized `APP_URL` host belongs to `DEMO_LOGIN_HOSTS`; the default allowlist is `ruflo.test`. Other environments receive fixed reference data only. Demo identity passwords and bearer credentials are intentionally random on first creation and are never documented; repeated seeding preserves existing password hashes and digest-only token records while the structural graph, natural keys and generated file paths remain stable.

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

- 48 first-party Eloquent models and 48 factories; no exemptions.
- Nine executable seeders plus the translation support class, including the orchestrator, operational, management-lifecycle and tenant-portfolio demo layers.
- `ModelFactoryAuditTest`, `FactoryStatesTest` and demo/seeder safeguards cover the complete graph, catalogue parity, ready QR SVG contents and repeated-run file hashes.
- Fresh isolated SQLite includes all 86 migrations. Repeated `DemoRestaurantSeeder` runs preserve graph counts, IDs, complete three-locale menu graphs, exactly 24 QR SVGs, eight secondary gallery rows and every deterministic file hash. A forced production run and default orchestration on a non-allowlisted host refuse demo data before changing it. Demo area/service-point/menu-category icons are restricted to supported Flux names, while presentation safely falls back for historical invalid values.
- Fixed natural keys, FK/unique constraints and production refusal remain enabled; seeders do not truncate unrestricted data. Demo and onboarding QR files are written only after their core database transactions commit; failed outer transactions leave no orphan SVGs, and a failed filesystem write raises an exception instead of silently reporting a complete seed.

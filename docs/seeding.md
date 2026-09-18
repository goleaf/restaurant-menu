<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

## Prompt 5 floor fixtures

Existing AreaNode, ServicePoint, QrCode, TableSession, order and organization factories compose nested/empty/foreign rooms, textual/duplicate display numbers, archived internal-code reservations, direct and merged unfinished service, nonactive QR and damaged files. Bulk bounds use real200/201-record scenarios. No new production/demo seed behavior or business entity is needed. Fixtures, images and documents are written only to owned test databases/storage; repeating this stage does not change working names, staff assignments, keys or permanent QR.

# Factories and seeding

## Prompt 7 availability fixtures — 2026-09-17

BranchScheduleExceptionFactory and AvailabilityCommandFactory create valid tenant-linked records. Existing branch, menu, item, variant, modifier and guest/order factories compose availability scenarios explicitly; ordinary factory defaults do not hide invalid historical selections. New order tests persist real selected variant/option IDs rather than fabricated IDs. Temporal and race fixtures use SQLite memory or owned temporary files. The five additive availability migrations preserve current data; default seed/idempotency checks run only against disposable databases. No working restaurants, accounts, invitations, menu restrictions or permanent QR are changed.

## Prompt 8 Team fixtures

Team acceptance tests use the existing factories in isolated SQLite databases. Cases include a shared User across independent organizations, inherited and explicit restaurant scope, independently suspended organization/restaurant memberships, organization overrides, archived rooms, typed accepted invitation identity and concurrent SQLite writers. No real invitation, account, role, permanent QR or canonical database is created or changed for this stage. Demo seeder behavior and production guards remain unchanged.


## Controller migration fixtures — 2026-09-16

`UserFactory::withTwoFactor()` now supplies a valid Base32 test secret for the real Fortify/Google2FA provider; the ordinary factory still has MFA disabled. Auth tests exercise real credentials, TOTP and single-use recovery codes. Invitation fixtures remain small, tenant-scoped and fictitious. File browser tests generate a complete SQLite backup in owned temporary storage and restore only a disposable database; external mail delivery is faked. No production seed roles, users or settings change.

## Flux Pro fixture boundary — 2026-09-16

The source import adds no models, migrations or seed data. Its integrity Unit test needs no database. Pro workflow acceptance must reuse existing factories in isolated SQLite fixtures, including multiple tenants, revoked access, empty/current/invalid selections, repeated language editors and bounded large option/ticket sets. Keep any local component reference fictitious and non-persistent; no package showcase should write to the working restaurant database. New rich-content storage, if implemented in P11, needs its own additive migration/factory/security proof before demo data uses it.

## Team fixtures — 2026-09-15

PermissionUserOverrideFactory::forOrganization creates an explicit scoped decision; the default remains a legacy compatibility fixture. Team lifecycle/isolation tests use factories for independent tenants, membership roles, pending/expired/rotated invitations and exact waiter areas. Concurrency tests use disposable file SQLite databases, distinct worker processes and readiness barriers. Browser identities use separate contexts and owned temporary session files. No real invitations or employee data are sent or modified.


## Photo presentation fixture state — 2026-09-15

MenuItemFactory and MenuItemImageFactory expose opt-in withPresentation states with fictitious EN/LT/RU alt/caption and off-center focal coordinates. Existing demo graphs and their production guard remain. New CSV fixtures create unavailable records and three translated names; bulk/archive and stale metadata tests use isolated factories. Runtime tests redirect storage and SQLite to disposable locations, preserving the working catalogue and originals.

## Product factory additions — 2026-09-14

`MenuOperationFactory` and `MenuOperationCategoryFactory` create valid scoped operation records and traversal frontiers. `DatabaseSessionRecordFactory` targets the configured session table/connection and creates a valid anonymous framework session; no independent-model exemption is needed. Large catalogue/media/continuation fixtures stay opt-in. Fresh model/schema tests cover all 52 models and the 89-migration chain; dated 49-model inventories below are historical.

## Inventory and contract

The eighth audit revalidates all 49 default model factories and the full 88-migration chain. An owned SQLite migration roundtrip and two default seeder runs pass; new area/table transport and bulk rollback fixtures use isolated factory graphs. Permission fixtures that choose an enum must use `Permission::factory()->forSystemPermission($permission)`, which keeps code, label and unique sort order consistent; overriding only `code` can collide with another permission's factory sort order. Menu transport fixtures use the existing opt-in complete-translation states.

There are 52 first-party Eloquent models and 52 model factories. There are no independent-model exemptions. Every default factory must satisfy required foreign keys, enums, ownership, uniqueness, date ordering and money invariants. Optional large graphs are explicit factory states/helpers rather than hidden callbacks. The `MenuItemImageFactory` creates one valid generated test path and parent item; multi-image graphs remain explicit so ordinary item factories stay small. Menu, category, item, variant, modifier-group and modifier-option factories expose explicit complete-translation states; the three newly persisted translation entity types have their own factories. `RestaurantOnboardingFactory` defaults to a valid user-owned empty checkpoint; complete restaurant graphs stay opt-in through the workflow tests.

Meaningful states cover workflow values actually used by each model: active/inactive/pending/approved/rejected/archived/expired/verified/public/private/deleted/failed/completed only where that model owns such a concept. Edge data includes empty optionals, complete optionals, Unicode/long values and historical/future dates where valid.

Variant item states explicitly complete missing `item.menu.branch` relationships before recycling the selected branch into the default draft/order graph, so variants retrieved in collections remain valid under strict lazy-loading prevention. Already loaded relationships are preserved without repeated reads, and explicitly supplied parents remain intact. Automatically created drafts backing orders use `ConvertedToOrder`; explicit draft overrides and opt-in statuses remain available. `OrderFactory::withItems()` and `withDepartmentReadiness()` share integer total calculation from active lines, excluding cancelled items. `FactoryStateConsistencyTest` covers default/explicit parent ordering, partial/eager variant graphs, lifecycle, department-ready graphs and cancelled-line totals.

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
| Orchestrator | `DatabaseSeeder` | Safe dependency order; model events enabled; no truncation; deterministic option |

Demo data is fictitious and covers every meaningful staff role, three independent tenants, ownership/non-ownership, all canonical order/draft/invitation states, localized menu data, empty/normal/heavy presentation cases and local file fixtures. The verified graph contains 12 roles, 17 users, 3 organizations, 5 brands, 6 branches, 12 areas, 24 service points, 24 permanent QR identities and 24 ready SVG files, 6 menus with 18 translations, 11 categories with 33 translations, 24 dishes with 72 translations, 8 ordered secondary gallery records, 35 variants with 105 translations, 8 modifier groups with 24 translations, 24 modifier options with 72 translations, 5 invitations, 20 table sessions, 24 drafts, 19 orders, 13 department tickets with 23 ticket items, 5 immutable payments, 12 order-status logs and 4 audit logs. The primary four-branch tenant supplies rich kitchen/bar/payment/audit histories; the two additional tenants provide realistic isolation data and complete EN/LT/RU guest menus. Every branch representative dish retains its legacy primary image and has two factory-backed secondary gallery images. Every bar department includes new, in-progress and ready ticket items. QR images use the production path contract under `storage/app/public/qr/{digest-prefix}/{sha256-token}.svg`; representative media live under `storage/app/public/demo/media/`. Filenames contain neither the bearer nor a sequential database ID, repeated seeding verifies and reuses exactly one path per QR, and `php artisan storage:link` exposes them through the configured public disk. No seeded capability depends on the internet.

## Canonical demo identities

`App\Support\DemoLogin\DemoAccountCatalog` is the single identity map shared by `DemoRestaurantSeeder` and the opt-in demo-login surface. It defines one deterministic fictitious name and email for each of the 12 `SystemRole` cases in canonical enum order; it contains no password or persistence logic. Seeder parity coverage proves that every generated demo account matches this catalogue and its assigned role.

`DemoRestaurantSeeder` remains idempotent, refuses production and uses natural keys without truncating unrestricted data. `DatabaseSeeder` includes it only when the resolved environment is non-production, `DEMO_LOGIN_ENABLED=true`, and the normalized `APP_URL` host belongs to `DEMO_LOGIN_HOSTS`; the default allowlist is `ruflo.test`. Other environments receive fixed reference data only. Demo identity passwords and bearer credentials are intentionally random on first creation and are never documented; repeated seeding preserves existing password hashes and digest-only token records while the structural graph, natural keys and generated file paths remain stable.

## Model events during seeding

`DatabaseSeeder` keeps model events enabled, including child seeders. The `saving` hooks on QR codes, table sessions and waiter calls populate the nullable service-point keys used by unique constraints; menu observers also preserve cache invalidation and deletion cascades. Wrapping the orchestrator in `WithoutModelEvents` bypassed those hooks and left all 24 active QR codes, 13 occupied sessions and one pending waiter call without their active guard keys in the isolated demo fixture. Removing that blanket suppression restores these invariants on newly seeded graphs, and the regression checks exact active-record identities after two runs.

The historical order-item snapshot backfill retains its narrowly placed `withoutEvents` callback. Muting is appropriate only when every suppressed model hook is unnecessary for that operation. Eloquent builder-level mass `update` already skips per-model update events, so adding `withoutEvents` around it alone provides no such event reduction. See Laravel's [model events](https://laravel.com/docs/13.x/eloquent#events) and [seeder event muting](https://laravel.com/docs/13.x/seeding#muting-model-events) documentation.

This code change does not repair or reseed an existing application database. In particular, existing permanent QR records may be reused without saving, so rerunning seeds is not a general repair for previously missing guard keys. Any existing-data correction requires a separate scoped check for duplicate active records before backfilling keys.

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

- 49 first-party Eloquent models and 49 factories; no exemptions. The added cache-entry factory targets the existing framework table for expiration-cleanup tests.
- Nine executable seeders plus the translation support class, including the orchestrator, operational, management-lifecycle and tenant-portfolio demo layers.
- `ModelFactoryAuditTest`, `FactoryStatesTest` and demo/seeder safeguards cover the complete graph, catalogue parity, ready QR SVG contents and repeated-run file hashes.
- Fresh isolated SQLite includes all 88 migrations. Repeated `DemoRestaurantSeeder` runs preserve graph counts, IDs, complete three-locale menu graphs, exactly 24 QR SVGs, eight secondary gallery rows and every deterministic file hash. A forced production run and default orchestration on a non-allowlisted host refuse demo data before changing it. Demo area/service-point/menu-category icons are restricted to supported Flux names, while presentation safely falls back for historical invalid values.
- Fixed natural keys, FK/unique constraints and production refusal remain enabled; seeders do not truncate unrestricted data. Demo and onboarding QR files are written only after their core database transactions commit; failed outer transactions leave no orphan SVGs, and a failed filesystem write raises an exception instead of silently reporting a complete seed.

`KitchenTicketItemFactory` resolves the source order-item snapshot once per generated model. Its cache is local to one `definition()` invocation; reusing the factory after a source edit loads the new values, while explicit states still override defaults. The two-item regression reduces source reads from 18 to 2 and checks ownership and snapshot values.

## Local account inventory — 2026-09-16

The local login directory never seeds on GET. This installation already contained 17 demo users: 12 role-switch identities and five fixed lifecycle/tenant fixtures, so no new users or restaurant graph were created. `DemoAccountCatalog::directoryAccounts()` allowlists all 17 for the local directory while `accounts()` retains exactly 12 role-switch identities. At explicit user request, all 17 known demo passwords were rotated to the local `DEMO_LOGIN_PASSWORD` value. Roles, company membership states and permissions were preserved; ordinary accounts outside that exact catalogue are never changed. Default factories and repeated seed behavior remain random-on-create and preserve-on-repeat. The configured local display is conditional on the current hash and does not turn the demo seeder into a password reset operation.


## Prompt 6 integration contract

Prompt 6 fixtures use existing factories for separate tenant graphs, different EN/LT/RU text, shared modifier groups, historical orders, archived children and isolated SQLite concurrency. DishPerformanceTest exercises 40 unrelated dishes plus a selected dish with 100 variants and 180 options. Default/demo seeders retain their existing guarded contracts; no production catalogue is generated or reset.

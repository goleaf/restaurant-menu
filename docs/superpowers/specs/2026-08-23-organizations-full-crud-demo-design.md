# Organizations full CRUD demo design

## Context

The authenticated `/organizations` workspace already exposes most restaurant-administration operations through nested Livewire pages. The existing `DemoRestaurantSeeder` creates a realistic factory-backed four-branch graph, but the current local database proves that several management surfaces remain empty: there are no opening-hour rows, menu schedules, modifier groups/options, waiter-area assignments, invitations, permission overrides, organization/brand/branch media, or dish images. Existing CRUD tests are distributed across feature files and do not provide one auditable operation-by-operation coverage matrix.

The required outcome is a fully populated, safe demo administration workspace reachable from `/organizations`, with every supported create/read/update/delete or lifecycle-equivalent operation tested. The existing restaurant graph, demo identities, guest and operational workflows remain canonical and must not be duplicated.

## Selected approach

Extend the existing demo graph with a focused subordinate `DemoOrganizationCrudSeeder`, invoked by `DemoRestaurantSeeder`. It resolves the canonical demo organization and branches by stable natural keys, uses model factories and their explicit states to build missing management fixtures, updates owned deterministic records idempotently, restores owned soft-deleted fixtures where required, and refuses production through the parent and direct entry points.

A documented CRUD matrix defines every resource, route, permission, mutation and deletion semantic. Focused Livewire and Action tests prove behavior; a matrix audit test ensures no listed operation lacks named executable evidence. This produces one demo restaurant and one source of truth.

## Rejected approaches

### A second independent CRUD demo organization

This is easy to add but duplicates users, memberships, branches and menu graphs, complicates demo-login routing and makes idempotency harder to reason about. It is rejected.

### A destructive snapshot seeder

Truncating and rebuilding the application database would make the UI predictable, but violates the repository's data-safety and shared-hosting rules. It is rejected.

### UI-only fixture creation

Creating demo data through browser automation tests every screen but is slow, non-idempotent and unsuitable for repeatable local setup. Browser automation remains an acceptance layer, not the source of demo data.

## Definition of full CRUD

CRUD is interpreted according to domain invariants:

- ordinary hierarchy/content records use create, bounded read/list, update and soft delete;
- identity-bearing or historical records use create/read/update plus disable, revoke, archive, detach or suspend instead of physical deletion;
- singleton branch configuration uses create-on-branch plus read/update; deleting individual opening intervals or replacing the schedule provides its delete behavior;
- many-to-many records use attach/read/detach;
- every mutation authorizes and reloads tenant-owned records server-side.

## Complete organizations CRUD inventory

| # | Resource | Surface | Create | Read | Update | Delete/lifecycle equivalent | Demo fixture |
|---|---|---|---|---|---|---|---|
| 1 | Organization | `/organizations` | create organization | accessible bounded list | rename, logo replace/remove | confirmed soft delete | canonical demo organization with logo |
| 2 | Brand | `/organizations/{organization}/brands` | create brand | tenant-scoped bounded list | rename, logo replace/remove | confirmed soft delete | three brands, one with logo |
| 3 | Branch | `/organizations/{organization}/brands/{brand}/branches` | create branch | accessible bounded list | identity, location, currency, timezone, active state, logo | confirmed soft delete | four branches including active and inactive examples |
| 4 | Branch public profile | branch settings | created with branch | current profile | public text/contact/social links, logo and cover | media removal and blank optional fields | complete profile plus deterministic local media |
| 5 | Branch settings | branch settings | auto-create missing singleton | current settings | guest/order/polling/service/charge/tip/locale/currency settings | reset through validated defaults, not row deletion | complete settings on every branch |
| 6 | Opening hours | branch settings | add day intervals | weekly schedule | replace/edit intervals | remove interval/closed day/disable schedule | open, split-shift and closed-day examples |
| 7 | Temporary closure | branch settings | set closure | read closure state | edit reason/until | clear closure | one temporary-closure scenario without blocking all demo branches |
| 8 | Organization staff membership | organization staff | manual add | bounded staff list | role and active status | suspend/reactivate; no historical hard delete | all non-superadmin roles plus suspended member |
| 9 | Branch staff assignment | branch staff | assign member | bounded branch list | branch role and waiter zones | suspend/reactivate/detach lifecycle | selected assignments plus suspended assignment |
| 10 | Invitation | organization/branch staff | create link/code | bounded invitation history | expiry/recipient scope remains immutable | cancel pending invitation | pending, expired and cancelled examples without exposing tokens |
| 11 | Permission override | staff permissions | allow/deny override | grouped effective matrix | switch allow/deny | return to role default | one allow and one deny override on non-critical demo permissions |
| 12 | Area node | branch areas | create root/child | ordered tree | rename, move, icon/order, active state | confirmed soft delete | nested active and inactive areas |
| 13 | Service point | branch service points | single/bulk create | searched, filtered, paginated list/board | name/area/type/capacity/icon/status/active state | guarded soft delete only without active session; otherwise disable | multiple types/statuses, active/inactive and with/without QR |
| 14 | QR identity | service point/QR pages | generate missing QR | show/download/print/bulk print | explicit reissue creates replacement | disable/revoke old identity | active plus disabled/revoked history with safe SVG fixtures |
| 15 | Kitchen department | branch menu/departments | create | ordered list | name/type/order/active | guarded delete with assignment/history checks | kitchen/bar plus inactive custom department |
| 16 | Menu | branch menu/catalog | create | ordered list | name/status/order | confirmed soft delete | active, draft and archived menus across branches |
| 17 | Menu availability schedule | menu catalog | create interval | ordered schedule list | edit day/start/end | delete interval | weekday and weekend intervals |
| 18 | Menu category | menu catalog | create root/child | ordered list/tree | base and EN/LT/RU content, icon/order/active | confirmed soft delete | localized active and inactive categories |
| 19 | Menu item | menu catalog | create dish | ordered list | base and EN/LT/RU content, price/nutrition/allergens/diet/department/order | confirmed soft delete | available/unavailable localized dishes |
| 20 | Menu item images | dish edit form | multi-upload up to eight | primary plus ordered gallery | choose primary | remove individual image and parent cleanup | primary plus secondary images on representative dishes |
| 21 | Menu item availability | availability page/catalog | availability exists with item | bounded stop-list/read | mark available/unavailable | unavailable is lifecycle removal from guest menu | both states in every representative branch |
| 22 | Modifier group | menu modifiers | create group | ordered list | required/min/max/order | delete group | required and optional groups |
| 23 | Modifier option | menu modifiers | create option | ordered nested list | name/price/availability/order | delete option | free, surcharge, discount and unavailable options |
| 24 | Item-modifier assignment | menu modifiers | attach group | read assigned groups | reattach is idempotent | detach group | multiple assigned and unassigned items |
| 25 | Menu item variant | menu variants | create variant/portion | ordered item list | type/name/price/size/default/availability/order/EN-LT-RU | delete variant with default invariant | default, optional and unavailable variants |
| 26 | Branch subscription context | seeded governance context | ensure subscription | organization access evaluation | superadmin status transition | inactive lifecycle state | active subscription for CRUD demo organization |

Operational orders, guests, drafts, tickets, calls, payments, audit logs and notifications remain seeded because they constrain deletion and make the restaurant realistic, but their primary CRUD surfaces live outside `/organizations` and are not reimplemented by this scope.

## Identified implementation gaps

The current code already covers organization/brand/branch, area, menu, modifier, variant, department, QR, settings and staff mutations extensively. The plan must close only observed gaps:

1. extend the demo graph with the empty management fixtures listed above;
2. add bounded pagination/search where growing organization, brand, branch, staff and invitation lists still call `get()`;
3. add staff role reassignment and invitation cancellation;
4. add guarded service-point soft deletion because the model already supports soft deletes but the UI has no delete operation;
5. add menu-schedule update;
6. add category/item EN/LT/RU editing and synchronization;
7. deliver the approved multi-image dish gallery design;
8. replace broad combined CRUD claims with operation-level positive, validation, authorization and cross-tenant tests;
9. add one browser journey that starts at `/organizations` and reaches every nested management surface using the seeded graph.

## Seeder contract

`DemoOrganizationCrudSeeder` must:

- fail before writes when `APP_ENV=production`;
- require the canonical demo organization created by `DemoRestaurantSeeder` and fail clearly when run out of order;
- use factories for every created Eloquent record and factory `make()` attributes for deterministic updates;
- use explicit natural keys scoped by owner, organization, branch, menu or relation;
- restore only its own deterministic soft-deleted fixtures;
- never truncate, delete unrelated rows, claim an existing key from another tenant, or expose bearer tokens;
- write media only after database persistence and use deterministic owned demo paths;
- make repeated runs preserve row counts, primary keys, QR public tokens and media hashes;
- seed enough active, inactive, empty, populated and historical states for every management screen;
- remain directly testable on isolated SQLite with `Storage::fake('public')`.

The local application database is seeded only after all isolated tests and idempotency checks pass. Running the seeder is an explicit deliverable, not merely a documented command.

## Test architecture

Tests are grouped by evidence strength:

1. `DemoOrganizationCrudSeederTest` proves the complete factory-backed snapshot, natural-key ownership, production refusal, restoration, repeated-run counts/IDs/hashes and missing fixture regression.
2. Existing resource feature tests are split or extended so every operation in the matrix has a named positive case, validation case, permission denial and cross-tenant tampering case where applicable.
3. `OrganizationsCrudCoverageTest` owns a data set with all 26 inventory rows and asserts each maps to existing component/Action/test evidence. It does not replace behavior tests.
4. `ModelFactoryAuditTest` and `FactoryStatesTest` prove every first-party model has a valid default factory and every newly required lifecycle state persists.
5. Query-budget tests prove list reads remain bounded and do not become N+1 as the seeded graph grows.
6. Translation audit/scan and markup tests prove EN/LT/RU parity, labels, confirmations, validation association, durable keys and presentation-only Blade.
7. A Pest Browser/Chrome journey verifies real seeded pages, create/edit/cancel/delete or lifecycle controls, navigation, console/network, mobile layout, keyboard/focus and 200% zoom.

## Data and security behavior

- Eloquent only; no raw SQL in first-party code or Blade queries.
- All public Livewire state and action arguments are untrusted.
- Policies and branch/organization-scoped reloads remain mandatory even when controls are hidden.
- Destructive actions use confirmation, and critical lifecycle changes require an audit reason.
- Files use the configured public disk, safe deterministic demo paths or UUID upload names, content-aware validation and compensation.
- Existing local data is never refreshed. Fresh migration and repeated seed verification use an isolated temporary SQLite file and isolated storage root.

## Acceptance criteria

- Running `DemoRestaurantSeeder` on a non-production database produces one idempotent, fully populated organization administration graph and running it again changes no deterministic counts, IDs, tokens or file hashes.
- The actual local database is seeded successfully after isolated proof, and `/organizations` shows the canonical organization with working links to every authorized nested surface.
- Every one of the 26 inventory rows has supported create/read/update/delete or documented lifecycle-equivalent behavior, meaningful factory fixtures and executable operation-level tests.
- Missing CRUD gaps identified above are implemented without duplicating existing components or weakening permanent identity/history invariants.
- Existing multi-image dish requirements remain included and the primary image continues to drive guest cards.
- Targeted, full, parallel, coverage, Pint, Larastan, migrations, repeat seeds, translations, build, caches and disposable-browser acceptance have observed results.
- Unrelated staged, unstaged and untracked work remains preserved and no commit absorbs unattributable changes.

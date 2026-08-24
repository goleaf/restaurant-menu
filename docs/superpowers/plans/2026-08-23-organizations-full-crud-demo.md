# Organizations Full CRUD Demo Implementation Plan

**Execution status (2026-08-24): COMPLETE.** The executable matrix contains all 26 resources; the focused slice passes 179 tests/1,746 assertions; the dedicated browser journey passes 1/183 and the complete browser suite passes 5/376. All 76 migrations, two isolated idempotent seeds, production refusal, two additive local seeds, coverage 93.5%, static analysis, formatting, dependency audits, translations, Vite, caches and a post-build isolated Chrome smoke were observed. Historical checklist boxes below preserve the original TDD execution instructions and are not used as the current status ledger; current evidence is recorded in `docs/PROGRESS.md`.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `https://restaurant-menu.test/organizations` and every nested organization-management page a complete, factory-backed, tenant-safe CRUD demonstration, seed it into the local application database, and prove all supported operations with automated and browser tests.

**Architecture:** Preserve the canonical `DemoRestaurantSeeder` graph and extend it through a subordinate `DemoOrganizationCrudSeeder`. Every database record is created from an Eloquent factory; focused Actions own writes and transactions; policies and tenant-scoped reloads protect Livewire mutations; query services return bounded prepared data; Blade remains presentation-only. Domain identity and history use lifecycle equivalents such as disable, revoke, archive, detach, suspend, or soft delete instead of unsafe hard deletion.

**Tech Stack:** PHP 8.5, Laravel 13, SQLite, Livewire 4 class-based components, Flux UI Free 2, Blade SSR, Tailwind CSS 4, Pest 4, Pest Browser, local public filesystem, EN/LT/RU JSON translations.

---

## Execution contract

- Work in `/Users/andrejprus/Herd/restaurant-menu` on the current branch. Do not create another branch unless the user requests it.
- The checkout is shared and dirty. Before each task, inspect `git status --short --branch`, cached and unstaged diffs, untracked paths, and `git log -5 --oneline`. Preserve all concurrent work.
- Never reset, restore, stash, clean, broadly stage, rewrite history, force-push, or absorb unattributable hunks into a commit.
- Before editing any path, re-read its current contents, `.ai/rules/index.md`, every matching rule, and `grep -rin 'organization\|staff\|invitation\|service.point\|menu\|seed\|factory' .ai/rules` when `.ai/rules` exists.
- Re-read `AGENTS.md`, `docs/index.md`, `docs/requirements.md`, `docs/compliance-matrix.md`, `docs/architecture.md`, `docs/domain-model.md`, `docs/data-model.md`, the topic documents, `PRODUCT.md`, `DESIGN.md`, frontend/accessibility documents, and root `ROADMAP.md` before the first implementation edit.
- Use Laravel Boost `database-schema` before schema-sensitive work, `search-docs` before version-sensitive Laravel/Livewire APIs, `get-absolute-url` before browser testing, and recent `browser-logs` during acceptance.
- Do not start a development server. Laravel Herd already serves the application.
- Use RED -> GREEN -> REFACTOR for every behavior change. Observe the targeted test failing for the intended reason before implementation and passing afterward.
- All created Eloquent rows in demo seeders must originate from factories. Deterministic updates use attributes produced by `Factory::new()->make()`; no raw model inserts, query-builder inserts, raw SQL, or unrestricted deletes.
- Run the real local seeder only after isolated fresh-database and repeat-seed proof succeeds. Never run `migrate:fresh` against the application database.
- Commits listed below are conditional. Stage exact owned paths only when the slice is attributable and all relevant checks have passed. Do not push unless explicitly requested.

## Complete CRUD inventory

| # | Resource | Create | Read | Update | Delete or lifecycle equivalent | Required demo state |
|---|---|---|---|---|---|---|
| 1 | Organization | create | bounded accessible list | name and logo | confirmed soft delete | canonical organization with logo |
| 2 | Brand | create | bounded tenant list | name and logo | confirmed soft delete | three brands, one with logo |
| 3 | Branch | create | bounded authorized list | identity, locale, currency, active state and logo | confirmed soft delete | active and inactive branches |
| 4 | Branch public profile | created with branch | current profile | text, contact, social, logo and cover | remove media and clear optional fields | complete profile and deterministic media |
| 5 | Branch settings | create missing singleton | current settings | guest, order, polling, service, charge, tip, locale and currency | reset to validated defaults | complete settings on every branch |
| 6 | Opening hours | add intervals | weekly schedule | replace/edit intervals | remove interval, close day or disable schedule | regular, split-shift and closed days |
| 7 | Temporary closure | set closure | current closure | reason and end time | clear closure | one branch temporarily closed |
| 8 | Organization staff | add member | searched and paginated members | role and status | suspend/reactivate | all roles plus suspended member |
| 9 | Branch staff | assign member | searched and paginated assignments | branch role and waiter areas | suspend/reactivate/detach lifecycle | active and suspended assignments |
| 10 | Invitation | create link/code | searched and paginated history | immutable recipient/expiry | cancel pending | pending, expired and cancelled rows |
| 11 | Permission override | set allow/deny | grouped effective matrix | switch allow/deny | return to role default | one allow and one deny |
| 12 | Area node | create root/child | ordered tree | name, parent, icon, order and active state | confirmed soft delete | nested active and inactive areas |
| 13 | Service point | single/bulk create | search/filter/paginated list and board | identity, area, capacity, status and active state | guarded soft delete when no active session | multiple types/statuses, active/inactive |
| 14 | QR identity | generate | show/download/print | reissue | disable/revoke | active and historical QR identities |
| 15 | Kitchen department | create | ordered list | identity, type, order and active state | guarded delete | kitchen, bar and inactive custom department |
| 16 | Menu | create | ordered list | name, status and order | confirmed soft delete | active, draft and archived menus |
| 17 | Menu schedule | create interval | ordered intervals | day/start/end | delete interval | weekday and weekend intervals |
| 18 | Menu category | create root/child | ordered tree | base plus EN/LT/RU text, icon, order and active state | confirmed soft delete | localized active/inactive categories |
| 19 | Menu item | create | ordered list | base plus EN/LT/RU text, price, nutrition, allergens, diet, department and order | confirmed soft delete | localized available/unavailable dishes |
| 20 | Menu item images | upload up to eight | ordered gallery and primary | promote primary | remove one and parent cleanup | primary plus secondary images |
| 21 | Item availability | created with item | stop-list and catalog state | available/unavailable | unavailable hides from guest menu | both states |
| 22 | Modifier group | create | ordered list | required/min/max/order | delete group | required and optional groups |
| 23 | Modifier option | create | nested ordered list | name, price, availability and order | delete option | free, surcharge, discount, unavailable |
| 24 | Item-modifier assignment | attach | assigned groups | idempotent reattach | detach | assigned and unassigned items |
| 25 | Item variant | create | ordered item list | type, name, price, size, default, availability, order and EN/LT/RU | delete with default invariant | default, optional and unavailable variants |
| 26 | Branch subscription context | ensure | access evaluation | status transition | inactive lifecycle | active subscription for canonical organization |

Orders, guests, shared drafts, tickets, waiter calls, payments, audit logs and notifications remain seeded because they constrain deletion and provide realistic history. Their main operational CRUD pages are outside `/organizations`, so this plan does not duplicate those workflows.

## Observed baseline and exact gaps

- Existing graph: 1 organization, 3 brands, 4 branches, 12 users, 9 areas, 19 service points, 19 QR codes, 4 menus, 9 categories, 21 dishes and 32 variants.
- Empty management fixtures: opening hours, menu schedules, modifier groups/options, item-modifier assignments, waiter-area assignments, invitations, permission overrides, logos/covers and dish images.
- Missing operation surfaces: staff role reassignment, pending-invitation cancellation, guarded service-point deletion, schedule update, category/item EN/LT/RU editing and multi-image dish editing.
- Unbounded reads still exist for organizations, brands, branches, organization staff, branch staff and invitation histories.
- Existing feature tests cover many combined workflows but do not expose one operation-by-operation 26-resource evidence matrix.

## File map

### Create

- `database/seeders/DemoOrganizationCrudSeeder.php`
- `tests/Feature/DemoOrganizationCrudSeederTest.php`
- `tests/Support/OrganizationCrudMatrix.php`
- `tests/Feature/OrganizationsCrudCoverageTest.php`
- `app/Actions/Staff/UpdateOrganizationStaffRoleAction.php`
- `app/Actions/Staff/UpdateBranchStaffRoleAction.php`
- `app/Actions/Invitations/CancelInvitationAction.php`
- `app/Actions/ServicePoints/DeleteServicePointAction.php`
- `app/Actions/Menus/UpdateMenuAvailabilityScheduleAction.php`
- `app/Actions/Menus/SyncMenuCategoryTranslationsAction.php`
- `app/Actions/Menus/SyncMenuItemTranslationsAction.php`
- `tests/Feature/StaffRoleAndInvitationLifecycleTest.php`
- `tests/Feature/ServicePointDeletionTest.php`
- `tests/Feature/MenuTranslationManagementTest.php`
- `tests/Feature/OrganizationsQueryBudgetTest.php`
- `tests/Browser/OrganizationsCrudJourneyTest.php`
- `docs/organization-crud.md`

### Modify

- `database/seeders/DemoRestaurantSeeder.php`
- `database/factories/BranchOpeningHourFactory.php`
- `database/factories/MenuAvailabilityScheduleFactory.php`
- `database/factories/InvitationFactory.php`
- `database/factories/PermissionUserOverrideFactory.php`
- `database/factories/AreaNodeWaiterFactory.php`
- relevant organization/brand/branch/menu/media factories only when a missing explicit state is required
- `app/Services/Organizations/OrganizationQueryService.php`
- `app/Services/Organizations/BrandQueryService.php`
- `app/Services/Branches/BranchQueryService.php`
- `app/Services/Staff/StaffQueryService.php`
- organization, brand, branch and both staff Livewire index components and Blade views
- service-point Livewire index and Blade view
- menu catalog Livewire component, `app/Services/Menus/CatalogData.php`, and catalog Blade view
- existing focused feature tests for each resource
- `lang/en.json`, `lang/lt.json`, `lang/ru.json`
- `docs/requirements.md`, `docs/compliance-matrix.md`, `docs/architecture.md`, `docs/domain-model.md`, `docs/data-model.md`, `docs/seeding.md`, `docs/testing.md`, `docs/security.md`, `docs/frontend.md`, `docs/accessibility.md`, `docs/IMPLEMENTATION_PLAN.md`, `docs/PROGRESS.md`, `docs/DECISIONS.md`, and `CHANGELOG.md`

### Required subordinate plan

- Execute `docs/superpowers/plans/2026-08-23-menu-item-image-gallery.md` in full for inventory row 20. It owns the additive `menu_item_images` migration/model/factory, upload/promote/remove Actions, parent cleanup, edit-form UI, translations, tests and gallery-specific documentation.

## Task 0: Reconcile the shared checkout and load path-specific rules

**Files:** Read-only inspection.

- [ ] Run the complete ownership preflight:

```bash
pwd
git status --short --branch
git diff --cached --name-status
git diff --name-status
git log -5 --oneline
git diff --cached --check
```

Expected: repository root is `/Users/andrejprus/Herd/restaurant-menu`; the branch and every pre-existing staged/unstaged/untracked path are recorded; no file is changed.

- [ ] Read the mandatory documents and `.ai/rules` routing before touching code.
- [ ] Confirm installed versions and application state:

```bash
composer show laravel/framework --direct
composer show livewire/livewire --direct
composer show livewire/flux --direct
php artisan about --only=environment
php artisan migrate:status
```

Expected: Laravel 13, Livewire 4, Flux 2, PHP 8.5 and SQLite.

- [ ] Run the untouched baseline:

```bash
php artisan test --compact tests/Feature/DemoRestaurantSeederTest.php tests/Feature/ModelFactoryAuditTest.php tests/Feature/FactoryStatesTest.php tests/Feature/OrganizationManagementTest.php tests/Feature/BrandManagementTest.php tests/Feature/BranchManagementTest.php tests/Feature/StaffManagementUiTest.php tests/Feature/ServicePointCrudTest.php tests/Feature/MenuCrudTest.php tests/Feature/MenuScheduleTest.php
```

Expected: pass. Any failure observed before implementation is recorded as pre-existing and must not be reported as caused or fixed by this work without proof.

## Task 1: Make the 26-resource CRUD contract executable

**Files:**

- Create `tests/Support/OrganizationCrudMatrix.php`
- Create `tests/Feature/OrganizationsCrudCoverageTest.php`
- Create `docs/organization-crud.md`
- Modify `docs/requirements.md`
- Modify `docs/compliance-matrix.md`

- [ ] Generate the feature test:

```bash
php artisan make:test --pest OrganizationsCrudCoverageTest --no-interaction
```

- [ ] Add `Tests\Support\OrganizationCrudMatrix` with 26 keyed records. Each record must contain `resource`, `surface`, `create`, `read`, `update`, `delete`, `fixture`, `feature_test`, and `implementation_paths`.

Use this typed public contract:

```php
/**
 * @return array<string, array{
 *     resource: string,
 *     surface: string,
 *     create: string,
 *     read: string,
 *     update: string,
 *     delete: string,
 *     fixture: string,
 *     feature_test: string,
 *     implementation_paths: list<string>
 * }>
 */
public static function resources(): array
```

The keys are exactly:

```php
[
    'organization', 'brand', 'branch', 'branch_public_profile',
    'branch_settings', 'opening_hours', 'temporary_closure',
    'organization_staff', 'branch_staff', 'invitation',
    'permission_override', 'area_node', 'service_point', 'qr_identity',
    'kitchen_department', 'menu', 'menu_schedule', 'menu_category',
    'menu_item', 'menu_item_images', 'menu_item_availability',
    'modifier_group', 'modifier_option', 'item_modifier_assignment',
    'menu_item_variant', 'branch_subscription_context',
]
```

- [ ] Write the audit test before the support class. It must fail until all 26 records are present, all operation strings are non-empty, every implementation path exists, every evidence file exists under `tests/Feature` or `tests/Browser`, and resource keys are unique.
- [ ] Add the same matrix to `docs/organization-crud.md`, clearly labeling it as an implementation/evidence index rather than a second requirements catalogue.
- [ ] Add one stable umbrella requirement to `docs/requirements.md` for full `/organizations` CRUD demonstration and map it in `docs/compliance-matrix.md` to the matrix audit plus focused tests.
- [ ] Run:

```bash
php artisan test --compact tests/Feature/OrganizationsCrudCoverageTest.php
```

Expected RED before evidence paths are completed. Keep this test RED until the final behavior task; do not weaken it to accept missing evidence.

Conditional commit after the matrix is complete and green: `test(organizations): define full CRUD contract`.

## Task 2: Seed every missing administration state through factories

**Files:**

- Create `database/seeders/DemoOrganizationCrudSeeder.php`
- Create `tests/Feature/DemoOrganizationCrudSeederTest.php`
- Modify `database/seeders/DemoRestaurantSeeder.php`
- Modify only factory files needed for explicit lifecycle states

- [ ] Generate the files through Artisan:

```bash
php artisan make:seeder DemoOrganizationCrudSeeder --no-interaction
php artisan make:test --pest DemoOrganizationCrudSeederTest --no-interaction
```

- [ ] Write failing tests with `RefreshDatabase` and `Storage::fake('public')` for:

1. direct and parent seeder refusal in production before any write;
2. clear failure when the canonical `Demo Food Group` graph is absent;
3. factory-backed creation of every missing fixture listed below;
4. deterministic tenant ownership and natural keys;
5. second-run preservation of counts, IDs, QR public tokens and media hashes;
6. restoration of only owned deterministic soft-deleted fixtures;
7. no deletion or mutation of an unrelated organization graph;
8. all stored demo media exists on the configured public disk;
9. no raw invitation token or code appears in persisted presentation data, logs or exported snapshots.

- [ ] Implement the subordinate seeder with this entry contract:

```php
final class DemoOrganizationCrudSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Demo organization CRUD data cannot be seeded in production.');
        }

        $organization = Organization::query()
            ->select(['id', 'owner_user_id', 'name'])
            ->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)
            ->firstOrFail();

        $this->seedBranchAdministration($organization);
        $this->seedStaffLifecycle($organization);
        $this->seedMenuAdministration($organization);
        $this->seedOwnedMedia($organization);
    }
}
```

The parent seeder calls it only after the canonical organizations, brands, branches, users, roles, menus and operational history exist.

- [ ] Add reusable factory states rather than seeder-only attribute arrays. At minimum:

```php
public function weekday(int $dayOfWeek): static
{
    return $this->state(fn (): array => [
        'day_of_week' => $dayOfWeek,
        'starts_at' => '11:00',
        'ends_at' => '15:00',
    ]);
}

public function weekend(int $dayOfWeek): static
{
    return $this->state(fn (): array => [
        'day_of_week' => $dayOfWeek,
        'starts_at' => '12:00',
        'ends_at' => '23:00',
    ]);
}
```

- [ ] Seed all currently empty or absent showcase states:

1. seven-day opening-hours coverage with regular, split and closed days;
2. a bounded temporary closure on one non-primary branch;
3. weekday and weekend menu schedules;
4. required and optional modifier groups;
5. free, surcharge, discount and unavailable modifier options;
6. attached and unattached item-modifier relationships;
7. waiter-area assignments;
8. pending, expired and cancelled invitations using digests only;
9. one allowed and one denied non-critical permission override;
10. active and inactive organization/branch staff states;
11. inactive branch, area, service point, department, dish, option and variant examples;
12. deterministic organization/brand/branch logos, branch cover and representative dish gallery files;
13. the existing active subscription plus a status-transition fixture only if it does not block canonical access.

- [ ] Use factory-created attributes for deterministic updates:

```php
$attributes = BranchOpeningHourFactory::new()
    ->weekday($dayOfWeek)
    ->make(['branch_id' => $branch->id])
    ->only(['day_of_week', 'is_closed', 'opens_at', 'closes_at']);
```

Create records using relation factories or `Factory::for(...)->create()`. Resolve existing owned records by tenant-scoped natural key before creating; do not call `updateOrCreate()` with attributes that could claim another tenant's row.

- [ ] Prove RED then GREEN:

```bash
php artisan test --compact tests/Feature/DemoOrganizationCrudSeederTest.php tests/Feature/DemoRestaurantSeederTest.php tests/Feature/FactoryStatesTest.php tests/Feature/ModelFactoryAuditTest.php
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/DemoOrganizationCrudSeederTest.php tests/Feature/DemoRestaurantSeederTest.php tests/Feature/FactoryStatesTest.php tests/Feature/ModelFactoryAuditTest.php
```

Expected: both seeder entry points are production-safe, every fixture persists, and the second run is unchanged.

Conditional commit: `feat(seed): populate organization CRUD demo`.

## Task 3: Bound and search every growing administration list

**Files:**

- Modify `app/Services/Organizations/OrganizationQueryService.php`
- Modify `app/Services/Organizations/BrandQueryService.php`
- Modify `app/Services/Branches/BranchQueryService.php`
- Modify `app/Services/Staff/StaffQueryService.php`
- Modify matching Livewire index components and Blade views
- Create `tests/Feature/OrganizationsQueryBudgetTest.php`
- Modify existing organization/brand/branch/staff feature tests

- [ ] Write failing tests that create at least 31 factory records and prove:

1. only the requested page is rendered;
2. search is tenant-scoped and escapes no organization/branch boundary;
3. filter changes reset the relevant paginator;
4. staff and invitations use distinct named paginators on the same page;
5. query count stays within a fixed budget when result cardinality grows;
6. relationships used by Blade are eager-loaded.

- [ ] Convert list methods to `Paginator` return types with explicit selects. Use `simplePaginate()` because totals are not displayed. Example:

```php
/** @return Paginator<int, Organization> */
public function paginateAccessibleTo(User $user, string $search, int $perPage): Paginator
{
    return $user->organizations()
        ->wherePivot('status', OrganizationUserStatus::Active->value)
        ->when(trim($search) !== '', fn ($query) => $query
            ->where('organizations.name', 'like', '%'.trim($search).'%'))
        ->select([
            'organizations.id',
            'organizations.owner_user_id',
            'organizations.name',
            'organizations.logo_path',
            'organizations.created_at',
            'organizations.updated_at',
        ])
        ->orderBy('organizations.name')
        ->orderBy('organizations.id')
        ->simplePaginate($perPage, pageName: 'organizationsPage');
}
```

Keep the existing active-subscription scope from `accessibleTo()` in the final implementation.

- [ ] Add `WithPagination`, typed search properties, deferred bindings, `updatedSearch()` reset methods, durable row keys, accessible search labels, empty states and paginator links.
- [ ] Use these page names exactly: `organizationsPage`, `brandsPage`, `branchesPage`, `organizationStaffPage`, `organizationInvitationsPage`, `branchStaffPage`, `branchInvitationsPage`.
- [ ] Run:

```bash
php artisan test --compact tests/Feature/OrganizationsQueryBudgetTest.php tests/Feature/OrganizationManagementTest.php tests/Feature/BrandManagementTest.php tests/Feature/BranchManagementTest.php tests/Feature/StaffManagementUiTest.php
```

Expected: all pages are bounded, scoped and N+1-safe.

Conditional commit: `perf(organizations): paginate administration lists`.

## Task 4: Complete staff roles and invitation lifecycle

**Files:**

- Create `app/Actions/Staff/UpdateOrganizationStaffRoleAction.php`
- Create `app/Actions/Staff/UpdateBranchStaffRoleAction.php`
- Create `app/Actions/Invitations/CancelInvitationAction.php`
- Create `tests/Feature/StaffRoleAndInvitationLifecycleTest.php`
- Modify both staff Livewire components and views
- Modify `lang/en.json`, `lang/lt.json`, `lang/ru.json`

- [ ] Write failing positive, validation, authorization and tampering tests for:

1. organization owner changes a member to an assignable organization role;
2. branch manager changes a branch assignment only within permitted roles;
3. superadmin cannot be assigned through either UI;
4. an actor cannot escalate their own role or remove the last organization owner capability;
5. cross-organization membership and cross-branch assignment IDs return not found/forbidden without mutation;
6. only a pending invitation can be cancelled;
7. accepted, expired and already-cancelled invitations remain immutable;
8. cancellation clears acceptance capability but does not expose or log credentials;
9. critical role/status changes require the existing audit-reason contract.

- [ ] Use focused Action contracts:

```php
public function handle(
    User $actor,
    Organization $organization,
    OrganizationUser $membership,
    Role $role,
    string $reason,
): OrganizationUser
```

```php
public function handle(
    User $actor,
    Branch $branch,
    BranchUser $branchUser,
    Role $role,
    string $reason,
): BranchUser
```

```php
public function handle(User $actor, Organization $organization, Invitation $invitation): Invitation
```

Each Action authorizes, reloads the target through tenant-scoped Eloquent, validates role scope/status, mutates inside one transaction and writes a safe audit event without token/hash payloads.

- [ ] Add edit/cancel controls to the existing staff pages. Controls must use confirmations for cancellation, visible focus, translated labels, server-side error association and stable `wire:key` values.
- [ ] Run:

```bash
php artisan test --compact tests/Feature/StaffRoleAndInvitationLifecycleTest.php tests/Feature/StaffManagementUiTest.php tests/Feature/StaffInvitationTest.php tests/Feature/PermissionOverrideUiTest.php tests/Feature/AuditLogTest.php
```

Expected: lifecycle operations pass; privilege escalation and cross-tenant tampering fail safely.

Conditional commit: `feat(staff): complete membership and invitation lifecycle`.

## Task 5: Add guarded service-point soft deletion

**Files:**

- Create `app/Actions/ServicePoints/DeleteServicePointAction.php`
- Create `tests/Feature/ServicePointDeletionTest.php`
- Modify service-point Livewire component and view
- Modify translations

- [ ] Write failing tests for:

1. authorized soft deletion of an inactive/unused service point;
2. refusal when a direct active table session exists;
3. refusal when an active table-session link exists;
4. preservation of QR, order and audit history;
5. automatic disable/revoke behavior required by the existing QR invariant;
6. cross-branch and unauthorized IDs do not mutate;
7. repeated confirmation cannot delete a different selected row;
8. deleted rows disappear from normal pagination but remain restorable by the owned demo seeder.

- [ ] Implement:

```php
public function handle(User $actor, Branch $branch, ServicePoint $servicePoint): void
```

The Action authorizes, reloads through `$branch->servicePoints()`, checks active direct and linked sessions with `withExists`, throws the existing localized business-rule exception when occupied, disables/revokes current public identity as required, then soft-deletes inside one transaction. It never force-deletes historical rows.

- [ ] Add confirmed delete UI next to enable/disable. Hide controls for users without permission, but still authorize the action server-side.
- [ ] Run:

```bash
php artisan test --compact tests/Feature/ServicePointDeletionTest.php tests/Feature/ServicePointCrudTest.php tests/Feature/PermanentQrFunctionalTest.php tests/Feature/AuditLogTest.php
```

Expected: unused rows soft-delete; occupied and foreign rows remain unchanged.

Conditional commit: `feat(service-points): add guarded deletion`.

## Task 6: Complete menu schedules and EN/LT/RU category/item editing

**Files:**

- Create `app/Actions/Menus/UpdateMenuAvailabilityScheduleAction.php`
- Create `app/Actions/Menus/SyncMenuCategoryTranslationsAction.php`
- Create `app/Actions/Menus/SyncMenuItemTranslationsAction.php`
- Create `tests/Feature/MenuTranslationManagementTest.php`
- Modify catalog Actions, data service, Livewire component and view
- Modify `tests/Feature/MenuScheduleTest.php` and `tests/Feature/MenuCrudTest.php`
- Modify translations

- [ ] Write failing schedule tests for valid update, overlapping interval rejection, invalid time order, cross-branch ID, authorization denial and cache invalidation.
- [ ] Write failing translation tests for create/update/read/remove-blank behavior in all three locales, placeholder-free fallback behavior, cross-branch tampering and guest-menu rendering.
- [ ] Implement the schedule Action:

```php
public function handle(
    Branch $branch,
    MenuAvailabilitySchedule $schedule,
    int $dayOfWeek,
    string $startsAt,
    string $endsAt,
): MenuAvailabilitySchedule
```

It reloads the schedule through a menu owned by the branch, validates day/time and overlap excluding its own ID, updates inside the established menu transaction/cache boundary and returns the refreshed row.

- [ ] Implement translation synchronization with an exact payload shape:

```php
/** @param array{en: array{name: string, description?: string|null}, lt: array{name: string, description?: string|null}, ru: array{name: string, description?: string|null}} $translations */
public function handle(MenuCategory|MenuItem $translatable, array $translations): void
```

Use factories only in seeders/tests; production Actions use scoped Eloquent relations. Trim values, require a name for every supported locale according to the current requirement, update existing translation rows, and delete a locale row only when the validated contract permits blank optional content.

- [ ] Add three explicit locale fieldsets to category and item create/edit forms. IDs, labels, descriptions and errors must be translated and associated; mobile layout must not overflow.
- [ ] Keep Blade query-free. `CatalogData` eagerly loads translation relations and returns prepared arrays.
- [ ] Run:

```bash
php artisan test --compact tests/Feature/MenuScheduleTest.php tests/Feature/MenuTranslationManagementTest.php tests/Feature/MenuCrudTest.php tests/Feature/GuestMenuDisplayTest.php tests/Feature/FieldTranslationAuditTest.php
```

Also run the repository commands documented in `docs/testing.md`:

```bash
php artisan translations:scan --json
php artisan translations:audit
```

Expected: schedule update and EN/LT/RU management pass without new queries in Blade.

Conditional commit: `feat(menu): edit schedules and translations`.

## Task 7: Deliver the approved multi-image dish gallery

**Files:** All paths owned by `docs/superpowers/plans/2026-08-23-menu-item-image-gallery.md`.

- [ ] Execute Tasks 0-6 of the gallery plan without omitting any RED/GREEN, migration, filesystem-compensation, parent-cleanup, localization, accessibility, browser or documentation step.
- [ ] Reconcile the currently overlapping `Catalog.php` and `CatalogData.php` work before every patch. Never overwrite the concurrent staged rename or untracked service changes.
- [ ] Prove the master-plan row 20 contract:

```bash
php artisan test --compact tests/Feature/MenuSchemaTest.php tests/Feature/MenuItemImageGalleryTest.php tests/Feature/MenuCrudTest.php tests/Feature/LocalMediaStorageTest.php tests/Feature/ModelFactoryAuditTest.php
```

Expected: up to eight images upload inside dish edit, primary promotion/removal works, parent deletion cleans all owned files, guest cards keep using the primary image, and branch authorization holds.

Use the conditional gallery commits already specified by its subordinate plan.

## Task 8: Close operation-level evidence for all 26 resources

**Files:** Existing focused feature tests plus `tests/Feature/OrganizationsCrudCoverageTest.php`.

- [ ] For every inventory row, name at least one test for create, read, update and delete/lifecycle behavior. Each mutation needs positive and validation coverage; protected resources also need permission denial and cross-tenant tampering coverage.
- [ ] Split only when an existing test file becomes hard to navigate. Do not duplicate setup helpers or re-test framework behavior.
- [ ] Add matrix evidence paths only after the named behavior test exists and passes.
- [ ] Ensure singleton/pivot rows use the documented lifecycle semantics rather than artificial hard deletion.
- [ ] Run the complete organizations slice:

```bash
php artisan test --compact \
  tests/Feature/OrganizationsCrudCoverageTest.php \
  tests/Feature/OrganizationManagementTest.php \
  tests/Feature/BrandManagementTest.php \
  tests/Feature/BranchManagementTest.php \
  tests/Feature/BranchSettingsTest.php \
  tests/Feature/BranchOpeningHoursTest.php \
  tests/Feature/BranchTemporaryClosedModeTest.php \
  tests/Feature/StaffManagementUiTest.php \
  tests/Feature/StaffRoleAndInvitationLifecycleTest.php \
  tests/Feature/PermissionOverrideUiTest.php \
  tests/Feature/AreaNodeCrudTest.php \
  tests/Feature/ServicePointCrudTest.php \
  tests/Feature/ServicePointDeletionTest.php \
  tests/Feature/PermanentQrFunctionalTest.php \
  tests/Feature/KitchenDepartmentTest.php \
  tests/Feature/MenuCrudTest.php \
  tests/Feature/MenuScheduleTest.php \
  tests/Feature/MenuTranslationManagementTest.php \
  tests/Feature/MenuItemImageGalleryTest.php \
  tests/Feature/MenuItemVariantTest.php \
  tests/Feature/OrganizationSubscriptionTest.php
```

Expected: all listed tests pass and the matrix contains exactly 26 complete resources.

Conditional commit: `test(organizations): cover complete CRUD matrix`.

## Task 9: Prove isolated migrations and idempotent seeds, then seed local data

**Files:** No application code changes unless a test reveals a defect.

- [ ] Create isolated temporary database and storage paths without touching `database/database.sqlite`:

```bash
CRUD_DB_PATH="$(mktemp "${TMPDIR:-/tmp}/restaurant-menu-crud.sqlite.XXXXXX")"
CRUD_STORAGE_PATH="$(mktemp -d "${TMPDIR:-/tmp}/restaurant-menu-crud-storage.XXXXXX")"
DB_CONNECTION=sqlite DB_DATABASE="$CRUD_DB_PATH" FILESYSTEM_DISK=public php artisan migrate:fresh --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$CRUD_DB_PATH" FILESYSTEM_DISK=public php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$CRUD_DB_PATH" FILESYSTEM_DISK=public php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
```

Use a test-specific public disk root through config/environment already supported by the repository; if none exists, keep storage isolation inside `DemoOrganizationCrudSeederTest` and do not add a new runtime dependency.

Expected: migrations and both seed runs succeed; the test assertions prove counts/IDs/tokens/hashes are identical.

- [ ] Verify direct production refusal:

```bash
APP_ENV=production php artisan db:seed --class=DemoOrganizationCrudSeeder --no-interaction
```

Expected: non-zero exit with `Demo organization CRUD data cannot be seeded in production.` and no writes.

- [ ] Inspect the actual local environment and current counts read-only. Do not expose credentials or invitation secrets.
- [ ] After all isolated tests pass, run the deliverable local seed twice:

```bash
php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
```

Expected: both runs succeed; the second run preserves deterministic identifiers and counts. Verify the 26 fixture categories through Eloquent/Boost read-only inspection.

- [ ] Remove only the explicitly created temporary database/storage paths after resolving their exact values. Never use a broad recursive target.

## Task 10: Browser acceptance from `/organizations`

**Files:** Create `tests/Browser/OrganizationsCrudJourneyTest.php`; application files only when a reproducible defect is found.

- [ ] Resolve the URL with Laravel Boost `get-absolute-url('/organizations')`.
- [ ] Use Pest Browser or Chrome DevTools with a fresh disposable isolated profile. Never attach to a personal or another agent's Chrome profile.
- [ ] Authenticate as the existing fictitious demo owner through the approved non-production mechanism without printing a password/token.
- [ ] Navigate this exact route chain:

1. organizations list and edit;
2. organization staff and permissions;
3. brands and branches;
4. branch settings/profile/hours/closure;
5. areas;
6. service points and QR show/print;
7. branch staff and waiter zones;
8. menu catalog/schedules/categories/items/gallery;
9. availability;
10. modifiers;
11. variants;
12. kitchen departments.

- [ ] Use disposable records with a stable `Browser CRUD` prefix for reversible create/edit/lifecycle checks. Delete/disable only those browser-created records; never mutate canonical history fixtures needed by tests.
- [ ] Assert no uncaught console errors, failed application requests, horizontal overflow at 375px and 1440px, inaccessible icon-only controls, broken keyboard focus, lost modal focus, or unusable 200% zoom layout.
- [ ] Run:

```bash
php artisan test --compact tests/Browser/OrganizationsCrudJourneyTest.php
```

Expected: the full nested journey passes against the freshly seeded local graph.

## Task 11: Documentation, formatting, static analysis and complete gates

**Files:** Documentation and all changed source/test paths.

- [ ] Update canonical requirements/compliance evidence, architecture/data model for new gallery storage, seeding fixture inventory, security/file lifecycle, testing matrix, frontend/accessibility behavior, implementation/progress/decision ledgers and changelog. Do not create another requirement catalogue.
- [ ] Record fresh before/after demo counts and query budgets; never claim unobserved results.
- [ ] Run PHP formatting after PHP changes:

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] Run targeted tests again, then complete gates:

```bash
composer validate --strict
composer audit --locked
composer analyse
php artisan test --compact
php artisan test --compact --parallel
composer test:coverage
npm audit --audit-level=moderate
npm run build
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan optimize:clear
```

- [ ] Run the repository translation audit/scan commands from `docs/testing.md` and prove EN/LT/RU key/placeholder parity.
- [ ] Re-run the browser journey after the production build and cache cycle.
- [ ] Review all owned diffs and the shared checkout:

```bash
git diff --check
git diff --stat
git diff --cached --check
git status --short --branch
```

Expected: no whitespace errors; all applicable gates pass; unrelated work remains byte-for-byte preserved; any skipped/external gate is reported exactly.

Conditional final documentation commit: `docs(organizations): record full CRUD delivery`.

## Final acceptance checklist

- [ ] All 26 resources have documented create/read/update/delete or domain-safe lifecycle semantics.
- [ ] All 26 resources have factory-created demo fixtures and named executable test evidence.
- [ ] `DemoRestaurantSeeder` remains the single canonical entry point and invokes `DemoOrganizationCrudSeeder` in the correct order.
- [ ] Direct and parent demo seeders refuse production before writes.
- [ ] Two isolated seed runs preserve counts, IDs, tokens and file hashes.
- [ ] Two actual local seed runs succeed after isolated proof.
- [ ] No unrelated tenant data is claimed, truncated, refreshed, deleted or overwritten.
- [ ] Organization/brand/branch/staff/invitation growing lists are bounded, searchable, eager-loaded and query-budget tested.
- [ ] Staff roles can be safely reassigned and pending invitations safely cancelled.
- [ ] Service points use guarded soft deletion and preserve identity/history.
- [ ] Menu schedules can be updated.
- [ ] Category and dish EN/LT/RU content can be created and edited.
- [ ] Dish editing supports up to eight images, primary promotion and per-image removal.
- [ ] Policies and tenant-scoped reloads protect every Livewire mutation.
- [ ] User-facing copy has EN/LT/RU parity and accessible controls.
- [ ] Targeted, full, parallel, coverage, Pint, Larastan, audit, migration, seed, build, cache and browser results are observed and recorded.
- [ ] Final commits contain only attributable verified work; no push occurs without explicit request.

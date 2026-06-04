# Restaurant Menu SaaS

Laravel SaaS foundation for restaurants, cafes, bars, hotels, food courts, and similar venues.

This project is not only a QR menu. The current codebase is a clean shared-hosting-friendly foundation for the platform, with authentication, system roles, permissions, organizations, simple SaaS subscription status, brands, branches, branch settings, branch service modes, local media storage, local SQLite backup download, nested branch areas, service point schema and CRUD, branch menu CRUD, multiple active branch menus, menu schedules, menu translations, menu modifiers, kitchen departments, guest menu display with modifier selection, table session schema, guest-created pending sessions, guest join approval UI, guest invite share links, guest table page shell, guest waiter-call and bill requests, database notifications with unread polling UI, draft order schema, shared table cart UI, guest ready status, guest item editing, polished waiter dashboard UX, waiter table detail, waiter draft editing/confirmation/rejection, repeat orders in the same table session, real order snapshots, kitchen/bar dispatch tickets, polished kitchen and bar production screens, waiter ready/served handoff, manual offline payments, branch/restaurant dashboard, basic cached analytics, audit logs, permanent QR schema, generation, admin display page, simple and bulk browser print templates, public QR guest landing with mobile-first error pages, basic superadmin access, staff invitation foundations, simple staff management UI, and staff permission override UI.

## Stack

- Laravel 13
- Livewire 4
- Blade server-rendered UI
- SQLite
- Database cache
- Database sessions
- Database queue
- Local public storage in `storage/app/public`

The project intentionally does not use Redis, WebSockets, Docker as a requirement, S3, paid external services, React, Vue, or a separate SPA frontend.

## Shared Hosting Deployment

Deployment notes for a classic shared-hosting setup live in:

```text
docs/DEPLOY_SHARED_HOSTING.md
```

The guide covers SQLite, writable directories, `storage:link` and shared-hosting alternatives, migrations, database cache, database sessions, database queue, optional scheduler cron, local storage boundaries, and files that must never be committed.

## Current Version Snapshot

A short developer/coding-agent overview of the current domain model, QR/session flow, shared-hosting mode, limitations, and next-step guardrails lives in:

```text
docs/CURRENT_VERSION.md
```

## Project Memory

Daily project memory for future coding-agent sessions is maintained in:

```text
docs/AI_CONTEXT.md
docs/TEST_CHECKLIST.md
docs/NEXT_STEPS.md
```

Read `docs/AI_CONTEXT.md` before every prompt. It records the current stack, implemented areas, tables, routes, Livewire components, mandatory business rules, shared-hosting constraints, forbidden infrastructure, and the next recommended prompt. `docs/TEST_CHECKLIST.md` keeps the manual and focused regression flow. `docs/NEXT_STEPS.md` keeps scoped future prompts that must be implemented only when explicitly requested.

Latest memory refresh: 2026-06-04 after Prompt 105 and the follow-up daily memory update. Branch public profiles, branch opening hours, temporary branch closed mode, menu availability schedules, multiple active branch menus, and branch service modes are now part of the baseline guest QR context.

The daily memory refresh after Prompt 105 is documentation-only and keeps the next recommended small step in `docs/NEXT_STEPS.md`.

## Project Cleanup Consistency

Prompt 098 cleaned remaining starter-kit and temporary surfaces without adding new product features.

- Composer metadata now identifies this repository as `goleaf/restaurant-menu`.
- `laravel/sail` was removed from dev dependencies; Docker remains unnecessary.
- Runtime filesystem config exposes only local `local` and `public` disks; no S3 disk is available.
- Default seeding creates only system data, optional first superadmin, and standard kitchen departments. It no longer creates `test@example.com`.
- Public entry and guest fallback pages no longer contain placeholder or "not implemented yet" copy.
- Unused starter-kit header layout and its repository/documentation icon overrides were removed.
- Routes, migrations, model naming, seeders, and policy usage were checked; no new business tables or routes were added. The project currently keeps authorization in actions, middleware, and user permission helpers rather than dedicated policy classes.

Focused cleanup regression command:

```bash
php artisan test --compact tests/Feature/ProjectCleanupConsistencyTest.php
```

## Vertical Slice Review

Prompt 099 added a first end-to-end vertical-slice regression test for the main restaurant flow. The test starts with real Fortify registration, creates the organization/brand/branch/zone/service point setup, generates one permanent QR, opens the public QR guest page, runs the two-guest invite and approval flow, adds guest draft items, sends the shared draft to the waiter, confirms and dispatches the order to kitchen/bar, marks kitchen/bar items ready, marks them served, requests the bill, records manual payment, closes the table session, and verifies the permanent QR remains unchanged.

Focused vertical-slice command:

```bash
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php
```

Affected-flow regression command:

```bash
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php tests/Feature/OnboardingRestaurantWizardTest.php tests/Feature/GuestCreatedPendingSessionTest.php tests/Feature/GuestInviteShareLinkTest.php tests/Feature/GuestJoinApprovalUiTest.php tests/Feature/GuestMenuDisplayTest.php tests/Feature/WaiterDraftReviewTest.php tests/Feature/KitchenTicketDispatchTest.php tests/Feature/KitchenScreenTest.php tests/Feature/BarDepartmentScreenTest.php tests/Feature/ReadyItemsToWaiterTest.php tests/Feature/BillRequestTest.php tests/Feature/ManualPaymentTest.php tests/Feature/TableSessionCloseTest.php
```

No Redis, WebSockets, S3, Docker, external queue, online payment, or paid service is used by this vertical slice.

## Access Control

Access is organization-scoped first and branch-scoped when a user has active `branch_users` assignments. Regular users see only active organizations where they have an active membership. If a staff member is assigned to specific branches, branch lists, branch admin pages, QR print/display pages, waiter/payment resolvers, kitchen/bar screens, and exports must stay limited to those branches.

Prompt 096 added a focused access-control audit covering normal organization isolation, branch assignment isolation, waiter price restrictions, cook staff restrictions, marketer order-confirmation restrictions, accountant payment visibility without menu editing, and the superadmin platform-wide bypass.

Focused regression command:

```bash
php artisan test --compact tests/Feature/AccessControlAuditTest.php
```

## Simple Design System

Reusable Blade UI primitives live in:

```text
resources/views/components/ui
```

Current primitives cover:

- buttons;
- cards;
- status badges;
- form fields;
- empty states;
- alerts and warnings;
- mobile bottom action bars for guest screens;
- clear area and service point icons using the existing Flux icon set.

The design system is intentionally small: Tailwind CSS, Blade components, Livewire-friendly attributes, and existing Flux icons only. It does not add React, Vue, a SPA frontend, a heavy UI framework, WebSockets, Redis, S3, Docker, or external services.

The first applied screens are guest QR/table/menu actions, branch area management, and branch service point management.

## Guest Mobile UI

The public QR guest flow is mobile-first and stays inside Blade + Livewire. The guest entry screen shows the venue logo/name, current zone/place, QR short code, a large name field, and one primary `Войти за стол` action.

Active guests see a polished table page with:

- an active table header with current service point data;
- a clear `Позвать официанта` action;
- an alphabetically sorted guest list with ready/status badges;
- mobile dish cards with photos, prices, availability, and a large add button;
- bottom sheets for item modifiers and editing own draft positions;
- a shared table cart grouped by guest;
- per-guest totals and the table total;
- a sticky bottom action bar for `Я готов`, `Отправить официанту`, and `Попросить счёт`.

This UI polish does not change guest business rules: guests are still not user accounts, the menu still uses database cache, draft reads still come from SQLite, and realtime behavior still uses isolated Livewire polling blocks rather than WebSockets.

## Restaurant Public Profile

Each branch has a public restaurant profile used by the QR landing page and guest table UI.

The profile stores:

- public venue name with fallback to the branch name;
- short description with a guest-friendly fallback;
- local logo and cover image;
- address, city, country;
- phone, email, website;
- Instagram, Facebook, and TikTok links as plain external links;
- default language from branch settings;
- default currency from branch settings / branch currency.

Public profile editing lives on the existing branch settings page:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/settings
```

Images are uploaded to the local public disk under `storage/app/public/media/...`. No maps, external APIs, social integrations, S3, paid services, React, Vue, or WebSockets are used. The public QR URL stays `/q/{public_token}` and does not expose branch or service point IDs.

## Branch Opening Hours

Each branch can store a weekly opening schedule from the branch settings page. The schedule supports closed days and multiple time intervals per day, and status checks use the branch timezone.

When opening hours are configured and the branch is currently closed, the public QR page still opens and guests can still view the restaurant profile and menu. Guest ordering actions are blocked with clear text such as `Сейчас закрыто` and `Откроется в 10:00`. If a branch has no schedule configured, opening hours do not block ordering.

The schedule is stored locally in SQLite in `branch_opening_hours`. No external calendar, map, booking, or paid service is used.

## Temporary Branch Closed Mode

Each branch can be temporarily closed from the existing branch settings page without disabling its permanent QR codes. The mode stores a required human reason, an optional `closed until` date/time in the branch timezone, and keeps QR/menu browsing available for guests.

While temporary closed mode is active, the guest QR page shows a clear message such as `Ресторан временно закрыт`, includes the closure reason, and blocks new draft item creation or sending a draft to the waiter. The waiter dashboard also shows the branch warning and lets staff with order access reopen ordering with one action. No external APIs, maps, paid services, Redis, WebSockets, S3, or Docker are used.

## Multiple Branch Menus

A branch can have several active menus at the same time, for example:

- main menu;
- breakfast;
- business lunch;
- bar menu;
- wine card;
- kids menu;
- seasonal menu;
- special menu.

These are stored in the existing `menus` table through the menu name, `status`, and `sort_order`; no new menu-type table or external service is required. The guest QR menu now groups available dishes by menu, keeps menus sorted by `sort_order`, `name`, and `id`, hides inactive/draft/archived menus from guests, and respects `menu_availability_schedules` in the branch timezone. Active menus that are scheduled for later can be shown as `Будет доступно позже` without exposing their dishes for ordering.

The guest menu still uses SQLite-backed database cache through `GetGuestMenuForBranchAction`. Existing menu, category, item, modifier, translation, availability, and schedule changes continue to clear branch cache through the centralized cache invalidation flow.

## Branch Service Modes

Each branch can enable one or more service modes from the existing branch settings page:

- dine-in;
- pickup;
- delivery;
- hotel room service;
- bar only;
- custom.

The selected modes are stored in `branch_settings.service_modes` as a local SQLite JSON list. The safe default is `dine_in`, which keeps existing QR/table behavior unchanged.

This is foundation only: `dine_in` stays the current QR table mode, `pickup` can later be paired with existing pickup-style service points, and `delivery` is only future-ready. No maps, couriers, online payments, external APIs, Redis, WebSockets, S3, Docker, React, Vue, or paid services are added.

## Database Notifications

Operational notifications are stored in Laravel's local `notifications` table and are delivered only through the `database` channel.

Current database notification events:

- new guest join request;
- new draft order sent to waiter;
- guest called waiter;
- guest requested the bill;
- waiter confirmed a guest draft order;
- kitchen/bar started preparing an item;
- kitchen/bar marked an item ready;
- waiter rejected a draft order.

Authenticated staff see an unread notification panel in the app layout for new orders, waiter calls, bill requests, and ready items. The panel polls only its own visible Livewire block every 5 seconds and can mark one notification or all notifications read locally.

Active guests see a small notification block on the public QR table page for join approvals, confirmed or rejected orders, and kitchen/bar item progress. The guest block polls only its own visible block every 2 seconds and stores notifications against `table_session_guests`; guests are still not user accounts.

No Push, WebSocket, Redis, SMS, Telegram API, or paid notification provider is used.

## Basic Localization

The interface has a lightweight localization foundation for:

- `ru`
- `en`
- `lt`

Supported languages are fixed in `App\Enums\SupportedLocale`. Authenticated users store their admin interface language in `users.locale` and can change it from the profile settings page. The web middleware applies the authenticated user's locale on each request.

Guest QR pages use the branch default language from `branch_settings.default_language` unless the guest chooses another supported language. The selected guest language is carried through the `lang` query parameter and is passed to the guest menu. Menu category and dish translations still use `menu_category_translations` and `menu_item_translations`; if a translation is missing, the guest menu falls back to the base/default menu text.

Baseline UI strings live in local JSON translation files:

```text
lang/en.json
lang/ru.json
lang/lt.json
```

No AI translation, external translate API, or paid localization service is used.

## Guest Error Pages

Public QR and guest-session errors use a dedicated mobile-first Blade component at `resources/views/components/guest-error-panel.blade.php`.

Covered guest-facing states:

- QR token not found;
- QR code disabled or revoked;
- inactive service point;
- restaurant temporarily unavailable through an inactive organization subscription;
- closed table session;
- rejected, removed, or left guest entry;
- stale or closed invite link.

The error UI shows clear human text, keeps technical IDs hidden, and gives a safe action such as returning to the QR page or start page when that is possible. These pages do not add ordering behavior and do not use Redis, WebSockets, S3, Docker, or external services.

## Currency Settings

Branch currency is local and fixed to a supported list in `App\Enums\SupportedCurrency`. The default is `EUR`.

Currency can be selected when creating/editing a branch and from branch settings. Branch settings store the chosen value in `branch_settings.default_currency`, and the application keeps it synced with `branches.currency` because guest screens, orders, payments, analytics, and exports use the branch currency.

Prices are not converted automatically. Menu item prices and modifier price deltas remain the exact values entered by staff; the selected branch currency only controls display formatting. Common examples:

```text
EUR -> €14.50
USD -> $14.50
PLN -> 14.50 PLN
```

No exchange-rate API, paid currency service, or external financial integration is used.

## Superadmin Access

`superadmin` is a platform-level role for SaaS administration. Superadmins can access the platform dashboard at:

```text
/superadmin/dashboard
```

The platform dashboard shows organizations, brands, branches, service points, orders, and users across the whole SaaS platform. Regular users do not see the platform dashboard link and receive `403 Forbidden` if they open the superadmin URL directly.

For each organization, superadmin can see:

- subscription status;
- activity state;
- owner email;
- started and next-payment dates;
- manual payment status;
- brand count;
- total and active branch count;
- service point count;
- order count.

Organization actions on the platform dashboard:

- open existing organization details;
- open the audit log;
- suspend organization access;
- reactivate organization access.

Impersonation is not implemented yet.

The first superadmin can be created by setting these values in `.env` before running the database seeder:

```text
SUPERADMIN_NAME="Platform Superadmin"
SUPERADMIN_EMAIL=admin@example.com
SUPERADMIN_PASSWORD=change-this-password
```

Then run:

```bash
php artisan db:seed
```

Do not commit real superadmin credentials. The seeder stores only the user and assigns the fixed `superadmin` role.

## Demo Seed

A local demo restaurant can be seeded explicitly after migrations:

```bash
php artisan db:seed --class=DemoRestaurantSeeder
```

The demo seed is intentionally not called from the default `DatabaseSeeder`; run it only when you want sample data for development, QA, or a fresh shared-hosting installation.

It creates:

- organization `Demo Food Group`;
- brand `Bella Pizza`;
- branch `Demo Old Town`;
- zones `Главный зал`, `Терраса`, and `Бар`;
- seven service points with one active permanent QR each;
- an active demo menu with pizza, drinks, and dessert categories;
- several dishes with kitchen/bar/dessert department assignment and ru/en/lt menu translations;
- demo staff users for owner, restaurant admin, waiter, chef, bartender, and cashier.

Demo user emails:

```text
demo.owner@example.com
demo.admin@example.com
demo.waiter@example.com
demo.chef@example.com
demo.bartender@example.com
demo.cashier@example.com
```

The default password for demo users is `password`. Change or remove these accounts before using real production data. Re-running the demo seeder updates the same demo records and should not create duplicate demo restaurants, service points, menus, or active QR codes.

## Smoke Test Checklist

The main manual flow checklist lives in:

```text
docs/TEST_CHECKLIST.md
```

It covers branch, zone, service point, QR, guest join approval, shared draft, waiter confirmation, kitchen/bar ready, served handoff, bill request, manual payment, and table-session close. It is intentionally a lightweight manual checklist and does not add a heavy browser test dependency.

## SaaS Subscription

Organizations have one local SaaS subscription record in `organization_subscriptions`. There is only one plan for everyone, and the system does not enforce tariff limits.

The subscription stores:

- `status`: `active` or `inactive`;
- `started_at`;
- `next_payment_at`;
- `payment_status`: `pending`, `paid`, `overdue`, or `failed`.

New organizations created through the application receive an active default subscription with a pending manual payment status. Superadmins can activate or suspend an organization from `/superadmin/dashboard`.

When an organization is explicitly inactive, regular users can no longer access that organization workspace. Superadmins keep platform-level access so they can reactivate it. Deactivation does not delete restaurants, menus, QR codes, guests, orders, payments, or audit logs.

No Stripe, PayPal, online acquiring, paid billing provider, webhook, Redis, WebSocket, or external billing service is used.

## Staff Management

Staff management is available only to users who have the `manage_staff` permission in the current organization context.

Organization staff is managed at:

```text
/organizations/{organization}/staff
```

Branch staff is managed at:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/staff
```

The UI can:

- list organization staff;
- list branch staff;
- add a staff member manually;
- assign a fixed system role;
- create an invite link;
- create an invite code;
- activate or deactivate staff members.

Staff permission overrides are managed at:

```text
/organizations/{organization}/staff/{staffMember}/permissions
```

The employee permission page keeps the fixed staff role unchanged and lets a manager set each permission to `default`, `allow`, or `deny`. `default` removes the user override and falls back to role permissions. `allow` and `deny` save a user override in `permission_user_overrides`. Superadmins always keep full computed access.

Changing critical permissions shows a warning, and users cannot edit their own permission overrides from this page.

Staff invitations are stored in the `invitations` table. An invitation can be scoped to an organization, optionally to a brand, and optionally to a branch. It stores the fixed role to assign later, email, phone, invite token, invite code, expiration date, status, and the user who created the invitation.

Supported statuses are:

- `pending`
- `accepted`
- `expired`
- `cancelled`
- `rejected`

This stage does not send email or SMS. The user copies invite links or invite codes manually and sends them outside the system.

## SQLite

SQLite is the only configured database connection.

For full shared-hosting deployment notes, see `docs/DEPLOY_SHARED_HOSTING.md`.

The default database file is:

```text
database/database.sqlite
```

This file is inside the project and outside `public/`, which keeps it suitable for shared hosting when the web root points to `public/`.

`.env.example` leaves `DB_DATABASE` empty so Laravel uses the safe default from `config/database.php`.

### SQLite Performance Recommendations

This project is tuned for small shared-hosting deployments where SQLite, PHP, and local files are enough.

Keep these guardrails in place:

- keep the SQLite file outside `public/`;
- keep `CACHE_STORE=database`, `SESSION_DRIVER=database`, and `QUEUE_CONNECTION=database`;
- do not add Redis, WebSockets, S3, Docker, or external queue/cache services;
- keep polling scoped to isolated Livewire blocks, not full-page refreshes;
- keep waiter/kitchen/bar polling queries limited to selected columns and bounded result sets;
- keep restaurant dashboard and analytics snapshots in the database cache;
- use pagination for growing history/list screens such as audit logs;
- do not load audit/history relationships unless the current page needs them.

Prompt 083 added extra indexes for hot SQLite paths: database notifications, service point lists, active table sessions, join requests, latest/sent drafts, draft items, confirmed-order dashboard reads, kitchen/bar ticket polling, ready unserved ticket items, and audit log history.

## Local Backups

Superadmins can download the current SQLite database file from the platform dashboard or directly at:

```text
/superadmin/backups/sqlite
```

The route is protected by `auth` and `superadmin` middleware. Regular users receive `403 Forbidden` and do not see the backup controls.

The download streams the configured SQLite file, normally:

```text
database/database.sqlite
```

No backup file is created on the server during this action. If you manually store backup copies on shared hosting, keep them outside the public web root and outside git. A backup contains sensitive data: users, staff access, guest sessions, orders, payments, guest tokens, and audit records.

Do not commit downloaded or manually created backup files. The repository already ignores SQLite database files under `database/`, and `storage/app/` is reserved for local generated files. Future media ZIP export should read from `storage/app/public` and must stay local, without S3 or paid backup services.

## Data Exports

Restaurant CSV exports are available at:

```text
/restaurant/exports
```

Access requires the flexible `export_data` permission in the current organization context. Active branch assignments are respected, so a user with access to only one branch can export only that branch. Superadmins can export all branches.

Current CSV downloads:

- orders;
- payments;
- menu;
- tables / service points.

The export routes stream CSV responses directly and do not create export files on the server. PDF export is planned for a later step. Exports can contain guest names, staff names, order history, and payment data, so downloaded files should be stored carefully and kept out of git.

## Local Media Storage

Media files are stored locally on Laravel's `public` disk. The disk root is:

```text
storage/app/public
```

The public web path is served through:

```text
public/storage
```

On shared hosting, make sure these paths are writable by PHP:

- `storage/app/public`
- `storage/framework`
- `storage/logs`

The public storage link should point from `public/storage` to `storage/app/public`. When shell access is available, run:

```bash
php artisan storage:link
```

If symbolic links are not available on a shared host, configure the host so `public/storage` exposes the same files from `storage/app/public`.

Organization, brand, and branch logos are stored in local folders under `storage/app/public/media`. Current logo paths are saved in `logo_path` columns on `organizations`, `brands`, and `branches`.

Current logo upload rules:

- images only;
- allowed extensions: `jpg`, `jpeg`, `png`, `webp`;
- maximum size: 2 MB.

Dish images use the same local public storage approach and are stored under branch-scoped menu item folders in `storage/app/public/media`.

## Branch Menu Management

The base menu tables are:

- `menus`
- `menu_availability_schedules`
- `menu_categories`
- `menu_items`
- `kitchen_departments`
- `modifier_groups`
- `modifier_options`
- `menu_item_modifier_groups`

Each menu belongs to a branch through `branch_id`, stores a name, a fixed status, and a sort order. Current menu statuses are `draft`, `active`, and `archived`.

Menu availability schedules are stored in `menu_availability_schedules`. Each row belongs to one menu and stores ISO weekday `day_of_week` (`1` Monday through `7` Sunday), local `starts_at`, and local `ends_at`. Status checks use `branches.timezone`. A menu with no schedule rows is available all day for backward compatibility. A schedule where the end time is earlier than or equal to the start time is treated as an overnight interval, so examples such as `22:00-02:00` and all-day `00:00-00:00` are supported without external services.

Menu categories belong to one menu and can be nested with `parent_id`. They store name, optional description, optional image path, optional icon, sort order, and `is_active`.

Menu items belong to one menu and one category. They can be assigned to one branch kitchen department through `kitchen_department_id`; when the admin leaves the selector on `Default kitchen`, the system stores the branch's default `kitchen` department. They store name, optional description, price, optional image path, optional weight, optional volume, optional calories, availability, and sort order.

Organizations, brands, branches, area nodes, service points, menus, categories, and menu items use soft deletes. Normal admin and guest lists hide soft-deleted records, while historical orders keep working from immutable `order_items` snapshots such as dish name, guest name, selected modifiers, and prices. Deleting or renaming a live menu item does not rewrite old confirmed orders.

Kitchen departments are stored per branch in `kitchen_departments`. Supported department types are `kitchen`, `bar`, `dessert`, `hookah`, and `custom`. New branches created through the backend action receive standard departments for kitchen, bar, dessert, and hookah; custom departments are created manually. Departments can be enabled or disabled, sorted, renamed, and assigned to dishes from the branch menu admin page. Typical routing is pizza to kitchen, coffee to bar, desserts to dessert, and hookah items to hookah.

Menu modifiers are managed in the same branch menu admin page. A modifier group belongs to a branch and stores `name`, `is_required`, `min_select`, `max_select`, and `sort_order`. Modifier options belong to a modifier group and store `name`, `price_delta`, `is_available`, and `sort_order`. The `menu_item_modifier_groups` pivot assigns reusable branch modifier groups to dishes, so examples like pizza size, doneness, extra cheese, milk type, or syrup can be attached without duplicating group definitions.

Category and dish translations are stored separately in:

- `menu_category_translations`
- `menu_item_translations`

Each translation belongs to its base category or dish, stores `language_code`, translated `name`, and optional translated `description`. Current supported guest menu languages are `ru`, `en`, and `lt`. If a selected language has no translation for a category or dish, the guest menu falls back to the base category or dish text.

Branch menu management is available at:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/menu
```

Access to the full menu CRUD requires `manage_menu` in the current organization context. Users can create, edit, sort, and delete menus, categories, dishes, kitchen departments, modifier groups, modifier options, and dish modifier assignments. Dish photos are uploaded locally to Laravel's `public` disk. Changing prices or modifier price deltas requires `change_prices`; changing dish or modifier option availability requires `change_availability`. Changing a dish department assignment clears the guest menu database cache.

The same branch menu page also contains a simple stop-list for users with `change_availability`. The stop-list uses the existing `menu_items.is_available` field instead of a separate table: staff can temporarily mark a dish out of stock or return it to the menu, while users without `manage_menu` do not see menu CRUD forms. A fixed `head_chef` role can be granted `change_availability` through the normal role-permission toggle system.

Stop-listed dishes remain visible in the admin UI and in the guest menu as `Нет в наличии`, but guests cannot add them to the shared draft order. Availability changes clear the branch guest-menu database cache and create `menu_availability_changed` audit log rows.

Active guests on the public QR table page see the current branch's first active menu that is available right now by menu schedule. If every active menu is outside its schedule, the guest page shows a clear message such as `Меню сейчас недоступно` and `Будет доступно с 12:00`. The guest menu shows active categories, dishes, prices, local dish photos when present, unavailable dish state, and available modifier options for dishes that have modifier groups.

When an active guest taps an available dish, a mobile-first bottom sheet lets them choose modifier options, satisfy required modifier groups, see the final item price with `price_delta`, and add a dish comment. Saving the sheet adds the position to the shared draft order for the table.

The guest menu payload is cached through Laravel's `database` cache store for a short shared-hosting-friendly window with language-specific keys:

```text
guest-menu:branch:{branch_id}:language:{language_code}
```

Menu cache uses the SQLite-backed `cache` table and a short database lock from `cache_locks` while rebuilding the branch payload. It does not use Redis, cache tags, WebSockets, S3, or any external service.

Branch cache invalidation is centralized in `App\Actions\Branches\ForgetBranchCacheAction`. The action clears branch-scoped database cache keys for guest menu payloads across supported languages, the legacy guest-menu key, and the cached guest polling interval. It is used by menu, schedule, category, dish, modifier, translation, branch settings, and local logo change paths.

Menu cache is forgotten automatically when menus, menu schedules, categories, dishes, kitchen departments, modifier groups, modifier options, dish modifier assignments, or translations are created, updated, or deleted. Price changes, availability changes, department assignment changes, modifier changes, schedule changes, translation changes, branch settings changes, and organization/brand/branch logo changes clear the centralized branch cache, so the next guest read rebuilds the payload and shows the current content.

The current guest menu UI writes configured items to `draft_order_items`, and the guest basket lets active guests edit or delete their own draft positions before the draft is sent to a waiter. The backend rechecks the menu schedule when adding a draft item and again when sending a draft to the waiter, so a menu that is no longer available cannot be ordered from an old tab. The basket is grouped by guests alphabetically and shows the same shared cart information to everyone at the table. Guest totals include already confirmed order snapshots plus the current open draft, and the table total uses the same rule. Active guests can send the shared draft to the waiter for review and can request the bill for the current table session. This does not start online payment logic.

## Restaurant Onboarding Wizard

New restaurants can be created from:

```text
/onboarding/restaurant
```

The wizard uses Blade + Livewire + Flux and keeps labels simple for non-technical staff. It creates a starter setup through the existing architecture:

1. create a company owner context;
2. create the restaurant name;
3. create the first branch;
4. add the first zone;
5. add the first tables;
6. generate permanent QR codes for those tables;
7. add the first active menu with one category and one dish;
8. open a token-only public guest page at `/q/{public_token}`.

Organization, brand, branch, area, service point, and QR creation reuse the existing backend Actions. The starter menu step uses a small onboarding Action that writes the existing `menus`, `menu_categories`, and `menu_items` tables; it does not add a separate onboarding schema and does not replace normal menu CRUD.

## Branch Setup UI

The branch list includes a simple `Настроить ресторан` wizard for each branch. It guides a manager through the existing setup path:

1. `Создать филиал`
2. `Добавить зоны`
3. `Добавить столы`
4. `Сгенерировать QR`
5. `Напечатать QR`
6. `Открыть гостевое меню`

The wizard is UI-only. It uses existing branch, area node, service point, QR, print, and public QR routes, and it does not add new tables or change the QR/order architecture. Counts for zones, service points, and active QR codes are prepared in the Livewire component through Eloquent eager loading and counts, not in Blade loops.

## Area Nodes

Area nodes are the nested zone structure inside a branch. They are stored in the `area_nodes` table and belong to one branch.

Each area node can have a `parent_id`, so branches can model structures such as floors, halls, terraces, VIP rooms, hotel areas, pickup areas, delivery areas, and custom groups. Area nodes store `type`, `name`, optional `icon`, `sort_order`, `is_active`, optional `metadata`, and support soft delete through `deleted_at`.

Branch areas are managed at:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/areas
```

The area UI is guarded by the `manage_zones` permission in the current organization context. It can add common zone presets, choose an icon, rename, move a zone inside another zone, disable/enable zones, and soft delete zones while keeping child zones visible.

The current zone UI is intentionally simple for non-technical staff: it shows large preset buttons for group, floor, hall, terrace, VIP room, and custom area, and uses visible labels such as `Зоны ресторана`, `Шаг 2: добавьте зоны`, and `Список зон`.

Area management does not create QR codes.

## Service Points

Service points are physical service locations inside a branch. They are stored in the `service_points` table and belong to one branch. A service point can optionally belong to an area node, so it can be moved between halls, floors, terraces, rooms, pickup areas, or other zones by changing `area_node_id`.

Supported service point types are table, bar seat, VIP table, room, booth, sunbed, hotel room, pickup window, delivery point, and other.

Service points store `type`, `name`, optional `display_number`, optional `internal_code`, `capacity`, optional `icon`, `status`, optional map coordinates, `is_active`, optional `metadata`, and support soft delete through `deleted_at`.

Service point statuses are:

- `free`
- `occupied`
- `reserved`
- `waiting_waiter`
- `has_new_order`
- `cooking`
- `ready_to_serve`
- `payment_requested`
- `paid`
- `closed`
- `blocked`

Service points are managed at:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/service-points
```

The service point UI is guarded by the `manage_service_points` permission in the current organization context for CRUD actions. It can add common service point presets, choose a zone, choose a type and icon, set a name, number, and capacity, rename, move between zones, and disable/enable service points.

The current service point UI avoids the technical term as much as possible in visible copy. Staff see `Столы и места`, `Шаг 3: добавьте столы`, large preset buttons for table, bar seat, room, and other place, and simple actions such as `Создать QR`, `Показать QR`, `Открыть стол`, and `Выключить`.

Service point status can be changed manually by a user with `manage_service_points` or by a user with the fixed `waiter` role in the organization. The status update is handled through a backend action so later table sessions and orders can reuse the same status-change path.

Users with `view_orders` or `confirm_orders` can open a table from the service point page. Opening a table creates or returns the current active or bill-requested table session for that service point and moves the service point status to `occupied` or keeps it at `payment_requested`.

Active guests can press `Позвать официанта` from the public QR table page. The request creates or reuses one pending `waiter_calls` row for the service point, moves the service point status to `waiting_waiter`, and writes database notifications for waiters who can view orders in that branch. No SMS, push, Telegram API, WebSockets, Redis, or external service is used.

Active guests can press `Попросить счёт` from the same shared basket. The action changes the current `table_sessions.status` to `payment_requested`, moves the service point status to `payment_requested`, keeps per-guest and table totals visible to guests, and writes a database notification for eligible waiters. This is a request/status flow only; it does not create payment records and does not start online payment logic.

Permanent QR codes are attached to the stable service point record. The CRUD action creates an internal service point code once, and editing does not change it. Renaming a service point or moving it to another area must not change the QR identity.

Users with `generate_qr` can open the service point page, create a missing active QR, and show the existing QR details. Users without `generate_qr` cannot generate or show QR details from this UI.

## Table Sessions

Table sessions are stored in the `table_sessions` table. A table session belongs to one branch and one service point.

The table stores:

- `branch_id`
- `service_point_id`
- `active_service_point_id`
- `pending_service_point_id`
- `opened_by_user_id`
- `opened_by_guest_id`
- `guest_invite_token`
- `guest_invite_created_at`
- `guest_invite_created_by_guest_id`
- `status`
- `source`
- `started_at`
- `ended_at`
- `closed_by_user_id`
- `metadata`

Supported statuses are:

- `pending`
- `active`
- `waiting_waiter_confirmation`
- `payment_requested`
- `paid`
- `closed`
- `cancelled`

Supported sources are:

- `waiter_opened`
- `guest_created`

`opened_by_user_id` is for waiter-created sessions. `opened_by_guest_id` stores the first session guest id when the first guest creates a pending session.

SQLite enforces one active or bill-requested table session per service point through internal nullable `active_service_point_id`. Closed, cancelled, paid, or other non-active session history can remain for the same service point.

SQLite also enforces one pending table session per service point through internal nullable `pending_service_point_id`. This protects the public QR flow from creating duplicate pending sessions on repeat submit.

`OpenTableSessionForServicePointAction` creates an active waiter-opened session only when the service point does not already have an active or bill-requested session. If such a session already exists, the action returns it and does not create a duplicate session automatically.

`CreateGuestPendingTableSessionAction` creates a pending guest-created session only when the service point has no active or pending session and branch settings allow guest-created sessions. The first guest is stored as an active guest inside that pending session.

When a table session already has active guests, a new QR guest creates a pending join request instead of becoming a table guest immediately.

An active guest can create an invite link for the current table session from the public QR page. The invite URL keeps the QR route shape and adds only a hidden session invite token:

```text
/q/{public_token}?invite={guest_invite_token}
```

The link does not expose organization IDs, branch IDs, service point IDs, table session IDs, table numbers, or area names. When the invited person opens the link and enters a name, the system creates a pending join request for the current table session. Current active guests approve or reject it through the same Livewire polling approval UI.

Guest-created sessions can display the cached branch menu for active guests and write active guest selections to `draft_order_items`, but they do not create final orders, start kitchen/bar workflows, or create payment flows. Guest-created sessions do not send anything to the kitchen or bar.

## Manual Payments

Manual payments are stored in the `manual_payments` table. They are staff-entered offline records for cash, card-terminal, or other local payment methods. Stripe, PayPal, online acquiring, paid payment providers, WebSockets, Redis, and external services are not used.

Each manual payment belongs to a branch, service point, and table session. A payment can cover the whole table or a specific table session guest. Guest-scoped payments store the guest id and a `guest_name` snapshot so the payment history remains readable later.

The waiter table detail page shows payment totals for confirmed non-cancelled orders, already recorded manual payments, remaining table balance, per-guest balances, and manual payment history. Payment actions are available to users with `manage_payments` in the branch context or the fixed `cashier` organization role. Users with `view_payments` can see the payment summary but cannot record payment.

Manual payment never pays an open draft. If the latest draft is still `draft`, `sent_to_waiter`, or `waiter_review`, staff must finish that draft first. This protects the rule that every order must be confirmed by a waiter before it becomes payable confirmed order history.

When the remaining confirmed order balance reaches zero, the table session status becomes `paid` and the service point status becomes `paid`. Staff can then close the paid session from the same page; closing sets the table session status to `closed`, fills `closed_by_user_id` / `ended_at`, and moves the service point back to `free`.

Users with the critical `close_table_sessions` permission can also close an unpaid active session manually from waiter table detail. Manual close blocks old guest tokens and invite links from adding positions, frees the service point for the next seating, keeps old orders and payment history intact, and does not reissue or modify the permanent QR code.

## Restaurant Dashboard

The restaurant dashboard is available at:

```text
/restaurant/dashboard
```

It is a simple branch-level workspace, not a heavy BI system. Dashboard data is prepared by `BuildRestaurantDashboardAction`, cached through Laravel's explicit `database` cache store, and rendered by Blade + Livewire without querying from the template.

The dashboard shows:

- active tables;
- new guest drafts sent to waiter;
- cooking orders;
- ready positions waiting for waiter service;
- today's order amount when the user has `view_reports`;
- popular dishes when the user has `view_reports`.

Quick actions are shown for the current user's permissions:

- menu;
- tables / service points;
- QR print;
- waiter screen;
- kitchen screen;
- reports.

Unavailable actions are shown as disabled buttons instead of linking to pages the user cannot open. The dashboard does not use WebSockets, Redis, S3, Docker, external reporting services, or 1-second polling.

## Basic Analytics

Basic analytics are shown on the restaurant dashboard for users with `view_reports` access in at least one branch context. Superadmins can see analytics across all branches.

The dashboard currently shows:

- orders today;
- total order amount today;
- average check;
- popular dishes;
- active tables;
- closed sessions;
- cancelled orders.

Analytics are built by `BuildBasicAnalyticsDashboardAction` and cached through Laravel's explicit `database` cache store for 300 seconds. Restaurant dashboard data is built by `BuildRestaurantDashboardAction` and cached separately for a shorter operational snapshot. Cache keys are grouped by accessible branch ids and current date, so the dashboard does not run the same aggregate reads on every page refresh.

The analytics and dashboard caches are invalidated by model observers when `orders`, `order_items`, `manual_payments`, or `table_sessions` change. The restaurant dashboard cache is also invalidated by `draft_orders`, `kitchen_tickets`, and `kitchen_ticket_items` changes. This keeps order totals, popular dishes, waiter handoff counts, kitchen progress, payment-related dashboard state, and session counts fresh without Redis, cache tags, WebSockets, queues, or external services.

## Audit Logs

Important operational changes are stored in the `audit_logs` table. The audit log is separate from `order_status_logs`: order status logs keep detailed order history, while `audit_logs` is the general restaurant control journal.

Audit rows store the acting `user_id`, optional `guest_id` or `guest_token`, `action`, `entity_type`, `entity_id`, JSON `old_values`, JSON `new_values`, and `created_at`. Optional `organization_id` and `branch_id` keep the log scoped for access checks.

Current audited actions include:

- dish price changes;
- dish availability changes;
- dish deletion;
- service point moves between areas;
- manual QR reissue;
- staff permission override changes;
- waiter order confirmation;
- order cancellation;
- table session close;
- manual payment recording.

The audit log viewer is available at:

```text
/restaurant/audit-log
```

Access requires `view_audit_log` in the organization/branch context. Superadmins can view all audit rows. Regular users only see rows for organizations and branches where they have audit access. The viewer is Blade + Livewire and does not use Redis, WebSockets, S3, Docker, or external logging services.

## Table Session Guests

Table session guests are stored in the `table_session_guests` table and belong to one table session.

The table stores `guest_name`, a random 64-character `guest_token`, `status`, optional `ready_at`, `joined_at`, optional `left_at`, and optional JSON `metadata`.

Guests are not user accounts and do not need registration. The public QR entry flow queues the `guest_token` in a browser cookie so the guest can be recognized later without exposing internal IDs.

When the guest refreshes the QR page, the cookie restores the same table session and guest record. If that table session has been closed, the guest sees a closed-session message. Guests with `rejected` or `removed` status are restored for display but are not allowed to add future order positions.

Supported guest statuses are:

- `pending_approval`
- `active`
- `rejected`
- `left`
- `removed`

The first guest created from the public QR landing is saved as `active`. Guest lists are ordered alphabetically by `guest_name` and show whether each guest is ready.

Active guests can request waiter help from the guest table shell. Waiter-call state is stored in `waiter_calls`, and Laravel's `notifications` table stores per-user database notifications for eligible waiters. When a waiter marks the call as processed, the call moves to `handled`, related unread database notifications are marked read, and the service point returns to the previous status if it is still `waiting_waiter`.

## Table Session Join Requests

Join requests are stored in the `table_session_join_requests` table and belong to one table session.

The table stores `guest_name`, a random 64-character `guest_token`, `status`, optional approval/rejection audit fields for guests and users, and `expires_at`.

Supported join request statuses are:

- `pending`
- `approved`
- `rejected`
- `expired`

If a table session already has active guests, a new QR guest creates a pending join request and does not enter the table immediately. Any active guest from the same table session can approve or reject the request through backend actions. Approval creates a real `table_session_guests` record using the request guest name and token. Rejection does not create a guest. Expired join requests are marked `expired` during guest polling or moderation attempts and cannot be approved into guests.

The public QR page now shows a waiting state for the new guest. Active guests see a small Livewire polling block with pending join requests and can accept or reject them without WebSockets. The waiting guest's status block also refreshes through Livewire polling and shows approved or rejected state clearly.

Active guests also see a simple `Пригласить гостя` action. It creates or reuses the table session invite link with a hidden 64-character token, uses the browser native share API when available, and falls back to a `Скопировать ссылку` button when native sharing is not available. Closed sessions and inactive service points cannot create new guest invite links. The project does not integrate directly with Telegram, WhatsApp, Viber, SMS, email, or any paid provider; the phone/browser decides which share targets are available.

After an active guest is recognized, the public QR page opens the main guest table shell instead of the entry form. The shell shows the venue name, current service point, saved entry state, the invite action, a guest list, the cached active branch menu, order status, draft positions, and draft totals.

The guest table is split into isolated Livewire polling blocks for guests, notifications, join requests, order statuses, draft positions, and draft totals. Each block uses the branch `polling_interval_seconds` setting, which defaults to 1 second, so the whole guest page is not refreshed.

## Draft Orders

Draft orders are the shared table draft before waiter confirmation. They are stored in:

- `draft_orders`
- `draft_order_items`

Each `table_session` can keep multiple `draft_orders` over time. `TableSession::draftOrder()` exposes the latest current draft, while `TableSession::draftOrders()` keeps the full draft history for repeat orders in the same open table session.

Draft order statuses are:

- `draft`
- `sent_to_waiter`
- `waiter_review`
- `rejected`
- `converted_to_order`

Each draft item belongs to one concrete `table_session_guest` and may reference a `menu_item`. The item stores a snapshot of `item_name`, `quantity`, `unit_price`, `modifier_total`, `total_price`, selected modifiers as JSON, and an optional guest comment. This keeps the table draft stable if menu names or prices change later.

Active guests can add available menu items to the shared draft from the public QR guest menu. The backend rechecks the guest token, guest status, table session status, service point activity, menu item availability, and modifier availability before creating a draft item. Rejected, removed, pending, or left guests cannot add positions.

The shared draft item list is a separate Livewire polling block. It groups active guests alphabetically by `guest_name` and shows each guest's positions, line prices, selected modifiers, comments, item count, and current-draft guest total.

Draft totals and order statuses are separate Livewire polling blocks. Totals show per-guest totals, current draft total, already confirmed non-cancelled orders, table total, ready counts, `Я готов`, `Отправить официанту`, and `Попросить счёт`. Order statuses show waiter rejection, waiter confirmation, kitchen/bar accepted state, and guest-facing cooking/ready/served status.

An active guest can edit only their own draft positions. They can change quantity, comment, and currently available modifier selections, or delete their own position. The backend rechecks the browser guest token, active guest status, item ownership, table session, service point activity, and draft status. Guests cannot edit or delete another guest's position.

All active guests see the same grouped table cart information. Only the current guest gets edit and delete controls for their own positions.

Each active guest can press `Я готов` in the shared cart to set `table_session_guests.ready_at`, or press `Снять готовность` to clear it. The guest list and shared cart show `Готов` / `Не готов`, plus the cart shows how many active guests are ready.

Any active guest can press `Отправить официанту` for the shared draft, even if some positions belong to other guests. If not all active guests are ready, the UI first shows an inline confirmation. The backend also rechecks that the service point is still active. After confirmation, the draft status becomes `sent_to_waiter`, `sent_to_waiter_at` and `sent_by_guest_id` are saved, guest readiness is cleared, and the service point status becomes `has_new_order`.

This guest action is only a waiter-review handoff. The draft does not become a real order until the waiter confirms it, and it still does not go to kitchen or bar until the waiter explicitly sends the confirmed order to kitchen/bar.

When a draft is no longer in `draft` status, for example after it is sent to waiter review, guest editing and deletion are blocked for the existing draft.

After a draft is confirmed into a real order, guests can add more positions in the same table session. The next guest add creates a new `draft_order` for the same `table_session`; that new draft must again be sent to the waiter, confirmed by the waiter, and explicitly dispatched to kitchen/bar. Old confirmed orders are not overwritten.

The waiter can confirm a sent draft from the waiter table detail page. Confirmation changes the draft to `converted_to_order`, creates one real `orders` row with status `confirmed_by_waiter`, and copies draft positions into `order_items` as snapshots. If a source menu item has a kitchen department, the order item also stores `kitchen_department_id`, `kitchen_department_type`, and `kitchen_department_name`. Confirmation alone does not send anything to kitchen or bar.

After confirmation, a waiter with `send_to_kitchen` can press `Send to kitchen/bar`. This changes the order status to `sent_to_kitchen_bar`, creates kitchen tickets grouped by department, updates the service point status to `cooking`, and writes an `order_status_logs` row. The guest shared cart then shows that the order was accepted by kitchen/bar.

Real orders are stored in:

- `orders`
- `order_items`
- `kitchen_tickets`
- `kitchen_ticket_items`
- `order_status_logs`

Each order belongs to a branch, service point, table session, and the source draft order. The source `draft_order_id` is unique, so the same draft cannot create two confirmed orders.

Current order lifecycle statuses are:

- `confirmed_by_waiter`
- `sent_to_kitchen_bar`
- `in_progress`
- `ready`
- `served`
- `payment_requested`
- `paid`
- `closed`
- `cancelled`

Order items keep optional live links to the original guest, menu item, and kitchen department, plus explicit immutable snapshot columns: `original_menu_item_id`, `guest_name_snapshot`, `item_name_snapshot`, `item_description_snapshot`, `unit_price_snapshot`, `modifiers_snapshot`, `tax_snapshot`, and `service_snapshot`. Legacy display columns such as `guest_name`, `item_name`, `unit_price`, `selected_modifiers`, and totals remain for compatibility.

If the menu item name, description, price, modifier group/option, guest name, or kitchen department changes later, old confirmed orders, CSV exports, dashboard popular-item summaries, and kitchen/bar ticket dispatch keep using the stored order item snapshot.

Kitchen tickets are created only when a confirmed order is explicitly sent to kitchen/bar. Each ticket belongs to one order, branch, service point, table session, and department snapshot. Ticket items reference the original `order_items`, so each department receives only its own positions. Repeating the send action does not create duplicate active tickets for the same confirmed order.

Kitchen ticket item statuses are:

- `new`
- `in_progress`
- `ready`

Ticket items also store waiter service tracking through `served_at` and `served_by_user_id`. Kitchen and bar staff mark production progress; the waiter marks a ready position as served. When all dispatched ticket items are ready, the order status becomes `ready` and the service point can move to `ready_to_serve`. When all dispatched ticket items are served, the order status becomes `served`.

The kitchen screen is available at:

```text
/restaurant/kitchen/dashboard
```

Access is allowed for superadmins, users with the fixed `head_chef` or `cook` role in an active organization membership, or users with the flexible `view_kitchen` permission. Active `branch_users` assignments limit the visible departments to assigned branches.

The kitchen screen reads only dispatched `kitchen_tickets`, shows one selected department at a time, and refreshes with Livewire polling every 1 second. It uses large production cards sorted by oldest ticket first, with the current service point number/name, zone, timer, positions, modifiers, comments, and two large item actions: `Начать` (`in_progress`) and `Готово` (`ready`). It does not use WebSockets, Redis, S3, Docker, or paid services.

The bar screen is available at:

```text
/restaurant/bar/dashboard
```

The bar screen reuses the same shared department screen logic as the kitchen screen, but filters departments to type `bar`. It shows dispatched bar tickets only with large drink cards, service point number/name, zone, modifiers, comments, item status, a live timer, department filter, and oldest-first sorting. Access is allowed for superadmins, users with the fixed `bartender` or `head_chef` role, or users with `view_orders` or `send_to_kitchen`. Active `branch_users` assignments still limit visible bar departments to assigned branches.

The guest shared cart polls only its basket block and shows the overall order service state as `Принято`, `Готовится`, `Готово`, or `Подано` after waiter confirmation and dispatch. These labels come from the confirmed order and ticket item states; guests do not mark items served.

Order status logs are the persistent history for draft and confirmed order events. They record branch, service point, table session, draft order, optional confirmed order, actor user or guest, actor type/name snapshot, event, previous status, new status, optional reason, metadata, and `occurred_at`.

Current log events are:

- `draft_created`
- `draft_edited`
- `draft_sent_to_waiter`
- `draft_confirmed`
- `draft_rejected`
- `draft_returned_to_draft`
- `order_status_changed`
- `order_sent_to_kitchen_bar`
- `order_cancelled`

Logs are written by backend actions for guest draft creation/editing, guest send-to-waiter, waiter draft edits, waiter confirmation/rejection/return-to-draft, explicit kitchen/bar dispatch, and manual confirmed-order status changes. Links to users, guests, drafts, and orders use nullable `nullOnDelete` references, while actor/status snapshots stay in the log row so restaurant control history remains readable.

The waiter can also reject a sent draft with a required reason. Rejection changes the draft to `rejected`; guests see the reason in the shared cart polling block. A rejected draft can be returned to `draft` from the waiter detail page so guests can edit and send it again. Repeat orders create a new draft only after the previous draft has become a confirmed order.

Before confirming, a waiter with `confirm_orders` or `edit_pending_orders` can edit a sent draft from the waiter table detail page. The waiter can change quantity, delete a position, add an available active-menu dish for an active guest, change comments, and update currently available modifier selections. Any waiter edit moves the draft to `waiter_review`, recalculates snapshot totals in `draft_order_items`, writes an `order_status_logs` row, and guests see the updated shared cart through Livewire polling.

This stage has kitchen/bar dispatch tickets and polished department production screens. It does not add advanced kitchen/bar production history.

## Waiter Dashboard

The waiter dashboard shell is available at:

```text
/restaurant/waiter/dashboard
```

Access requires authentication and the `view_orders` permission in the organization context. Superadmins keep the normal platform-level permission bypass. If a user has active `branch_users` assignments, the dashboard shows only those assigned branches; otherwise it shows the branches from organizations where the user can view orders.

The dashboard uses Livewire polling every 1 second and does not use WebSockets. The screen is optimized for restaurant work: new orders stay at the top, urgent work is color-coded, and service points are grouped by their current zones.

It shows:

- branches available to the waiter;
- service points grouped by current area/zone;
- color-coded service point statuses and urgency badges;
- open table sessions;
- pending guest waiter calls;
- guest bill requests;
- ready kitchen/bar items waiting to be served;
- shared drafts with `sent_to_waiter` or `waiter_review` status;
- a small browser audio notice when a new sent draft, guest waiter call, bill request, or ready item appears during polling.

Free active service points show an `Open table` action for users with order access. Existing sessions show a detailed table card with guests, draft/payment state, a detail link, and a `Close table` link only for users who have `close_table_sessions`; the actual close action and warning remain on the waiter table detail page.

Each open session links to a waiter table detail page:

```text
/restaurant/waiter/tables/{table_session}
```

The detail page is protected by branch-level order or payment access. Users with `view_orders` see the waiter order view. Users with `view_payments`, `manage_payments`, or the fixed `cashier` role can access the same page for payment handling. It shows branch, current zone, current service point, session status, latest draft status, guests sorted alphabetically, each guest's current draft positions, comments, selected modifiers, per-guest draft totals, confirmed orders total, current draft total, payment summary, and the total amount for the table.

The detail page refreshes through Livewire polling every 1 second. Users with `confirm_orders` or `edit_pending_orders` can edit a pending sent draft before confirmation. Users with `confirm_orders` can confirm a sent draft into a real order or reject it with a reason. Users with `send_to_kitchen` can send a confirmed order to kitchen/bar, which creates department tickets and moves the service point to `cooking`. Users with `manage_payments` or the fixed `cashier` role can record whole-table or per-guest manual payments. Once kitchen or bar marks positions ready, the waiter table detail shows the ready/served counts and lets the waiter mark ready positions as served.

## Permanent QR Codes

Permanent QR records are stored in the `qr_codes` table.

Each QR record belongs to one service point and stores:

- `public_token`
- `short_code`
- `status`
- creation audit user
- optional revocation date and revocation audit user

QR statuses are:

- `active`
- `disabled`
- `revoked`

The QR record does not store table numbers, service point names, area names, or branch IDs. The future public QR URL must use `public_token`, not internal IDs or visible table labels.

SQLite enforces one active QR per service point through an internal nullable `active_service_point_id` uniqueness guard. This allows disabled and revoked QR history while preventing a second active QR for the same physical service point.

`GenerateQrCodeForServicePointAction` creates a QR only when the service point does not already have an active QR. If an active QR already exists, the action returns the existing record instead of creating a second active QR automatically.

Generated QR URLs use a random 64-character `public_token`:

```text
/q/{public_token}
```

The public `/q/{public_token}` route resolves the QR token, checks the QR status, loads the current service point, current area, branch, brand, organization, and local logo, and opens a mobile-first guest landing page. The URL does not include organization IDs, branch IDs, service point IDs, table numbers, or area names.

The guest landing page shows the venue name, logo when available, current area, current service point, a guest name field, and the `Войти за стол` button. If there is no active or pending table session and `allow_guest_created_sessions` is enabled, submitting the name creates a pending table session and the first active guest inside it, then stores the random guest token in a browser cookie. Refreshing the page restores that guest from the cookie. If an active or pending session already has active guests, submitting the name creates a pending join request instead of adding the guest immediately.

Disabled and revoked QR codes show a clear public error message. Active QR codes for inactive service points show a clear message telling the guest to ask staff, and backend guest actions also reject ordering/invite attempts for inactive service points. Moving or renaming a service point does not change the QR URL; the public page loads the current service point data each time.

Users with `generate_qr` can open the QR admin page from a service point. The page shows the branch, current area, current service point, public URL, local SVG QR image, short code, status, and creation date. It can open the guest URL, download the QR SVG image, disable the QR, or manually reissue the QR after a danger warning.

Manual reissue revokes the current active QR and creates a new active QR for the same service point. Normal service point editing, including rename and area move, does not reissue or change the QR.

Users with `generate_qr` can also open a browser print-friendly sticker template for one service point QR. The sticker shows a restaurant logo only if a local logo field already exists, otherwise it uses the brand name as a simple text mark. It prints the text `Сканируйте, чтобы открыть меню`, the QR image, and the `short_code`.

The print template does not print service point number or area by default. It has a `print_table_number` setting for including the service point display number. When that setting is enabled, the UI warns that future rename or area moves can make printed sticker text stale.

Users with `generate_qr` can also open a branch-level bulk QR print page:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/qr/print
```

The bulk print page lets the user filter by area or show all areas, select service points, see which service points already have active QR codes, create missing QR codes, and open a browser print-friendly multi-sticker page. Existing active QR codes are reused, so one service point still has one active permanent QR.

PDF generation is not implemented yet.

## Current Scope

Implemented:

- Public guest, auth, restaurant dashboard, and superadmin dashboard layout zones.
- Laravel/Fortify authentication with Livewire-friendly screens.
- Fixed system roles seeded from enums.
- Flexible permissions with role permissions and user overrides.
- Explicit demo restaurant seed for local QA and first-run testing.
- Manual smoke test checklist for the main guest/waiter/kitchen/payment flow.
- Organizations with owner membership.
- Simple one-plan SaaS subscription status stored in `organization_subscriptions` with superadmin manual activation/suspension.
- Organization users with role, status, joined date, and inviter fields.
- Brands inside organizations.
- Branches inside brands and organizations.
- Branch settings stored in `branch_settings`.
- Local logo uploads for organizations, brands, and branches.
- Nested branch areas stored in `area_nodes`.
- Service point schema and CRUD UI stored in `service_points`.
- Branch menu CRUD stored in `menus`, `menu_availability_schedules`, `menu_categories`, and `menu_items`.
- Branch kitchen departments stored in `kitchen_departments`, assignable to menu items and snapshotted into confirmed order items.
- Branch menu modifier CRUD stored in `modifier_groups`, `modifier_options`, and `menu_item_modifier_groups`.
- Cached schedule-aware guest menu display with modifier selection, shared table cart UI, guest ready status, send-to-waiter draft handoff, and guest draft item creation/editing on the active public QR table page.
- Service point operational statuses and manual status changes.
- Table session schema stored in `table_sessions`.
- Shared draft order schema and guest-owned draft item creation/editing stored in `draft_orders` and `draft_order_items`.
- First guest pending session creation from the public QR landing.
- Table session join request schema, backend create / approve / reject logic, guest approval UI, guest invite share links, and guest table page shell.
- Guest waiter-call requests stored in `waiter_calls` with Laravel database notifications for the waiter dashboard.
- Guest bill requests stored as `table_sessions.status = payment_requested`, with service point status updates and Laravel database notifications for the waiter dashboard.
- Manual offline payment records stored in `manual_payments`, with whole-table and per-guest payment actions from waiter table detail.
- Table sessions can be closed after full manual payment or manually through the `close_table_sessions` permission; closing frees the service point while preserving old orders and the permanent QR.
- Branch/restaurant dashboard with active tables, new waiter drafts, cooking orders, ready positions, today amount, popular dishes, and role-aware quick actions cached through the SQLite-backed database cache store.
- Audit log storage and viewer for menu, service point, QR, staff permission, order, payment, and table-session control events.
- CSV data exports for branch orders, payments, menu, and tables guarded by `export_data`.
- Basic ru/en/lt localization foundation for admin profile language, branch-default guest language, guest language switching, and key UI strings.
- Local branch currency settings with fixed supported currencies, readable price formatting, and no exchange rates or automatic conversion.
- Soft deletes for organizations, brands, branches, area nodes, service points, menus, categories, and menu items, while old orders remain readable through stored snapshots.
- Superadmin-only local SQLite backup download with a sensitive-data warning and a reserved media ZIP follow-up.
- Permanent QR schema, generation action, admin display page, simple and bulk browser print templates, and public `/q/{public_token}` route.
- QR and guest session security hardening: unguessable QR/guest/invite tokens, expired join request blocking, inactive service point ordering checks, closed-session ordering blocks, and disabled QR public error handling.
- Basic superadmin access for the platform dashboard.
- Staff invitation model and backend creation action.
- Simple staff management UI for organization and branch staff.
- Staff permission override UI with default / allow / deny states.
- Waiter dashboard shell, table detail, draft edit actions, and draft confirm/reject actions for branches, service points, open sessions, guests, draft positions, and drafts sent to waiter review.
- Real order snapshot tables stored in `orders` and `order_items` after waiter confirmation.
- Explicit order item snapshot columns for original menu item id, guest name, item name/description, unit price, modifiers, and future tax/service data.
- Kitchen/bar dispatch tickets stored in `kitchen_tickets` and `kitchen_ticket_items` after explicit waiter dispatch.
- Polished kitchen and bar production screens for dispatched department tickets with department filtering, oldest-first sorting, large cards, timers, modifiers, comments, and `Начать` / `Готово` item actions through Livewire polling.
- Ready/served handoff where kitchen/bar marks positions ready, the waiter sees ready items and marks them served, and guests see `Принято` / `Готовится` / `Готово` / `Подано`.

Branch settings currently include safe defaults:

- Orders require waiter confirmation by default.
- New guests require approval by default.
- Guest-created sessions are allowed by default.
- Waiter-opened sessions are allowed by default.
- Guest invite links are allowed by default.
- Polling interval defaults to 1 second.
- Default language is `en`.
- Default currency is `EUR`.

Branch settings store order flow, guest session behavior, invite-link behavior, service charge and tips toggles, language/currency defaults, and Livewire polling interval. They are kept in the `branch_settings` table and are managed from the branch settings Livewire page. The public guest page reads the polling interval through the SQLite-backed database cache; saving settings clears centralized branch cache through `ForgetBranchCacheAction`.

Temporary branch closure fields live directly on `branches`: `is_temporarily_closed`, `temporary_closed_reason`, and `temporary_closed_until`. The status check resolves the saved UTC timestamp into the branch timezone so guest/admin text stays consistent on SQLite and shared hosting.

Not implemented yet:

- Menu translation admin editor.
- QR PDF generation.
- Advanced kitchen/bar production history.
- Online payments.
- Staff invitation acceptance flow and email/SMS delivery.

## Project Memory

After Prompt 105, the current working memory is:

- branch public restaurant profiles are implemented and used by QR landing / guest UI;
- branch opening hours are implemented and block guest ordering while a configured branch is closed;
- temporary branch closed mode is implemented and blocks new guest ordering while preserving QR and menu viewing;
- menu availability schedules are implemented and block guest ordering when the active menu is outside its configured branch-timezone interval;
- multiple active branch menus are supported in guest UI, grouped and sorted, while inactive menus stay hidden;
- SQLite, database cache, database sessions, database queue, local storage, Blade, and Livewire remain the required stack;
- Redis, WebSockets, S3, Docker as a requirement, paid services, React/Vue SPA, online payments, and external APIs remain out of scope;
- the next recommended prompt is Prompt 106: a small menu translation admin editor for existing `ru`, `en`, and `lt` translation tables.

Before the next coding prompt, read `docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, `docs/NEXT_STEPS.md`, and `docs/DEPLOY_SHARED_HOSTING.md`.

## Local Verification

Run the core checks:

```bash
php artisan migrate
php artisan test --compact
npm run build
```

For PHP formatting:

```bash
vendor/bin/pint --dirty --format agent
```

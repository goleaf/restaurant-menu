# Changelog

## 2026-06-04

### Prompt 280 - Functional Consistency Pass For Menu And Staff

- Checked menu, guest, waiter, kitchen/bar department, access-control, payment, and table-close flows without adding new product features.
- Fixed a consistency gap where waiter-side pending draft item additions could still list/add items from an active menu that was outside its current availability schedule.
- Waiter add-item backend validation now reuses the existing menu availability schedule rule, and the waiter table detail add-item list filters out currently unavailable scheduled menus.
- Added focused regression coverage proving a waiter cannot add a draft item from a menu outside the current schedule.
- Touched modules/files: `App\Actions\Waiter\AddDraftOrderItemByWaiterAction`, `App\Livewire\Waiter\TableDetail`, `tests/Feature/WaiterDraftEditingTest.php`, README, AI context, smoke checklist, and next-step notes.
- Limitations: no new variants/tags/allergens/shared-allocation schema was added; the current variant-like behavior remains modifier groups/options, and shared payment allocation remains a future scoped prompt.
- Manual check: set a breakfast menu schedule to `08:00-12:00`, open a waiter table detail after the interval, confirm that breakfast items are not offered in the waiter add-item list, and confirm guests still see only currently available menus.

### Docs - Daily Project Memory Update After Prompt 107

- Refreshed README, AI context, smoke checklist, and next-step notes after Prompt 107 without adding product features.
- Confirmed the current stack remains Laravel + Livewire + Blade on SQLite with database cache/session/queue and local public storage only.
- Recorded Prompt 108 as the next recommended prompt: a small menu translation admin editor using the existing translation tables and centralized database cache invalidation.

### Prompt 107 - Bulk Service Point Creation

- Added bulk creation for branch service points from the existing `Столы и места` page.
- Managers with `manage_service_points` can choose zone, type, prefix, from/to range, and capacity, then preview generated codes before confirming.
- Duplicate `internal_code` values are detected per branch, including soft-deleted records, and skipped instead of creating duplicates.
- Created rows use the generated code as `name`, `display_number`, and stable `internal_code`; QR codes are not created automatically.
- After creation, the UI suggests generating QR later through the existing bulk QR print flow.
- Touched modules/files: `App\Actions\ServicePoints\BulkCreateServicePointsAction`, branch service point Livewire component and Blade view, README, AI context, smoke checklist, next-step notes, and `tests/Feature/ServicePointCrudTest.php`.
- Limitations: no automatic QR generation, no delivery/pickup workflow, no maps, no couriers, no payments, no new routes, no new tables, no Redis/WebSockets/S3/Docker, and no external services.
- Manual check: open branch service points, choose a zone/type/prefix/range/capacity, preview `T1..T20`, confirm duplicates are shown as skipped, create the missing places, and then use the existing bulk QR print page to create QR only when ready.

### Docs - Daily Project Memory Update After Prompt 106

- Refreshed README, AI context, smoke checklist, and next-step notes after Prompt 106 without adding product features.
- Confirmed the current stack remains Laravel + Livewire + Blade on SQLite with database cache/session/queue and local public storage only.
- Recorded Prompt 107 as the next recommended prompt: a small menu translation admin editor using the existing translation tables and centralized database cache invalidation.

### Prompt 106 - Service Modes

- Added branch service modes stored on `branch_settings.service_modes` with safe default `dine_in`.
- Added fixed mode values for `dine_in`, `pickup`, `delivery`, `hotel_room_service`, `bar_only`, and `custom`, plus normalization so unknown values cannot be saved.
- Extended the branch settings Livewire page with simple enable/disable checkboxes for service modes.
- Touched modules/files: `App\Enums\BranchServiceMode`, `branch_settings` migration, `App\Models\BranchSetting`, `App\Actions\Branches\UpdateBranchSettingsAction`, branch settings Livewire component and Blade view, README, AI context, smoke checklist, next-step notes, and `tests/Feature/BranchSettingsTest.php`.
- Limitations: foundation only; no maps, couriers, delivery workflow, pickup workflow, online payments, external APIs, Redis, WebSockets, S3, Docker, React, Vue, or paid services were added.
- Manual check: open branch settings, confirm `Dine-in` is enabled by default, enable pickup/delivery/bar/custom modes, save, reload the page, and confirm the selected modes persist while existing QR/table behavior still works.

### Docs - Daily Project Memory Update After Prompt 105

- Refreshed README, AI context, smoke checklist, and next-step notes after Prompt 105 without adding product features.
- Confirmed the current stack remains Laravel + Livewire + Blade on SQLite with database cache/session/queue and local public storage only.
- Recorded Prompt 106 as the next recommended prompt: a small menu translation admin editor using the existing translation tables and centralized database cache invalidation.

### Prompt 105 - Multiple Menus Per Branch

- Improved the guest menu payload so one branch can expose several active menus at the same time while preserving the legacy `menu` and `categories` keys for existing Livewire flows.
- Guest UI now groups dishes by available menu, keeps menus sorted by `sort_order`, `name`, and `id`, hides draft/archived menus, and respects menu availability schedules in the branch timezone.
- Active menus that are scheduled for later are shown as a small `Будет доступно позже` hint without exposing their dishes for ordering.
- Touched modules/files: `App\Actions\Menus\GetGuestMenuForBranchAction`, `App\Livewire\PublicQr\GuestMenu`, `resources/views/livewire/public-qr/guest-menu.blade.php`, `tests/Feature/MenuScheduleTest.php`, README, AI context, smoke checklist, and next-step notes.
- Limitations: no new menu type enum/table, no holiday calendar, no AI translation, no external APIs, no Redis/WebSockets/S3/Docker, and no ordering from menus outside their active schedule.
- Manual check: create active menus such as Main, Breakfast, Business lunch, Bar, and Wine card, set different sort orders and schedules, open `/q/{public_token}`, confirm currently available menus are grouped and sorted, confirm inactive menus are hidden, and confirm later menus show only a next-availability hint.

### Docs - Daily Project Memory Update After Prompt 104

- Refreshed README, AI context, smoke checklist, and next-step notes after Prompt 104 without adding product features.
- Confirmed the current stack remains Laravel + Livewire + Blade on SQLite with database cache/session/queue and local public storage only.
- Recorded Prompt 105 as the next recommended prompt: a small menu translation admin editor using the existing translation tables and centralized database cache invalidation.

### Prompt 104 - Menu Schedules

- Added `menu_availability_schedules` for weekday menu availability intervals using each branch timezone.
- Added `MenuAvailabilitySchedule`, factory, `Menu::availabilitySchedules()`, `GetMenuAvailabilityStatusAction`, and an observer that clears centralized branch database cache on schedule changes.
- Extended the branch menu Livewire admin page with a simple schedule block per menu: current status, existing intervals, add interval, and delete interval, guarded by `manage_menu`.
- Updated guest menu payload selection so guests see only the first active menu that is available right now; if all active menus are outside schedule, the guest menu shows a clear next-availability message.
- Added backend guards in guest draft item creation and send-to-waiter flow so unavailable scheduled menus cannot be ordered from stale tabs.
- Touched modules/files: `menus` model relationship, new schedule migration/model/factory/observer/action, `GetGuestMenuForBranchAction`, draft-order guard actions, branch menu Livewire UI, guest menu empty state, docs, and `tests/Feature/MenuScheduleTest.php`.
- Limitations: no special holiday calendar, no per-date overrides, no external calendar APIs, no Redis/WebSockets/S3/Docker, and no online ordering outside active menu schedule windows.
- Manual check: add breakfast and lunch intervals to two active menus on the branch menu page, open the QR guest menu before/during/after each interval, confirm only the currently available menu appears, then try to add/send a draft after the menu window ends and confirm it is blocked.

### Docs - Daily Project Memory Update After Prompt 103

- Refreshed README, AI context, smoke checklist, and next-step notes after Prompt 103 without adding product features.
- Confirmed the current stack remains Laravel + Livewire + Blade on SQLite with database cache/session/queue and local public storage only.
- Recorded Prompt 104 as the next recommended prompt: a small menu translation admin editor using the existing translation tables and centralized database cache invalidation.

### Prompt 103 - Temporary Branch Closed Mode

- Added branch-level temporary closed mode with `is_temporarily_closed`, `temporary_closed_reason`, and optional `temporary_closed_until` stored on `branches`.
- Extended the existing branch settings Livewire page with a simple closure form, preset reason examples, and an admin warning while closed mode is enabled.
- Updated branch open/closed status resolution so temporary closure takes priority over opening hours, QR pages still open, menu browsing still works, and guest draft creation/send-to-waiter actions are blocked while temporary closure is active.
- Added waiter dashboard visibility for temporary branch closure plus a small action for order-access staff to reopen ordering.
- Touched modules/files: `Branch`, `GetBranchOpeningStatusAction`, `UpdateBranchTemporaryClosureAction`, branch settings Livewire UI, public QR guest UI, waiter dashboard payload/action/UI, draft-order guard actions, branch cache observer, migration, docs, and `tests/Feature/BranchTemporaryClosedModeTest.php`.
- Limitations: no holiday calendar, no external status API, no automatic social/map/banner integrations, no paid services, no Redis/WebSockets/S3/Docker, and no order-taking while temporary closure is active.
- Manual check: enable temporary closure from branch settings with a reason and optional until time, open `/q/{public_token}` to confirm QR/menu viewing still works and ordering is blocked, then reopen ordering from branch settings or waiter dashboard.

### Docs - Daily Project Memory Update After Prompt 102

- Refreshed README, AI context, smoke checklist, and next-step notes after Prompt 102 without adding product features.
- Confirmed the current stack remains Laravel + Livewire + Blade on SQLite with database cache/session/queue and local public storage only.
- Recorded Prompt 104 as the next recommended prompt: a small menu translation admin editor using the existing translation tables and centralized database cache invalidation.

### Prompt 102 - Branch Opening Hours

- Added `branch_opening_hours` for weekly branch schedules with closed days and multiple opening intervals per day, using the branch timezone for current open/closed status.
- Extended the branch settings Livewire page with a simple opening-hours editor and safe behavior when no schedule is configured.
- Updated the public QR guest landing/table UI to show `Сейчас открыто` / `Сейчас закрыто` status while keeping QR pages and menu browsing available when the restaurant is closed.
- Blocked guest draft item creation and send-to-waiter actions while a configured branch schedule says the restaurant is closed.
- Touched modules/files: `BranchOpeningHour`, `Branch::openingHours()`, `GetBranchOpeningStatusAction`, `UpdateBranchOpeningHoursAction`, branch settings Livewire UI, public QR guest components, draft-order actions, docs, and `tests/Feature/BranchOpeningHoursTest.php`.
- Limitations: no holidays/special dates, no external calendar/maps/booking APIs, no paid services, and no separate online ordering cutoff rules beyond branch open/closed status.
- Manual check: configure a branch schedule with two intervals and one closed day, open `/q/{public_token}` during open and closed times, confirm menu viewing still works when closed, and confirm adding/sending guest draft items is blocked while closed.

### Docs - Daily Project Memory Update After Prompt 101

- Refreshed README, AI context, smoke checklist, and next-step notes after Prompt 101 without adding product features.
- Confirmed the current stack remains Laravel + Livewire + Blade on SQLite with database cache/session/queue and local storage only.
- Kept the future menu translation admin editor scoped to existing translation tables and database cache invalidation.

### Prompt 101 - Restaurant Public Profile

- Added branch-level public restaurant profile fields for public venue name, short description, local cover image, phone, email, website, and Instagram/Facebook/TikTok links while reusing existing branch logo, address, city, country, default language, and default currency data.
- Extended the existing branch settings Livewire page with a simple public profile form and local image uploads for logo/cover images.
- Updated the public QR guest landing page and active guest table header to use the branch public profile with tidy fallback text when profile details are missing.
- Touched modules/files: `branches` migration/model, `App\Actions\Branches\UpdateBranchPublicProfileAction`, `App\Livewire\Organizations\Brands\Branches\Settings`, `App\Livewire\PublicQr\Show`, QR landing Blade UI, branch cache invalidation observer, docs, and `tests/Feature/RestaurantPublicProfileTest.php`.
- Limitations: no maps, no external profile/social APIs, no paid services, no S3, no WebSockets, no separate public restaurant directory, and no online booking or marketing integrations.
- Manual check: open branch settings, save public name/description/contact/social links plus logo and cover image, scan/open `/q/{public_token}`, and confirm the QR landing shows the profile while the URL stays token-only.

### Docs - Daily Project Memory Update

- Refreshed project memory docs for the current Laravel + Livewire + SQLite shared-hosting baseline without adding features.
- Added `docs/NEXT_STEPS.md` with scoped recommended future prompts and guardrails.
- Updated README, AI context, and smoke checklist links/checkpoints so future coding-agent sessions can restore stack, tables, routes, Livewire components, business rules, forbidden services, and next prompt direction from files.

### Bugfix - Restore Project After Previous Prompt

- Fixed feature-test crashes caused by Laravel Vite font manifest resolution when ignored `public/build` assets are missing or stale locally.
- Disabled Vite asset resolution in the base test case with Laravel's `withoutVite()` helper so HTTP/Livewire tests do not depend on generated build files.
- Kept runtime deployment rules unchanged: production/shared hosting should still run `npm run build`, and `public/build` remains uncommitted.

### Prompt 100 - Document Current Version

- Added `docs/CURRENT_VERSION.md` as a short current-version snapshot for the next developer or coding agent.
- Documented Organization, Brand, Branch, Area Node, Service Point, permanent QR identity, table sessions, guest entry/approval, shared drafts, waiter confirmation, kitchen/bar dispatch, manual payment/close, shared-hosting mode, current limits, and likely next steps.
- Linked the snapshot from README and updated AI context while keeping the project scope unchanged: SQLite, database cache/session/queue, Blade + Livewire, no Redis, WebSockets, S3, Docker, paid services, or separate SPA.

### Prompt 099 - Review Vertical Slice

- Added `tests/Feature/VerticalSliceFlowTest.php` as a focused first vertical-slice regression from registration through restaurant setup, permanent QR, guest entry, invite approval, shared draft, waiter confirmation, kitchen/bar dispatch, ready/served handoff, bill request, manual payment, and table-session close.
- Verified the slice keeps the QR URL as `/q/{public_token}` without exposing organization, branch, service point, table, or area IDs, and keeps the permanent QR unchanged after the session closes.
- Exercised existing Actions and Livewire components instead of adding new business features or changing architecture.
- Ran the affected-flow regression suite on SQLite/database drivers with no Redis, WebSockets, S3, Docker, online payments, or paid services.

### Prompt 098 - Cleanup Project Consistency

- Removed remaining starter-kit cleanup leftovers: default `test@example.com` seeding, public placeholder copy, unused app header layout, and unused starter repository/documentation icon overrides.
- Removed the Docker-oriented `laravel/sail` dev dependency and updated package metadata for the restaurant-menu project.
- Tightened shared-hosting filesystem configuration so resolved disks are local-only (`local` and `public`) with no S3 disk.
- Added focused cleanup regression coverage for local-only infrastructure, clean default seeding, and non-placeholder public entry pages.
- Updated README, AI context, and the smoke checklist with cleanup results while keeping SQLite, database drivers, Livewire polling, and no Redis/WebSockets/S3/Docker.

### Prompt 097 - Shared Hosting Deployment Notes

- Added `docs/DEPLOY_SHARED_HOSTING.md` with a shared-hosting deployment checklist for SQLite, local public storage, writable directories, migrations, database cache, database sessions, database queue, optional scheduler cron, and production cache commands.
- Documented `storage:link` plus shared-hosting alternatives when symbolic links are unavailable.
- Explicitly documented that Redis, WebSockets, S3, Docker, external queues, paid storage, and paid services are not part of the deployment profile.
- Updated README and AI context to point future agents to the shared-hosting deployment notes and preserve the SQLite/database-driver baseline.

### Prompt 096 - Audit Access Control

- Added a focused access-control regression test covering organization isolation, branch assignment isolation, waiter price restrictions, cook staff restrictions, marketer order-confirmation restrictions, accountant payment visibility without menu editing, and superadmin bypass.
- Added `User::canAccessBranch()` and `User::accessibleBranchIdsForOrganization()` so active `branch_users` assignments consistently narrow branch-scoped access inside an organization.
- Guarded branch-scoped Livewire pages for areas, service points, menus, settings, branch staff, bulk QR print, QR display, and QR print templates with branch-level access checks.
- Filtered branch lists by accessible branch ids, while preserving superadmin access and unassigned organization-level manager access.
- Verified the new audit test and affected branch/menu/staff/QR/settings/waiter/superadmin tests on SQLite with database drivers and no Redis/WebSockets/S3/Docker.

### Prompt 095 - Add Smoke Test Checklist

- Added `docs/TEST_CHECKLIST.md` as a lightweight manual smoke checklist for the main restaurant flow.
- Covered branch, zone, service point, QR, public `/q/{token}`, guest session, second guest approval, shared draft, waiter confirmation, kitchen/bar ready, waiter served handoff, bill request, manual payment, and table-session close.
- Linked the checklist from README and documented that it avoids heavy browser/E2E dependencies while keeping SQLite, database drivers, shared-hosting compatibility, and no Redis/WebSockets/S3/Docker.

### Prompt 094 - Add Demo Restaurant Seed

- Added an explicit `DemoRestaurantSeeder` for a runnable demo restaurant without wiring it into the default production seed path.
- Seeded `Demo Food Group`, `Bella Pizza`, `Demo Old Town`, three branch zones, seven service points, one active permanent QR per service point, an active demo menu with categories, dishes, and ru/en/lt translations.
- Added demo staff users for owner, restaurant admin, waiter, chef, bartender, and cashier with branch memberships and user-level permission overrides for testing the existing flow.
- Documented the manual demo seed command and added focused coverage proving the seeder creates runnable data and can be run twice without duplicating demo rows.

### Prompt 093 - Centralize Branch Cache Invalidation

- Added `ForgetBranchCacheAction` as the central branch cache invalidation point for SQLite-backed database cache keys.
- Centralized clearing of guest menu language keys, the legacy guest menu key, and branch polling interval cache without Redis or cache tags.
- Routed menu/category/dish/modifier/translation changes, branch settings saves, menu-item modifier assignments, and organization/brand/branch logo changes through the central branch cache action.
- Added focused coverage proving menu changes, branch settings changes, logo changes, and direct central invalidation clear the expected database cache keys.

### Prompt 092 - Polish Kitchen Bar UX

- Reworked the shared kitchen/bar department screen into large production cards with service point number/name, zone, live timer, positions, modifiers, comments, and clearer item status badges.
- Kept department filtering and made oldest-first ticket sorting visible in the UI, using the existing `sent_at`/`id` ordering and bounded polling payload.
- Replaced the dense three-status button row with two large cook-friendly actions: `Начать` for `in_progress` and `Готово` for `ready`.
- Kept core order flow unchanged: kitchen/bar still only sees tickets created by explicit waiter dispatch, and realtime remains Livewire polling with SQLite/database drivers only.

### Prompt 091 - Polish Waiter UX

- Reworked the waiter dashboard into a faster restaurant work surface with priority blocks for new orders, waiter calls, bill requests, and ready kitchen/bar items.
- Grouped service points by current zone and added color-coded table cards with status, urgency badges, guests, draft/payment state, and quick detail links.
- Added a dashboard-level `Open table` action using the existing table-session action and branch-level order access checks.
- Added `Close table` dashboard links only for users with `close_table_sessions`, while keeping the actual close warning/action on the existing waiter table detail page.
- Kept order confirmation, kitchen/bar dispatch, payments, polling, storage, and infrastructure rules unchanged: SQLite, database drivers, Livewire polling, no Redis/WebSockets/S3/Docker.

### Prompt 090 - Polish Guest Mobile UI

- Polished the public QR guest entry screen with a stronger mobile welcome card, venue/logo context, current zone/place details, QR short code, large name input, and reachable bottom action.
- Updated active guest table UI with clearer table context, waiter-call action, alphabetic guest list badges, mobile dish cards, item bottom sheets, shared cart grouping, per-guest totals, and sticky table actions.
- Kept the work UI-only: no new tables, routes, backend actions, business rules, packages, Redis, WebSockets, S3, Docker, React, Vue, or external services.
- Verified the existing guest table/menu/call/bill and design-system tests still pass after the polish.

### Prompt 089 - Add Simple Design System

- Added small reusable Blade design-system primitives under `resources/views/components/ui` for buttons, cards, status badges, form fields, empty states, alerts, mobile guest bottom actions, and zone/service-point icons.
- Applied the new primitives to the guest QR/table/menu actions and the branch area/service point screens without changing business logic, routes, tables, drivers, or polling behavior.
- Kept the UI stack lightweight: Blade + Livewire + Tailwind + existing Flux icons only, with no React/Vue SPA, heavy UI framework, Redis, WebSockets, S3, Docker, or external services.
- Added focused Blade render coverage for the design-system components.

### Prompt 088 - Improve Order Snapshots

- Added explicit `order_items` snapshot columns for original menu item id, guest name, item name/description, unit price, selected modifiers, and future tax/service payloads.
- Backfilled new snapshot columns from existing order item snapshot fields without raw SQL and kept the legacy columns for current UI/export compatibility.
- Updated waiter confirmation, kitchen/bar dispatch, CSV order export, and popular-item dashboard reads to prefer stored order snapshots over live menu data.
- Added regression coverage proving old confirmed order snapshots survive later dish, guest, and modifier changes on SQLite.

### Prompt 087 - Add Soft Deletes

- Added soft-delete columns and indexes for organizations, brands, branches, menus, menu categories, and menu items, while preserving existing soft-delete support on area nodes and service points.
- Enabled `SoftDeletes` on the important organization/brand/branch/menu models and kept historical relationships readable with `withTrashed()` where order, draft, kitchen, or route context may reference archived records.
- Replaced hard-delete menu/category cascade behavior with soft-delete cascade behavior so ordinary admin and guest lists hide removed menus, categories, and dishes without physically removing rows.
- Added focused regression coverage proving old confirmed order snapshots remain readable after soft-deleting the source organization, brand, branch, area, service point, menu, category, and dish.

### Prompt 086 - Guest Error Pages

- Added a dedicated mobile-first guest error panel for public QR and guest-session problems.
- Covered QR not found, disabled/revoked QR, inactive service point, closed session, rejected guest, stale invite link, and inactive restaurant subscription states.
- Kept guest error rendering in Blade/Livewire with prepared component state and no technical IDs exposed to guests.
- Added focused regression tests for every requested error case while keeping SQLite, database drivers, Livewire polling, and no Redis/WebSockets/S3/Docker.

### Prompt 085 - Harden QR and Guest Session Security

- Added backend checks so inactive service points cannot create guest sessions, guest invite links, join requests, draft item changes, or send a draft to waiter review.
- Marked expired join requests as `expired` when restored through guest polling, keeping them blocked from approval and guest creation.
- Added focused regression tests for inactive service point ordering, rejected guest draft writes, expired join restores, and disabled QR public errors.
- Kept the stack unchanged: SQLite, database cache/session/queue, Livewire polling, and no Redis, WebSockets, S3, Docker, or external services.

### Prompt 084 - Optimize Livewire Polling

- Split the public QR guest table into smaller isolated polling blocks for guests, notifications, join requests, order statuses, draft items, and draft totals.
- Added `GetBranchPollingIntervalAction` so guest polling intervals come from `branch_settings.polling_interval_seconds` through the SQLite-backed database cache.
- Updated the shared draft item block so the active guest page can poll draft rows without loading order status, kitchen ticket status, or confirmed-order totals.
- Kept realtime on Livewire polling only; no WebSockets, Redis, S3, Docker, or external services were added.

### Prompt 083 - SQLite Performance Guardrails

- Added focused SQLite indexes for hot polling, dashboard, notification, kitchen/bar ticket, draft order, and audit log paths.
- Switched growing audit history from a fixed latest-record list to cursor pagination while keeping access checks and prepared row payloads in the backend action.
- Reduced background notification polling pressure with visible-only polling and a slower authenticated unread notification interval.
- Documented shared-hosting SQLite guardrails and kept the stack unchanged: SQLite, database cache/session/queue, no Redis, WebSockets, S3, Docker, or external services.

### Prompt 082 - Notification UI

- Expanded the authenticated notification component from count-only to a small unread event panel for new orders, waiter calls, bill requests, and ready items.
- Added an isolated guest notification block on the public QR table page for join requests, confirmed/rejected orders, and kitchen/bar item progress.
- Added database-only guest notifications for waiter-confirmed draft orders and kitchen/bar `in_progress` item status.
- Kept realtime updates on Livewire polling and limited polling to notification blocks only; no WebSockets, Redis, Push, SMS, Telegram API, mail delivery, S3, Docker, or paid providers were added.

### Prompt 081 - Database Notifications

- Added database-only notifications for new guest join requests, new drafts sent to waiters, kitchen/bar ready items, and rejected draft orders.
- Kept existing guest waiter-call and bill-request notifications on the same Laravel `database` notification channel.
- Made `table_session_guests` notifiable so guest-facing events can be stored without creating user accounts.
- Added a compact Livewire unread notification counter to the authenticated layout with polling and a local mark-read action.
- Verified notification creation on SQLite and kept the stack unchanged: no Push, WebSockets, Redis, SMS, Telegram API, S3, Docker, or paid notification services.

### Prompt 080 - Expand Superadmin Organization Controls

- Expanded the platform dashboard top stats with service point and order counts.
- Added organization-level counters for brands, total branches, active branches, service points, and orders using Eloquent relationships and `withCount`.
- Added superadmin organization controls to open existing organization details, open the audit log, suspend an organization, and reactivate it.
- Renamed the visible inactive action to `Suspend` while keeping the one-plan local subscription model and superadmin bypass intact.
- Kept the stack unchanged: SQLite, database cache/session/queue, Blade + Livewire, no Redis, WebSockets, S3, Docker, Stripe, PayPal, impersonation, or paid billing services.

### Prompt 079 - Simple SaaS Subscription

- Added local `organization_subscriptions` for the single SaaS plan without tariff limits or online billing providers.
- Stored subscription status, manual payment status, start date, and next payment date for each organization.
- Created default active subscriptions when organizations are created through the existing backend action.
- Extended the superadmin platform dashboard with subscription status, payment status, dates, and manual activate/deactivate controls.
- Blocked regular organization access when a subscription is explicitly inactive while preserving the superadmin bypass.
- Kept the stack local and shared-hosting friendly: SQLite, Blade + Livewire, no Stripe, PayPal, Redis, WebSockets, S3, Docker, or paid billing services.

### Prompt 078 - Currency Settings

- Added a fixed local `SupportedCurrency` list for branch currency settings without exchange APIs or paid services.
- Added a shared `MoneyFormatter` for readable branch-currency display such as `€14.50`, `$14.50`, or `14.50 PLN`.
- Changed branch creation/editing and branch settings currency inputs from free text to validated currency selectors.
- Synced `branch_settings.default_currency` with `branches.currency` so guest/menu/order-facing screens use the selected branch currency.
- Updated guest menu and admin stop-list price display while preserving stored menu prices and modifier price deltas without automatic conversion.

### Prompt 077 - Basic Localization

- Added fixed supported interface locales `ru`, `en`, and `lt` through a shared `SupportedLocale` enum.
- Added `users.locale`, profile language selection, and a web middleware that applies the authenticated user's interface language.
- Added guest QR language selection that defaults to the branch language and passes the selected language into the guest menu.
- Added baseline JSON translation files for the most important admin/profile/guest/menu interface strings without AI translation or external services.
- Kept menu translation fallback intact: missing category or dish translations still fall back to the branch default/base menu text.

### Prompt 076 - CSV Data Exports

- Added a restaurant data exports page at `/restaurant/exports` guarded by the flexible `export_data` permission.
- Added streamed CSV downloads for branch orders, manual payments, menu items, and tables/service points without writing export files to local storage.
- Reused branch access rules so organization access and active branch assignments prevent users from exporting another branch's data.
- Added sidebar and restaurant dashboard entry points only for users with export access, while PDF export remains a later step.
- Kept the shared-hosting stack unchanged: SQLite, Blade + Livewire, no paid libraries, Redis, WebSockets, S3, Docker, or external export services.

### Prompt 075 - Local SQLite Backup Action

- Added a superadmin-only `/superadmin/backups/sqlite` download route for the configured SQLite database file.
- Added a small backup resolver Action and invokable controller that stream the current SQLite file without creating backup files on the server.
- Added a platform dashboard backup section with a sensitive-data warning and a disabled media ZIP placeholder for a later local-only step.
- Documented where the SQLite file lives, what backup files must stay out of git, and kept the shared-hosting stack unchanged: no S3, paid backup services, Docker, Redis, or WebSockets.

### Prompt 074 - Add Onboarding Wizard

- Added an authenticated `/onboarding/restaurant` Livewire wizard for creating a new starter restaurant setup from one simple flow.
- The wizard creates organization, brand, branch, first area, first service points, permanent QR codes, and a first active menu with one category and one dish.
- Reused existing organization, brand, branch, area, service point, and QR Actions, and added a small `CreateStarterMenuAction` for the starter menu rows only.
- Added sidebar and dashboard entry points labelled `Настроить ресторан` without changing existing organization/brand/branch CRUD.
- Verified the generated guest URL stays token-only as `/q/{public_token}` and does not expose restaurant, branch, or table IDs.
- Kept the shared-hosting stack unchanged: SQLite, database cache/session/queue, Blade + Livewire, no Redis, WebSockets, S3, Docker, or external services.

### Prompt 073 - Simplify Branch Setup UI

- Added a `Настроить ресторан` wizard to each branch card with the existing setup path: create branch, add zones, add tables, generate QR, print QR, and open the guest menu.
- Prepared branch setup counts in the Livewire component with Eloquent counts/eager loading so Blade stays display-only.
- Simplified visible branch, area, and service point copy for non-technical restaurant staff while keeping existing routes, permissions, CRUD actions, and QR architecture unchanged.
- Enlarged preset buttons for area nodes and service points and kept the current `manage_zones`, `manage_service_points`, and `generate_qr` permission boundaries.
- Added feature coverage for the setup wizard and simplified zone/service point headings.
- Kept the shared-hosting stack unchanged: SQLite, database cache/session/queue, Blade + Livewire, no Redis, WebSockets, S3, Docker, or external services.

### Prompt 072 - Menu Stop-list

- Added a dedicated branch menu stop-list section backed by the existing `menu_items.is_available` field, without adding a new business table.
- Allowed users with `change_availability` to access the branch menu page for availability-only work while keeping full menu CRUD behind `manage_menu`.
- Added branch-list navigation for availability-only users, showing the page as `Stop-list` when they do not have menu CRUD access.
- Kept unavailable dishes visible in admin and guest menu, now shown to guests as `Нет в наличии`, while backend Livewire actions still block adding them to draft orders.
- Verified stop-list availability changes clear the SQLite database cache and create `menu_availability_changed` audit log rows.
- Kept the shared-hosting stack unchanged: SQLite, database cache/session/queue, Blade + Livewire, no Redis, WebSockets, S3, Docker, or external services.

### Prompt 071 - Audit Logs

- Added `audit_logs` as a general control journal with actor user, optional guest/guest token, action, entity, old/new JSON values, and creation timestamp.
- Added `AuditLogAction`, `AuditLog`, `RecordAuditLogAction`, and `BuildAuditLogIndexAction`.
- Logged menu price changes, menu availability changes, dish deletion, service point moves, manual QR reissue, staff permission override changes, waiter order confirmation, order cancellation, manual payment recording, and table-session close.
- Added `/restaurant/audit-log` as a simple Blade + Livewire viewer guarded by `view_audit_log`, with sidebar/dashboard navigation only for users who can access the audit log.
- Verified multiple audit events and `view_audit_log` UI restrictions on SQLite without Redis, WebSockets, S3, Docker, or external logging services.

### Prompt 070 - Restaurant Dashboard

- Added `BuildRestaurantDashboardAction` for a branch/restaurant operational dashboard backed by the SQLite database cache store.
- Extended `/restaurant/dashboard` with active tables, new waiter drafts, cooking orders, ready positions, today amount, popular dishes, and role-aware quick actions.
- Kept report-sensitive amount and popular dish data behind `view_reports`, while operational users such as waiters can still see table/order/kitchen status cards.
- Added quick transitions for menu, tables/service points, QR print, waiter screen, kitchen screen, and reports, with disabled states when the user lacks access.
- Added restaurant dashboard cache invalidation for draft orders, kitchen tickets, and kitchen ticket items, and wired existing order/payment/session observers into the new dashboard cache.
- Verified the dashboard with manager and waiter roles, plus cache invalidation for draft and kitchen item changes, without Redis, WebSockets, S3, Docker, or external BI/reporting services.

### Prompt 069 - Basic Analytics

- Added `BuildBasicAnalyticsDashboardAction` for branch-scoped restaurant dashboard analytics guarded by `view_reports`.
- Added cached metrics for orders today, today amount, average check, popular dishes, active tables, closed sessions, and cancelled orders.
- Cached analytics snapshots through Laravel's explicit `database` cache store with branch/date keys so dashboard refreshes do not rerun the same reads.
- Added model observers for orders, order items, manual payments, and table sessions to invalidate affected branch analytics cache entries.
- Extended the restaurant dashboard with a simple analytics block and a manual refresh action for authorized users.
- Verified analytics demo data and cache invalidation on SQLite without Redis, WebSockets, S3, Docker, external BI tools, or paid services.

### Prompt 068 - Close Table Sessions

- Added the critical `close_table_sessions` permission for manually closing table sessions without granting payment management.
- Added `CloseTableSessionAction` and kept `ClosePaidTableSessionAction` as a compatibility wrapper for the paid-session close flow.
- Extended waiter table detail with close-session flags, a manual-close warning, and a `closeTableSession` Livewire action.
- Closing a session now sets `table_sessions.status` to `closed`, fills `closed_by_user_id` / `ended_at`, moves the service point to `free`, blocks old guest ordering through the closed session, and preserves old orders.
- Verified that closing does not reissue or modify the permanent QR and that a new waiter-opened seating creates a new table session for the same service point.
- Kept SQLite, database drivers, Livewire polling, local storage, and the no Redis/WebSocket/S3/Docker baseline.

### Prompt 067 - Manual Payments

- Added local `manual_payments` records for staff-entered cash, card-terminal, and other offline payments.
- Added the flexible `manage_payments` permission while keeping the fixed `cashier` role able to manage payments without online acquiring.
- Extended waiter table detail with a payment summary, whole-table payment action, per-guest payment actions, manual payment history, and a close-paid-session action through Livewire polling.
- Full manual payment now moves the table session to `paid` and the service point to `paid`; closing the paid session moves it to `closed` and frees the service point.
- Guarded manual payment against unpaid/open drafts so unconfirmed guest selections cannot be paid as confirmed orders.
- Verified whole-table payment, per-guest cashier payment, view-only payment access, and open-draft blocking on SQLite without Redis, WebSockets, S3, Docker, Stripe, PayPal, or external payment services.

### Prompt 066 - Request Bill Button

- Added `RequestBillForTableSessionAction` so an active guest can press `Попросить счёт` from the shared guest basket.
- Requesting the bill changes `table_sessions.status` and the related service point status to `payment_requested` while preserving visible per-guest totals and the table total.
- Added database-only `BillRequestedNotification` for waiters with branch-level `view_orders` access; no online payments or external notification services were added.
- Extended the waiter dashboard polling payload with bill-request counts, branch bill-request lists, service point badges, and a browser-local audio notice.
- Kept bill-requested sessions guarded as current sessions so opening a table does not create a second session for the same service point before manual payment closure exists.
- Verified the request bill flow, duplicate-click idempotency, waiter dashboard visibility, and neighboring waiter/open-table/repeat-order behavior without Redis, WebSockets, S3, Docker, or online payments.

### Prompt 065 - Request Waiter Button

- Added local `notifications` and `waiter_calls` tables for database-only guest waiter-call requests.
- Added `RequestWaiterForTableSessionAction` so an active guest can press `Позвать официанта`, create or reuse one pending call for the service point, and move the service point status to `waiting_waiter`.
- Added database notifications for waiters with branch-level `view_orders` access while respecting active branch assignments and superadmin access.
- Extended the waiter dashboard polling payload with guest-call counts, branch call lists, service point badges, a browser-local audio notice, and a `Processed` action.
- Added `MarkWaiterCallHandledAction` so a waiter can mark a call handled, mark related database notifications read, and restore the previous service point status when it is still safe to do so.
- Verified the guest call -> waiter notification -> handled flow without Redis, WebSockets, S3, Docker, SMS, push, Telegram API, or paid services.

### Prompt 064 - Repeat Orders

- Removed the one-draft-per-table-session database limit so a table session can keep repeat draft history.
- Kept `TableSession::draftOrder()` as the latest current draft and added `TableSession::draftOrders()` for history.
- Updated guest draft item creation so adding positions after a confirmed order creates a new draft in the same table session.
- Updated guest and waiter totals so the table total includes already confirmed non-cancelled orders plus the current open draft without double-counting converted drafts.
- Verified the second-order flow in the same session: guest draft -> waiter confirmation -> explicit kitchen/bar dispatch, while old order snapshots remain unchanged.
- Preserved the shared-hosting stack: SQLite, database drivers, Livewire polling, and no Redis, WebSockets, S3, Docker, or paid services.

### Prompt 063 - Ready Items To Waiter

- Added waiter served tracking on `kitchen_ticket_items` through `served_at` and `served_by_user_id`.
- Added order/service point status sync from kitchen/bar ticket item states: in progress, ready, and served.
- Extended waiter table detail polling so ready kitchen/bar positions are visible with ready/served counts and can be marked served by a waiter.
- Extended the guest shared cart polling block so guests see `Принято`, `Готовится`, `Готово`, or `Подано` from confirmed order and ticket item states.
- Protected served ticket items from being changed again by kitchen/bar staff and kept guests unable to mark items served.
- Verified the full flow kitchen ready -> waiter sees ready -> waiter marks served -> guest sees served without Redis, WebSockets, S3, Docker, or paid services.

### Prompt 062 - Bar Department Screen

- Added a bar dashboard at `/restaurant/bar/dashboard` with Livewire polling every 1 second and no WebSockets.
- Extracted shared department screen actions, Livewire base component, and Blade view so kitchen and bar screens do not duplicate the full ticket UI.
- Filtered the bar dashboard to active `bar` departments only, showing service point, zone, drinks, modifiers, comments, item status, and a live timer.
- Allowed access for superadmins, fixed `bartender` and `head_chef` roles, or users with `view_orders` or `send_to_kitchen`, while preserving branch assignment limits.
- Added restaurant dashboard and sidebar navigation that appears only when the current user can access at least one bar department.
- Verified bar access, department filtering, item status changes, forbidden staff access, and neighboring kitchen screen behavior.

### Prompt 061 - Kitchen Screen

- Added a kitchen dashboard at `/restaurant/kitchen/dashboard` with Livewire polling every 1 second and no WebSockets.
- Added `KitchenTicketItemStatus` and `kitchen_ticket_items.status` with `new`, `in_progress`, and `ready` item states.
- Added kitchen access resolution for fixed `head_chef` and `cook` organization roles, superadmins, or users with the new flexible `view_kitchen` permission.
- Added backend actions to build department-scoped kitchen payloads and update ticket item status without querying from Blade.
- Added navigation from the restaurant workspace/sidebar only for users who can access at least one kitchen department.
- Hardened `SystemPermissionsSeeder` so adding new fixed permissions does not collide with the unique `permissions.sort_order` index on existing SQLite databases.
- Verified department filtering, item status changes, branch assignment restrictions, permission access, and neighboring dispatch/permission tests.

### Prompt 060 - Send Orders To Kitchen Bar

- Added `kitchen_tickets` and `kitchen_ticket_items` for department-split dispatch of confirmed waiter orders.
- Added `SendOrderToKitchenBarAction`, guarded by `send_to_kitchen`, to move orders from `confirmed_by_waiter` to `sent_to_kitchen_bar`, create department tickets, update service point status to `cooking`, and write an `order_status_logs` row.
- Extended the waiter table detail page with a `Send to kitchen/bar` action and dispatch status display.
- Extended the guest shared cart polling block so guests see that kitchen/bar accepted the order after dispatch.
- Verified department splitting, idempotent dispatch, permission checks, and SQLite migrations without Redis, WebSockets, S3, Docker, or paid services.

### Prompt 059 - Assign Menu Items To Departments

- Made the dish department selector save the branch's default `kitchen` department when no explicit department is selected.
- Kept department assignment in the existing branch menu admin form, so pizza, coffee, dessert, and hookah items can be routed to kitchen, bar, dessert, or hookah departments.
- Added guest menu cache invalidation for kitchen department create/update/delete events and verified item department assignment changes clear the database cache.
- Preserved the shared-hosting stack: SQLite, database cache, Blade + Livewire, and no kitchen screen, Redis, WebSocket, S3, Docker, or paid service was added.

### Prompt 058 - Kitchen Departments

- Added branch-level `kitchen_departments` with fixed department types: `kitchen`, `bar`, `dessert`, `hookah`, and `custom`.
- Added default department seeding for existing branches and automatic standard department creation for new branches.
- Added optional `menu_items.kitchen_department_id` so dishes can be routed to a branch department.
- Added kitchen department snapshots on `order_items` during waiter confirmation: department id, type, and name are copied from the source menu item.
- Extended branch menu admin with simple kitchen department CRUD and dish department assignment, guarded by `manage_menu`.
- Kept this step backend/admin-only: no kitchen/bar screen, dispatch workflow, payments, Redis, WebSocket, S3, Docker, or paid service was added.

### Prompt 057 - Order Status Logs

- Added `order_status_logs` as an append-only audit history table for draft and confirmed order status events.
- Added `OrderStatusLogEvent`, `OrderStatusLog`, factory, relationships, and shared `CreateOrderStatusLogAction`.
- Added logging for guest draft creation/editing, guest send-to-waiter, waiter draft editing, waiter confirm/reject/return-to-draft, and manual confirmed-order status changes.
- Added `ChangeOrderStatusAction` for backend order status changes with `confirm_orders`, `send_to_kitchen`, and `cancel_orders` checks.
- Preserved history with nullable `nullOnDelete` links plus actor/status snapshots, so logs do not disappear if related users, guests, drafts, or orders are later removed.
- Kept this step backend-only: no kitchen/bar screen, payment flow, WebSocket, Redis, S3, Docker, or paid service was added.

### Prompt 056 - Orders Schema

- Completed the real order lifecycle enum with `confirmed_by_waiter`, `sent_to_kitchen_bar`, `in_progress`, `ready`, `served`, `payment_requested`, `paid`, `closed`, and `cancelled`.
- Kept waiter confirmation as the only path that converts a shared draft into an `orders` row and `order_items` snapshots.
- Added inverse Eloquent relationships from service points and menu items to confirmed order records.
- Added feature coverage proving confirmed order items keep dish names, prices, modifiers, comments, guest names, and totals unchanged after the source menu item or modifier option changes.
- Kept this step schema/backend-only: no kitchen/bar dispatch, payments, Redis, WebSocket, S3, Docker, or paid integrations were added.

### Prompt 055 - Waiter Draft Editing

- Added the fixed `edit_pending_orders` permission while keeping `confirm_orders` able to edit pending sent drafts.
- Added waiter draft edit actions for adding active-menu positions, updating quantity/comments/modifiers, and deleting draft items before confirmation.
- Extended waiter table detail with edit/delete controls, an add-position form, and a mobile-friendly edit sheet for pending sent drafts.
- Waiter edits move a sent draft to `waiter_review`, recalculate `draft_order_items` snapshot totals, and remain visible to guests through the existing shared cart polling block.
- Extended the waiter dashboard to keep both `sent_to_waiter` and `waiter_review` drafts visible.
- Kept kitchen/bar protected: editing a draft still does not create a real order or send anything to kitchen/bar, and no Redis, WebSocket, S3, Docker, or paid services were added.

### Prompt 054 - Waiter Draft Confirm Reject

- Added `orders` and `order_items` for real order snapshots created after waiter confirmation.
- Added waiter review fields to `draft_orders`: rejection reason/audit fields and converted-to-order audit fields.
- Added `OrderStatus::ConfirmedByWaiter`, order models, factories, and relationships from branches, table sessions, draft orders, guests, and order items.
- Added waiter actions to confirm a sent draft, reject a sent draft with a reason, and return a rejected draft back to `draft`.
- Extended the waiter table detail page with `confirm_orders`-guarded confirm/reject controls and order status display.
- Extended the guest shared cart polling block so rejected drafts show the waiter rejection reason and confirmed drafts show that editing is closed.
- Kept kitchen/bar protected: confirmation creates a real order with `confirmed_by_waiter` status but does not send anything to kitchen or bar, and no Redis, WebSocket, S3, Docker, or paid services were added.

### Prompt 053 - Waiter Table Detail

- Added a waiter table detail route at `/restaurant/waiter/tables/{table_session}` guarded by auth and `view_orders`.
- Added `BuildWaiterTableDetailAction` and `App\Livewire\Waiter\TableDetail` to prepare branch, zone, service point, session, draft, guests, positions, comments, modifiers, and totals before Blade rendering.
- Extracted `ResolveWaiterAccessibleBranchIdsAction` so the waiter dashboard and table detail share the same superadmin, organization permission, and active branch assignment rules.
- Added dashboard links from open sessions and sent drafts to the detail page.
- The detail page polls every 1 second with Livewire and stays display-only: no waiter confirmation, final order conversion, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.
- Added feature tests for auth, `view_orders`, branch assignment restrictions, rendered table details, guest ordering, totals, modifiers, comments, and Livewire refresh behavior.

### Prompt 052 - Waiter Dashboard Shell

- Added an authenticated waiter dashboard at `/restaurant/waiter/dashboard` guarded by `view_orders`.
- Added `BuildWaiterDashboardAction` to prepare branches, service points, open sessions, and `sent_to_waiter` drafts before Blade rendering.
- The dashboard polls every 1 second with Livewire and can play a small browser audio notice when a new sent draft appears.
- Waiter branch visibility respects active `branch_users` assignments when they exist, otherwise it uses organization-level `view_orders` access.
- Added waiter dashboard navigation from the restaurant workspace for users with available order-view branches.
- Kept this step display-only: no waiter confirmation, final order conversion, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 051 - Send Draft To Waiter

- Added `SendDraftOrderToWaiterAction` so any active guest can send the shared table draft to waiter review.
- The public shared cart now shows `Отправить официанту` and asks for inline confirmation when not all active guests are ready.
- Sending the draft sets `draft_orders.status` to `sent_to_waiter`, fills `sent_to_waiter_at` and `sent_by_guest_id`, clears guest `ready_at`, and blocks further draft edits.
- The related service point status now moves to `has_new_order` so a future waiter dashboard can surface the waiting draft.
- Kept this step waiter-handoff-only: no waiter confirmation screen, final orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 050 - Guest Ready Status

- Added `ready_at` to `table_session_guests` so each active guest can mark or clear readiness.
- Added a backend action for toggling readiness with active guest and open table-session checks.
- Added a `Я готов` / `Снять готовность` action to the isolated shared cart block.
- The guest list and shared cart now show `Готов` / `Не готов`, and the cart shows ready guest count versus active guest count.
- Kept this step readiness-only: no waiter submission action, final orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 049 - Shared Table Cart UI

- Reworked the isolated public QR basket into a shared table cart grouped by active guests alphabetically.
- Each guest section now shows that guest's draft positions, line prices, modifiers, comments, item counts, and guest total.
- The cart keeps the table total visible and continues to refresh only the basket block through Livewire polling.
- All active guests see the same grouped cart information; edit and delete controls appear only on the current guest's own draft positions.
- Draft cart reads current `draft_orders` and `draft_order_items` from SQLite on refresh and does not cache draft state.
- Kept this step UI-only for the shared cart: no waiter review screen, final orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

## 2026-06-03

### Prompt 048 - Guest Draft Item Editing

- Added guest-owned draft item editing in the isolated public QR basket component.
- Active guests can change quantity, comment, and available modifier selections for their own draft positions.
- Active guests can delete only their own draft positions.
- The shared basket now separates `Мои позиции` from `Позиции других гостей` while keeping per-guest and table totals updated through Livewire polling.
- Editing and deleting recheck the browser guest token, active guest status, draft ownership, table session, and draft status.
- Draft item editing is blocked after the shared draft is sent to waiter review; this step does not add waiter screens, final orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 047 - Guest Draft Items

- Added `AddGuestDraftOrderItemAction` so active guests can add configured menu items into the shared table draft.
- Guest menu additions now persist to `draft_order_items` with item name, price, selected modifier, and comment snapshots.
- Added an isolated `App\Livewire\PublicQr\DraftOrder` polling component for the shared basket block.
- The shared basket now shows all draft positions, per-guest totals sorted by guest name, and the total table amount.
- Rejected or removed guests cannot add draft items, and item creation rechecks the guest token, table session, menu item, and modifier availability.
- Kept this step draft-only: no waiter review screen, final orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 046 - Draft Order Schema

- Added `draft_orders` with one shared draft per `table_session`.
- Added `draft_order_items` so each draft item belongs to a concrete table session guest and can keep a menu item reference.
- Added `DraftOrderStatus` for `draft`, `sent_to_waiter`, `waiter_review`, `rejected`, and `converted_to_order`.
- Added model relationships from table sessions, guests, and menu items to draft orders/items.
- Added snapshot fields for item name, quantity, unit price, modifier total, line total, selected modifiers, and guest comments.
- Added backend helpers and tests for total amount and alphabetically sorted per-guest totals.
- Kept this step schema/backend-only: no guest add-to-draft UI, waiter review screen, final orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 045 - Guest Modifier Selection

- Added modifier groups and available modifier options to the cached guest menu payload.
- Added a mobile-first guest bottom sheet for configuring available dishes before order submission.
- Required modifier groups now block completion until the guest selects enough available options.
- Modifier `price_delta` values now affect the displayed item total in the guest UI.
- Guests can add a local dish comment while configuring an item.
- Kept this step UI-only: no persistent cart rows, shared order draft, waiter submission, orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 044 - Menu Modifiers

- Added `modifier_groups`, `modifier_options`, and `menu_item_modifier_groups` for reusable branch-level dish modifiers.
- Added modifier group and option models, factories, relationships, schema coverage, and cache-clearing observers.
- Added branch menu admin CRUD for modifier groups, modifier options, and assigning modifier groups to dishes.
- Kept modifier price deltas behind `change_prices` and modifier option availability behind `change_availability`.
- Modifier changes now clear the branch guest menu cache through Laravel's `database` cache store.
- Kept this step admin-only: no guest modifier selection, cart, shared order draft, orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 043 - Menu Translations

- Added `menu_category_translations` and `menu_item_translations` for translated category and dish names/descriptions.
- Added menu translation models, factories, relationships, and observers.
- Added guest menu language selection for `ru`, `en`, and `lt`.
- Made guest menu cache language-specific through Laravel's `database` cache store.
- Translation changes now clear the branch guest menu cache, and missing translations fall back to base menu text.
- Kept this step translation-only: no AI translation, cart, shared order draft, orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 042 - Database Cache for Menu

- Made guest menu caching explicitly use Laravel's `database` cache store instead of relying on the default store.
- Added a short database-backed lock around guest menu payload rebuilds so repeated guest reads stay shared-hosting friendly.
- Strengthened cache invalidation coverage for menu, category, and item updates, including price changes.
- Verified the guest menu does not keep stale prices after a dish price update.
- Kept this step cache-only: no cart, shared order draft, orders, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 041 - Guest Menu Display

- Added a mobile-first guest menu block to the active public QR table page.
- Guests now see the current branch's first active menu, active categories, dishes, prices, local dish photos, and unavailable dish state.
- Added `GetGuestMenuForBranchAction` with database cache-backed menu payloads.
- Added menu, category, and item observers to forget the guest menu cache when menu data changes.
- Kept this step display-only: no cart add action, shared order draft, waiter confirmation flow, kitchen/bar flow, payments, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 040 - Menu CRUD

- Added a branch admin menu page guarded by `manage_menu`.
- Added CRUD for menus, categories, and dishes with simple manual sort ordering and a branch dish list.
- Added local dish photo upload/removal on the public disk without S3 or external media services.
- Kept price edits behind `change_prices` and availability edits behind `change_availability`.
- Kept this step admin-only: no guest menu display, translations, modifiers, order draft, kitchen/bar flow, payment logic, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 039 - Menu Schema

- Added base menu tables: `menus`, `menu_categories`, and `menu_items`.
- Added menu, category, and item models, factories, enum-backed menu status, branch relationship, and focused schema tests.
- Kept this step schema-only: no menu CRUD UI, guest menu display, translations, modifiers, order draft, kitchen/bar flow, payment logic, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 038 - Guest Table Page Shell

- Added the main guest table page shell for active table guests.
- The shell shows venue, current place, saved entry state, guest invite action, guest list, menu placeholder, shared order placeholder, and a `0,00` total.
- Added an isolated Livewire polling component for the guest list so only the guest block refreshes.
- Kept the step limited to the table page shell: no menu items, order draft, kitchen/bar, payment, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 037 - Guest Invite Share Link

- Added a guest invite action inside the active public QR table session.
- Active guests can create a hidden invite link for the current table session and share it through the browser native share API when available.
- Added a copy-link fallback for browsers without native share support.
- Opening the invite link keeps the public QR URL token-based and creates a pending join request after the new guest enters a name.
- Kept the step limited to guest invitation and join approval flow: no menu, order, payment, kitchen/bar, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 036 - Guest Join Approval UI

- Added a Livewire polling block where active table guests see pending join requests.
- Allowed any active guest at the same table session to approve or reject a pending guest from the public QR UI.
- Added a waiting-state refresh for the new guest so approval restores them as an active guest and rejection shows a clear message.
- Kept the step limited to guest join approval: no menu, order draft, kitchen/bar, payment, Redis, WebSocket, S3, or Docker.

### Prompt 035 - Table Session Join Requests

- Added `table_session_join_requests` for guests who want to join an existing table session.
- Added fixed join request statuses: `pending`, `approved`, `rejected`, and `expired`.
- Added backend logic to create a join request when a table session already has active guests.
- Added backend actions so any active guest in the same table session can approve or reject a pending request.
- Kept the step limited to database and backend logic: no approval UI, menu, order, payment, kitchen/bar, Redis, WebSocket, S3, or Docker.

### Prompt 034 - Persist Guest Token

- Restored public QR guests from the saved browser guest token after page refresh.
- Added guest access state for active, closed, rejected, removed, pending approval, and left guest/session cases.
- Blocked rejected and removed guests from future item-adding capability while keeping them out of normal user accounts.
- Kept the step limited to guest token persistence: no menu, order, payment, kitchen/bar, or join approval UI.

### Prompt 033 - Table Session Guests

- Updated table session guests to store `guest_name` and a random `guest_token`.
- Added the `rejected` guest status for the future join approval flow.
- The public QR entry flow now queues the guest token in a browser cookie and keeps the guest out of normal user accounts.
- Guest relationships now return guests alphabetically by name.
- Kept the step limited to guest identity inside a table session: no join approval UI, menus, orders, kitchen/bar, or payment logic.

### Prompt 032 - Guest-Created Pending Session

- Added `table_session_guests` for storing guests inside a table session.
- Added a guest-created pending session action for the public QR landing.
- The first guest now creates a pending table session and becomes the first active guest when branch settings allow guest-created sessions.
- Added a SQLite-safe pending-session guard so repeat submits do not create duplicate pending sessions for the same service point.
- Kept the step limited to guest entry and pending sessions: no menu, orders, kitchen/bar, payment, or join approval flow.

### Prompt 031 - Waiter Open Table Action

- Added a waiter open-table backend action that creates an active table session for a service point.
- Added a SQLite-safe active-session guard so one service point cannot have two active table sessions.
- Updated the service point page with an `Open table` action for users with `view_orders` or `confirm_orders`.
- Opening a table moves the service point status to `occupied` and shows the active session in the admin UI.
- Kept the step limited to table opening: no guests, orders, order drafts, kitchen/bar, or payment logic.

### Prompt 030 - Table Sessions Schema

- Added the `table_sessions` table for branch and service point session lifecycle tracking.
- Added fixed table session statuses from `pending` through `closed` and `cancelled`.
- Added fixed session sources for waiter-opened and guest-created flows.
- Added the TableSession model, factory, enum casts, branch/service point/user relationships, and focused schema tests.
- Kept the step schema-only: no guests, menus, orders, kitchen/bar flow, payment flow, or guest landing session creation yet.

### Prompt 029 - Guest QR Landing Page

- Replaced the public QR placeholder with a mobile-first guest landing page.
- Shows the venue name, local logo when available, current area, current service point, short code, guest name field, and `Войти за стол` button.
- Added Livewire name entry validation without guest registration, table sessions, menus, or orders.
- Kept QR identity stable: moving or renaming a service point still keeps the same `/q/{public_token}` URL while showing current data.
- Added tests for logo display, current area/service point display, name entry, validation, and QR stability.

### Prompt 028 - Local Media Storage

- Added local public-storage logo uploads for organizations, brands, and branches.
- Added `logo_path` fields for organization, brand, and branch records.
- Added reusable local image storage actions with image type checks and a 2 MB size limit.
- Added simple upload/remove controls to the existing organization, brand, and branch screens.
- Kept storage local to `storage/app/public` without S3, paid services, or external media providers.
- Added tests for logo upload, replacement, removal, URL generation, and file validation.

### Prompt 027 - Bulk QR Print

- Added a branch-level bulk QR print page guarded by the `generate_qr` permission.
- Added area filtering, service point selection, and a browser print-friendly multi-sticker preview.
- Added actions to select all visible service points with active QR codes and create missing QR codes for shown service points.
- Kept QR identity permanent: existing active QR codes are reused and service point rename or area moves do not affect printed URLs.
- Added tests for access control, area filtering, multi-QR selection, missing QR creation, and branch-list navigation.

### Prompt 026 - QR Print Template

- Added a browser print-friendly sticker template for one service point QR.
- The sticker shows a restaurant logo when a local logo field exists, otherwise it uses the brand name as a text mark.
- The sticker prints the menu scan text, QR image, and short code without printing service point number or area by default.
- Added a `print_table_number` setting to include the service point display number and show a stale-label warning.
- Verified the print media view in the browser without using PDF services.

### Prompt 025 - QR Admin Display Page

- Added an authenticated QR admin page for a specific service point QR record.
- The page shows branch, current area, current service point, public guest URL, SVG QR image, short code, status, and creation date.
- Added local SVG QR rendering and SVG download without external services or storage uploads.
- Added actions to open the guest URL, disable an active QR, and manually reissue a QR after a danger warning.
- Verified that normal service point rename or area move does not change or reissue the QR.

### Prompt 024 - Public QR Route

- Added the public `/q/{token}` route for permanent QR links without exposing organization, branch, service point, or table IDs.
- Added a simple mobile-first guest QR landing page that resolves active QR codes to the current service point, branch, brand, and organization.
- Added clear public messages for disabled QR codes, revoked QR codes, inactive service points, and unknown tokens.
- Verified that moving or renaming a service point keeps the QR URL stable while the public page shows the current place data.
- Added tests for active, moved, disabled, revoked, inactive, and unknown QR token cases.

### Prompt 023 - QR Generation Action

- Added a backend action that creates an active permanent QR for a service point only when one does not already exist.
- Added random 64-character public tokens and short human-readable QR codes for printing preparation.
- Added QR controls to the service point page for users with the `generate_qr` permission.
- Added QR status, short code, and `/q/{public_token}` display without adding the public QR route or PDF output.
- Added tests proving repeated generation does not create a second active QR and QR identity stays stable after service point rename or move.

### Prompt 022 - Permanent QR Schema

- Added the `qr_codes` table for permanent QR records attached to service points.
- Added QR statuses for active, disabled, and revoked records.
- Added public token and short code fields without storing table numbers, service point names, area names, or branch IDs.
- Added SQLite-safe uniqueness enforcement so one service point can have only one active QR while keeping disabled and revoked history.
- Added QR model, factory, service point relationships, audit user relationships, and tests for stability across service point rename/move.

### Prompt 021 - Service Point Statuses

- Replaced the old service point statuses with table-flow statuses from `free` through `closed` and `blocked`.
- Updated `service_points.status` to default to `free` on SQLite and migrated old status values safely.
- Added manual service point status changes from the service point page.
- Allowed users with `manage_service_points` and users with the fixed `waiter` role to change service point status.
- Added a backend status update action that future table sessions and orders can reuse.
- Added tests for status taxonomy, default status, manager status changes, and waiter-only status changes.

### Prompt 020 - Service Points CRUD

- Added a branch service point management page guarded by the `manage_service_points` permission.
- Added create, rename, zone move, icon selection, capacity editing, disable, and enable actions for service points.
- Added stable one-time `internal_code` creation so future QR identity is not tied to the service point name, number, or area.
- Added the service point management route and branch-list link.
- Added tests for access control, create, move between zones, rename, disable, invalid area assignment, and stable identity during edits.

### Prompt 019 - Service Points Schema

- Added the `service_points` table for physical branch service locations.
- Added fixed service point types for tables, bar seats, VIP tables, rooms, booths, sunbeds, hotel rooms, pickup windows, delivery points, and other points.
- Added service point status values, model, factory, branch relationship, area node relationship, metadata casting, coordinate casting, and soft delete support.
- Added tests for schema fields, fixed taxonomies, branch and area relationships, moving between areas without changing identity fields, optional area assignment, and soft delete behavior.

### Prompt 018 - Area Nodes CRUD

- Added a branch area management page guarded by the `manage_zones` permission.
- Added simple nested area tree UI for floors, groups, halls, terraces, VIP rooms, and custom areas.
- Added create, rename, move, icon selection, enable/disable, and soft delete actions for area nodes.
- Added tests for access control, nested creation, moving, disabling, soft delete behavior, and cycle prevention.

### Prompt 017 - Area Nodes Schema

- Added the `area_nodes` table for nested branch area structure.
- Added fixed area node types for groups, floors, halls, terraces, VIP rooms, bar areas, banquet halls, rooms, hotel areas, pickup areas, delivery areas, and custom areas.
- Added the AreaNode model, factory, branch relationship, parent/children relationships, metadata casting, and soft delete support.
- Added tests for schema fields, fixed area types, nesting, metadata casting, and soft delete behavior.

### Prompt 016 - Permission Override UI

- Added a staff permission override page guarded by `manage_staff`.
- Added default / allow / deny permission states for individual staff users.
- Added critical permission warnings and self-edit protection.
- Added computed effective permission display that respects superadmin access, user overrides, and role defaults.
- Added staff list links and tests for access control, override persistence, and superadmin behavior.

### Prompt 015 - Staff Management UI

- Added the `branch_users` table for branch-level staff assignments.
- Added organization and branch staff management Livewire pages.
- Added manual staff creation with fixed role assignment.
- Added invite link and invite code creation from the staff UI without sending email or SMS.
- Added staff activate/deactivate actions guarded by `manage_staff`.
- Added tests proving regular users without `manage_staff` cannot access staff pages.

### Prompt 014 - Staff Invitations

- Added staff invitation statuses for pending, accepted, expired, cancelled, and rejected invitations.
- Added the `invitations` table for organization, brand, and branch-scoped staff invitations.
- Added the Invitation model, factory, relationships, and backend creation action.
- Added backend safeguards so brand and branch invitation scopes must belong to the selected organization.
- Added tests for invitation schema, generated token/code defaults, fixed role assignment, and invalid scope rejection.

### Prompt 013 - Superadmin Access

- Added first-superadmin seeding through safe `.env` configuration.
- Added protected platform dashboard access for users with the fixed `superadmin` role.
- Hid platform dashboard navigation from regular users and blocked direct access with `403 Forbidden`.
- Added a simple platform dashboard that lists all organizations, brands, branches, and users.
- Updated user access checks so superadmins bypass organization and branch-level restrictions.

### Prompt 012 - Branch Settings

- Added branch settings defaults for order confirmation, guest approvals, guest-created sessions, waiter-opened sessions, invite links, polling interval, language, currency, service charge, tips, and order flow mode.
- Updated branch settings so guest-created sessions and guest invite links are enabled by default.
- Verified the `branch_settings` SQLite defaults through migration and schema inspection.

### Foundation

- Prepared a Laravel + Livewire + SQLite foundation for a shared-hosting restaurant SaaS platform.
- Configured SQLite as the only database connection, with the database file stored at `database/database.sqlite`.
- Configured cache, sessions, and queues to use database-backed storage.
- Added basic Blade + Livewire layout zones for guest, auth, restaurant dashboard, and superadmin dashboard surfaces.
- Added Laravel/Fortify authentication flows for registration, login, logout, password reset, and profile/security pages.
- Added fixed system roles and seeded them from enum-backed definitions.
- Added flexible permissions with role permissions and user-level overrides.
- Added organizations as restaurant-business owners, including owner membership creation.
- Added organization user memberships with role, status, join date, and inviter fields.
- Added brands inside organizations.
- Added branches inside brands and organizations.
- Added branch settings stored in `branch_settings`, including safe defaults for waiter confirmation, guest approval, and 1-second polling.
- Added tests for authentication, layout zones, roles, permissions, organizations, organization access, brands, branches, and branch settings.

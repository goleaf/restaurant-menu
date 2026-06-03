# Changelog

## 2026-06-04

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

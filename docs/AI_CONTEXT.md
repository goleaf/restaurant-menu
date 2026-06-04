# AI Context

This file is the working memory for coding agents. Read it before each prompt and update it after each completed step.

## Current Stack

- Laravel 13.13
- PHP 8.5
- Livewire 4.3
- Flux UI Free 2.14
- Blade server-rendered UI
- SQLite only
- Database cache
- Database sessions
- Database queue
- Local public storage in `storage/app/public`
- Pest 4
- Vite / Tailwind CSS 4

## Main Business Rules

- The product is a SaaS platform for restaurants, cafes, bars, hotels, food courts, and similar venues.
- It must grow beyond a simple QR menu, but each prompt must stay small.
- One physical table / place / service point should have one active permanent QR code.
- QR links must not expose restaurant IDs, branch IDs, table IDs, or table numbers.
- Orders must require waiter confirmation by default.
- Each repeat order in the same table session must create a new draft, require waiter confirmation, and preserve previous confirmed orders.
- New guests must require approval by default.
- Branch realtime behavior must use Livewire polling, not WebSockets.
- Active guest waiter calls must use local database state and database notifications only.
- Active guest bill requests must use `table_sessions.status = payment_requested`, local service point status, database notifications, and Livewire polling only.
- Manual payments must be offline staff-entered records only; no Stripe, PayPal, online acquiring, or external payment service is connected.
- Basic analytics must stay lightweight, branch-scoped, and cached through the database cache store; no Redis, external BI service, or heavy refresh query loop.

## What Is Already Done

- Laravel + Livewire project foundation.
- SQLite-only database configuration.
- Database-backed cache, sessions, and queues.
- Guest, auth, restaurant dashboard, and superadmin dashboard layout zones.
- Fortify-backed authentication.
- Fixed system roles.
- Flexible permissions with role permissions and user overrides.
- Organizations.
- Organization user memberships.
- Brands.
- Branches.
- Branch settings.
- Local media storage for organization, brand, and branch logos.
- Area nodes nested branch schema and CRUD UI.
- Service points schema and CRUD UI.
- Service point operational statuses and manual status changes.
- Branch menu CRUD with branch menus, nested menu categories, menu items, local dish photos, menu category/item translation tables, branch-level kitchen departments, menu-item department assignment with default kitchen fallback, branch-level menu modifiers, and permission-gated price/availability changes.
- Table sessions schema for branch/service point lifecycle tracking.
- Waiter/admin open-table action and service point UI for creating active table sessions.
- Guest-created pending table sessions from the public QR landing.
- Table session guests with guest names, random browser guest tokens, cookie restore, statuses, and alphabetical ordering.
- Table session join requests with backend create / approve / reject logic, guest approval UI, guest invite share links, guest table page shell, and database-cached guest menu display with modifier selection.
- Draft order schema with repeat draft history per table session, latest/current draft access, guest-owned draft items with price snapshots, guest add/edit/delete UI for own positions, guest ready status, send-to-waiter handoff, waiter edit/confirm/reject actions, and an isolated shared table cart polling block grouped by guest.
- Real order snapshot schema in `orders` and `order_items`, created only after waiter confirmation, with the prepared order lifecycle status enum and kitchen department snapshots.
- Order status log schema in `order_status_logs` for persistent draft/order history.
- Waiter dashboard shell and waiter table detail with branch/service-point/session status, sent/waiter-review draft visibility, guest positions, modifiers, comments, totals, edit controls, and confirm/reject controls through Livewire polling.
- Kitchen/bar dispatch for confirmed orders with department-split `kitchen_tickets`, explicit `send_to_kitchen` permission checks, service point status updates, guest accepted state, and order status logging.
- Basic kitchen and bar screens for dispatched department tickets with per-item `new`, `in_progress`, and `ready` statuses.
- Waiter ready/served handoff: kitchen/bar ready items appear in waiter table detail, waiters can mark ready items served, service point status can move to `ready_to_serve`, and guests see `Принято` / `Готовится` / `Готово` / `Подано`.
- Guest waiter-call button on the public QR table shell with `waiter_calls`, database notifications, waiter dashboard polling, and handled state.
- Guest request-bill button on the public QR shared basket with `table_sessions.status = payment_requested`, `service_points.status = payment_requested`, database notifications, waiter dashboard polling, and per-guest/table totals.
- Manual payment flow with local `manual_payments`, whole-table and per-guest staff payment actions, `manage_payments` permission, fixed cashier access, paid session status, and table-session close action.
- Manual table-session close with the critical `close_table_sessions` permission; closing moves the session to `closed`, frees the service point, blocks old guest ordering, preserves old orders, and keeps the permanent QR unchanged.
- Basic restaurant dashboard analytics with `view_reports` access, SQLite/database-cache snapshots, and cache invalidation on order, order item, payment, and session changes.
- Permanent QR schema, generation action, admin display page, simple and bulk browser print templates, and public QR guest landing with name entry.
- Basic superadmin access for the platform dashboard.
- Staff invitation backend foundation.
- Simple organization and branch staff management UI.
- Staff permission override UI.

No menu translation admin editor, QR PDF generation, online payment provider, or advanced kitchen production history has been implemented yet.

## Tables

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `notifications`
- `passkeys`
- `roles`
- `permissions`
- `permission_role`
- `role_user`
- `permission_user_overrides`
- `organizations`
- `organization_users`
- `brands`
- `branches`
- `menus`
- `menu_categories`
- `menu_category_translations`
- `menu_items`
- `menu_item_translations`
- `kitchen_departments`
- `modifier_groups`
- `modifier_options`
- `menu_item_modifier_groups`
- `area_nodes`
- `service_points`
- `table_sessions`
- `table_session_guests`
- `table_session_join_requests`
- `waiter_calls`
- `manual_payments`
- `draft_orders`
- `draft_order_items`
- `orders`
- `order_items`
- `kitchen_tickets`
- `kitchen_ticket_items`
- `order_status_logs`
- `qr_codes`
- `branch_users`
- `branch_settings`
- `invitations`
- `migrations`

## Current Domain Model

Organization:

- Represents the company or owner of a restaurant business.
- Has many brands.
- Has many branches.
- Has many users through `organization_users`.
- Stores optional `logo_path` for a locally stored logo.

Brand:

- Belongs to an organization.
- Has many branches.
- Stores optional `logo_path` for a locally stored logo.

Branch:

- Belongs to a brand and an organization.
- Is the current working unit for future menu, zones, service points, and orders.
- Has one settings record.
- Has many menus.
- Has many kitchen departments.
- Has many modifier groups.
- Has many nested area nodes.
- Has many service points.
- Has many branch staff assignments through `branch_users`.
- Stores optional `logo_path` for a locally stored logo.
- New branches created through `CreateBranchAction` receive standard kitchen departments through `SeedKitchenDepartmentsForBranchAction`.

Menu:

- Stored in `menus`.
- Belongs to one branch through `branch_id`.
- Status is cast to `MenuStatus`.
- Current status values are `draft`, `active`, and `archived`.
- Stores `name` and `sort_order`.
- Has many categories and items.
- Managed from the branch menu page guarded by `manage_menu`.
- The menu admin UI can create, edit, sort, and delete menus.
- Active guests see the current branch's first active menu on the public QR table page.
- Guest menu payloads are cached through the explicit `database` cache store with the key `guest-menu:branch:{branch_id}:language:{language_code}`.
- `GetGuestMenuForBranchAction` builds and caches the guest menu payload for five minutes.
- Guest menu cache rebuilds use the database-backed lock key `guest-menu:branch:{branch_id}:language:{language_code}:lock`.
- Guest menu cache invalidation must use the database cache store and must not use Redis or cache tags.
- Supported guest menu languages are `ru`, `en`, and `lt`.
- If no guest language is selected, `branch_settings.default_language` is used.
- If a selected category or item translation is missing, the guest menu falls back to the base category/item `name` and `description`.

Menu category:

- Stored in `menu_categories`.
- Belongs to one menu through `menu_id`.
- Can optionally belong to a parent category through `parent_id`.
- Supports nested category structures through `parent` and `children` relationships.
- Stores `name`, optional `description`, optional `image`, optional `icon`, `sort_order`, and `is_active`.
- Has many translations through `menu_category_translations`.
- Managed from the branch menu page guarded by `manage_menu`.
- The menu admin UI can create, edit, sort, activate/deactivate, and delete categories.
- Category image paths still exist in the schema, but category upload UI is not implemented yet.

Menu item:

- Stored in `menu_items`.
- Belongs to one menu through `menu_id`.
- Belongs to one category through `category_id`.
- Can belong to one branch kitchen department through `kitchen_department_id`; the admin form's empty `Default kitchen` choice resolves to the branch's default `kitchen` department before saving.
- Stores `name`, optional `description`, `price`, optional `image`, optional `weight`, optional `volume`, optional `calories`, `is_available`, and `sort_order`.
- `price`, `weight`, and `volume` are decimal casts; `is_available` is a boolean cast.
- Managed from the branch menu page guarded by `manage_menu`.
- Dish photo upload/removal is implemented with local public storage only.
- Dish photos are stored under `media/organizations/{organization}/brands/{brand}/branches/{branch}/menu-items/{item}/images`.
- Creating or editing dish price requires `change_prices`; without it, price edits are preserved as the current value.
- Creating or editing dish availability requires `change_availability`; without it, availability edits are preserved as the current value.
- Guest menu display shows item price, photo when present, and unavailable state.
- Has many translations through `menu_item_translations`.
- Has many reusable modifier groups through `menu_item_modifier_groups`.
- Has many draft order items through `draft_order_items`.
- Has many confirmed order item records through `order_items`.
- Translation support exists for guest display, but a full admin editor for translations is not implemented yet.
- Modifier assignment exists in admin CRUD and the guest UI can configure available modifiers and persist configured selections into `draft_order_items`.
- Changing the dish department assignment clears the branch guest-menu database cache through `MenuItemObserver`.

Kitchen department:

- Stored in `kitchen_departments`.
- Belongs to one branch through `branch_id`.
- Fixed department types are `kitchen`, `bar`, `dessert`, `hookah`, and `custom`.
- Type is cast to `KitchenDepartmentType`.
- Stores `name`, `sort_order`, `is_active`, and timestamps.
- Branch and department name are unique together.
- New branch creation seeds standard active departments for kitchen, bar, dessert, and hookah.
- `KitchenDepartmentsSeeder` can backfill standard departments for existing branches without creating duplicates for an already present standard type.
- Custom departments are created manually from branch menu admin.
- Managed from the branch menu page guarded by `manage_menu`.
- Menu items can be assigned to a department; inactive departments remain visible for existing assignments.
- If a dish department is not explicitly selected in the admin form, the branch's default `kitchen` department is saved. If standard departments are missing, `SeedKitchenDepartmentsForBranchAction` restores them before the item payload is saved.
- Pizza can be routed to kitchen, coffee to bar, dessert to dessert, and hookah items to hookah by selecting the matching branch department.
- `KitchenDepartmentObserver` clears all supported language cache variants for the branch guest menu when departments are created, updated, deleted, restored, or force deleted.
- Deleting a department sets nullable menu item and order item department links to null, while confirmed order item type/name snapshots remain.

Menu category translation:

- Stored in `menu_category_translations`.
- Belongs to one menu category through `menu_category_id`.
- Stores `language_code`, translated `name`, and optional translated `description`.
- Unique per `menu_category_id` and `language_code`.
- Observer clears all supported language cache variants for the branch guest menu.

Menu item translation:

- Stored in `menu_item_translations`.
- Belongs to one menu item through `menu_item_id`.
- Stores `language_code`, translated `name`, and optional translated `description`.
- Unique per `menu_item_id` and `language_code`.
- Observer clears all supported language cache variants for the branch guest menu.

Modifier group:

- Stored in `modifier_groups`.
- Belongs to one branch through `branch_id`.
- Stores `name`, `is_required`, `min_select`, `max_select`, and `sort_order`.
- Has many options through `modifier_options`.
- Can be assigned to many dishes through `menu_item_modifier_groups`.
- Managed from the branch menu page guarded by `manage_menu`.
- Changing a modifier group clears all supported language cache variants for the branch guest menu.

Modifier option:

- Stored in `modifier_options`.
- Belongs to one modifier group through `modifier_group_id`.
- Stores `name`, `price_delta`, `is_available`, and `sort_order`.
- `price_delta` is a decimal cast and can be positive or negative.
- Creating or editing price deltas requires `change_prices`; without it, price delta edits are preserved as the current value.
- Creating or editing availability requires `change_availability`; without it, availability edits are preserved as the current value.
- Managed from the branch menu page guarded by `manage_menu`.
- Changing a modifier option clears all supported language cache variants for the branch guest menu.

Menu item modifier assignment:

- Stored in `menu_item_modifier_groups`.
- Assigns reusable branch modifier groups to individual menu items.
- A menu item can have multiple modifier groups.
- The same modifier group can be reused by multiple menu items in the same branch.
- The pivot is unique by `menu_item_id` and `modifier_group_id`.
- Assigning or removing a group from a dish clears all supported language cache variants for the branch guest menu.
- Guest menu payloads expose available modifier groups/options for local guest configuration.
- Modifier `price_delta` affects the local displayed item total in the guest UI.
- Draft order item schema stores selected modifiers as a JSON snapshot when an active guest adds a configured item to the shared draft.

Area node:

- Stored in `area_nodes`.
- Belongs to one branch.
- Can optionally belong to a parent area node through `parent_id`.
- Supports nested structures through `parent` and `children` relationships.
- Type is cast to `AreaNodeType`.
- Fixed types are `group`, `floor`, `hall`, `terrace`, `vip_room`, `bar_area`, `banquet_hall`, `room`, `hotel_area`, `pickup_area`, `delivery_area`, and `custom`.
- Stores `name`, optional `icon`, `sort_order`, `is_active`, and optional JSON `metadata`.
- Supports soft delete through `deleted_at`.
- Managed from the branch area page guarded by `manage_zones`.
- Area CRUD can create common presets, choose icons, rename, move inside another area, disable/enable, and soft delete.
- Soft deleting an area moves its direct children to the deleted area's parent before hiding the deleted area.
- QR codes are not attached to area nodes; areas are only used to organize and filter service points.

Service point:

- Stored in `service_points`.
- Represents a physical service location inside one branch.
- Belongs to one branch.
- Can optionally belong to one area node through `area_node_id`.
- Can be moved between area nodes by updating `area_node_id`.
- Type is cast to `ServicePointType`.
- Fixed types are `table`, `bar_seat`, `vip_table`, `room`, `booth`, `sunbed`, `hotel_room`, `pickup_window`, `delivery_point`, and `other`.
- Status is cast to `ServicePointStatus`.
- Status values are `free`, `occupied`, `reserved`, `waiting_waiter`, `has_new_order`, `cooking`, `ready_to_serve`, `payment_requested`, `paid`, `closed`, and `blocked`.
- Default status is `free`.
- Stores `name`, optional `display_number`, optional `internal_code`, `capacity`, optional `icon`, optional coordinates `position_x` and `position_y`, `is_active`, and optional JSON `metadata`.
- Supports soft delete through `deleted_at`.
- Managed from the branch service point page guarded by `manage_service_points`.
- Service point CRUD can add common presets, choose a zone, choose type/icon, set name, number, and capacity, rename, move to another zone, disable, and enable.
- Manual status changes are allowed for users with `manage_service_points` and users with the fixed `waiter` role in the organization.
- Users with `view_orders` or `confirm_orders` can open a table from the service point page.
- Opening a table creates or returns the service point's current active table session and sets service point status to `occupied`.
- Active guest waiter-call requests move the service point status to `waiting_waiter` through `RequestWaiterForTableSessionAction`.
- `MarkWaiterCallHandledAction` restores the previous service point status only when the service point is still `waiting_waiter`.
- Users with `generate_qr` can access the service point page to create or show permanent QR details.
- `UpdateServicePointStatusAction` updates only `service_points.status` and is the future reuse point for table sessions and orders.
- `CreateServicePointAction` creates a stable `internal_code` once.
- `UpdateServicePointAction` intentionally does not update `internal_code`.
- Permanent QR records attach to the stable service point record, not to the name, display number, branch path, or area path.
- Renaming or moving a service point must not change QR identity.

Table session:

- Stored in `table_sessions`.
- Belongs to one branch through `branch_id`.
- Belongs to one service point through `service_point_id`.
- Uses internal nullable `active_service_point_id` to enforce one active table session per service point on SQLite.
- Uses internal nullable `pending_service_point_id` to enforce one pending table session per service point on SQLite.
- Can be opened by a staff user through nullable `opened_by_user_id`.
- Can be opened by a guest through nullable `opened_by_guest_id`.
- `opened_by_guest_id` stores the first `table_session_guests.id` for a guest-created pending session.
- Stores nullable `guest_invite_token` for the current guest invite link.
- Stores nullable `guest_invite_created_at` and `guest_invite_created_by_guest_id` for invite creation audit.
- Can be closed by a future staff user through nullable `closed_by_user_id`.
- Status is cast to `TableSessionStatus`.
- Status values are `pending`, `active`, `waiting_waiter_confirmation`, `payment_requested`, `paid`, `closed`, and `cancelled`.
- Source is cast to `TableSessionSource`.
- Source values are `waiter_opened` and `guest_created`.
- Default status is `pending`.
- Default source is `guest_created`.
- Stores `started_at`, `ended_at`, and optional JSON `metadata`.
- Has indexes for branch/status, service point/status, branch/service point/status, source/status, `opened_by_guest_id`, and `started_at`.
- `TableSession` sets `active_service_point_id` automatically while saving active or `payment_requested` sessions and clears it for other non-active statuses.
- `TableSession` sets `pending_service_point_id` automatically while saving pending sessions and clears it for non-pending statuses.
- `ServicePoint::activeTableSession()` returns the current active or bill-requested table session for UI display.
- `TableSession::draftOrder()` returns the latest/current draft order for the session.
- `TableSession::draftOrders()` returns repeat-order draft history for the session.
- `TableSession::waiterCalls()` returns guest waiter-call history for the session.
- `OpenTableSessionForServicePointAction` creates an active waiter-opened session with `started_at` when no active or bill-requested session exists.
- If an active or bill-requested session already exists for the service point, `OpenTableSessionForServicePointAction` returns it instead of creating a duplicate.
- Opening a table updates the service point status to `occupied` through `UpdateServicePointStatusAction`; returning a bill-requested session keeps the service point at `payment_requested`.
- `CreateGuestPendingTableSessionAction` creates a pending guest-created session when there is no active or pending session and `branch_settings.allow_guest_created_sessions` is true.
- If an active or pending session already exists and has active guests, guest QR entry creates a pending table session join request instead of a guest.
- If an active or pending session already exists without active guests, guest QR entry returns the existing-session message without creating a join request.
- `CreateGuestInviteLinkAction` creates or reuses one hidden invite token for the current table session.
- Only an active guest in the same table session can create the guest invite link.
- Guest invite links respect `branch_settings.allow_guest_invite_links`.
- Guest invite URLs use `/q/{public_token}?invite={guest_invite_token}` and must not expose table session IDs, service point IDs, branch IDs, table numbers, or area names.
- Opening a guest invite link asks the invited person for a name and creates a pending join request for the invited table session.
- Draft order schema, guest add-to-draft UI, send-to-waiter handoff, waiter dashboard visibility, waiter draft editing, waiter confirm/reject actions, request-bill status flow, manual offline payment flow, and explicit kitchen/bar dispatch exist. Confirmed orders are stored in `orders` and `order_items`; dispatch creates `kitchen_tickets` and `kitchen_ticket_items`. Online payment provider logic does not exist yet.

Table session guest:

- Stored in `table_session_guests`.
- Belongs to one table session through `table_session_id`.
- Stores `guest_name`, `guest_token`, `status`, optional `ready_at`, `joined_at`, optional `left_at`, optional JSON `metadata`, and timestamps.
- `guest_token` is a random 64-character token and is unique.
- Guests are not `users` records and do not require registration.
- The public QR flow stores `guest_token` in a browser cookie named `guest_token_{hash}`.
- Refreshing the public QR page restores the same guest and table session from that cookie when the token still belongs to the current service point.
- Status is cast to `TableSessionGuestStatus`.
- Status values are `pending_approval`, `active`, `rejected`, `left`, and `removed`.
- The first guest from a guest-created pending session is stored as `active`.
- Rejected and removed guests are restored for messaging but cannot use the future item-adding path.
- Closed or cancelled table sessions are restored for messaging but cannot use the future item-adding path.
- `TableSession::guests()` returns all session guests ordered by `guest_name` and id.
- `TableSession::activeGuests()` returns active guests ordered by `guest_name` and id.
- `TableSessionGuest::approvedJoinRequests()` and `TableSessionGuest::rejectedJoinRequests()` expose join request moderation history.
- `TableSessionGuest::waiterCalls()` exposes waiter calls requested by that guest.
- `TableSessionGuest::draftOrderItems()` exposes draft items owned by the guest.
- `ready_at` marks that an active guest is ready; `null` means not ready.
- Active guests can approve or reject new guest join requests from the public QR UI.
- `App\Livewire\PublicQr\TableGuests` renders the guest list for active guests and polls only that block.
- The guest list shows guest names alphabetically, human-readable guest statuses, and ready/not-ready labels.

Waiter call:

- Stored in `waiter_calls`.
- Belongs to one branch, service point, table session, and optionally the guest who requested it.
- Uses internal nullable `active_service_point_id` to enforce one pending waiter call per service point on SQLite.
- Status is cast to `WaiterCallStatus`.
- Status values are `pending` and `handled`.
- Stores `requested_at`, nullable `handled_at`, nullable `handled_by_user_id`, and optional JSON `metadata`.
- `metadata.previous_service_point_status` stores the service point status before the call so the handled action can restore it when safe.
- `RequestWaiterForTableSessionAction` requires an active table session guest and refuses closed/cancelled sessions or inactive service points.
- If a pending call already exists for the service point, `RequestWaiterForTableSessionAction` reuses it and does not create a duplicate or send another notification.
- New calls move the service point to `waiting_waiter` and create Laravel database notifications for users who can access the branch through `view_orders`.
- `ResolveWaiterNotificationRecipientsAction` respects superadmin access, active organization memberships, permission overrides, role permissions, and active branch assignments.
- `WaiterCalledNotification` uses only the `database` channel; no mail, SMS, push, Telegram API, WebSocket, Redis, or external service is used.
- `MarkWaiterCallHandledAction` requires branch-level `view_orders`, moves the call to `handled`, fills `handled_at` and `handled_by_user_id`, marks matching unread database notifications read, and restores the previous service point status only if the service point is still `waiting_waiter`.

Bill request:

- `RequestBillForTableSessionAction` requires an active table session guest and refuses paid, closed, cancelled, or inactive service point cases.
- The action sets `table_sessions.status` to `payment_requested`.
- The action stores `metadata.bill_requested_at` and `metadata.bill_requested_by_guest_id` on the table session.
- The action sets the related service point status to `payment_requested`.
- Repeating the request while the session is already `payment_requested` is idempotent and does not send duplicate database notifications.
- `BillRequestedNotification` uses only the `database` channel; no mail, SMS, push, Telegram API, WebSocket, Redis, online payment provider, or external service is used.
- Waiter notification recipients are resolved through the same `ResolveWaiterNotificationRecipientsAction` and branch-level `view_orders` access as guest waiter calls.
- Waiters can see the bill request in the waiter dashboard polling payload.
- The guest `Попросить счёт` button does not create `manual_payments`; only staff can record payment later.

Manual payment:

- Stored in `manual_payments`.
- Belongs to one branch, service point, and table session.
- Optionally belongs to one table session guest through `table_session_guest_id`.
- Stores `recorded_by_user_id`, `scope`, `payment_method`, `amount`, `currency`, optional `guest_name` snapshot, optional `note`, `paid_at`, optional JSON `metadata`, and timestamps.
- Scope values are `table` and `guest`.
- Payment method values are `cash`, `card_terminal`, and `other`.
- `ManualPayment` casts scope to `ManualPaymentScope`, method to `ManualPaymentMethod`, amount to decimal, `paid_at` to datetime, and metadata to array.
- `TableSession::manualPayments()`, `TableSessionGuest::manualPayments()`, `ServicePoint::manualPayments()`, and `User::manualPayments()` expose payment history.
- `SystemPermission::ManagePayments` exists as `manage_payments` and is marked critical.
- `SystemPermission::CloseTableSessions` exists as `close_table_sessions` and is marked critical.
- Users with `view_payments` can view the payment summary.
- Users with `manage_payments` or the fixed `cashier` organization role can record manual payments.
- `ResolvePaymentAccessibleBranchIdsAction` resolves view/manage branch access through permissions, superadmin bypass, cashier organization role, and active branch assignments.
- `BuildManualPaymentSummaryAction` computes confirmed non-cancelled order totals, manual paid totals, remaining balance, per-guest balances, and payment history from SQLite.
- Payment balance is based on confirmed `orders` / `order_items`; open unconfirmed drafts are not payable.
- `RecordManualPaymentAction` records either whole-table or guest-scoped payment and refuses payment while the latest draft is `draft`, `sent_to_waiter`, or `waiter_review`.
- If the remaining confirmed order balance reaches zero, `RecordManualPaymentAction` sets `table_sessions.status` to `paid`, stores `metadata.paid_at` and `metadata.paid_by_user_id`, and moves the service point to `paid`.
- Partial guest payment keeps or moves the session to `payment_requested` and the service point to `payment_requested`.
- `CloseTableSessionAction` closes paid sessions for users who can manage payments and closes unpaid sessions for users with `close_table_sessions`.
- `ClosePaidTableSessionAction` remains as a compatibility wrapper around `CloseTableSessionAction`.
- Closing fills `closed_by_user_id` and `ended_at`, sets status to `closed`, moves the service point to `free`, and does not touch `orders`, `order_items`, or `qr_codes`.
- Manual payments do not change kitchen tickets, do not dispatch orders, and do not connect Stripe, PayPal, online acquiring, or other external services.

Basic analytics:

- Stored as cached dashboard payloads in the existing database-backed `cache` table; no analytics tables were added in Prompt 069.
- `App\Actions\Analytics\BuildBasicAnalyticsDashboardAction` builds the restaurant dashboard payload for users with `view_reports` branch access.
- Superadmins see analytics for all branches through the same branch resolver bypass used by waiter/report access.
- The action currently computes today's order count, today's order amount, average check, popular dishes, active table count, closed sessions today, and cancelled orders today.
- Orders today and amount exclude `orders.status = cancelled` and use `orders.confirmed_at` within the current application day.
- Popular dishes are based on confirmed `order_items` snapshots from today's non-cancelled orders, so later menu name/price changes do not rewrite old analytics history.
- Active tables include table sessions in `pending`, `active`, `waiting_waiter_confirmation`, and `payment_requested`.
- Closed sessions use `table_sessions.status = closed` with `ended_at` during the current application day.
- Cancelled orders use `orders.status = cancelled` with `updated_at` during the current application day because there is no separate `cancelled_at` field yet.
- Analytics cache keys are grouped by sorted branch ids and current date, for example `analytics:dashboard:branches:{sha1}:today:{date}`.
- Branch cache-key indexes are also stored in the database cache so changing one branch can forget dashboard snapshots that include that branch without Redis cache tags.
- `OrderObserver`, `OrderItemObserver`, `ManualPaymentObserver`, and `TableSessionObserver` invalidate affected branch analytics cache.
- Dashboard analytics must not be added to 1-second waiter/kitchen/bar polling loops; keep it on the restaurant dashboard or use explicit short-lived database cache.

Draft order:

- Stored in `draft_orders`.
- Belongs to one table session through `table_session_id`.
- Each table session can have multiple draft orders over time for repeat orders in the same open table session.
- `TableSession::draftOrder()` returns the latest/current draft order by id.
- `TableSession::draftOrders()` returns draft history, latest first by default.
- Status is cast to `DraftOrderStatus`.
- Status values are `draft`, `sent_to_waiter`, `waiter_review`, `rejected`, and `converted_to_order`.
- Stores nullable `sent_to_waiter_at` and nullable `sent_by_guest_id` for the guest submission path.
- `sent_by_guest_id` points to `table_session_guests.id`.
- Stores nullable `rejected_at`, `rejected_by_user_id`, and `rejection_reason` when a waiter rejects a sent draft.
- Stores nullable `converted_to_order_at` and `converted_by_user_id` when a waiter confirms a sent draft into a real order.
- `DraftOrder::items()` returns draft items ordered by creation time and id.
- `DraftOrder::order()` returns the real order created after waiter confirmation.
- `DraftOrder::totalAmount()` calculates the whole table draft total from draft item `total_price` snapshots.
- `DraftOrder::guestTotals()` groups draft item totals by guest and returns guests sorted alphabetically by `guest_name`.
- `AddGuestDraftOrderItemAction` creates the shared draft on first add and stores guest item snapshots. If the latest open draft has already been converted to an order, the action creates a new `draft_orders` row in the same table session for the repeat order.
- `UpdateGuestDraftOrderItemAction` lets an active guest update only their own draft item quantity, comment, and selected modifiers while the draft is still `draft`.
- `DeleteGuestDraftOrderItemAction` lets an active guest delete only their own draft item while the draft is still `draft`.
- `SendDraftOrderToWaiterAction` lets any active guest in the same open table session send the shared draft to waiter review.
- Sending sets the draft status to `sent_to_waiter`, stores `sent_to_waiter_at` and `sent_by_guest_id`, clears active guest `ready_at`, and moves the related service point to `has_new_order`.
- `ConfirmDraftOrderByWaiterAction` requires `confirm_orders`, converts a `sent_to_waiter` or `waiter_review` draft to `converted_to_order`, creates one `orders` row with status `confirmed_by_waiter`, and copies draft items into `order_items` snapshots, including kitchen department id/type/name when the source menu item has a department.
- Every repeat draft must pass through `SendDraftOrderToWaiterAction`, `ConfirmDraftOrderByWaiterAction`, and explicit `SendOrderToKitchenBarAction`; old orders are not overwritten.
- `RejectDraftOrderByWaiterAction` requires `confirm_orders`, sets a sent draft to `rejected`, and stores a required rejection reason for guests to see.
- `ReturnRejectedDraftOrderToDraftAction` requires `confirm_orders` and returns a rejected draft to `draft` so guests can edit and send the same shared draft again.
- `AddDraftOrderItemByWaiterAction`, `UpdateDraftOrderItemByWaiterAction`, and `DeleteDraftOrderItemByWaiterAction` allow staff with `confirm_orders` or `edit_pending_orders` to edit a `sent_to_waiter` or `waiter_review` draft before confirmation.
- Waiter draft edits move `sent_to_waiter` drafts to `waiter_review`, recalculate draft item snapshot totals, and keep the same shared draft visible to guests through polling.
- `EnsureWaiterCanEditDraftOrderAction` is the shared backend guard for waiter draft edits.
- Guest and waiter draft actions write `order_status_logs` rows for draft creation, edits, send-to-waiter, confirm, reject, and return-to-draft events.
- `BuildDraftOrderItemModifierSnapshots` is shared by add and update actions for modifier selection validation and JSON snapshots.
- This draft flow still does not send anything to kitchen, bar, payment, or analytics directly. A confirmed order must be dispatched later by `SendOrderToKitchenBarAction`.

Draft order item:

- Stored in `draft_order_items`.
- Belongs to one shared draft order through `draft_order_id`.
- Belongs to one table session guest through `table_session_guest_id`.
- Can optionally reference the original menu item through `menu_item_id`.
- Stores price/name snapshots: `item_name`, `quantity`, `unit_price`, `modifier_total`, and `total_price`.
- Stores selected modifiers as optional JSON in `selected_modifiers`.
- Stores optional guest comment in `comment`.
- Snapshot fields protect the shared draft from later menu name, price, or modifier changes.
- Active guests can create these rows from `App\Livewire\PublicQr\GuestMenu`.
- Active guests can edit or delete their own rows from `App\Livewire\PublicQr\DraftOrder`.
- Waiters with `confirm_orders` or `edit_pending_orders` can add rows, update quantity/comment/modifiers, or delete rows before confirmation from `App\Livewire\Waiter\TableDetail`.
- Guests cannot edit or delete another guest's draft item.
- Editing can change quantity, comment, and currently available modifier selections.
- Updating quantity recalculates `total_price` from the item snapshot `unit_price`, per-unit `modifier_total`, and quantity.
- Guest editing and deletion are blocked once the shared draft status is no longer `draft`, including `sent_to_waiter`.
- Waiter editing is allowed only for `sent_to_waiter` and `waiter_review` drafts before confirmation.
- Rejected drafts show the waiter rejection reason in the guest shared cart polling block.
- Confirmed drafts show guests that the order was confirmed and editing is closed; after kitchen/bar dispatch, the same polling block shows that kitchen/bar received the positions.
- Rejected, removed, pending approval, left, or token-mismatched guests cannot create, edit, or delete draft item rows.

Order:

- Stored in `orders`.
- Created only by `ConfirmDraftOrderByWaiterAction` after waiter confirmation.
- Belongs to branch, service point, table session, and draft order.
- Has one unique `draft_order_id`, so the same draft cannot create two real orders.
- A table session can have many orders over time through repeat drafts, and their snapshots form the table order history.
- Status is cast to `OrderStatus`.
- Status values are `confirmed_by_waiter`, `sent_to_kitchen_bar`, `in_progress`, `ready`, `served`, `payment_requested`, `paid`, `closed`, and `cancelled`.
- New confirmed orders start as `confirmed_by_waiter`.
- Stores `confirmed_by_user_id`, `confirmed_at`, `total_price`, `currency`, and optional JSON `metadata`.
- Metadata initially marks that kitchen/bar dispatch is prepared but not sent.
- `SendOrderToKitchenBarAction` requires `send_to_kitchen`, creates department-split kitchen tickets, changes the status to `sent_to_kitchen_bar`, stores dispatch metadata, updates the service point status to `cooking`, and writes an `order_status_logs` row.
- `SyncOrderStatusFromTicketItemsAction` syncs confirmed order status from dispatched ticket items after kitchen/bar status changes or waiter service. It can move orders through `sent_to_kitchen_bar`, `in_progress`, `ready`, and `served`, and updates service point status to `cooking`, `ready_to_serve`, or back to `occupied` for served.
- Has many `order_items`.
- Has many `kitchen_tickets`.
- Branch, service point, table session, and draft order models expose order relationships.
- `Order::statusLogs()` exposes persistent status history ordered by `occurred_at` and id.
- `ChangeOrderStatusAction` changes confirmed `orders.status` with permission checks and writes an `order_status_logs` row. It currently requires `send_to_kitchen` for `sent_to_kitchen_bar`, `cancel_orders` for `cancelled`, and `confirm_orders` for other manual status changes.
- `orders` are not shown to kitchen/bar until explicit waiter dispatch creates tickets.

Order item:

- Stored in `order_items`.
- Belongs to one real order through `order_id`.
- Optionally references the original table session guest, menu item, and kitchen department.
- Stores guest/item/department/price snapshots copied from `draft_order_items` and the source menu item: `guest_name`, `item_name`, `kitchen_department_type`, `kitchen_department_name`, `quantity`, `unit_price`, `modifier_total`, `total_price`, selected modifiers, and optional comment.
- Snapshot fields must remain unchanged if the source menu item name, menu item price, modifier options, or kitchen department name/type change later.
- Table session guests, menu items, and kitchen departments expose `orderItems()` relationships for confirmed order history.
- These rows prepare kitchen/bar dispatch without exposing unconfirmed drafts to kitchen/bar.

Kitchen ticket:

- Stored in `kitchen_tickets`.
- Created only by `SendOrderToKitchenBarAction` after a real order already has `orders.status = confirmed_by_waiter`.
- Belongs to one order, branch, service point, table session, and optional kitchen department.
- Stores department snapshot fields `department_type` and `department_name`.
- Status is cast to `KitchenTicketStatus`; current value is `sent`.
- Stores `sent_by_user_id`, `sent_at`, and optional JSON `metadata`.
- One dispatch groups order items by department snapshot, so kitchen, bar, dessert, hookah, or custom departments receive only their own positions.
- Repeating dispatch for the same order does not create duplicate tickets.
- Branch, service point, table session, order, and kitchen department models expose `kitchenTickets()` relationships.

Kitchen ticket item:

- Stored in `kitchen_ticket_items`.
- Belongs to one kitchen ticket through `kitchen_ticket_id`.
- References one confirmed order item through unique `order_item_id`.
- Optionally references the original table session guest and menu item.
- Stores guest name, item name, quantity, item work status, selected modifiers, and optional comment snapshots for the department ticket.
- Status is cast to `KitchenTicketItemStatus`.
- Status values are `new`, `in_progress`, and `ready`.
- Stores waiter service tracking in `served_at` and `served_by_user_id`.
- Kitchen/bar status actions refuse to change an item after `served_at` is filled.
- Waiters mark ready items served through `MarkKitchenTicketItemServedAction`, which requires branch-level `view_orders` access and only accepts `ready` items.

Order status log:

- Stored in `order_status_logs`.
- Belongs optionally to branch, service point, table session, draft order, confirmed order, actor user, and actor guest.
- Foreign keys use nullable `nullOnDelete` links so log rows survive if related records are later removed.
- Stores actor snapshots in `actor_type` and `actor_name`.
- Event is cast to `OrderStatusLogEvent`.
- Current event values are `draft_created`, `draft_edited`, `draft_sent_to_waiter`, `draft_confirmed`, `draft_rejected`, `draft_returned_to_draft`, `order_status_changed`, `order_sent_to_kitchen_bar`, and `order_cancelled`.
- Stores `status_type`, `previous_status`, `new_status`, optional `reason`, optional JSON `metadata`, and `occurred_at`.
- `CreateOrderStatusLogAction` is the shared append-only writer used by guest draft actions, waiter review actions, explicit kitchen/bar dispatch, and confirmed order status changes.
- Branch, service point, table session, draft order, order, table session guest, and user models expose order status log relationships.
- There is no audit UI yet.

Table session join request:

- Stored in `table_session_join_requests`.
- Belongs to one table session through `table_session_id`.
- Stores `guest_name`, `guest_token`, `status`, `approved_by_guest_id`, `rejected_by_guest_id`, `approved_by_user_id`, `rejected_by_user_id`, `expires_at`, and timestamps.
- `guest_token` is a random 64-character token and is unique inside join requests.
- Status is cast to `TableSessionJoinRequestStatus`.
- Status values are `pending`, `approved`, `rejected`, and `expired`.
- `TableSession::joinRequests()` returns join requests ordered by creation time and id.
- `CreateTableSessionJoinRequestAction` creates a pending request only when the table session is pending or active and already has at least one active guest.
- Join requests default to a 30-minute expiration in backend creation logic.
- `ApproveTableSessionJoinRequestAction` allows an active guest from the same table session to approve a pending request.
- Approval creates a real `table_session_guests` row using the request guest name and token, then marks the request `approved`.
- `RejectTableSessionJoinRequestAction` allows an active guest from the same table session to reject a pending request without creating a guest.
- Expired pending requests are marked `expired` when moderation is attempted.
- `approved_by_user_id` and `rejected_by_user_id` are present for future staff moderation, but current backend actions use guest moderation only.
- `App\Livewire\PublicQr\JoinRequests` shows active guests a polled block of pending join requests for the same table session.
- The join request UI requires the active guest's saved browser token before allowing approval or rejection.
- Waiting guests poll only their join request status block and see approved, rejected, or expired messaging.
- Approved waiting guests are restored as active table guests through their saved guest token.

QR code:

- Stored in `qr_codes`.
- Belongs to one service point through `service_point_id`.
- A service point has many QR records and one active QR record.
- Stores `public_token`, `short_code`, `status`, `created_by_user_id`, `revoked_at`, and `revoked_by_user_id`.
- Status is cast to `QrCodeStatus`.
- Status values are `active`, `disabled`, and `revoked`.
- `public_token` and `short_code` are unique.
- The QR table does not store table numbers, service point names, area names, or branch IDs.
- SQLite enforces one active QR per service point with internal nullable `active_service_point_id`.
- Disabled and revoked QR history can exist for the same service point.
- QR identity remains stable when the service point is renamed or moved to another area.
- `GenerateQrCodeForServicePointAction` creates a new active QR only when the service point has no active QR.
- If an active QR already exists, `GenerateQrCodeForServicePointAction` returns the existing active QR and does not create a second active QR automatically.
- `DisableQrCodeAction` changes an active QR to `disabled` and clears its active-service-point uniqueness guard through the model save hook.
- `ReissueQrCodeForServicePointAction` revokes current active QR records for the service point and then creates one new active QR through the normal generation action.
- `QrCodeSvgRenderer` renders a local SVG QR image for the public URL without external services or storage uploads.
- Generated `public_token` values are 64-character random strings.
- Generated `short_code` values use the `QR-XXXXXXXX` format with a readable uppercase alphabet.
- `QrCode::publicPath()` returns `/q/{public_token}` and matches the public QR route.
- The branch service point page can show QR status, `short_code`, and `/q/{public_token}` for users with `generate_qr`.
- The QR admin page is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points/{servicePoint}/qr/{qrCode}` and is guarded by `generate_qr` in the current organization context.
- The QR admin page shows branch, current area, current service point, public URL, SVG QR image, short code, status, and creation date.
- The QR admin page can open the guest URL, download the QR SVG image, disable an active QR, and manually reissue a QR after a danger warning.
- The QR print template page is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points/{servicePoint}/qr/{qrCode}/print` and is guarded by `generate_qr` in the current organization context.
- The QR print template is browser print-friendly and intended for one sticker at a time.
- The QR print template shows a restaurant logo when a local `logo_path` exists on branch, brand, or organization.
- Without a logo field, the QR print template uses the brand name as a simple text mark.
- The QR print template prints `Сканируйте, чтобы открыть меню`, the local SVG QR image, and `short_code`.
- The QR print template does not print service point number or area by default.
- The `print_table_number` URL setting can include the service point display number or name in the sticker.
- When `print_table_number` is enabled, the UI shows the warning: `Если вы потом переименуете или перенесёте стол, текст на наклейке может устареть.`
- Toggling `print_table_number` must not change QR identity.
- The branch bulk QR print page is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/qr/print` and is guarded by `generate_qr` in the current organization context.
- The bulk print page can show all areas, one area node, or service points without an area.
- The bulk print page lets users select service points with active QR codes and prints multiple stickers in the browser print view.
- The bulk print page offers single and visible-batch creation for service points that do not have an active QR yet.
- Bulk QR creation reuses `GenerateQrCodeForServicePointAction`, so it does not create a second active QR for a service point that already has one.
- Bulk printing uses the same local SVG QR renderer and the same optional local logo resolver as the one-sticker print template.
- Manual reissue is the only current UI path that intentionally changes the QR identity.
- Public route `GET /q/{token}` resolves `public_token` without exposing organization IDs, branch IDs, service point IDs, or table numbers.
- Public QR route accepts active, disabled, revoked, and unknown token states.
- Active QR codes load the current service point, current area, branch, brand, organization, and local logo path for the guest landing page.
- Disabled and revoked QR codes show public error messages instead of opening the guest landing state.
- Active QR codes attached to inactive service points show a public unavailable message.
- Moving or renaming a service point keeps the same QR URL and the public page shows current service point data.
- The public guest landing page shows venue name, local logo when available, current area, current service point, short code, guest name field, and `Войти за стол`.
- Submitting the guest name validates and creates a pending guest-created table session only when no active or pending session exists and branch settings allow guest-created sessions.
- The first guest is stored in `table_session_guests` as `active`, and the pending session stores that guest id in `opened_by_guest_id`.
- The public QR entry flow stores the created guest token in `guest_entries.{public_token}` session data and queues a browser cookie named `guest_token_{hash}`.
- If an active or pending session already has active guests, submitting the guest name creates a pending `table_session_join_requests` row and queues that request token in the same `guest_token_{hash}` cookie.
- Active guests can create a guest invite link inside `App\Livewire\PublicQr\Show`.
- Guest invite share UI uses the browser native share API when available and a copy-link fallback when native share is not available.
- The guest invite link opens the same `GET /q/{token}` route with an `invite` query token and still keeps internal IDs out of the URL.
- When an invited person opens the link and enters a name, `App\Livewire\PublicQr\Show` creates a pending join request for the invited table session.
- Active guests see the main guest table page shell instead of the entry form.
- The guest table shell shows venue name, current service point, saved entry state, invite action, guest list, cached active branch menu, and the shared draft basket.
- The guest table shell can add menu items to `draft_order_items`, shows a shared cart grouped by guests alphabetically, lets active guests edit/delete only their own draft positions, and lets any active guest send the shared draft to waiter review, but it does not create final orders, payments, kitchen tasks, or bar tasks.
- On page refresh, `App\Livewire\PublicQr\Show` reads `guest_token_{hash}` and restores the matching guest only when the guest belongs to a table session for the current service point.
- If no guest matches the cookie token, `App\Livewire\PublicQr\Show` can restore a matching join request for the current service point and show pending/rejected/expired messaging.
- Active guests see pending join requests in `App\Livewire\PublicQr\JoinRequests`, which refreshes with Livewire polling and does not require WebSockets.
- The waiting guest status block in `App\Livewire\PublicQr\Show` polls only the join request status and turns approved requests into active guest state.
- Restored active guests get `guestCanAddItems = true` for future order-position UI.
- Restored guests from closed/cancelled sessions or with `rejected`, `removed`, `pending_approval`, or `left` status get `guestCanAddItems = false` and a public message.
- Guest-created pending sessions can display the cached branch menu for active guests, show the shared grouped cart, and create/edit/delete own draft item rows, but they do not create final orders, payment records, kitchen tasks, or bar tasks.
- If an active session exists, the public QR page shows a future-join message and does not create a pending session.
- If a pending session exists, the public QR page shows a pending-session message and does not create another first guest.
- If `branch_settings.allow_guest_created_sessions` is false, the public QR page asks the guest to call staff and does not create a session or guest.
- No QR PDF generation exists yet.

Branch settings:

- Stored in `branch_settings`.
- Created with each new branch.
- Safely created on the settings page for existing branches that do not have settings yet.

Superadmin access:

- Uses the fixed `superadmin` role from the `roles` table.
- First superadmin can be seeded from `SUPERADMIN_NAME`, `SUPERADMIN_EMAIL`, and `SUPERADMIN_PASSWORD`.
- The superadmin route is protected by `superadmin` middleware.
- The platform dashboard is visible only to superadmins.
- Superadmins can see all organizations, brands, branches, and users.
- Superadmins bypass organization and branch-level access checks.
- Regular users keep organization-scoped access only.

Invitation:

- Stored in `invitations`.
- Belongs to an organization.
- Can optionally belong to a brand.
- Can optionally belong to a branch.
- Belongs to a fixed system role through `role_id`.
- Stores optional `email` and `phone`.
- Stores `invite_token` and `invite_code` for future link/code flows.
- Stores `expires_at`, `status`, and `invited_by_user_id`.
- Status enum values are `pending`, `accepted`, `expired`, `cancelled`, and `rejected`.
- `CreateInvitationAction` creates pending invitations with generated token/code defaults.
- Invitation scopes must stay inside the selected organization; branch scope must match selected brand when a brand is provided.
- Staff UI can create invite links/codes, but no email/SMS delivery or public acceptance flow exists yet.

Staff management:

- Organization staff page is `App\Livewire\Organizations\Staff\Index`.
- Branch staff page is `App\Livewire\Organizations\Brands\Branches\Staff\Index`.
- Staff permission override page is `App\Livewire\Organizations\Staff\Permissions`.
- Organization staff route is `GET /organizations/{organization}/staff`.
- Branch staff route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/staff`.
- Staff permission route is `GET /organizations/{organization}/staff/{staffMember}/permissions`.
- Access requires `manage_staff` in the current organization context.
- Manual staff creation creates or reuses a user, assigns the fixed role, and creates an active `organization_users` membership.
- Branch manual staff creation also creates an active `branch_users` assignment.
- Staff deactivate sets status to `suspended`; activate sets status to `active`.
- Invite link/code creation stores an `invitations` record only; no email/SMS is sent.
- Invite links are displayed for manual copy, but public invite acceptance is not implemented yet.

Staff permission overrides:

- Roles remain fixed; the permission page does not edit a staff member's role.
- Each permission can be `default`, `allow`, or `deny`.
- `default` removes the `permission_user_overrides` row and falls back to `permission_role`.
- `allow` and `deny` save `permission_user_overrides.enabled` as true or false.
- Effective permission display is computed from superadmin access, then user override, then role default.
- Superadmins always have full effective access.
- Critical permissions include `manage_staff`, `manage_subscription`, `manage_settings`, and `export_data`.
- Critical permission changes show a warning.
- Users cannot edit their own permission overrides from the staff permission page.

Local media storage:

- Uses Laravel's `public` disk only.
- Public disk root is `storage/app/public`.
- Public browser path is `public/storage`.
- Shared hosting must keep `storage/app/public`, `storage/framework`, and `storage/logs` writable by PHP.
- `public/storage` should point to `storage/app/public`; use `php artisan storage:link` when symbolic links are available.
- No S3, paid storage, or external media services are used.
- Organization logos are stored under `media/organizations/{organization}/logos`.
- Brand logos are stored under `media/organizations/{organization}/brands/{brand}/logos`.
- Branch logos are stored under `media/organizations/{organization}/brands/{brand}/branches/{branch}/logos`.
- Dish photos are stored under `media/organizations/{organization}/brands/{brand}/branches/{branch}/menu-items/{item}/images`.
- Current logo paths are stored in `organizations.logo_path`, `brands.logo_path`, and `branches.logo_path`.
- `StoreLocalImageAction` stores images on the public disk and deletes the previous file when replacing a logo.
- `DeleteLocalMediaFileAction` removes old local files when a logo is removed.
- `HasLocalLogo` exposes `logoUrl()` for local public logo URLs.
- Current upload validation allows only images with `jpg`, `jpeg`, `png`, or `webp` extensions and a maximum size of 2 MB.
- Category image path columns exist, but category upload UI is not implemented yet.

## Branch Settings Defaults

- `require_waiter_confirmation_for_orders`: true
- `allow_guest_created_sessions`: true
- `allow_waiter_opened_sessions`: true
- `allow_guest_invite_links`: true
- `guest_join_requires_approval`: true
- `polling_interval_seconds`: 1
- `default_language`: `en`
- `default_currency`: branch currency, or `EUR`
- `service_charge_enabled`: false
- `tips_enabled`: false
- `order_flow_mode`: `waiter_confirmation`

## Routes

- `GET /` -> `home`
- `GET /q/{token}` -> `public.qr.show`
- `GET /guest` -> `guest.home`
- `GET /dashboard` -> `dashboard`
- `GET /organizations` -> `organizations.index`
- `GET /organizations/{organization}/staff` -> `organizations.staff.index`
- `GET /organizations/{organization}/staff/{staffMember}/permissions` -> `organizations.staff.permissions`
- `GET /organizations/{organization}/brands` -> `organizations.brands.index`
- `GET /organizations/{organization}/brands/{brand}/branches` -> `organizations.brands.branches.index`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/areas` -> `organizations.brands.branches.areas.index`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/menu` -> `organizations.brands.branches.menu.index`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/qr/print` -> `organizations.brands.branches.qr.print`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points` -> `organizations.brands.branches.service-points.index`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points/{servicePoint}/qr/{qrCode}` -> `organizations.brands.branches.service-points.qr.show`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points/{servicePoint}/qr/{qrCode}/print` -> `organizations.brands.branches.service-points.qr.print`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/staff` -> `organizations.brands.branches.staff.index`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/settings` -> `organizations.brands.branches.settings.index`
- `GET /restaurant/dashboard` -> `restaurant.dashboard`
- `GET /restaurant/bar/dashboard` -> `restaurant.bar.dashboard`
- `GET /restaurant/kitchen/dashboard` -> `restaurant.kitchen.dashboard`
- `GET /restaurant/waiter/dashboard` -> `restaurant.waiter.dashboard`
- `GET /restaurant/waiter/tables/{tableSession}` -> `restaurant.waiter.tables.show`
- `GET /superadmin/dashboard` -> `superadmin.dashboard` guarded by `auth` + `superadmin`
- Auth and profile routes are provided by Fortify and `routes/settings.php`.

## Livewire Components

- `resources/views/pages/restaurant/dashboard.blade.php` is the restaurant dashboard Livewire single-file component and now shows the cached basic analytics block for users with `view_reports`.
- `App\Livewire\Organizations\Index`
- `App\Livewire\Organizations\Staff\Index`
- `App\Livewire\Organizations\Staff\Permissions`
- `App\Livewire\Organizations\Brands\Index`
- `App\Livewire\Organizations\Brands\Branches\Index`
- `App\Livewire\Organizations\Brands\Branches\Areas`
- `App\Livewire\Organizations\Brands\Branches\Menu\Index`
- `App\Livewire\Organizations\Brands\Branches\Qr\BulkPrint`
- `App\Livewire\Organizations\Brands\Branches\ServicePoints\Index`
- `App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\PrintTemplate`
- `App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\Show`
- `App\Livewire\Organizations\Brands\Branches\Staff\Index`
- `App\Livewire\Organizations\Brands\Branches\Settings`
- `App\Livewire\PublicQr\Show`
- `App\Livewire\PublicQr\JoinRequests`
- `App\Livewire\PublicQr\TableGuests`
- `App\Livewire\PublicQr\GuestMenu`
- `App\Livewire\PublicQr\DraftOrder`
- `App\Livewire\Departments\Dashboard` shared abstract department ticket screen
- `App\Livewire\Bar\Dashboard`
- `App\Livewire\Kitchen\Dashboard`
- `App\Livewire\Superadmin\Dashboard`
- `App\Livewire\Waiter\Dashboard`
- `App\Livewire\Waiter\TableDetail`
- `App\Livewire\Settings\Profile`
- `App\Livewire\Settings\Security`
- `App\Livewire\Settings\Appearance`
- `App\Livewire\Settings\DeleteUserForm`
- `App\Livewire\Settings\TwoFactor\RecoveryCodes`
- `App\Livewire\Actions\Logout`

## Current Public QR Route

- Public QR route is `GET /q/{token}` and is named `public.qr.show`.
- The route is not protected by auth because guests open it from printed QR codes.
- The route parameter is only the QR `public_token`; URLs must not expose organization IDs, branch IDs, service point IDs, table IDs, table numbers, or area names.
- Guest invite URLs may add an `invite` query parameter, but that value is a hidden 64-character table-session invite token, not an internal ID.
- `App\Livewire\PublicQr\Show` owns the public QR landing state.
- The component eager-loads QR, service point, current area, branch, brand, organization, and logo paths before rendering.
- Blade displays prepared state only and must not query the database.
- Active QR plus active service point shows a mobile-first guest landing page with venue, logo, current area, current service point, guest name field, and `Войти за стол`.
- Disabled QR, revoked QR, inactive service point, and unknown token show public error states.
- Public QR route accepts a guest name and can create a pending guest-created table session plus the first active table session guest.
- Public QR route queues a browser cookie with the guest token after creating that first guest.
- Public QR route creates a pending join request instead of a guest when the current table session already has active guests.
- Public QR route creates a pending join request for a specific table session when opened with a valid guest invite token.
- Active guests can create the invite link from the public QR page, share through native browser sharing, or copy the link manually.
- Active guests see a guest table page shell with the venue, current service point, guests, invite action, cached active branch menu with modifier selection, and the shared draft basket.
- Active guests can press `Позвать официанта` from the shell; this calls `RequestWaiterForTableSessionAction`, creates or reuses a pending waiter call, and shows a local confirmation.
- Active guests can press `Попросить счёт` from the shared basket; this calls `RequestBillForTableSessionAction`, changes the session/service point to `payment_requested`, and shows the table total plus per-guest totals.
- The guest list in the shell is rendered by isolated `App\Livewire\PublicQr\TableGuests` and uses `wire:poll.1s="refreshGuests"` so the whole page is not refreshed.
- The guest list shows each guest's ready/not-ready state from `table_session_guests.ready_at`.
- The menu in the shell is rendered by `App\Livewire\PublicQr\GuestMenu` and reads active branch menu data through the explicit database cache store.
- The shared draft basket in the shell is rendered by isolated `App\Livewire\PublicQr\DraftOrder` and uses `wire:poll.1s="refreshDraft"` so only the basket block refreshes.
- Public QR route restores a guest from that cookie after page refresh and shows closed/blocked status messages when needed.
- Public QR route can also restore a join request from that cookie and show pending/rejected/expired request messages.
- Active guests get a separate polled join-request block for accepting or rejecting waiting guests.
- Waiting guests stay on a clear waiting screen until polling sees approval, rejection, or expiration.
- Public QR route shows the active branch menu for active guests and allows item modifier/comment configuration that persists into `draft_order_items`.
- Public QR route shows the shared table cart grouped by guests alphabetically.
- Public QR route lets active guests edit or delete only their own draft positions from the basket before the draft is sent to waiter review.
- Public QR route lets any active guest send the shared draft to waiter review from the basket.
- Public QR route lets active guests request waiter help, but rejected/removed/left/pending guests cannot request waiter help.
- Public QR route lets active guests request the bill, but rejected/removed/left/pending guests cannot request the bill.
- Public QR route does not create final orders directly, create payment records, or send anything to kitchen/bar.

## Current Guest Menu Display

- `App\Livewire\PublicQr\GuestMenu` renders the guest menu block inside the active guest table shell.
- `App\Actions\Menus\GetGuestMenuForBranchAction` loads the first active menu for the current branch, sorted by `sort_order`, `name`, and `id`.
- The component exposes a compact `RU` / `EN` / `LT` selector and stores the selected guest language in the `lang` query parameter.
- Guest menu payloads are cached in Laravel's explicit `database` cache store for 300 seconds, even if the default cache store is changed in a test or environment.
- Cache key format is `guest-menu:branch:{branch_id}:language:{language_code}`.
- Rebuild lock key format is `guest-menu:branch:{branch_id}:language:{language_code}:lock` and uses the SQLite-backed `cache_locks` table.
- `MenuObserver`, `MenuCategoryObserver`, `MenuItemObserver`, `MenuCategoryTranslationObserver`, `MenuItemTranslationObserver`, `KitchenDepartmentObserver`, `ModifierGroupObserver`, and `ModifierOptionObserver` forget the branch guest-menu cache on create, update, delete, restore, and force delete events.
- Updating a dish price, department assignment, kitchen department, modifier, or translation clears the branch guest-menu cache, so the next guest read rebuilds the payload with the current content.
- Guest menu display shows only active categories.
- Guest menu display shows both available and unavailable dishes.
- Unavailable dishes are visually dimmed and marked `Недоступно`; they cannot be added to the draft.
- Dish cards show local dish photos when `menu_items.image` is present, otherwise a small photo placeholder.
- Available dishes show a `Добавить` action for active guests.
- Tapping `Добавить` opens a mobile-first bottom sheet inside `App\Livewire\PublicQr\GuestMenu`.
- The bottom sheet shows assigned modifier groups and only available modifier options.
- Required modifier groups validate `min_select` before the guest can complete the local configuration.
- `price_delta` values from selected options affect the displayed local item total.
- Guests can add a local dish comment up to 500 characters.
- Completing the sheet creates a `draft_order_items` row through `AddGuestDraftOrderItemAction` and shows a local confirmation on the dish card.
- `AddGuestDraftOrderItemAction` rechecks the guest token, active guest status, table session status, menu item availability, active category, active menu, and selected modifier option availability before writing.
- Add/update draft item modifier snapshots must use `BuildDraftOrderItemModifierSnapshots` so add and edit rules stay aligned.
- Changing guest menu language clears local confirmation summaries to avoid mixed translated labels.
- The guest menu block is mobile-first and uses stable image dimensions.
- The guest menu block must not poll; menu freshness comes from cache invalidation on admin/backend changes.
- The guest menu block does not create final orders, kitchen tasks, bar tasks, or payment records; it only adds positions to the shared draft.

## Current Guest Draft Basket

- `App\Livewire\PublicQr\DraftOrder` renders the shared table cart block inside the active guest table shell.
- The component is isolated and uses `wire:poll.1s="refreshDraft"`.
- The component eager-loads draft items with their guest records before rendering; Blade does not query the database.
- The basket groups guests alphabetically by `guest_name` in `guestSections`.
- Each guest section shows that guest's ready/not-ready state, item count, positions, line prices, selected modifier names, optional comments, quantity, and guest total.
- The basket shows per-guest totals sorted alphabetically by `guest_name`.
- The basket shows the current draft total, the already confirmed non-cancelled orders total, and the table total when confirmed orders exist.
- Converted drafts do not add their draft item total to the table total again; the confirmed order total already carries that amount.
- All active guests see the same grouped cart information.
- The basket shows ready guest count versus active guest count and whether all active guests are ready.
- The current active guest can toggle readiness through `ToggleTableSessionGuestReadyAction`, which sets or clears `table_session_guests.ready_at`.
- Any active guest can send the shared draft to waiter review through `SendDraftOrderToWaiterAction`.
- If not all active guests have `ready_at` set, the basket shows inline confirmation before sending.
- Sending clears active guests' `ready_at`, sets the draft to `sent_to_waiter`, stores sender/timestamp, and updates the service point to `has_new_order`.
- The basket receives the public QR token so edit/delete actions can recheck the saved browser guest token.
- Active guests can edit only their own positions from the basket.
- Editing own positions supports quantity, comment, and currently available modifier selections.
- Active guests can delete only their own positions from the basket.
- Other guests' positions are read-only.
- If the draft status is no longer `draft`, the basket shows a blocked-editing message and does not expose edit/delete actions.
- If the draft status is `rejected`, the basket shows the waiter rejection reason.
- If the draft status is `converted_to_order`, the basket tells guests that the order was confirmed and editing is closed.
- After a converted draft, adding a new guest menu position creates a new latest draft for the same table session so guests can make a repeat order.
- `UpdateGuestDraftOrderItemAction`, `DeleteGuestDraftOrderItemAction`, and `SendDraftOrderToWaiterAction` enforce the same active guest and draft status checks on the backend.
- Draft cart state is read fresh from SQLite on polling refresh and is not cached; database cache is used for menu payloads only.
- Guest basket per-person totals include confirmed non-cancelled `order_items` snapshots plus the current open draft items.
- Guest basket table total includes confirmed non-cancelled `orders.total_price` plus the current open draft total and does not double-count converted drafts.
- Active guests can request the bill from the basket; this sets the table session to `payment_requested` and notifies waiters through the database notification table.
- After a draft is converted to an order, the cart shows a guest-facing service status from the confirmed order and ticket items: `Принято`, `Готовится`, `Готово`, or `Подано`.
- Guests only see the shared status. They cannot mark kitchen/bar ticket items ready or served.
- The basket can submit the draft only to waiter review and does not create final orders or online payments directly.

## Current Restaurant Dashboard

- Restaurant dashboard route is `GET /restaurant/dashboard`.
- The Livewire single-file component is `resources/views/pages/restaurant/dashboard.blade.php`.
- Analytics data is prepared by `App\Actions\Analytics\BuildBasicAnalyticsDashboardAction`; Blade receives arrays and must not query the database.
- Access requires at least one branch resolved through `view_reports`; superadmins see all branches.
- The dashboard shows orders today, amount today, average check, popular dishes, active tables, closed sessions, and cancelled orders.
- Dashboard analytics use Laravel's explicit `database` cache store for 300 seconds.
- The analytics block has a manual refresh button but does not poll every second.
- Cache is invalidated by observers on `orders`, `order_items`, `manual_payments`, and `table_sessions`.
- Keep this dashboard shared-hosting friendly: SQLite, database cache, no Redis, no cache tags, no WebSockets, no external BI service.

## Current Waiter Dashboard

- Waiter dashboard route is `GET /restaurant/waiter/dashboard`.
- Waiter table detail route is `GET /restaurant/waiter/tables/{tableSession}`.
- Livewire component is `App\Livewire\Waiter\Dashboard`.
- Waiter table detail Livewire component is `App\Livewire\Waiter\TableDetail`.
- Data is prepared by `App\Actions\Waiter\BuildWaiterDashboardAction`; Blade receives arrays and must not query the database.
- Table detail data is prepared by `App\Actions\Waiter\BuildWaiterTableDetailAction`; Blade receives arrays and must not query the database.
- Waiter branch access is shared through `App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction`.
- Waiter dashboard access requires auth and `view_orders` in the organization context.
- Waiter table detail access requires `view_orders`, `view_payments`, `manage_payments`, or fixed `cashier` access for the table session branch.
- Edit actions require `confirm_orders` or `edit_pending_orders` in the organization context and respect active `branch_users` assignments.
- Confirm/reject/return-to-draft actions require `confirm_orders` in the organization context and respect active `branch_users` assignments.
- Manual payment actions require `manage_payments` or the fixed `cashier` organization role and respect active `branch_users` assignments.
- Superadmin access still works through the existing computed permission bypass.
- If the user has active `branch_users` assignments inside organizations where they can view orders, the dashboard shows only those assigned branches.
- If the user has no active branch assignments, the dashboard shows branches from organizations where the user has `view_orders`.
- The dashboard shows available branches, service points, service point statuses, open table sessions, active guest counts, and drafts with `draft_orders.status` of `sent_to_waiter` or `waiter_review`.
- The dashboard also shows pending guest waiter calls from `waiter_calls`, branch call counts, service point `Waiter called` badges, guest name, zone, requested time, and a `Processed` action.
- The dashboard also shows bill-requested table sessions, branch bill counts, service point `Bill requested` badges, guest count, started time, and links to table detail.
- Open sessions and sent drafts link to the waiter table detail page.
- Open sessions currently include `pending`, `active`, `waiting_waiter_confirmation`, and `payment_requested`.
- Service points with sent or waiter-review drafts show the current service point status, usually `has_new_order` after `SendDraftOrderToWaiterAction`.
- Service points with pending guest waiter calls usually show `waiting_waiter` until a waiter marks the call processed.
- The dashboard uses `wire:poll.1s="refreshDashboard"` and does not use WebSockets.
- The table detail page uses `wire:poll.1s="refreshTable"` and does not use WebSockets.
- A browser-local audio notice can play when polling sees the number of sent drafts, guest waiter calls, or bill requests increase; no external service is used.
- `App\Livewire\Waiter\Dashboard::markWaiterCallHandled()` calls `MarkWaiterCallHandledAction` and refreshes the polling payload after handling.
- The table detail page shows branch, organization, brand, current zone, current service point, service point status, session status, latest draft status, sent timestamp, sent-by guest, guests sorted alphabetically, each guest's current draft positions, selected modifiers, guest comments, per-guest draft totals, confirmed orders total, current draft total, and the table total.
- Waiter table total includes already confirmed non-cancelled orders plus the current open draft, but does not double-count a latest draft that is already `converted_to_order`.
- The table detail page can edit a pending sent draft for users with `confirm_orders` or `edit_pending_orders`: change quantity, add an available active-menu dish for an active guest, delete a position, change comments, and update currently available modifier selections.
- The table detail page can confirm a `sent_to_waiter` or `waiter_review` draft, which creates an `orders` row and `order_items` snapshots with `orders.status = confirmed_by_waiter`.
- The table detail page uses the latest draft for review/confirm/send-to-kitchen actions, while older converted drafts remain available through order history.
- The table detail page can send a confirmed order to kitchen/bar for users with `send_to_kitchen`. This creates `kitchen_tickets` grouped by department, changes the order to `sent_to_kitchen_bar`, and moves the service point status to `cooking`.
- The table detail page shows dispatched kitchen/bar ticket items, ready count, served count, department names, modifiers, comments, and item status once an order has been sent to kitchen/bar.
- The table detail page lets a waiter with `view_orders` mark ready ticket items as served. This fills `kitchen_ticket_items.served_at` and `served_by_user_id`, updates the order status when all items are served, and refreshes through polling.
- The table detail page shows a manual payment summary for users with `view_payments`, `manage_payments`, or fixed `cashier` access.
- The table detail page can record whole-table or per-guest manual payment for users with `manage_payments` or fixed `cashier` access.
- The table detail page can close a fully paid session for payment managers/cashiers or manually close an unpaid session for users with `close_table_sessions`.
- Closed sessions keep old order history but old guest cookies/invite links cannot add positions because guest draft actions refuse `closed` sessions.
- Confirmed order snapshots keep the original dish names, prices, selected modifiers, comments, guest name, and totals even if menu data changes later.
- The table detail page actions write `order_status_logs` for waiter draft edits, confirmation, rejection, return-to-draft, and kitchen/bar dispatch.
- The table detail page can reject a sent draft with a required reason; guests see the reason in the shared cart.
- The table detail page can return a rejected draft to `draft` for guest edits.
- Waiter detail edit/review actions do not send anything to kitchen/bar until the explicit `Send to kitchen/bar` action is clicked.
- Manual payment actions do not create, confirm, or dispatch orders; they only record offline staff-entered payments for already confirmed non-cancelled orders.

## Current Kitchen Screen

- Kitchen screen route is `GET /restaurant/kitchen/dashboard`.
- Livewire component is `App\Livewire\Kitchen\Dashboard`.
- `App\Livewire\Kitchen\Dashboard` extends the shared `App\Livewire\Departments\Dashboard` base component.
- Data is prepared by shared `App\Actions\Departments\BuildDepartmentDashboardAction`; Blade receives arrays and must not query the database. `App\Actions\Kitchen\BuildKitchenDashboardAction` remains a thin kitchen-specific wrapper for backend reuse.
- Access is resolved by `App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction`.
- Access is allowed for superadmins, fixed `head_chef` and `cook` organization roles, or users with the flexible `view_kitchen` permission.
- Active `branch_users` assignments limit kitchen access to assigned branches.
- The component shows one selected active kitchen department at a time.
- The component reads only dispatched `kitchen_tickets` with `KitchenTicketStatus::Sent`.
- The screen shows service point display/name, zone, ticket creation time, timer, item name, quantity, guest name, modifiers, comments, and item status.
- `App\Actions\Kitchen\UpdateKitchenTicketItemStatusAction` changes `kitchen_ticket_items.status` to `new`, `in_progress`, or `ready`.
- Kitchen item status changes call shared order/ticket sync, so waiter and guest polling see `Готовится` or `Готово` without WebSockets.
- Kitchen cannot change a ticket item after the waiter marks it served.
- Ticket-level work status is computed from item statuses for display only.
- The shared screen uses `wire:poll.1s="refreshDepartment"` and does not use WebSockets.
- The restaurant sidebar and restaurant dashboard show a kitchen link only when the current user can access at least one active department.
- The screen does not expose unconfirmed drafts or merely confirmed orders; it reads only tickets created by explicit waiter dispatch.

## Current Bar Screen

- Bar screen route is `GET /restaurant/bar/dashboard`.
- Livewire component is `App\Livewire\Bar\Dashboard`.
- `App\Livewire\Bar\Dashboard` extends the shared `App\Livewire\Departments\Dashboard` base component.
- Data is prepared by shared `App\Actions\Departments\BuildDepartmentDashboardAction`; Blade receives arrays and must not query the database. `App\Actions\Bar\BuildBarDashboardAction` remains a thin bar-specific wrapper for backend reuse.
- Access is resolved by `App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction`.
- Access is allowed for superadmins, fixed `bartender` and `head_chef` organization roles, or users with flexible `view_orders` or `send_to_kitchen` permissions.
- Active `branch_users` assignments limit bar access to assigned branches.
- The component filters departments to active `KitchenDepartmentType::Bar` only.
- The component reads only dispatched `kitchen_tickets` with `KitchenTicketStatus::Sent`.
- The screen shows service point display/name, zone, ticket creation time, timer, drink item name, quantity, guest name, modifiers, comments, and item status.
- `App\Actions\Bar\UpdateBarTicketItemStatusAction` changes `kitchen_ticket_items.status` to `new`, `in_progress`, or `ready`.
- Bar item status changes use the same shared order/ticket sync as kitchen, so waiter and guest polling see `Готовится` or `Готово` without WebSockets.
- Bar cannot change a ticket item after the waiter marks it served.
- Ticket-level work status is computed from item statuses for display only.
- The shared screen uses `wire:poll.1s="refreshDepartment"` and does not use WebSockets.
- The restaurant sidebar and restaurant dashboard show a bar link only when the current user can access at least one active bar department.
- The screen does not expose unconfirmed drafts, merely confirmed orders, or non-bar department tickets; it reads only bar tickets created by explicit waiter dispatch.

## Current Branch Menu UI

- Branch menu route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/menu`.
- Route model nesting is checked in the Livewire component: brand must belong to organization, and branch must belong to the route brand and organization.
- Access requires `manage_menu` in the current organization context; superadmin bypass still works through computed permissions.
- The UI uses Blade + Livewire + Flux components and does not use React, Vue, WebSockets, Redis, S3, Docker, or external media services.
- The page can create, edit, manually sort, and delete menus.
- The page can create, edit, manually sort, activate/deactivate, and delete categories.
- The page can create, edit, manually sort, and delete dishes.
- Dish photo upload/removal uses `StoreLocalImageAction` and `DeleteLocalMediaFileAction` on Laravel's local `public` disk.
- The page eager-loads categories, items, item category labels, item kitchen department labels, item modifier groups, modifier options, modifier item counts, kitchen department item counts, and menu counts; Blade must not query the database.
- Price fields are only shown and applied for users with `change_prices`.
- Availability switches and manual availability changes are only shown and applied for users with `change_availability`.
- Deleting dishes, categories, or menus removes related local dish photos.
- Menu/category/item/translation/modifier model observers forget guest menu cache after menu changes.
- The branch list shows a `Menu` action only to users with `manage_menu`.
- This UI now updates the data shown by the public QR guest menu through cache invalidation.
- The page can create, edit, manually sort, and delete modifier groups.
- The page can create, edit, manually sort, and delete modifier options.
- The page can assign and remove modifier groups from dishes.
- The page can create, edit, manually sort, enable/disable, and delete kitchen departments.
- The page can assign a kitchen department to each dish.
- Leaving a dish department on `Default kitchen` saves the branch's default `kitchen` department, not `null`.
- Changing a dish's department assignment clears the guest menu database cache.
- Modifier group CRUD and dish assignment require `manage_menu`.
- Modifier option price deltas require `change_prices`.
- Modifier option availability changes require `change_availability`.
- The branch menu UI manages department and modifier setup only; waiter confirmation and final order conversion happen from the waiter table detail page, not from menu admin.

## Current Service Point UI

- Branch service point route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points`.
- Route model nesting is checked in the Livewire component: branch must belong to the route brand and organization.
- Users can access the page when they can change service point statuses, open tables, or when they have `generate_qr` in the current organization context.
- CRUD actions still require `manage_service_points`.
- Manual status changes require `manage_service_points` or the fixed `waiter` organization role.
- Opening a table requires `view_orders` or `confirm_orders` in the current organization context.
- The `Open table` button creates or returns the active table session and marks the service point `occupied`.
- Service points with an active table session show an `Active session` badge and a disabled `Table opened` button.
- QR generation and QR detail display require `generate_qr`.
- The UI eager-loads `areaNode`, `activeQrCode`, and `activeTableSession`; Blade must not query the database.
- The QR panel displays `short_code`, status, and `/q/{public_token}` only. It must not expose service point IDs, branch IDs, area names, or table numbers in the QR URL.
- The `Show QR` action opens the QR admin page for the active QR record.

## Current QR Admin Page

- QR admin route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points/{servicePoint}/qr/{qrCode}`.
- Access requires auth, organization access, and `generate_qr` in the current organization context.
- Route model nesting is checked: brand must belong to organization, branch must belong to brand and organization, service point must belong to branch, and QR must belong to service point.
- The page eager-loads current service point and current area before rendering.
- Blade displays prepared state only and must not query the database.
- The page shows branch, current area, current service point, public URL, SVG QR image, short code, status, and creation date.
- `downloadQrImage` streams a local SVG file generated from the public URL.
- `disableQr` changes active QR status to `disabled`.
- `reissueQr` is intentionally dangerous, requires a warning confirmation, revokes the current active QR, and creates one new active QR.
- The page links to the print template for the same QR record.
- Normal service point edit actions must not call reissue or create a new QR.

## Current QR Print Template

- QR print route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points/{servicePoint}/qr/{qrCode}/print`.
- Access requires auth, organization access, and `generate_qr` in the current organization context.
- Route model nesting is checked: brand must belong to organization, branch must belong to brand and organization, service point must belong to branch, and QR must belong to service point.
- The page uses `resources/views/layouts/print.blade.php` instead of the normal admin sidebar layout.
- The sticker is built for browser print first, not PDF generation.
- The printed sticker shows brand/logo, `Сканируйте, чтобы открыть меню`, the QR image, and `short_code`.
- Area name is not printed.
- Service point display number is not printed by default.
- `print_table_number` is a URL-backed Livewire setting for including the display number or service point name.
- When `print_table_number` is enabled, the warning about stale sticker text is visible on screen and hidden in print media.
- Print CSS lives in `resources/css/app.css`; the admin toolbar and warning are hidden in `@media print`.
- No paid PDF service, external QR service, S3, WebSockets, Redis, or Docker is used.

## Current Bulk QR Print Page

- Bulk QR print route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/qr/print`.
- Access requires auth, organization access, and `generate_qr` in the current organization context.
- Route model nesting is checked: brand must belong to organization, and branch must belong to brand and organization.
- The page uses `resources/views/layouts/print.blade.php` and remains browser print-friendly first.
- The page lets users filter by all areas, one area node, or service points without an area.
- The page lists service points with prepared `areaNode` and `activeQrCode` relationships; Blade must not query the database.
- Users can select service points that already have an active QR.
- If a shown service point has no active QR, the page offers to create one.
- `createMissingQrForVisible` creates active QR codes for the currently shown missing service points and selects them for print.
- Existing active QR records are reused through `GenerateQrCodeForServicePointAction`; bulk print must not create duplicate active QR codes.
- The printable grid uses local SVG QR images and the same optional local logo behavior as the single sticker template.
- Service point display number is not printed by default.
- `print_table_number` is available on the bulk page too and shows the same stale-label warning when enabled.
- PDF export is still not implemented.

## Current Branch Area UI

- Branch area route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/areas`.
- Route model nesting is checked in the Livewire component: branch must belong to the route brand and organization.
- Access requires `manage_zones` in the current organization context; superadmin bypass still works through computed permissions.
- The UI uses Blade + Livewire + Flux components.
- The tree is built in the Livewire component from one eager collection; Blade does not query the database.
- The UI does not show technical IDs to users.
- QR is intentionally not part of this step.

## Next Step

The next expected product step may be manual payment reporting/refinement, ticket/service status history, a bar-specific workflow refinement, QR PDF generation, staff invite acceptance flow, a menu translation admin editor, or guest menu refinements, but only implement it when a prompt explicitly requests it.

## Do Not Break

- Do not rewrite architecture.
- Do not add unrelated future features.
- Do not add Redis, WebSockets, S3, Docker, paid services, React, Vue, Inertia, or a separate SPA.
- Do not move restaurant dashboard analytics away from SQLite/database cache or make analytics refresh with 1-second polling.
- Do not use Redis cache tags for analytics invalidation; use explicit database-cache keys and model observers.
- Do not send waiter calls through SMS, push, Telegram API, WebSockets, Redis, or an external notification provider.
- Do not create more than one pending waiter call for the same service point.
- Do not send bill requests through online payments, SMS, push, Telegram API, WebSockets, Redis, or an external notification provider.
- Do not create payment records from the guest `Попросить счёт` button; it is only a `payment_requested` status and database notification flow.
- Do not add Stripe, PayPal, online acquiring, or paid payment providers to the manual payment flow.
- Do not allow manual payment while the latest draft is still `draft`, `sent_to_waiter`, or `waiter_review`.
- Do not allow opening a second table session for a service point while its current session is `payment_requested`.
- Do not let closed table sessions accept guest draft items, guest invite joins, or any new guest ordering.
- Do not reissue, disable, revoke, or regenerate a permanent QR when closing a table session.
- Do not delete or overwrite old orders, order items, manual payments, or order status logs when closing a table session.
- Do not expose internal IDs in future QR/public guest URLs.
- Keep public QR URLs token-only as `/q/{public_token}`.
- Do not expose table session IDs in guest invite links.
- Keep guest list polling isolated to the guest list block; do not make the whole guest table page poll.
- Do not make the guest menu block poll; menu freshness should come from database cache invalidation.
- Do not add AI translations, a complex translation editor, advanced kitchen/bar production history, or online payment logic unless a prompt explicitly asks for that exact step.
- Do not bypass `AddGuestDraftOrderItemAction` when adding guest draft rows.
- Do not bypass `UpdateGuestDraftOrderItemAction` or `DeleteGuestDraftOrderItemAction` when changing guest-owned draft rows.
- Do not bypass `SendDraftOrderToWaiterAction` when sending the shared draft to waiter review.
- Do not bypass `AddDraftOrderItemByWaiterAction`, `UpdateDraftOrderItemByWaiterAction`, or `DeleteDraftOrderItemByWaiterAction` when staff edit a sent draft before confirmation.
- Do not allow a guest to edit or delete another guest's draft item.
- Do not allow guest draft edits after the draft status leaves `draft`.
- Do not create real orders from the guest UI; waiter confirmation must come first for every draft, including repeat orders.
- Do not overwrite old orders or old order items when guests make a repeat order in the same table session.
- Do not auto-dispatch confirmed orders during waiter confirmation; kitchen/bar tickets must be created only by explicit `SendOrderToKitchenBarAction`.
- Do not expose unconfirmed drafts or merely confirmed orders to kitchen/bar screens; these screens must read only dispatched tickets.
- Do not show non-bar department tickets on the bar screen.
- Do not let kitchen/bar change a ticket item after the waiter has marked it served.
- Do not let guests mark ticket items ready or served.
- Do not duplicate the full kitchen/bar ticket UI; keep shared department screen logic where practical.
- Do not switch kitchen or bar screens away from Livewire polling or add WebSockets.
- Do not recalculate old `order_items` from live menu data; confirmed orders must keep immutable snapshots.
- Do not overwrite old `order_items.kitchen_department_type` or `order_items.kitchen_department_name` when a department is renamed, disabled, deleted, or retyped.
- Do not cascade-delete `order_status_logs`; history rows must survive with actor/status snapshots.
- Do not let the branch menu admin save a blank dish department as `null`; blank means the default `kitchen` department.
- Keep the shared table cart grouped by guest alphabetically and keep draft cart reads live from the database.
- Keep guest readiness on `table_session_guests.ready_at`; do not create a separate readiness table unless a later prompt explicitly asks for it.
- Do not break guest menu language fallback: missing translations must show base category/item text.
- Do not switch guest menu cache away from explicit database cache or remove language from guest menu cache keys.
- Do not let users without `change_prices` change menu item prices.
- Do not let users without `change_availability` change menu item availability.
- Do not let users without `change_prices` change modifier option price deltas.
- Do not let users without `change_availability` change modifier option availability.
- Do not make QR generation create a second active QR automatically when one already exists.
- Do not reissue QR from ordinary service point edits.
- Do not print service point number or area by default on QR stickers.
- Do not remove SQLite support.
- Do not switch cache, sessions, or queues away from database drivers.
- Do not commit `.env`, SQLite database files, `vendor`, `node_modules`, or storage uploads.
- Do not add business logic to Blade templates.
- Do not add raw SQL strings.

## Verification Commands

Use these checks after small changes:

```bash
php artisan migrate --no-interaction
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build
```

For migration reversibility when a new migration is added:

```bash
php artisan migrate:rollback --step=1 --no-interaction
php artisan migrate --no-interaction
```

# Restaurant Menu SaaS

Laravel SaaS foundation for restaurants, cafes, bars, hotels, food courts, and similar venues.

This project is not only a QR menu. The current codebase is a clean shared-hosting-friendly foundation for the platform, with authentication, system roles, permissions, organizations, brands, branches, branch settings, local media storage, nested branch areas, service point schema and CRUD, branch menu CRUD, menu translations, menu modifiers, kitchen departments, guest menu display with modifier selection, table session schema, guest-created pending sessions, guest join approval UI, guest invite share links, guest table page shell, guest waiter-call and bill requests, draft order schema, shared table cart UI, guest ready status, guest item editing, waiter dashboard shell, waiter table detail, waiter draft editing/confirmation/rejection, repeat orders in the same table session, real order snapshots, kitchen/bar dispatch tickets, basic kitchen and bar screens, waiter ready/served handoff, manual offline payments, permanent QR schema, generation, admin display page, simple and bulk browser print templates, public QR guest landing, basic superadmin access, staff invitation foundations, simple staff management UI, and staff permission override UI.

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

## Superadmin Access

`superadmin` is a platform-level role for SaaS administration. Superadmins can access the platform dashboard at:

```text
/superadmin/dashboard
```

The platform dashboard shows organizations, brands, branches, and users across the whole SaaS platform. Regular users do not see the platform dashboard link and receive `403 Forbidden` if they open the superadmin URL directly.

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

The default database file is:

```text
database/database.sqlite
```

This file is inside the project and outside `public/`, which keeps it suitable for shared hosting when the web root points to `public/`.

`.env.example` leaves `DB_DATABASE` empty so Laravel uses the safe default from `config/database.php`.

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
- `menu_categories`
- `menu_items`
- `kitchen_departments`
- `modifier_groups`
- `modifier_options`
- `menu_item_modifier_groups`

Each menu belongs to a branch through `branch_id`, stores a name, a fixed status, and a sort order. Current menu statuses are `draft`, `active`, and `archived`.

Menu categories belong to one menu and can be nested with `parent_id`. They store name, optional description, optional image path, optional icon, sort order, and `is_active`.

Menu items belong to one menu and one category. They can be assigned to one branch kitchen department through `kitchen_department_id`; when the admin leaves the selector on `Default kitchen`, the system stores the branch's default `kitchen` department. They store name, optional description, price, optional image path, optional weight, optional volume, optional calories, availability, and sort order.

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

Access requires `manage_menu` in the current organization context. Users can create, edit, sort, and delete menus, categories, dishes, kitchen departments, modifier groups, modifier options, and dish modifier assignments. Dish photos are uploaded locally to Laravel's `public` disk. Changing prices or modifier price deltas requires `change_prices`; changing dish or modifier option availability requires `change_availability`. Changing a dish department assignment clears the guest menu database cache.

Active guests on the public QR table page see the current branch's first active menu. The guest menu shows active categories, dishes, prices, local dish photos when present, unavailable dish state, and available modifier options for dishes that have modifier groups.

When an active guest taps an available dish, a mobile-first bottom sheet lets them choose modifier options, satisfy required modifier groups, see the final item price with `price_delta`, and add a dish comment. Saving the sheet adds the position to the shared draft order for the table.

The guest menu payload is cached through Laravel's `database` cache store for 300 seconds with language-specific keys:

```text
guest-menu:branch:{branch_id}:language:{language_code}
```

Menu cache uses the SQLite-backed `cache` table and a short database lock from `cache_locks` while rebuilding the branch payload. It does not use Redis, cache tags, WebSockets, S3, or any external service.

Menu cache is forgotten automatically when menus, categories, dishes, kitchen departments, modifier groups, modifier options, dish modifier assignments, or translations are created, updated, or deleted. Price changes, department assignment changes, modifier changes, and translation changes clear the branch menu cache, so the next guest read rebuilds the payload and shows the current content.

The current guest menu UI writes configured items to `draft_order_items`, and the guest basket lets active guests edit or delete their own draft positions before the draft is sent to a waiter. The basket is grouped by guests alphabetically and shows the same shared cart information to everyone at the table. Guest totals include already confirmed order snapshots plus the current open draft, and the table total uses the same rule. Active guests can send the shared draft to the waiter for review and can request the bill for the current table session. This does not start online payment logic.

## Area Nodes

Area nodes are the nested zone structure inside a branch. They are stored in the `area_nodes` table and belong to one branch.

Each area node can have a `parent_id`, so branches can model structures such as floors, halls, terraces, VIP rooms, hotel areas, pickup areas, delivery areas, and custom groups. Area nodes store `type`, `name`, optional `icon`, `sort_order`, `is_active`, optional `metadata`, and support soft delete through `deleted_at`.

Branch areas are managed at:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/areas
```

The area UI is guarded by the `manage_zones` permission in the current organization context. It can add common zone presets, choose an icon, rename, move a zone inside another zone, disable/enable zones, and soft delete zones while keeping child zones visible.

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

## Table Session Guests

Table session guests are stored in the `table_session_guests` table and belong to one table session.

The table stores `guest_name`, a random `guest_token`, `status`, optional `ready_at`, `joined_at`, optional `left_at`, and optional JSON `metadata`.

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

The table stores `guest_name`, a random `guest_token`, `status`, optional approval/rejection audit fields for guests and users, and `expires_at`.

Supported join request statuses are:

- `pending`
- `approved`
- `rejected`
- `expired`

If a table session already has active guests, a new QR guest creates a pending join request and does not enter the table immediately. Any active guest from the same table session can approve or reject the request through backend actions. Approval creates a real `table_session_guests` record using the request guest name and token. Rejection does not create a guest.

The public QR page now shows a waiting state for the new guest. Active guests see a small Livewire polling block with pending join requests and can accept or reject them without WebSockets. The waiting guest's status block also refreshes through Livewire polling and shows approved or rejected state clearly.

Active guests also see a simple `Пригласить гостя` action. It creates or reuses the table session invite link, uses the browser native share API when available, and falls back to a `Скопировать ссылку` button when native sharing is not available. The project does not integrate directly with Telegram, WhatsApp, Viber, SMS, email, or any paid provider; the phone/browser decides which share targets are available.

After an active guest is recognized, the public QR page opens the main guest table shell instead of the entry form. The shell shows the venue name, current service point, saved entry state, the invite action, a guest list, the cached active branch menu, and the shared draft basket.

The guest list and shared draft basket are rendered by separate isolated Livewire components and refresh with polling. This keeps those blocks current without refreshing the whole guest page.

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

Active guests can add available menu items to the shared draft from the public QR guest menu. The backend rechecks the guest token, guest status, table session status, menu item availability, and modifier availability before creating a draft item. Rejected, removed, pending, or left guests cannot add positions.

The shared basket is a separate Livewire polling block. It groups active guests alphabetically by `guest_name`, shows each guest's positions, line prices, selected modifiers, comments, item count, guest total, the current draft total, and the table total including already confirmed non-cancelled orders.

An active guest can edit only their own draft positions. They can change quantity, comment, and currently available modifier selections, or delete their own position. The backend rechecks the browser guest token, active guest status, item ownership, table session, and draft status. Guests cannot edit or delete another guest's position.

All active guests see the same grouped table cart information. Only the current guest gets edit and delete controls for their own positions.

Each active guest can press `Я готов` in the shared cart to set `table_session_guests.ready_at`, or press `Снять готовность` to clear it. The guest list and shared cart show `Готов` / `Не готов`, plus the cart shows how many active guests are ready.

Any active guest can press `Отправить официанту` for the shared draft, even if some positions belong to other guests. If not all active guests are ready, the UI first shows an inline confirmation. After confirmation, the draft status becomes `sent_to_waiter`, `sent_to_waiter_at` and `sent_by_guest_id` are saved, guest readiness is cleared, and the service point status becomes `has_new_order`.

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

Order items can keep optional links to the original guest, menu item, and kitchen department, but they also store immutable snapshots of guest name, dish name, kitchen department type/name, unit price, modifier total, line total, selected modifiers, and comment. If the menu item name, price, modifier options, or kitchen department name/type change later, old confirmed orders keep the original order snapshot.

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

The kitchen screen reads only dispatched `kitchen_tickets`, shows one selected department at a time, and refreshes with Livewire polling every 1 second. It shows the current service point, zone, ticket items, modifiers, comments, creation time, and large buttons for changing each item status to `new`, `in_progress`, or `ready`. It does not use WebSockets, Redis, S3, Docker, or paid services.

The bar screen is available at:

```text
/restaurant/bar/dashboard
```

The bar screen reuses the same shared department screen logic as the kitchen screen, but filters departments to type `bar`. It shows dispatched bar tickets only: service point, zone, drinks, modifiers, comments, item status, and a live timer. Access is allowed for superadmins, users with the fixed `bartender` or `head_chef` role, or users with `view_orders` or `send_to_kitchen`. Active `branch_users` assignments still limit visible bar departments to assigned branches.

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

This stage adds kitchen/bar dispatch tickets and basic department kitchen and bar screens. It does not add payments, analytics, or advanced kitchen/bar production history.

## Waiter Dashboard

The waiter dashboard shell is available at:

```text
/restaurant/waiter/dashboard
```

Access requires authentication and the `view_orders` permission in the organization context. Superadmins keep the normal platform-level permission bypass. If a user has active `branch_users` assignments, the dashboard shows only those assigned branches; otherwise it shows the branches from organizations where the user can view orders.

The dashboard uses Livewire polling every 1 second and does not use WebSockets. It shows:

- branches available to the waiter;
- service points in those branches;
- service point statuses;
- open table sessions;
- pending guest waiter calls;
- guest bill requests;
- shared drafts with `sent_to_waiter` or `waiter_review` status;
- a small browser audio notice when a new sent draft, guest waiter call, or bill request appears during polling.

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

Generated QR URLs use:

```text
/q/{public_token}
```

The public `/q/{public_token}` route resolves the QR token, checks the QR status, loads the current service point, current area, branch, brand, organization, and local logo, and opens a mobile-first guest landing page. The URL does not include organization IDs, branch IDs, service point IDs, table numbers, or area names.

The guest landing page shows the venue name, logo when available, current area, current service point, a guest name field, and the `Войти за стол` button. If there is no active or pending table session and `allow_guest_created_sessions` is enabled, submitting the name creates a pending table session and the first active guest inside it, then stores the guest token in a browser cookie. Refreshing the page restores that guest from the cookie. If an active or pending session already has active guests, submitting the name creates a pending join request instead of adding the guest immediately.

Disabled and revoked QR codes show a clear public error message. Active QR codes for inactive service points show a clear message telling the guest to ask staff. Moving or renaming a service point does not change the QR URL; the public page loads the current service point data each time.

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
- Organizations with owner membership.
- Organization users with role, status, joined date, and inviter fields.
- Brands inside organizations.
- Branches inside brands and organizations.
- Branch settings stored in `branch_settings`.
- Local logo uploads for organizations, brands, and branches.
- Nested branch areas stored in `area_nodes`.
- Service point schema and CRUD UI stored in `service_points`.
- Branch menu CRUD stored in `menus`, `menu_categories`, and `menu_items`.
- Branch kitchen departments stored in `kitchen_departments`, assignable to menu items and snapshotted into confirmed order items.
- Branch menu modifier CRUD stored in `modifier_groups`, `modifier_options`, and `menu_item_modifier_groups`.
- Cached guest menu display with modifier selection, shared table cart UI, guest ready status, send-to-waiter draft handoff, and guest draft item creation/editing on the active public QR table page.
- Service point operational statuses and manual status changes.
- Table session schema stored in `table_sessions`.
- Shared draft order schema and guest-owned draft item creation/editing stored in `draft_orders` and `draft_order_items`.
- First guest pending session creation from the public QR landing.
- Table session join request schema, backend create / approve / reject logic, guest approval UI, guest invite share links, and guest table page shell.
- Guest waiter-call requests stored in `waiter_calls` with Laravel database notifications for the waiter dashboard.
- Guest bill requests stored as `table_sessions.status = payment_requested`, with service point status updates and Laravel database notifications for the waiter dashboard.
- Manual offline payment records stored in `manual_payments`, with whole-table and per-guest payment actions from waiter table detail.
- Permanent QR schema, generation action, admin display page, simple and bulk browser print templates, and public `/q/{public_token}` route.
- Basic superadmin access for the platform dashboard.
- Staff invitation model and backend creation action.
- Simple staff management UI for organization and branch staff.
- Staff permission override UI with default / allow / deny states.
- Waiter dashboard shell, table detail, draft edit actions, and draft confirm/reject actions for branches, service points, open sessions, guests, draft positions, and drafts sent to waiter review.
- Real order snapshot tables stored in `orders` and `order_items` after waiter confirmation.
- Kitchen/bar dispatch tickets stored in `kitchen_tickets` and `kitchen_ticket_items` after explicit waiter dispatch.
- Basic kitchen and bar screens for dispatched department tickets with item statuses and Livewire polling.
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

Branch settings store order flow, guest session behavior, invite-link behavior, service charge and tips toggles, language/currency defaults, and Livewire polling interval. They are kept in the `branch_settings` table and are managed from the branch settings Livewire page.

Not implemented yet:

- Menu translation admin editor.
- QR PDF generation.
- Advanced kitchen/bar production history.
- Online payments and analytics.
- Staff invitation acceptance flow and email/SMS delivery.

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

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
- New guests must require approval by default.
- Branch realtime behavior must use Livewire polling, not WebSockets.

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
- Branch menu CRUD with branch menus, nested menu categories, menu items, local dish photos, menu category/item translation tables, branch-level menu modifiers, and permission-gated price/availability changes.
- Table sessions schema for branch/service point lifecycle tracking.
- Waiter/admin open-table action and service point UI for creating active table sessions.
- Guest-created pending table sessions from the public QR landing.
- Table session guests with guest names, random browser guest tokens, cookie restore, statuses, and alphabetical ordering.
- Table session join requests with backend create / approve / reject logic, guest approval UI, guest invite share links, guest table page shell, and database-cached guest menu display with modifier selection.
- Draft order schema with one shared draft per table session, guest-owned draft items with price snapshots, guest add/edit/delete UI for own positions, guest ready status, send-to-waiter handoff, waiter confirm/reject actions, and an isolated shared table cart polling block grouped by guest.
- Real order snapshot schema in `orders` and `order_items`, created only after waiter confirmation.
- Waiter dashboard shell and waiter table detail with branch/service-point/session status, sent draft visibility, guest positions, modifiers, comments, totals, and confirm/reject controls through Livewire polling.
- Permanent QR schema, generation action, admin display page, simple and bulk browser print templates, and public QR guest landing with name entry.
- Basic superadmin access for the platform dashboard.
- Staff invitation backend foundation.
- Simple organization and branch staff management UI.
- Staff permission override UI.

No menu translation admin editor, QR PDF generation, kitchen, bar, payment, analytics, or confirmed-order dispatch logic has been implemented yet.

## Tables

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
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
- `modifier_groups`
- `modifier_options`
- `menu_item_modifier_groups`
- `area_nodes`
- `service_points`
- `table_sessions`
- `table_session_guests`
- `table_session_join_requests`
- `draft_orders`
- `draft_order_items`
- `orders`
- `order_items`
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
- Has many modifier groups.
- Has many nested area nodes.
- Has many service points.
- Has many branch staff assignments through `branch_users`.
- Stores optional `logo_path` for a locally stored logo.

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
- Translation support exists for guest display, but a full admin editor for translations is not implemented yet.
- Modifier assignment exists in admin CRUD and the guest UI can configure available modifiers and persist configured selections into `draft_order_items`.

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
- `TableSession` sets `active_service_point_id` automatically while saving active sessions and clears it for non-active statuses.
- `TableSession` sets `pending_service_point_id` automatically while saving pending sessions and clears it for non-pending statuses.
- `ServicePoint::activeTableSession()` returns the current active table session for UI display.
- `TableSession::draftOrder()` returns the one shared draft order for the session.
- `OpenTableSessionForServicePointAction` creates an active waiter-opened session with `started_at` when no active session exists.
- If an active session already exists for the service point, `OpenTableSessionForServicePointAction` returns it instead of creating a duplicate.
- Opening a table updates the service point status to `occupied` through `UpdateServicePointStatusAction`.
- `CreateGuestPendingTableSessionAction` creates a pending guest-created session when there is no active or pending session and `branch_settings.allow_guest_created_sessions` is true.
- If an active or pending session already exists and has active guests, guest QR entry creates a pending table session join request instead of a guest.
- If an active or pending session already exists without active guests, guest QR entry returns the existing-session message without creating a join request.
- `CreateGuestInviteLinkAction` creates or reuses one hidden invite token for the current table session.
- Only an active guest in the same table session can create the guest invite link.
- Guest invite links respect `branch_settings.allow_guest_invite_links`.
- Guest invite URLs use `/q/{public_token}?invite={guest_invite_token}` and must not expose table session IDs, service point IDs, branch IDs, table numbers, or area names.
- Opening a guest invite link asks the invited person for a name and creates a pending join request for the invited table session.
- Draft order schema, guest add-to-draft UI, send-to-waiter handoff, waiter dashboard visibility, and waiter confirm/reject actions exist. Confirmed orders are stored in `orders` and `order_items`, but no kitchen/bar dispatch or payment logic exists yet.

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
- `TableSessionGuest::draftOrderItems()` exposes draft items owned by the guest.
- `ready_at` marks that an active guest is ready; `null` means not ready.
- Active guests can approve or reject new guest join requests from the public QR UI.
- `App\Livewire\PublicQr\TableGuests` renders the guest list for active guests and polls only that block.
- The guest list shows guest names alphabetically, human-readable guest statuses, and ready/not-ready labels.

Draft order:

- Stored in `draft_orders`.
- Belongs to one table session through `table_session_id`.
- Each table session can have one shared draft order, enforced by a unique `table_session_id`.
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
- `AddGuestDraftOrderItemAction` creates the shared draft on first add and stores guest item snapshots.
- `UpdateGuestDraftOrderItemAction` lets an active guest update only their own draft item quantity, comment, and selected modifiers while the draft is still `draft`.
- `DeleteGuestDraftOrderItemAction` lets an active guest delete only their own draft item while the draft is still `draft`.
- `SendDraftOrderToWaiterAction` lets any active guest in the same open table session send the shared draft to waiter review.
- Sending sets the draft status to `sent_to_waiter`, stores `sent_to_waiter_at` and `sent_by_guest_id`, clears active guest `ready_at`, and moves the related service point to `has_new_order`.
- `ConfirmDraftOrderByWaiterAction` requires `confirm_orders`, converts a `sent_to_waiter` or `waiter_review` draft to `converted_to_order`, creates one `orders` row with status `confirmed_by_waiter`, and copies draft items into `order_items`.
- `RejectDraftOrderByWaiterAction` requires `confirm_orders`, sets a sent draft to `rejected`, and stores a required rejection reason for guests to see.
- `ReturnRejectedDraftOrderToDraftAction` requires `confirm_orders` and returns a rejected draft to `draft` so guests can edit and send the same shared draft again.
- `BuildDraftOrderItemModifierSnapshots` is shared by add and update actions for modifier selection validation and JSON snapshots.
- This draft flow still does not send anything to kitchen, bar, payment, or analytics. A confirmed order must be dispatched by a later explicit kitchen/bar step.

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
- Guests cannot edit or delete another guest's draft item.
- Editing can change quantity, comment, and currently available modifier selections.
- Updating quantity recalculates `total_price` from the item snapshot `unit_price`, per-unit `modifier_total`, and quantity.
- Editing and deletion are blocked once the shared draft status is no longer `draft`, including `sent_to_waiter`.
- Rejected drafts show the waiter rejection reason in the guest shared cart polling block.
- Confirmed drafts show guests that the order was confirmed and editing is closed.
- Rejected, removed, pending approval, left, or token-mismatched guests cannot create, edit, or delete draft item rows.

Order:

- Stored in `orders`.
- Created only by `ConfirmDraftOrderByWaiterAction` after waiter confirmation.
- Belongs to branch, service point, table session, and draft order.
- Has one unique `draft_order_id`, so the same draft cannot create two real orders.
- Status is cast to `OrderStatus`.
- Current status value is `confirmed_by_waiter`.
- Stores `confirmed_by_user_id`, `confirmed_at`, `total_price`, `currency`, and optional JSON `metadata`.
- Metadata currently marks that kitchen/bar dispatch is prepared but not sent.
- Has many `order_items`.
- `orders` are not shown to kitchen/bar yet.

Order item:

- Stored in `order_items`.
- Belongs to one real order through `order_id`.
- Optionally references the original table session guest and menu item.
- Stores guest/item/price snapshots copied from `draft_order_items`: `guest_name`, `item_name`, `quantity`, `unit_price`, `modifier_total`, `total_price`, selected modifiers, and optional comment.
- These rows prepare later kitchen/bar dispatch without exposing unconfirmed drafts to kitchen/bar.

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
- `GET /restaurant/waiter/dashboard` -> `restaurant.waiter.dashboard`
- `GET /restaurant/waiter/tables/{tableSession}` -> `restaurant.waiter.tables.show`
- `GET /superadmin/dashboard` -> `superadmin.dashboard` guarded by `auth` + `superadmin`
- Auth and profile routes are provided by Fortify and `routes/settings.php`.

## Livewire Components

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
- Public QR route does not create final orders, payment records, or send anything to kitchen/bar yet.

## Current Guest Menu Display

- `App\Livewire\PublicQr\GuestMenu` renders the guest menu block inside the active guest table shell.
- `App\Actions\Menus\GetGuestMenuForBranchAction` loads the first active menu for the current branch, sorted by `sort_order`, `name`, and `id`.
- The component exposes a compact `RU` / `EN` / `LT` selector and stores the selected guest language in the `lang` query parameter.
- Guest menu payloads are cached in Laravel's explicit `database` cache store for 300 seconds, even if the default cache store is changed in a test or environment.
- Cache key format is `guest-menu:branch:{branch_id}:language:{language_code}`.
- Rebuild lock key format is `guest-menu:branch:{branch_id}:language:{language_code}:lock` and uses the SQLite-backed `cache_locks` table.
- `MenuObserver`, `MenuCategoryObserver`, `MenuItemObserver`, `MenuCategoryTranslationObserver`, `MenuItemTranslationObserver`, `ModifierGroupObserver`, and `ModifierOptionObserver` forget the branch guest-menu cache on create, update, delete, restore, and force delete events.
- Updating a dish price, modifier, or translation clears the branch guest-menu cache, so the next guest read rebuilds the payload with the current content.
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
- The basket shows the total amount for the table.
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
- `UpdateGuestDraftOrderItemAction`, `DeleteGuestDraftOrderItemAction`, and `SendDraftOrderToWaiterAction` enforce the same active guest and draft status checks on the backend.
- Draft cart state is read fresh from SQLite on polling refresh and is not cached; database cache is used for menu payloads only.
- The basket can submit the draft only to waiter review and does not create final orders directly.

## Current Waiter Dashboard

- Waiter dashboard route is `GET /restaurant/waiter/dashboard`.
- Waiter table detail route is `GET /restaurant/waiter/tables/{tableSession}`.
- Livewire component is `App\Livewire\Waiter\Dashboard`.
- Waiter table detail Livewire component is `App\Livewire\Waiter\TableDetail`.
- Data is prepared by `App\Actions\Waiter\BuildWaiterDashboardAction`; Blade receives arrays and must not query the database.
- Table detail data is prepared by `App\Actions\Waiter\BuildWaiterTableDetailAction`; Blade receives arrays and must not query the database.
- Waiter branch access is shared through `App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction`.
- Access requires auth and `view_orders` in the organization context.
- Confirm/reject/return-to-draft actions require `confirm_orders` in the organization context and respect active `branch_users` assignments.
- Superadmin access still works through the existing computed permission bypass.
- If the user has active `branch_users` assignments inside organizations where they can view orders, the dashboard shows only those assigned branches.
- If the user has no active branch assignments, the dashboard shows branches from organizations where the user has `view_orders`.
- The dashboard shows available branches, service points, service point statuses, open table sessions, active guest counts, and drafts with `draft_orders.status = sent_to_waiter`.
- Open sessions and sent drafts link to the waiter table detail page.
- Open sessions currently include `pending`, `active`, `waiting_waiter_confirmation`, and `payment_requested`.
- Service points with sent drafts show the current service point status, usually `has_new_order` after `SendDraftOrderToWaiterAction`.
- The dashboard uses `wire:poll.1s="refreshDashboard"` and does not use WebSockets.
- The table detail page uses `wire:poll.1s="refreshTable"` and does not use WebSockets.
- A browser-local audio notice can play when polling sees the number of sent drafts increase; no external service is used.
- The table detail page shows branch, organization, brand, current zone, current service point, service point status, session status, draft status, sent timestamp, sent-by guest, guests sorted alphabetically, each guest's draft positions, selected modifiers, guest comments, per-guest totals, and the table total.
- The table detail page can confirm a `sent_to_waiter` or `waiter_review` draft, which creates an `orders` row and `order_items` snapshots with `orders.status = confirmed_by_waiter`.
- The table detail page can reject a sent draft with a required reason; guests see the reason in the shared cart.
- The table detail page can return a rejected draft to `draft` for guest edits.
- Waiter detail actions do not send anything to kitchen/bar and do not create payments.

## Current Branch Menu UI

- Branch menu route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/menu`.
- Route model nesting is checked in the Livewire component: brand must belong to organization, and branch must belong to the route brand and organization.
- Access requires `manage_menu` in the current organization context; superadmin bypass still works through computed permissions.
- The UI uses Blade + Livewire + Flux components and does not use React, Vue, WebSockets, Redis, S3, Docker, or external media services.
- The page can create, edit, manually sort, and delete menus.
- The page can create, edit, manually sort, activate/deactivate, and delete categories.
- The page can create, edit, manually sort, and delete dishes.
- Dish photo upload/removal uses `StoreLocalImageAction` and `DeleteLocalMediaFileAction` on Laravel's local `public` disk.
- The page eager-loads categories, items, item category labels, item modifier groups, modifier options, modifier item counts, and menu counts; Blade must not query the database.
- Price fields are only shown and applied for users with `change_prices`.
- Availability switches and manual availability changes are only shown and applied for users with `change_availability`.
- Deleting dishes, categories, or menus removes related local dish photos.
- Menu/category/item/translation/modifier model observers forget guest menu cache after menu changes.
- The branch list shows a `Menu` action only to users with `manage_menu`.
- This UI now updates the data shown by the public QR guest menu through cache invalidation.
- The page can create, edit, manually sort, and delete modifier groups.
- The page can create, edit, manually sort, and delete modifier options.
- The page can assign and remove modifier groups from dishes.
- Modifier group CRUD and dish assignment require `manage_menu`.
- Modifier option price deltas require `change_prices`.
- Modifier option availability changes require `change_availability`.
- The branch menu UI manages modifier setup only; waiter confirmation and final order conversion happen from the waiter table detail page, not from menu admin.

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

The next expected product step may be kitchen/bar dispatch for confirmed orders, QR PDF generation, staff invite acceptance flow, a menu translation admin editor, or guest menu refinements, but only implement it when a prompt explicitly requests it.

## Do Not Break

- Do not rewrite architecture.
- Do not add unrelated future features.
- Do not add Redis, WebSockets, S3, Docker, paid services, React, Vue, Inertia, or a separate SPA.
- Do not expose internal IDs in future QR/public guest URLs.
- Keep public QR URLs token-only as `/q/{public_token}`.
- Do not expose table session IDs in guest invite links.
- Keep guest list polling isolated to the guest list block; do not make the whole guest table page poll.
- Do not make the guest menu block poll; menu freshness should come from database cache invalidation.
- Do not add AI translations, a complex translation editor, kitchen/bar dispatch, or payment logic unless a prompt explicitly asks for that exact step.
- Do not bypass `AddGuestDraftOrderItemAction` when adding guest draft rows.
- Do not bypass `UpdateGuestDraftOrderItemAction` or `DeleteGuestDraftOrderItemAction` when changing guest-owned draft rows.
- Do not bypass `SendDraftOrderToWaiterAction` when sending the shared draft to waiter review.
- Do not allow a guest to edit or delete another guest's draft item.
- Do not allow guest draft edits after the draft status leaves `draft`.
- Do not create real orders from the guest UI; waiter confirmation must come first.
- Do not send confirmed orders to kitchen/bar from the waiter table detail page until a prompt explicitly asks for kitchen/bar dispatch.
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

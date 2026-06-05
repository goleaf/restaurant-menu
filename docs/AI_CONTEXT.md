# AI Context

This file is the working memory for coding agents. Read it before each prompt and update it after each completed step.

## Rescue Mode Before Prompt 122 - 2026-06-05

Prompt 122 was not implemented because the pre-prompt health check found the project was already broken. Rescue mode restored the existing waiter table detail flow by adding the missing `Flux\\Flux` import used by `Flux::modals()->close()`.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Same baseline as Prompt 121: public QR guest flow, permanent QR codes, branch setup, service points, guest sessions, shared drafts, waiter confirmation/rejection/cancellation, kitchen/bar tickets, manual payments, split bill by guests, manual service charge/tips, audit logs, and shared-hosting deployment notes.
- Rescue mode only restored an existing Livewire component namespace/import problem; it did not add order item voiding.

Current tables:

- No new tables or columns were added in rescue mode.
- Existing order/payment tables remain `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No routes were added or removed in rescue mode.
- Main relevant routes remain `GET /restaurant/waiter/dashboard`, `GET /restaurant/waiter/tables/{tableSession}`, `GET /restaurant/kitchen`, `GET /restaurant/bar`, and public `GET /q/{token}`.

Current Livewire components and actions:

- `App\\Livewire\\Waiter\\TableDetail` now imports `Flux\\Flux` so its existing modal close call resolves correctly.
- No Prompt 122 action, enum, migration, or UI was added yet.

Mandatory business rules:

- Rescue mode must not add new business behavior.
- Order cancellation remains whole-order status/history only until a future prompt implements item-level voiding.
- Existing payment totals, order snapshots, audit logs, kitchen/bar cancellation guardrails, and permanent QR rules must remain intact.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- No new infrastructure was added.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings in app code, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Rescue verification:

- `php artisan migrate --no-interaction`
- `php artisan test --compact tests/Feature/OrderCancellationTest.php tests/Feature/ManualPaymentTest.php`

Next recommended prompt:

- Prompt 122 - Void item before payment. Start it only after a fresh context/health check, and implement it as a small order-item status/history flow without deleting order data.

## Prompt 121 Order Cancellation With Reason - 2026-06-05

Prompt 121 added safe order cancellation with a required reason. It did not add new tables, routes, payment providers, kitchen ticket statuses, Redis, WebSockets, S3, Docker, paid services, or external APIs.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Public guest QR flow, branch public profile, opening hours, temporary branch closed mode, menu schedules, multiple active menus, service modes, bulk service point creation, permanent QR generation/printing/lookup, branch service point filtering/board, waiter zone assignments, manual waiter order entry, duplicate guest-name handling, session inactivity cleanup, active session transfer, merged table sessions, and waiter-side schedule checks.
- Guest sessions, join requests, invite links, shared draft cart, ready status, waiter handoff, waiter review/edit/confirm/reject, real order snapshots, kitchen/bar tickets, ready/served handoff, waiter calls, bill requests, manual payments, split bill by guests, manual service charge/tips snapshots, session close, dashboard analytics, audit logs, CSV exports, localization, currency settings, local media, superadmin controls, and shared-hosting deployment notes.
- Prompt 121: waiters with `cancel_orders`, directors, shift managers, and superadmin users can cancel eligible orders from the existing waiter table detail flow.
- Prompt 121: cancellation requires a non-empty reason and stores stable metadata on the order.
- Prompt 121: guests see cancelled order status and reason; kitchen/bar dashboards hide cancelled-order tickets and ticket item updates are blocked after cancellation.

Current tables:

- No new table or column was added in Prompt 121.
- Existing affected tables: `orders`, `order_status_logs`, `audit_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `draft_orders`, `table_sessions`, `table_session_guests`, `branches`, `organizations`, `roles`, `permissions`, `permission_role`, and `organization_users`.
- `orders.status` uses existing `cancelled`.
- `orders.metadata` stores `cancelled_at`, `cancelled_by_user_id`, `cancellation_reason`, `ready_ticket_items_at_cancellation`, and `served_ticket_items_at_cancellation`.
- `order_status_logs.reason` stores the required cancellation reason; `order_status_logs.metadata` stores ready/served warning counts.
- Full inventory includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `branch_users`, `area_node_waiters`, `invitations`, `branch_opening_hours`, `area_nodes`, `service_points`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_sessions`, `table_session_service_points`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No new routes were added in Prompt 121.
- Waiter dashboard route remains `GET /restaurant/waiter/dashboard`.
- Waiter table detail route remains `GET /restaurant/waiter/tables/{tableSession}` and now contains the cancellation form for eligible orders.
- Kitchen route remains `GET /restaurant/kitchen`.
- Bar route remains `GET /restaurant/bar`.
- Public QR route remains `GET /q/{token}` and still exposes no branch, service point, table, session, or guest IDs.

Current Livewire components and actions:

- `App\Actions\Orders\ChangeOrderStatusAction` now requires a cancellation reason, allows director/shift-manager cancellation, stores order metadata, writes status log metadata, and writes audit log details.
- `App\Actions\Waiter\BuildWaiterTableDetailAction` exposes `draft.can_cancel`, `draft.cancellation_reason`, and `draft.has_ready_or_served_warning`.
- `App\Livewire\Waiter\TableDetail` exposes `orderCancellationReason` and `cancelOrder()`.
- `resources/views/livewire/waiter/table-detail.blade.php` shows cancellation reason input, ready/served warning, and cancelled-order reason.
- `App\Livewire\PublicQr\OrderStatuses` and its Blade view show guest-facing cancelled order state and reason.
- `App\Actions\Departments\BuildDepartmentDashboardAction` hides cancelled-order tickets.
- `App\Actions\Departments\UpdateDepartmentTicketItemStatusAction` blocks kitchen/bar item status changes for cancelled orders.
- `App\Actions\Waiter\MarkKitchenTicketItemServedAction` blocks serving cancelled-order items.

Mandatory business rules:

- Orders are never deleted for cancellation.
- Cancellation must require a human-readable reason.
- Cancelled orders must keep history in `orders`, `order_items`, `order_status_logs`, and `audit_logs`.
- Guests must see that their order was cancelled and why.
- Kitchen/bar must not keep working on cancelled orders.
- If any kitchen/bar positions are already ready or served, waiter UI must show a warning before cancellation.
- Every normal order still requires waiter confirmation before kitchen/bar dispatch.
- Permanent QR identity is unrelated to order cancellation and must not change.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Keep cancellation checks selected, eager-loaded, branch-scoped, and bounded for SQLite.
- Do not query from Blade.
- No new infrastructure is required for cancellation.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings in app code, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 121 notes:

- Focused coverage: `tests/Feature/OrderCancellationTest.php`.
- Related regression coverage: kitchen screen, kitchen dispatch, waiter draft review, and waiter table detail tests.
- Verification included a red test first for missing cancellation UI/reason/guards, then green focused and regression runs.
- Post-feature daily memory update refreshed README, CHANGELOG, AI context, smoke checklist, and next-step notes without adding product behavior.
- Keep cancellation as a status/history action, not a destructive delete.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 120 Manual Service Charge And Tips - 2026-06-05

Prompt 120 added manual service charge and tips to the existing offline payment flow. It did not add online payments, tax logic, payment providers, new routes, Redis, WebSockets, S3, Docker, paid services, or external APIs.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Public guest QR flow, branch public profile, opening hours, temporary branch closed mode, menu schedules, multiple active menus, service modes, bulk service point creation, permanent QR generation/printing/lookup, branch service point filtering/board, waiter zone assignments, manual waiter order entry, duplicate guest-name handling, session inactivity cleanup, active session transfer, merged table sessions, and waiter-side schedule checks.
- Guest sessions, join requests, invite links, shared draft cart, ready status, waiter handoff, waiter review/edit/confirm/reject, real order snapshots, kitchen/bar tickets, ready/served handoff, waiter calls, bill requests, manual payments, split bill by guests, session close, dashboard analytics, audit logs, CSV exports, localization, currency settings, local media, superadmin controls, and shared-hosting deployment notes.
- Prompt 120: branch settings can store `service_charge_percent` alongside `service_charge_enabled` and `tips_enabled`.
- Prompt 120: waiter/cashier payment summary shows confirmed subtotal, service charge, recorded tips, paid total, and remaining balance.
- Prompt 120: manual payment history stores covered subtotal, service charge percent, service charge amount, tips amount, and total paid snapshot values.

Current tables:

- New columns in `branch_settings`: `service_charge_percent`.
- New columns in `manual_payments`: `covered_subtotal_amount`, `service_charge_percent`, `service_charge_amount`, `tips_amount`.
- Existing affected tables: `table_sessions`, `table_session_guests`, `orders`, `order_items`, `manual_payments`, `branch_settings`, and `service_points`.
- `manual_payments.amount` stores total collected amount, including tips when entered.
- `manual_payments.covered_subtotal_amount` and `manual_payments.service_charge_amount` store the bill-covering snapshot; tips are extra and do not reduce the required remaining bill.
- Full inventory now includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `branch_users`, `area_node_waiters`, `invitations`, `branch_opening_hours`, `area_nodes`, `service_points`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_sessions`, `table_session_service_points`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No new routes were added in Prompt 120.
- Branch settings route remains under `GET /organizations/{organization}/brands/{brand}/branches/{branch}/settings`.
- Waiter dashboard route remains `GET /restaurant/waiter/dashboard`.
- Waiter table detail route remains `GET /restaurant/waiter/tables/{tableSession}` and contains the manual service charge/tips UI.
- Public QR route remains `GET /q/{token}` and still exposes no branch, service point, table, session, or guest IDs.

Current Livewire components and actions:

- `App\Livewire\Organizations\Brands\Branches\Settings` exposes `serviceChargePercent` and validates it from 0 to 100 with up to two decimals.
- `App\Actions\Branches\UpdateBranchSettingsAction` normalizes `service_charge_percent` and keeps branch cache invalidation through existing settings save behavior.
- `App\Actions\Payments\BuildManualPaymentSummaryAction` calculates confirmed subtotal, optional service charge, paid covered subtotal, paid service charge, recorded tips, remaining bill total, and per-guest balances including service charge.
- `App\Actions\Payments\RecordManualPaymentAction` records table or guest payment snapshots and rejects positive tips when branch tips are disabled.
- `App\Actions\Waiter\BuildWaiterTableDetailAction` forwards the new payment summary fields to the waiter table detail payload.
- `App\Livewire\Waiter\TableDetail` exposes `tipsAmount` and resets it after successful table or guest payment.
- `resources/views/livewire/waiter/table-detail.blade.php` shows service charge, tips input, tips total, and payment history snapshot rows.
- `resources/views/livewire/organizations/brands/branches/settings.blade.php` shows the service charge percent field and manual/offline billing note.

Mandatory business rules:

- Only manual offline payments are supported.
- Online payments, Stripe, PayPal, external acquiring, tax logic, and paid payment services remain forbidden.
- `service_charge_enabled` controls whether the configured percent is added to confirmed order subtotals.
- `tips_enabled` controls whether a manual tips amount can be recorded.
- Tips are optional extras and must not reduce the required subtotal/service-charge bill balance.
- Payment history must preserve service charge and tips snapshots even if branch settings change later.
- Open drafts cannot be paid; every order still requires waiter confirmation before becoming payable confirmed order history.
- Permanent QR identity is unrelated to payments and must not change.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Keep payment queries selected, eager-loaded, branch-scoped, and bounded for SQLite.
- Do not query from Blade.
- No new infrastructure is required for manual service charge and tips.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings in app code, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 120 notes:

- Focused coverage: `tests/Feature/BranchSettingsTest.php` and `tests/Feature/ManualPaymentTest.php`.
- Related verification: table-session close, vertical slice, audit log, data export, and basic analytics tests.
- Verification included a red test first for missing service charge/tips snapshot behavior, then green focused and regression runs.
- Post-feature daily memory update refreshed README, CHANGELOG, AI context, smoke checklist, and next-step notes without adding product behavior.
- Keep payments manual-only until a future prompt explicitly asks for online payment, tax, or allocation features.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 119 Split Bill By Guests - 2026-06-05

Prompt 119 added explicit split-bill handling for manual payments. It did not add online payments, payment providers, shared allocation rules, new routes, new tables, Redis, WebSockets, S3, Docker, paid services, or external APIs.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Public guest QR flow, branch public profile, opening hours, temporary branch closed mode, menu schedules, multiple active menus, service modes, bulk service point creation, permanent QR generation/printing/lookup, branch service point filtering/board, waiter zone assignments, manual waiter order entry, duplicate guest-name handling, session inactivity cleanup, active session transfer, merged table sessions, and waiter-side schedule checks.
- Guest sessions, join requests, invite links, shared draft cart, ready status, waiter handoff, waiter review/edit/confirm/reject, real order snapshots, kitchen/bar tickets, ready/served handoff, waiter calls, bill requests, manual payments, session close, dashboard analytics, audit logs, CSV exports, localization, currency settings, local media, superadmin controls, and shared-hosting deployment notes.
- Prompt 119: waiter table detail now shows split bill by guests, unpaid guests, and manual whole-table or per-guest payment actions.
- Prompt 119: payment totals now use confirmed `order_items` as the source of truth for guest balances and the table bill.

Current tables:

- No new table or column was added in Prompt 119.
- Existing affected tables: `table_sessions`, `table_session_guests`, `orders`, `order_items`, `manual_payments`, and `service_points`.
- `manual_payments.table_session_guest_id` stores the paid guest for individual manual payments.
- Full inventory now includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `branch_users`, `area_node_waiters`, `invitations`, `branch_opening_hours`, `area_nodes`, `service_points`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_sessions`, `table_session_service_points`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No new routes were added in Prompt 119.
- Waiter dashboard route remains `GET /restaurant/waiter/dashboard`.
- Waiter table detail route remains `GET /restaurant/waiter/tables/{tableSession}` and contains the split-bill UI.
- Public QR route remains `GET /q/{token}` and still exposes no branch, service point, table, session, or guest IDs.

Current Livewire components and actions:

- `App\Actions\Payments\BuildManualPaymentSummaryAction` now calculates confirmed totals from confirmed `order_items`, builds per-guest balances, and returns `unpaid_guests` / `unpaid_guests_count`.
- `App\Actions\Waiter\BuildWaiterTableDetailAction` now forwards unpaid guest payload and aligns waiter confirmed totals with confirmed item sums.
- `App\Livewire\Waiter\TableDetail` continues to use the existing `recordTablePayment()` and `recordGuestPayment()` methods.
- `resources/views/livewire/waiter/table-detail.blade.php` now shows unpaid guests and all-paid state inside the manual payments block.

Mandatory business rules:

- Only manual offline payments are supported.
- Online payments, Stripe, PayPal, external acquiring, and paid payment services remain forbidden.
- Each guest's bill is the sum of that guest's confirmed `order_items`.
- The table bill is the sum of confirmed guest items.
- Staff can mark the whole table paid or a specific unpaid guest paid.
- Guest-scoped payment records must store `table_session_guest_id`.
- If all guest/table balances are paid, the table session can become `paid`; closing remains a separate table-session action.
- Open drafts cannot be paid; every order still requires waiter confirmation before becoming payable confirmed order history.
- Permanent QR identity is unrelated to payments and must not change.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Keep payment queries selected, eager-loaded, branch-scoped, and bounded for SQLite.
- Do not query from Blade.
- No new infrastructure is required for split bill.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings in app code, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 119 notes:

- Focused coverage: `tests/Feature/ManualPaymentTest.php`.
- Related verification: table-session close and vertical slice flow tests.
- Verification included a red test first for missing unpaid guest payload and item-based confirmed totals, then green focused regression.
- Post-feature daily memory update refreshed README, CHANGELOG, AI context, smoke checklist, and next-step notes without adding product behavior.
- Keep split bill manual-only until a future prompt explicitly asks for online payments or allocation rules.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 118 Merged Table Sessions - 2026-06-05

Prompt 118 added basic merged-table-session support. It did not add new routes, roles, permissions, QR regeneration, unmerge UI, billing changes, delivery/payment integrations, Redis, WebSockets, S3, Docker, paid services, or external APIs.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block guest ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 110: the branch `Столы и места` page can search/filter/paginate service points without loading every row.
- Prompt 111: the same page has a simple visual board grouped by zone.
- Prompt 112: branch staff managers can assign fixed `waiter` users to active branch `area_nodes`; waiter dashboard can filter to `My zones` or `All zones`.
- Prompt 113: a waiter can manually add positions to an active table and still confirm through the normal order snapshot flow.
- Prompt 114: duplicate guest display names show a warning and suggestions before join request creation.
- Prompt 116: stale empty pending sessions can be cancelled safely, active sessions show waiter inactivity warnings, and cleanup can run by scheduler or manual action.
- Prompt 117: staff with `view_orders` or `confirm_orders` can transfer an active session to another active free service point without changing QR codes.
- Prompt 118: staff with `view_orders` or `confirm_orders` can link additional free service points to one active table session; linked QR scans resolve to the same session.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.

Current tables:

- New table: `table_session_service_points`.
- `table_session_service_points` stores `table_session_id`, `service_point_id`, nullable guarded `active_service_point_id`, `linked_by_user_id`, `linked_at`, `unlinked_by_user_id`, `unlinked_at`, and timestamps.
- `active_service_point_id` is unique and nullable so one service point can be actively linked to at most one session, then reused after `unlinked_at` is set.
- Existing affected tables: `table_sessions`, `service_points`, `qr_codes`, `table_session_guests`, `table_session_join_requests`, `audit_logs`, `draft_orders`, `orders`, and `manual_payments`.
- Closing a merged session sets linked records `unlinked_at`/`unlinked_by_user_id` and frees all linked service points.
- Permanent QR rows stay attached to their original `qr_codes.service_point_id` and are not changed by merge.
- Full inventory now includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `branch_users`, `area_node_waiters`, `invitations`, `branch_opening_hours`, `area_nodes`, `service_points`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_sessions`, `table_session_service_points`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No new routes were added in Prompt 118.
- Waiter dashboard route remains `GET /restaurant/waiter/dashboard`.
- Waiter table detail route remains `GET /restaurant/waiter/tables/{tableSession}`.
- Public QR route remains `GET /q/{token}` and still exposes no branch, service point, table, or guest IDs.
- Invite links still use the existing `/q/{token}?invite={token}` route.

Current Livewire components and actions:

- New action: `App\Actions\TableSessions\MergeTableSessionServicePointAction`.
- New model/factory: `App\Models\TableSessionServicePoint` and `Database\Factories\TableSessionServicePointFactory`.
- `App\Models\TableSession` now has `servicePointLinks()` and `activeServicePointLinks()`.
- `App\Models\ServicePoint` now has `tableSessionServicePointLinks()` and `activeTableSessionServicePointLinks()`.
- `App\Actions\Waiter\BuildWaiterTableDetailAction` now returns `linked_service_points` and `merge.can_merge` with bounded free service point options.
- `App\Livewire\Waiter\TableDetail` exposes `mergeTargetServicePointId`, `mergeFeedbackMessage`, and `mergeServicePoint()`.
- `resources/views/livewire/waiter/table-detail.blade.php` shows linked places and the `Объединить столы` block.
- `App\Actions\TableSessions\OpenTableSessionForServicePointAction` reuses an active linked session instead of creating a duplicate session from a linked occupied QR/place.
- `App\Actions\TableSessions\CloseTableSessionAction` frees linked service points and deactivates links when a session closes.
- `App\Actions\TableSessions\CreateGuestPendingTableSessionAction` resolves an active linked service point to the main active session.
- `App\Livewire\PublicQr\Show` resolves guest-name conflict checks and entry landing data through the linked active session.
- `App\Livewire\Organizations\Brands\Branches\ServicePoints\Index` now eager-loads active service point links so linked occupied places do not show as openable.

Mandatory business rules:

- One physical service point still owns one permanent QR; merge must not change, reissue, disable, revoke, or regenerate QR codes.
- Merge links additional physical service points to the active session; the main `table_sessions.service_point_id` remains the main service point.
- Linked service points become `occupied` while linked.
- Guests scanning any linked QR should reach the same active session and go through the normal active-guest join approval flow.
- Closing a merged session frees the main and linked service points and does not touch QR identity.
- Only active table sessions can be merged in this step.
- Target service point must be active, free, same-branch, without an open direct session, and without another active merge link.
- Orders still require waiter confirmation before kitchen/bar dispatch.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Keep merge queries selected, eager-loaded, branch-scoped, bounded, and index-backed for SQLite.
- Do not query from Blade.
- No new infrastructure is required for merge.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 118 notes:

- Focused coverage: `tests/Feature/TableSessionMergeTest.php`.
- Related verification: table-session transfer, waiter open table, table close, guest created session, and guest table shell tests.
- Verification run included SQLite migration status, route list, database driver config checks, and HTTP smoke for `/`, `/login`, and waiter dashboard redirect.
- Post-feature daily memory update refreshed README, CHANGELOG, AI context, smoke checklist, and next-step notes without adding product behavior.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 117 Active Table Session Transfer - 2026-06-04

Prompt 117 added active table-session transfer to another free service point. It did not add routes, migrations, roles, permissions, guest accounts, direct kitchen/bar dispatch, online payments, Redis, WebSockets, S3, Docker, paid services, or external APIs.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block guest ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 110: the branch `Столы и места` page can search and filter service points and paginate results without loading every service point at once.
- Prompt 111: the same page has a simple visual board that groups the currently loaded service point page by zone.
- Prompt 112: branch staff managers can assign fixed `waiter` users to active branch `area_nodes`; waiter dashboard can filter to `My zones` or show `All zones`.
- Prompt 113: a waiter can manually add positions to an active table, choose an active guest or create a manual guest name, create/reuse a waiter-review draft, and confirm it through the normal order snapshot flow.
- Prompt 114: a guest entering a duplicate display name sees a warning and suggestions before a join request is created, while still being allowed to continue with the same display name intentionally.
- Prompt 116: stale empty pending sessions can be cancelled safely, active sessions show waiter warnings after inactivity, and cleanup can run by scheduler or manual admin/superadmin action.
- Prompt 117: staff with `view_orders` or `confirm_orders` branch access can transfer an active table session to another active free service point in the same branch without changing QR codes.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.

Current tables:

- No new table or column was added in Prompt 117.
- Existing affected tables: `table_sessions`, `service_points`, `qr_codes`, `table_session_guests`, `draft_orders`, `orders`, `manual_payments`, and `audit_logs`.
- Transfer updates `table_sessions.service_point_id`; `TableSession::booted()` keeps `active_service_point_id` pointed at the new service point for active sessions.
- Transfer stores a short history in `table_sessions.metadata.transfers` and `table_sessions.metadata.last_transfer`.
- Transfer writes `audit_logs.action = table_session_transferred` with old/new service point data.
- Permanent QR rows stay attached to their original `service_point_id` and are not changed by transfer.
- Full inventory still includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `branch_users`, `area_node_waiters`, `invitations`, `branch_opening_hours`, `area_nodes`, `service_points`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No new routes were added in Prompt 117.
- Waiter dashboard route remains `GET /restaurant/waiter/dashboard`.
- Waiter table detail route remains `GET /restaurant/waiter/tables/{tableSession}`.
- Public QR route remains `GET /q/{token}` and still exposes no branch, service point, table, or guest IDs.
- Invite links still use the existing `/q/{token}?invite={token}` route.

Current Livewire components and actions:

- New action: `App\Actions\TableSessions\TransferTableSessionAction`.
- `App\Actions\Waiter\BuildWaiterTableDetailAction` now returns `transfer.can_transfer` and a bounded list of free active service points in the same branch.
- `App\Livewire\Waiter\TableDetail` exposes `transferTargetServicePointId`, `transferFeedbackMessage`, and `transferTableSession()`.
- `resources/views/livewire/waiter/table-detail.blade.php` shows the `Перенести стол` block when transfer is allowed.
- `App\Livewire\PublicQr\Show` now restores existing guests and invite-link join flows by branch/session token rather than by the original QR service point, then updates the displayed guest landing service point from the current table session.

Mandatory business rules:

- One physical service point still owns one permanent QR; transfer must not change, reissue, disable, revoke, or regenerate QR codes.
- Transfer changes the active session's current `service_point_id`, not the QR identity of either service point.
- Only active table sessions can be transferred in this step.
- Target service point must be active, free, in the same branch, and without a pending/active/payment open session.
- Old service point becomes `free`; new service point becomes `occupied`.
- Existing guests, drafts, orders, payments, kitchen tickets, and audit history stay preserved under the same table session.
- Already-entered guests should see the current transferred place after refresh; fresh scans of a physical QR still start from that QR's physical service point.
- Orders still require waiter confirmation before kitchen/bar dispatch.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Keep transfer queries selected, eager-loaded, branch-scoped, and bounded for SQLite.
- Do not query from Blade.
- No new infrastructure is required for transfer.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 117 notes:

- Focused coverage: `tests/Feature/TableSessionTransferTest.php`.
- Related verification: table-session close, guest table shell, audit log, and waiter open-table tests.
- Verification run included SQLite migration status, route list, database driver config checks, and HTTP smoke for `/`, `/login`, and waiter dashboard redirect.
- Post-feature daily memory update refreshed README, CHANGELOG, AI context, smoke checklist, and next-step notes without adding product behavior.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 116 Session Inactivity Cleanup - 2026-06-04

Prompt 116 added safe inactivity cleanup for table sessions. It did not add new routes, guest accounts, roles, permissions, online payments, Redis, WebSockets, S3, Docker, paid services, external APIs, or automatic active-table closing.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block guest ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 110: the branch `Столы и места` page can search and filter service points and paginate results without loading every service point at once.
- Prompt 111: the same page has a simple visual board that groups the currently loaded service point page by zone.
- Prompt 112: branch staff managers can assign fixed `waiter` users to active branch `area_nodes`; waiter dashboard can filter to `My zones` or show `All zones`.
- Prompt 113: a waiter can manually add positions to an active table, choose an active guest or create a manual guest name, create/reuse a waiter-review draft, and confirm it through the normal order snapshot flow.
- Prompt 114: a guest entering a duplicate display name sees a warning and suggestions before a join request is created, while still being allowed to continue with the same display name intentionally.
- Prompt 116: stale empty pending sessions can be cancelled safely, active sessions show waiter warnings after inactivity, and cleanup can run by scheduler or manual admin/superadmin action.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.

Current tables:

- New columns on `branch_settings`: `inactivity_warning_minutes` and `pending_session_expire_minutes`.
- New SQLite hot-path index on `table_sessions`: `table_sessions_branch_status_updated_idx` for branch/status/updated cleanup scans.
- Cleanup reuses existing `table_sessions.status = cancelled` and stores metadata under `table_sessions.metadata.cleanup`.
- Existing affected tables: `branch_settings`, `table_sessions`, `draft_orders`, `orders`, and `service_points`.
- Full inventory still includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `branch_users`, `area_node_waiters`, `invitations`, `branch_opening_hours`, `area_nodes`, `service_points`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No new HTTP routes were added in Prompt 116.
- New Artisan command: `php artisan table-sessions:cleanup-inactive {--branch=}`.
- Scheduler entry in `routes/console.php`: `table-sessions:cleanup-inactive` every 15 minutes with `withoutOverlapping(10)`.
- Existing waiter dashboard route remains `GET /restaurant/waiter/dashboard`.
- Existing branch settings route remains `GET /organizations/{organization}/brands/{brand}/branches/{branch}/settings`.
- Existing superadmin dashboard route remains protected by superadmin access.

Current Livewire components and actions:

- `App\Actions\TableSessions\BuildTableSessionInactivityStateAction` computes minutes inactive, active-session warning state, and pending-session expiry state from branch settings.
- `App\Actions\TableSessions\CleanupInactiveTableSessionsAction` checks open pending/active sessions, cancels stale empty pending sessions, skips unpaid orders/drafts, and counts active warnings.
- `App\Console\Commands\CleanupInactiveTableSessionsCommand` exposes manual/cron CLI cleanup.
- `App\Livewire\Organizations\Brands\Branches\Settings` now edits inactivity thresholds and can run cleanup for one branch.
- `App\Livewire\Superadmin\Dashboard` can run cleanup globally.
- `App\Actions\Waiter\BuildWaiterDashboardAction` includes inactivity state in session payloads.
- `resources/views/livewire/waiter/dashboard.blade.php` shows `No activity` warnings on active table cards.

Mandatory business rules:

- Do not automatically close active table sessions.
- Do not cancel/close any session that has unpaid orders.
- Pending cleanup is allowed only for stale empty pending sessions with no draft orders and no orders.
- Cleanup must preserve permanent QR identity; closing/cancelling a session must not reissue QR.
- Guests remain account-free and guest tokens remain independent of user auth.
- Orders still require waiter confirmation before kitchen/bar dispatch.
- Live waiter updates remain polling-based.

Shared-hosting constraints:

- Use Laravel scheduler only through optional shared-hosting cron running `php artisan schedule:run`.
- If cron is unavailable, use branch settings or superadmin manual cleanup buttons.
- Keep cleanup bounded and indexed for SQLite; do not scan public URLs or guest tokens.
- Keep database cache locks for scheduler overlap prevention; do not introduce Redis.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 116 notes:

- Focused coverage: `tests/Feature/SessionInactivityCleanupTest.php`.
- Verification run included focused inactivity cleanup tests, SQLite migration status, route list, database driver config checks, and HTTP smoke checks.
- Manual follow-up: on shared hosting, add a cron entry for `php artisan schedule:run` if available; otherwise run cleanup from branch settings or superadmin dashboard.
- Post-feature daily memory update refreshed README, CHANGELOG, AI context, smoke checklist, deployment notes, and next-step notes without adding product behavior.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 114 Guest Name Conflict Handling - 2026-06-04

Prompt 114 added duplicate guest-name handling to the existing QR / invite entry flow. It did not add routes, migrations, tables, guest accounts, roles, permissions, Redis, WebSockets, S3, Docker, paid services, or external APIs.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block guest ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 110: the branch `Столы и места` page can search and filter service points and paginate results without loading every service point at once.
- Prompt 111: the same page has a simple visual board that groups the currently loaded service point page by zone.
- Prompt 112: branch staff managers can assign fixed `waiter` users to active branch `area_nodes`; waiter dashboard can filter to `My zones` or show `All zones`.
- Prompt 113: a waiter can manually add positions to an active table, choose an active guest or create a manual guest name, create/reuse a waiter-review draft, and confirm it through the normal order snapshot flow.
- Prompt 114: a guest entering a duplicate display name sees a warning and suggestions before a join request is created, while still being allowed to continue with the same display name intentionally.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.

Current tables:

- No new table or column was added in Prompt 114.
- Existing affected tables: `table_sessions`, `table_session_guests`, `table_session_join_requests`, and `qr_codes`.
- Duplicate-name handling uses `table_session_guests.guest_name` as the display name. Internal identity remains unique through `table_session_guests.id` and `guest_token`.
- Full inventory still includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `branch_users`, `area_node_waiters`, `invitations`, `branch_opening_hours`, `area_nodes`, `service_points`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No new routes were added in Prompt 114.
- Public QR route remains `GET /q/{token}` and still exposes no branch, service point, table, or guest IDs.
- Invite links still use the existing `/q/{token}?invite={token}` route.

Current Livewire components and actions:

- `App\Livewire\PublicQr\Show` now tracks `hasGuestNameConflict`, `guestNameConflictExistingName`, `guestNameSuggestions`, and `allowDuplicateGuestName`.
- The same component exposes `chooseGuestNameSuggestion()` and `continueWithDuplicateGuestName()` for the QR landing form.
- The public QR Blade view now shows a warning block with suggested display names before a duplicate join request is created.
- `App\Livewire\PublicQr\TableGuests` still sorts guests by `guest_name` and `id`.
- `CreateGuestPendingTableSessionAction` and `CreateTableSessionJoinRequestAction` remain the backend creation points.

Mandatory business rules:

- A guest is not a registered `user`.
- Guests keep unique internal identity through `table_session_guests.id` and non-guessable `guest_token`.
- Duplicate display names are not fully forbidden.
- When an active guest already has the same display name, the next guest should see a warning before creating a join request.
- Suggested names must be understandable, for example `Анна 2` and `Анна К.`.
- Guests must continue sorting by display name and stable `id`.
- Active guests still approve new guests through the existing join-request flow.
- QR identity, invite-token identity, table-session close rules, draft order rules, waiter confirmation, and kitchen/bar dispatch rules are unchanged.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Keep duplicate-name lookup bounded to the current table session's active guests.
- Do not query from Blade and do not load unrelated guest/session history.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 114 notes:

- Focused coverage: `tests/Feature/GuestCreatedPendingSessionTest.php`.
- Verification run included duplicate-name tests, full guest-created session tests, guest invite share link tests, guest join approval tests, public QR route tests, SQLite migration status, route list, database driver config checks, and HTTP smoke for `/`, `/login`, and waiter dashboard redirect.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 113 Manual Waiter Order Entry - 2026-06-04

Prompt 113 added manual waiter order entry on the existing waiter table detail screen. It did not add routes, migrations, roles, permissions, guest accounts, direct kitchen/bar dispatch, WebSockets, Redis, S3, Docker, paid services, or external APIs.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block guest ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 110: the branch `Столы и места` page can search and filter service points and paginate results without loading every service point at once.
- Prompt 111: the same page has a simple visual board that groups the currently loaded service point page by zone.
- Prompt 112: branch staff managers can assign fixed `waiter` users to active branch `area_nodes`; waiter dashboard can filter to `My zones` or show `All zones`.
- Prompt 113: a waiter can manually add positions to an active table, choose an active guest or create a manual guest name, create/reuse a waiter-review draft, and confirm it through the normal order snapshot flow.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.

Current tables:

- No new table or column was added in Prompt 113.
- Existing affected tables: `table_sessions`, `table_session_guests`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `menu_items`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`.
- Manual guests are stored in `table_session_guests` with `metadata.source = waiter_manual_entry` and a non-guessable `guest_token`; they are not `users`.
- Full inventory still includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `branch_users`, `area_node_waiters`, `invitations`, `branch_opening_hours`, `area_nodes`, `service_points`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, and `audit_logs`.

Current routes:

- No new routes were added in Prompt 113.
- Waiter dashboard route remains `GET /restaurant/waiter/dashboard`.
- Waiter table detail route remains `GET /restaurant/waiter/tables/{tableSession}`.
- Public QR route remains `GET /q/{token}` and still exposes no branch, service point, or table IDs.

Current Livewire components and actions:

- `App\Livewire\Waiter\TableDetail` now exposes `manualGuestName` and uses `AddManualWaiterOrderItemAction` for the add-position flow.
- The waiter table detail Blade view shows `Manual waiter order` when an active table has no editable draft, and `Edit draft` when a waiter-review/sent draft already exists.
- `App\Actions\Waiter\AddManualWaiterOrderItemAction` creates/reuses a waiter-review draft, creates a manual active guest when needed, then delegates actual item creation to the existing waiter draft item action.
- `App\Actions\Waiter\BuildWaiterTableDetailAction` now returns `manual_order.can_add` for active sessions where the user has `confirm_orders` or `edit_pending_orders` in an accessible branch.

Mandatory business rules:

- Manual waiter entry is only for active `table_sessions`.
- A manual guest is a `table_session_guest`, not a normal registered `user`.
- Manual guest tokens must remain non-guessable.
- A waiter can choose an existing active guest or type a new guest name.
- Manual items enter the existing draft/order flow and use the same snapshot logic for menu item name, unit price, modifiers, comments, and totals.
- The draft still requires waiter confirmation before a real `order` exists.
- Kitchen/bar still sees nothing until the confirmed order is explicitly dispatched.
- Guests already in the session should see waiter-entered draft changes through existing Livewire polling.
- QR identity and table-session close rules are unchanged.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Keep waiter table polling bounded by selected/eager-loaded Eloquent queries; do not query from Blade.
- No new infrastructure is required for waiter manual entry.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 113 notes:

- New action: `App\Actions\Waiter\AddManualWaiterOrderItemAction`.
- Focused coverage: `tests/Feature/WaiterDraftEditingTest.php`.
- Verification run included focused waiter draft tests, waiter detail/review/repeat order tests, SQLite migration status, route list, database driver config checks, and HTTP smoke for `/`, `/login`, and waiter dashboard redirect.
- Post-feature daily memory docs were refreshed after the Prompt 113 feature commit without adding product behavior.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 112 Waiter Zone Assignments - 2026-06-04

Prompt 112 added branch area-node assignments for waiters. It did not add routes, roles, permissions, QR changes, maps, canvas floor plans, WebSockets, Redis, S3, Docker, or paid services.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 110: the branch `Столы и места` page can search by service point name, display number, stable internal code, or active QR short code, filter by current branch, zone, type, status, active state, and active QR presence, and paginate results without loading every service point at once.
- Prompt 111: the same page has a simple visual board that groups the currently loaded service point page by zone, shows service point cards with type icons/status/QR/session badges, and exposes existing quick actions for opening a table, QR, and edit.
- Prompt 112: branch staff managers can assign fixed `waiter` users to active branch `area_nodes`; waiter dashboard can filter to `My zones` or show `All zones`.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.

Current tables:

- New table: `area_node_waiters` with `organization_id`, `branch_id`, `area_node_id`, `user_id`, `assigned_by_user_id`, `assigned_at`, timestamps, unique `area_node_id/user_id`, and SQLite-friendly indexes for organization/user and branch/user lookups.
- Existing affected tables: `branch_users`, `area_nodes`, `service_points`, `table_sessions`, `waiter_calls`, `draft_orders`, `kitchen_ticket_items`.
- Full inventory still includes: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`, `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_settings`, `invitations`, `branch_opening_hours`, `qr_codes`, `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`, `table_session_guests`, `table_session_join_requests`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `manual_payments`, and `audit_logs`.

Current routes:

- No new routes were added in Prompt 112.
- Branch staff route remains `GET /organizations/{organization}/brands/{brand}/branches/{branch}/staff`.
- Waiter dashboard route remains `GET /restaurant/waiter/dashboard`.
- Waiter table detail route remains `GET /restaurant/waiter/tables/{tableSession}`.

Current Livewire components:

- `App\Livewire\Organizations\Brands\Branches\Staff\Index` now loads active branch `areaNodes`, keeps `areaAssignments`, and saves waiter-zone assignments through `saveAreaAssignments()`.
- `App\Livewire\Waiter\Dashboard` now keeps `zoneScope` and can switch between `My zones` and `All zones`.
- `App\Actions\Waiter\BuildWaiterDashboardAction` now applies zone assignment filtering before payload creation so service points, sessions, sent drafts, waiter calls, bill requests, and ready items stay consistent.
- `App\Livewire\Waiter\TableDetail` is unchanged; review, edit, confirm, payment, served, and close actions stay there.

Mandatory business rules:

- Only users with `manage_staff` in the organization context can change branch waiter-zone assignments.
- Assignments are only for fixed `waiter` staff members on the branch staff page.
- Superadmin bypass remains platform-wide and does not get narrowed by waiter-zone assignments.
- Active `branch_users` assignments still narrow branch-level access first; `area_node_waiters` only narrows the waiter dashboard view inside accessible branches.
- If a waiter has no assigned zones for a branch, the dashboard can show all accessible places with a hint.
- `My zones` must filter urgent work too, not only the service point cards.
- QR identity and table-session rules are unchanged.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Keep waiter dashboard polling bounded by selected/eager-loaded Eloquent queries; do not query from Blade.
- Use the new indexes on `area_node_waiters`; do not add Redis, WebSockets, search services, maps, or heavy UI libraries.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 112 notes:

- Pivot/model: `App\Models\AreaNodeWaiter`.
- UI labels: `Waiter zones`, `My zones`, `All zones`.
- Focused coverage: `tests/Feature/WaiterZoneAssignmentsTest.php`, plus existing `StaffManagementUiTest` and `WaiterDashboardTest`.
- Verification run included SQLite migration, config checks for database drivers, full route list, HTTP smoke for `/`, `/login`, and protected waiter dashboard redirect.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 111 Simple Visual Floor Board - 2026-06-04

Prompt 111 added a simple visual floor board to the existing branch `Столы и места` service point page. No database schema, routes, packages, canvas editor, drag-and-drop editor, or heavy JavaScript were added.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 110: the branch `Столы и места` page can search by service point name, display number, stable internal code, or active QR short code, filter by current branch, zone, type, status, active state, and active QR presence, and paginate results without loading every service point at once.
- Prompt 111: the same page now has a simple visual board that groups the currently loaded service point page by zone, shows service point cards with type icons/status/QR/session badges, and exposes existing quick actions for opening a table, QR, and edit.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.

Current tables:

- No new tables or columns were added in Prompt 111.
- The affected tables are existing `service_points`, `area_nodes`, `qr_codes`, and `table_sessions`.
- The floor board reuses the existing paginated service point query and eager-loaded `areaNode`, `activeQrCode`, and `activeTableSession` relationships.
- The full current table inventory remains unchanged from the Prompt 109 section below.

Current routes:

- No new route was added in Prompt 111.
- Branch service point admin route remains `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points`.
- The visual board lives inside the existing branch route and inherits its organization/brand/branch access checks.

Current Livewire components:

- `App\Livewire\Organizations\Brands\Branches\ServicePoints\Index` now includes computed `floorBoardSections` and `floorBoardServicePointCount` values built from the current paginated `servicePoints` collection.
- The component also has `startEditingFromBoard()` so board edit actions focus the existing edit form through a stable service point `internal_code` search.
- Single creation, bulk creation, search/filter pagination, status changes, table opening, QR generation, and inline QR preview remain in the same component.
- `App\Livewire\QrCodes\ShortCodeLookup` remains the separate global QR lookup page for users with `generate_qr`.

Mandatory business rules:

- The board must stay scoped to the route branch and organization access; it must not become a cross-branch editing screen.
- The board must not show technical IDs in the UI.
- QR identity remains stable during board display, search, filtering, pagination, service point rename, service point move, and ordinary edits.
- Quick actions reuse existing permissions: CRUD/edit requires `manage_service_points`; QR requires `generate_qr`; table opening requires `view_orders` or `confirm_orders`.
- The board must not create QR automatically and must not reissue QR during ordinary service point edits.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Do not load every service point in a large branch for the board. It intentionally uses the current paginated service point collection.
- Use Eloquent relationships and existing SQLite indexes; do not add search services or heavy realtime infrastructure.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, canvas floor-plan editors, drag-and-drop floor-plan editors, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 111 notes:

- Board UI label: `Визуальный зал`.
- Board source: current branch service point page after search/filter/pagination, not a separate all-rows query.
- Card actions: `Открыть стол`, `Показать QR` or `Создать QR`, and `Изменить`.
- Focused coverage: `tests/Feature/ServicePointCrudTest.php`.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 110 Service Point Search Filters - 2026-06-04

Prompt 110 added branch admin search, filters, and pagination for `service_points`. No database schema, packages, external search services, QR changes, or infrastructure were added.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 110: the branch `Столы и места` page can search by service point name, display number, stable internal code, or active QR short code, filter by current branch, zone, type, status, active state, and active QR presence, and paginate results without loading every service point at once.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.

Current tables:

- No new tables or columns were added in Prompt 110.
- The affected tables are existing `service_points`, `area_nodes`, `qr_codes`, and `table_sessions`.
- Existing hot-path SQLite indexes cover branch-scoped service point filtering (`branch_id` with area/status/type/display/internal/name variants) and `qr_codes.short_code` remains unique for short-code search.
- The full current table inventory remains unchanged from the Prompt 109 section below.

Current routes:

- No new route was added in Prompt 110.
- Branch service point admin route remains `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points`.
- The branch filter is the current route branch, because create/edit/status/QR actions on this page are branch-scoped.

Current Livewire components:

- `App\Livewire\Organizations\Brands\Branches\ServicePoints\Index` now uses `WithPagination`, URL-backed search/filter state, server-side Eloquent filtering, and eager-loaded `areaNode`, `activeQrCode`, and `activeTableSession` relationships for the current page only.
- The component still owns service point single creation, bulk creation preview/confirm, status change, table opening, QR generation, and inline QR preview.
- `App\Livewire\QrCodes\ShortCodeLookup` remains the separate global QR lookup page for users with `generate_qr`.

Mandatory business rules:

- A service point search/filter must stay scoped to the route branch and organization access; do not show service points from another branch on this page.
- Search by QR `short_code` is staff/admin convenience only. It must not expose or derive public QR `public_token` beyond already authorized UI behavior.
- QR identity remains stable during search, filtering, pagination, service point rename, service point move, and ordinary edits.
- The service point list must not show technical IDs in the UI.
- CRUD still requires `manage_service_points`; manual status changes still require `manage_service_points` or the fixed `waiter` role; QR actions still require `generate_qr`; table opening still requires `view_orders` or `confirm_orders`.

Shared-hosting constraints:

- Keep SQLite, database cache, database sessions, database queue, local public storage, and Livewire polling.
- Use Eloquent relationships and indexes; do not add external search services or infrastructure for this small admin list.
- Keep pagination enabled so large branches do not load all service points in one request.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage/search, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 110 notes:

- Search fields: `service_points.name`, `service_points.display_number`, `service_points.internal_code`, and active `qr_codes.short_code`.
- Filters: current branch route, area/zone, type, status, active/inactive, has active QR / no active QR.
- Pagination uses simple Livewire pagination with 10 rows per page.
- Focused coverage: `tests/Feature/ServicePointCrudTest.php`.

Next recommended prompt:

- Wait for the next explicit user prompt. If no new prompt is provided, do not continue feature work automatically; keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.

## Prompt 109 QR Short Code Lookup - 2026-06-04

Prompt 109 added a restaurant-admin QR lookup page for printed sticker short codes. No database schema, packages, external QR/PDF services, or infrastructure were added.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print support fixed browser print label design presets.
- Prompt 109: users with `generate_qr` can search existing QR records by printed `short_code` from `/restaurant/qr-lookup`, scoped to accessible branches.
- Prompt 280: waiter-side draft item adding respects menu availability schedules.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Local images remain in `storage/app/public/media/...`.

Current tables:

- `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`.
- `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`.
- `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_users`, `branch_settings` including `service_modes`, `invitations`, `branch_opening_hours`.
- `area_nodes`, `service_points`, `qr_codes`.
- `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`.
- `table_sessions`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, `audit_logs`.

Current routes:

- Public/auth: `home`, `guest.home`, `public.qr.show`, `dashboard`, Fortify auth/password routes.
- Settings: `profile.edit`, `appearance.edit`, `security.edit`.
- Onboarding and admin: `onboarding.restaurant`, organization/staff/brand/branch routes, branch areas/menu/QR/service-points/staff/settings.
- QR admin/print/lookup: `organizations.brands.branches.service-points.qr.show`, `organizations.brands.branches.service-points.qr.print`, `organizations.brands.branches.qr.print`, `restaurant.qr-lookup.index`.
- Operations: `restaurant.dashboard`, audit log, CSV exports, waiter dashboard/table detail, kitchen dashboard, bar dashboard.
- Superadmin: `superadmin.dashboard`, `superadmin.backups.sqlite.download`.

Current Livewire components:

- Auth/settings/notifications: logout, profile, appearance, security, two-factor recovery codes, unread notification panel.
- Organization/branch admin: organizations, staff permissions, brands, branches, areas, menu, QR bulk print, service points with single and bulk creation, QR display/print with label presets, branch staff, branch settings with public profile, opening hours, temporary closure, currency, and service modes.
- QR lookup: `App\Livewire\QrCodes\ShortCodeLookup`.
- Guest QR: `PublicQr\Show`, `TableGuests`, `JoinRequests`, `Notifications`, `GuestMenu`, `DraftOrder`, `DraftTotals`, `OrderStatuses`.
- Operations: waiter dashboard/table detail, shared department dashboard, kitchen dashboard, bar dashboard, audit log, exports, onboarding, superadmin dashboard.

Mandatory business rules:

- One physical service point has one active permanent QR; `/q/{public_token}` must not expose organization, branch, service point, table, or area IDs.
- `short_code` is a printed staff lookup label, not a secret and not a public guest token.
- QR lookup by `short_code` must be scoped to branches the authenticated user can access with `generate_qr`.
- QR lookup must not change QR records; disable and reissue remain explicit button actions with existing warnings.
- QR identity does not change on rename, move, session close, ordinary edits, bulk-created service point review, print-table-number toggle, QR label preset switch, or short-code lookup.
- QR label presets are fixed presentation-only values.
- Guests are not user accounts; guest access uses unguessable guest tokens.
- New guests require active guest approval when active guests already exist.
- Guests see one shared draft/cart, guests are sorted alphabetically, and each guest edits only their own draft items while the draft is still editable.
- Branch opening hours, temporary closure, menu schedules, and future service-mode rules may block ordering while still allowing QR/menu viewing.
- Every guest draft must be confirmed by a waiter before becoming an order and explicitly dispatched before kitchen/bar can see it.
- Order items keep immutable snapshots; manual payments and table close preserve history and never reissue QR.

Shared-hosting constraints:

- SQLite file stays in the project, normally `database/database.sqlite`, outside `public`, and is never committed.
- Keep `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=public`, and `BROADCAST_CONNECTION=log`.
- Media is local in `storage/app/public`; realtime is Livewire polling only.
- QR label printing is browser print only.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 109 notes:

- Route: `GET /restaurant/qr-lookup` named `restaurant.qr-lookup.index`.
- Component: `App\Livewire\QrCodes\ShortCodeLookup`.
- Access uses the existing branch permission resolver with `SystemPermission::GenerateQr`.
- The page shows branch, organization/brand, zone, service point, QR status, and public URL for an accessible `short_code`.
- Actions reuse `DisableQrCodeAction` and `ReissueQrCodeForServicePointAction`.

Next recommended prompt:

- Superseded by the current Prompt 110 service point search/filter section at the top of this file. Continue only with the next explicit user prompt.

## Prompt 108 QR Label Design Presets - 2026-06-04

Prompt 108 added browser print-friendly design presets for existing permanent QR sticker printing. No database schema, routes, packages, PDF services, external services, or infrastructure were added.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 108: single service point QR print and branch bulk QR print now support fixed label design presets: `minimal`, `classic`, `restaurant`, `bar`, `hotel`, and `premium`.
- Prompt 280: waiter-side draft item adding respects menu availability schedules; unavailable scheduled menu items are filtered from the waiter add-item list and blocked server-side.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Local images remain in `storage/app/public/media/...`.

Current tables:

- `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`.
- `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`.
- `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_users`, `branch_settings` including `service_modes`, `invitations`, `branch_opening_hours`.
- `area_nodes`, `service_points`, `qr_codes`.
- `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`.
- `table_sessions`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, `audit_logs`.

Current routes:

- Public/auth: `home`, `guest.home`, `public.qr.show`, `dashboard`, Fortify auth/password routes.
- Settings: `profile.edit`, `appearance.edit`, `security.edit`.
- Onboarding and admin: `onboarding.restaurant`, organization/staff/brand/branch routes, branch areas/menu/QR/service-points/staff/settings.
- QR admin/print: `organizations.brands.branches.service-points.qr.show`, `organizations.brands.branches.service-points.qr.print`, and `organizations.brands.branches.qr.print`.
- Operations: `restaurant.dashboard`, audit log, CSV exports, waiter dashboard/table detail, kitchen dashboard, bar dashboard.
- Superadmin: `superadmin.dashboard`, `superadmin.backups.sqlite.download`.

Current Livewire components:

- Auth/settings/notifications: logout, profile, appearance, security, two-factor recovery codes, unread notification panel.
- Organization/branch admin: organizations, staff permissions, brands, branches, areas, menu, QR bulk print, service points with single and bulk creation, QR display/print with label presets, branch staff, branch settings with public profile, opening hours, temporary closure, currency, and service modes.
- Guest QR: `PublicQr\Show`, `TableGuests`, `JoinRequests`, `Notifications`, `GuestMenu`, `DraftOrder`, `DraftTotals`, `OrderStatuses`.
- Operations: waiter dashboard/table detail, shared department dashboard, kitchen dashboard, bar dashboard, audit log, exports, onboarding, superadmin dashboard.

Mandatory business rules:

- One physical service point has one active permanent QR; `/q/{public_token}` must not expose organization, branch, service point, table, or area IDs.
- QR identity does not change on rename, move, session close, ordinary edits, bulk-created service point review, print-table-number toggle, or QR label preset switch.
- QR label presets are fixed in `App\Enums\QrLabelPreset` and are presentation-only.
- QR stickers print the local logo when available, the text `Сканируйте, чтобы открыть меню`, the QR image, and `short_code`.
- QR stickers must not print service point number or area by default; enabling `print_table_number` must keep the stale-text warning.
- Guests are not user accounts; guest access uses unguessable guest tokens.
- New guests require active guest approval when active guests already exist.
- Guests see one shared draft/cart, guests are sorted alphabetically, and each guest edits only their own draft items while the draft is still editable.
- Branch opening hours, temporary closure, menu schedules, and future service-mode rules may block ordering while still allowing QR/menu viewing.
- Menu schedule rules apply to guest ordering and waiter-side adding of new items to a pending draft.
- Bulk service point creation must preview generated codes before writing, skip duplicate `internal_code` values, and never create QR automatically.
- Every guest draft must be confirmed by a waiter before becoming an order and explicitly dispatched before kitchen/bar can see it.
- Order items keep immutable snapshots; manual payments and table close preserve history and never reissue QR.

Shared-hosting constraints:

- SQLite file stays in the project, normally `database/database.sqlite`, outside `public`, and is never committed.
- Keep `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=public`, and `BROADCAST_CONNECTION=log`.
- Media is local in `storage/app/public`; realtime is Livewire polling only.
- QR label printing is browser print only.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, paid PDF services, heavy PDF/print libraries, maps/courier/payment integrations, AI translation, React/Vue/Inertia SPA, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 108 notes:

- `App\Enums\QrLabelPreset` owns the fixed preset list and labels.
- `App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\PrintTemplate` and `App\Livewire\Organizations\Brands\Branches\Qr\BulkPrint` both keep `preset` in Livewire URL state and normalize unknown values to `minimal`.
- `resources/css/app.css` owns the browser print CSS for preset classes such as `qr-sticker-preset-premium`.

Next recommended prompt:

- Superseded by the current Prompt 110 service point search/filter section at the top of this file. Continue only with the next explicit user prompt.

## Prompt 280 Functional Consistency Pass - 2026-06-04

Prompt 280 checked menu, guest, staff, department, access-control, payment, and table-close consistency without adding new product features, routes, tables, packages, services, or infrastructure.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Prompt 280: waiter-side draft item adding now also respects menu availability schedules; unavailable scheduled menu items are filtered from the waiter add-item list and blocked server-side.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Local images remain in `storage/app/public/media/...`.

Current tables:

- `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`.
- `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`.
- `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_users`, `branch_settings` including `service_modes`, `invitations`, `branch_opening_hours`.
- `area_nodes`, `service_points`, `qr_codes`.
- `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`.
- `table_sessions`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, `audit_logs`.

Current routes:

- Public/auth: `home`, `guest.home`, `public.qr.show`, `dashboard`, Fortify auth/password routes.
- Settings: `profile.edit`, `appearance.edit`, `security.edit`.
- Onboarding and admin: `onboarding.restaurant`, organization/staff/brand/branch routes, branch areas/menu/QR/service-points/staff/settings.
- Operations: `restaurant.dashboard`, audit log, CSV exports, waiter dashboard/table detail, kitchen dashboard, bar dashboard.
- Superadmin: `superadmin.dashboard`, `superadmin.backups.sqlite.download`.

Current Livewire components:

- Auth/settings/notifications: logout, profile, appearance, security, two-factor recovery codes, unread notification panel.
- Organization/branch admin: organizations, staff permissions, brands, branches, areas, menu, QR bulk print, service points with single and bulk creation, QR display/print, branch staff, branch settings with public profile, opening hours, temporary closure, currency, and service modes.
- Guest QR: `PublicQr\Show`, `TableGuests`, `JoinRequests`, `Notifications`, `GuestMenu`, `DraftOrder`, `DraftTotals`, `OrderStatuses`.
- Operations: waiter dashboard/table detail, shared department dashboard, kitchen dashboard, bar dashboard, audit log, exports, onboarding, superadmin dashboard.

Mandatory business rules:

- One physical service point has one active permanent QR; `/q/{public_token}` must not expose organization, branch, service point, table, or area IDs.
- QR identity does not change on rename, move, session close, ordinary edits, or bulk-created service point review.
- Guests are not user accounts; guest access uses unguessable guest tokens.
- New guests require active guest approval when active guests already exist.
- Guests see one shared draft/cart, guests are sorted alphabetically, and each guest edits only their own draft items while the draft is still editable.
- Branch opening hours, temporary closure, menu schedules, and future service-mode rules may block ordering while still allowing QR/menu viewing.
- Menu schedule rules apply to guest ordering and waiter-side adding of new items to a pending draft.
- Branch service modes are fixed values on branch settings; they prepare dine-in, pickup, delivery, hotel room service, bar-only, and custom operation without delivery/payment infrastructure.
- Bulk service point creation must preview generated codes before writing, skip duplicate `internal_code` values, and never create QR automatically.
- Multiple active menus can be visible together only when they are currently available by schedule; inactive/draft/archived menus are hidden from guests.
- Every guest draft must be confirmed by a waiter before becoming an order and explicitly dispatched before kitchen/bar can see it.
- Order items keep immutable snapshots; manual payments and table close preserve history and never reissue QR.

Shared-hosting constraints:

- SQLite file stays in the project, normally `database/database.sqlite`, outside `public`, and is never committed.
- Keep `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=public`, and `BROADCAST_CONNECTION=log`.
- Media is local in `storage/app/public`; realtime is Livewire polling only.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, maps/courier/payment integrations for service modes, AI translation, React/Vue/Inertia SPA, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Prompt 280 notes:

- Dedicated menu variants, tags, allergens, and shared payment allocations are not implemented as separate product features yet. Variant-like choices currently use modifier groups/options.
- Do not add those schemas opportunistically during bugfix or consistency prompts; use a separate explicit prompt if the product needs them.

Next recommended prompt:

- Prompt 281: decide whether to add dedicated menu tags/allergens and shared payment allocation foundations. Keep it schema-first and small if requested; otherwise continue with the previously queued menu translation admin editor.

## Daily Project Memory Update After Prompt 107 - 2026-06-04

This is a documentation-only memory refresh after Prompt 107. No code, routes, migrations, models, Livewire components, packages, services, or infrastructure were added in this update.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: branch service point managers can bulk-create numbered service points with preview, duplicate `internal_code` skips, and no automatic QR generation.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Local images remain in `storage/app/public/media/...`.

Current tables:

- `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`.
- `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`.
- `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_users`, `branch_settings` including `service_modes`, `invitations`, `branch_opening_hours`.
- `area_nodes`, `service_points`, `qr_codes`.
- `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`.
- `table_sessions`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, `audit_logs`.

Current routes:

- Public/auth: `home`, `guest.home`, `public.qr.show`, `dashboard`, Fortify auth/password routes.
- Settings: `profile.edit`, `appearance.edit`, `security.edit`.
- Onboarding and admin: `onboarding.restaurant`, organization/staff/brand/branch routes, branch areas/menu/QR/service-points/staff/settings.
- Operations: `restaurant.dashboard`, audit log, CSV exports, waiter dashboard/table detail, kitchen dashboard, bar dashboard.
- Superadmin: `superadmin.dashboard`, `superadmin.backups.sqlite.download`.

Current Livewire components:

- Auth/settings/notifications: logout, profile, appearance, security, two-factor recovery codes, unread notification panel.
- Organization/branch admin: organizations, staff permissions, brands, branches, areas, menu, QR bulk print, service points with single and bulk creation, QR display/print, branch staff, branch settings with public profile, opening hours, temporary closure, currency, and service modes.
- Guest QR: `PublicQr\Show`, `TableGuests`, `JoinRequests`, `Notifications`, `GuestMenu`, `DraftOrder`, `DraftTotals`, `OrderStatuses`.
- Operations: waiter dashboard/table detail, shared department dashboard, kitchen dashboard, bar dashboard, audit log, exports, onboarding, superadmin dashboard.

Mandatory business rules:

- One physical service point has one active permanent QR; `/q/{public_token}` must not expose organization, branch, service point, table, or area IDs.
- QR identity does not change on rename, move, session close, ordinary edits, or bulk-created service point review.
- Guests are not user accounts; guest access uses unguessable guest tokens.
- New guests require active guest approval when active guests already exist.
- Guests see one shared draft/cart, guests are sorted alphabetically, and each guest edits only their own draft items while the draft is still editable.
- Branch opening hours, temporary closure, menu schedules, and future service-mode rules may block ordering while still allowing QR/menu viewing.
- Branch service modes are fixed values on branch settings; they prepare dine-in, pickup, delivery, hotel room service, bar-only, and custom operation without delivery/payment infrastructure.
- Bulk service point creation must preview generated codes before writing, skip duplicate `internal_code` values, and never create QR automatically.
- Multiple active menus can be visible together only when they are currently available by schedule; inactive/draft/archived menus are hidden from guests.
- Every guest draft must be confirmed by a waiter before becoming an order and explicitly dispatched before kitchen/bar can see it.
- Order items keep immutable snapshots; manual payments and table close preserve history and never reissue QR.

Shared-hosting constraints:

- SQLite file stays in the project, normally `database/database.sqlite`, outside `public`, and is never committed.
- Keep `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=public`, and `BROADCAST_CONNECTION=log`.
- Media is local in `storage/app/public`; realtime is Livewire polling only.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, maps/courier/payment integrations for service modes, AI translation, React/Vue/Inertia SPA, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Next recommended prompt:

- Prompt 108: add a small menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Daily Project Memory Update After Prompt 106 - 2026-06-04

This is a documentation-only memory refresh after Prompt 106. No code, routes, migrations, models, Livewire components, packages, services, or infrastructure were added in this update.

Current stack:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

What is already implemented:

- Prompt 101: branch public profiles power the guest QR landing and guest table context.
- Prompt 102: branch opening hours show guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106: branch service modes can be enabled from branch settings using fixed values for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Prompt 107: service point managers can bulk-create numbered service points with preview and duplicate `internal_code` skips.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Local images remain in `storage/app/public/media/...`.

Current tables:

- `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`.
- `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`.
- `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_users`, `branch_settings` including `service_modes`, `invitations`, `branch_opening_hours`.
- `area_nodes`, `service_points`, `qr_codes`.
- `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`.
- `table_sessions`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, `audit_logs`.

Current routes:

- Public/auth: `home`, `guest.home`, `public.qr.show`, `dashboard`, Fortify auth/password routes.
- Settings: `profile.edit`, `appearance.edit`, `security.edit`.
- Onboarding and admin: `onboarding.restaurant`, organization/staff/brand/branch routes, branch areas/menu/QR/service-points/staff/settings.
- Operations: `restaurant.dashboard`, audit log, CSV exports, waiter dashboard/table detail, kitchen dashboard, bar dashboard.
- Superadmin: `superadmin.dashboard`, `superadmin.backups.sqlite.download`.

Current Livewire components:

- Auth/settings/notifications: logout, profile, appearance, security, two-factor recovery codes, unread notification panel.
- Organization/branch admin: organizations, staff permissions, brands, branches, areas, menu, QR bulk print, service points, QR display/print, branch staff, branch settings with public profile, opening hours, temporary closure, currency, and service modes.
- Guest QR: `PublicQr\Show`, `TableGuests`, `JoinRequests`, `Notifications`, `GuestMenu`, `DraftOrder`, `DraftTotals`, `OrderStatuses`.
- Operations: waiter dashboard/table detail, shared department dashboard, kitchen dashboard, bar dashboard, audit log, exports, onboarding, superadmin dashboard.

Mandatory business rules:

- One physical service point has one active permanent QR; `/q/{public_token}` must not expose organization, branch, service point, table, or area IDs.
- QR identity does not change on rename, move, session close, or ordinary edits.
- Guests are not user accounts; guest access uses unguessable guest tokens.
- New guests require active guest approval when active guests already exist.
- Guests see one shared draft/cart, guests are sorted alphabetically, and each guest edits only their own draft items while the draft is still editable.
- Branch opening hours, temporary closure, menu schedules, and future service-mode rules may block ordering while still allowing QR/menu viewing.
- Branch service modes are fixed values on branch settings; they prepare dine-in, pickup, delivery, hotel room service, bar-only, and custom operation without delivery/payment infrastructure.
- Bulk service point creation must preview generated codes before writing, skip duplicate `internal_code` values, and never create QR automatically.
- Multiple active menus can be visible together only when they are currently available by schedule; inactive/draft/archived menus are hidden from guests.
- Every guest draft must be confirmed by a waiter before becoming an order and explicitly dispatched before kitchen/bar can see it.
- Order items keep immutable snapshots; manual payments and table close preserve history and never reissue QR.

Shared-hosting constraints:

- SQLite file stays in the project, normally `database/database.sqlite`, outside `public`, and is never committed.
- Keep `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=public`, and `BROADCAST_CONNECTION=log`.
- Media is local in `storage/app/public`; realtime is Livewire polling only.

Forbidden:

- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, maps/courier/payment integrations for service modes, AI translation, React/Vue/Inertia SPA, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Next recommended prompt:

- Prompt 108: add a small menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Prompt 107 - Bulk Service Point Creation

Prompt 107 added bulk service point creation to the existing branch service point page.

Implemented:

- New `App\Actions\ServicePoints\BulkCreateServicePointsAction` for previewing generated codes and creating only missing service points.
- Existing `App\Livewire\Organizations\Brands\Branches\ServicePoints\Index` now has bulk form state, preview and confirm actions, duplicate counts, and a small shared-hosting-safe range limit of 200 records per run.
- Existing service point Blade UI now includes a `Добавить сразу несколько` section with zone, type, prefix, from/to, capacity, preview rows, skipped duplicates, and QR-next-step copy.
- Focused coverage lives in `tests/Feature/ServicePointCrudTest.php`.

Rules:

- Bulk create requires `manage_service_points`.
- Generated values such as `T1` are stored as `name`, `display_number`, and stable `internal_code`.
- Duplicate `internal_code` values are skipped per branch and checked with soft-deleted service points included.
- QR codes are not generated automatically after bulk creation.
- The UI only suggests using the existing bulk QR print page after staff review the created service points.
- No new routes, tables, maps, couriers, delivery workflow, payments, Redis, WebSockets, S3, Docker, or external services were added.

Next recommended prompt:

- Prompt 108: add a small menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Daily Project Memory Update After Prompt 105 - 2026-06-04

This section was last refreshed before Prompt 106. Prompt 106 now adds branch service modes; see the dedicated Prompt 106 section below.

Current stack remains:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

Current state:

- Prompt 101 is complete: branch public profiles power the guest QR landing and guest table context.
- Prompt 102 is complete: branch opening hours power guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103 is complete: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104 is complete: menu schedules restrict guest ordering to active branch-timezone menu windows.
- Prompt 105 is complete: guest menu payloads and UI support several active branch menus at once, grouped and sorted, while hiding inactive menus and respecting schedules.
- Prompt 106 is complete: branch settings can enable fixed service modes for dine-in, pickup, delivery, hotel room service, bar-only, and custom foundation scenarios.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Local images remain in `storage/app/public/media/...`.

Current tables:

- `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`.
- `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`.
- `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches`, `branch_users`, `branch_settings` including `service_modes`, `invitations`, `branch_opening_hours`.
- `area_nodes`, `service_points`, `qr_codes`.
- `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`.
- `table_sessions`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`, `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, `audit_logs`.

Current routes:

- Public/auth: `home`, `guest.home`, `public.qr.show`, `dashboard`, Fortify auth/password routes.
- Settings: `profile.edit`, `appearance.edit`, `security.edit`.
- Onboarding and admin: `onboarding.restaurant`, organization/staff/brand/branch routes, branch areas/menu/QR/service-points/staff/settings.
- Operations: `restaurant.dashboard`, audit log, CSV exports, waiter dashboard/table detail, kitchen dashboard, bar dashboard.
- Superadmin: `superadmin.dashboard`, `superadmin.backups.sqlite.download`.

Current Livewire components:

- Auth/settings/notifications: logout, profile, appearance, security, two-factor recovery codes, unread notification panel.
- Organization/branch admin: organizations, staff permissions, brands, branches, areas, menu, QR bulk print, service points, QR display/print, branch staff, branch settings.
- Guest QR: `PublicQr\Show`, `TableGuests`, `JoinRequests`, `Notifications`, `GuestMenu`, `DraftOrder`, `DraftTotals`, `OrderStatuses`.
- Operations: waiter dashboard/table detail, shared department dashboard, kitchen dashboard, bar dashboard, audit log, exports, onboarding, superadmin dashboard.

Mandatory business rules:

- One physical service point has one active permanent QR; `/q/{public_token}` must not expose internal IDs.
- QR identity does not change on rename, move, session close, or ordinary edits.
- Guests are not user accounts; guest access uses unguessable guest tokens.
- New guests require active guest approval when active guests already exist.
- Guests see one shared draft/cart, guests are sorted alphabetically, and each guest edits only their own draft items while the draft is still editable.
- Branch opening hours, temporary closure, and menu schedules can block ordering while still allowing QR/menu viewing.
- Multiple active menus can be visible together only when they are currently available by schedule; inactive/draft/archived menus are hidden from guests.
- Branch service modes are fixed values stored per branch; they prepare dine-in, pickup, delivery, hotel room service, bar-only, and custom operation without adding delivery/payment infrastructure.
- Every guest draft must be confirmed by a waiter before becoming an order and explicitly dispatched before kitchen/bar can see it.
- Order items keep immutable snapshots; manual payments and table close preserve history and never reissue QR.

Shared-hosting constraints and forbidden infrastructure:

- SQLite file stays in the project, normally `database/database.sqlite`, outside `public`, and is never committed.
- Keep `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=public`, and `BROADCAST_CONNECTION=log`.
- Media is local in `storage/app/public`; realtime is Livewire polling only.
- Do not use Redis, WebSockets/Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage, Stripe, PayPal, paid APIs, Push/SMS/Telegram API, AI translation, React/Vue/Inertia SPA, raw SQL strings, committed `.env`, SQLite database files, backups, `vendor`, `node_modules`, uploads, or generated build/export files.

Next recommended prompt:

- Prompt 107: add a small menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Prompt 106 - Service Modes

Prompt 106 added branch-level service modes as a small foundation setting.

Implemented:

- New `branch_settings.service_modes` JSON column for enabled branch service modes.
- New `App\Enums\BranchServiceMode` with fixed values: `dine_in`, `pickup`, `delivery`, `hotel_room_service`, `bar_only`, and `custom`.
- `BranchSetting::defaults()` now returns `service_modes => ['dine_in']`.
- `App\Actions\Branches\UpdateBranchSettingsAction` normalizes mode lists before saving and keeps branch cache invalidation unchanged through the existing branch settings observer/action flow.
- Existing branch settings Livewire page now shows service mode checkboxes.
- Focused coverage lives in `tests/Feature/BranchSettingsTest.php`.

Rules:

- `dine_in` is the safe default and preserves current QR/service-point behavior.
- `pickup`, `delivery`, `hotel_room_service`, `bar_only`, and `custom` are foundation flags only.
- Delivery does not add maps, couriers, payments, external APIs, or routing logic.
- Pickup does not add a separate pickup workflow yet; existing pickup-style service points can be used later.
- No Redis, WebSockets, S3, Docker, paid services, React, Vue, or new frontend SPA were added.

Next recommended prompt:

- Prompt 107: add a small menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Daily Project Memory Update After Prompt 104 - 2026-06-04

This is a documentation-only memory refresh after Prompt 104. No code, routes, migrations, models, Livewire components, packages, services, or infrastructure were added in this update.

Current stack remains:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

Current state:

- Prompt 101 is complete: branch public profiles power the guest QR landing and guest table context.
- Prompt 102 is complete: branch opening hours power guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103 is complete: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Prompt 104 is complete: menu schedules show only currently available menus to guests and block unavailable scheduled menus at add/send time.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Local images remain in `storage/app/public/media/...`.

Next recommended prompt:

- Prompt 106: add a simple menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Prompt 105 - Multiple Menus Per Branch

Prompt 105 improved guest support for several active menus in one branch without changing the existing menu schema or CRUD.

Implemented:

- `App\Actions\Menus\GetGuestMenuForBranchAction` now returns a `menus` list with every active menu available right now, sorted by `sort_order`, `name`, and `id`.
- The payload keeps legacy `menu` and `categories` keys mapped to the first available menu so existing guest add-item and menu tests remain compatible.
- Active menus outside their current `menu_availability_schedules` window are returned in `unavailable_menus` with next-availability text; their dishes are not exposed for ordering.
- Draft, archived, inactive, or soft-deleted menus are not shown to guests.
- `App\Livewire\PublicQr\GuestMenu` can find/add items from any available menu section and formats all menu groups for display.
- `resources/views/livewire/public-qr/guest-menu.blade.php` now groups dishes by menu when several menus are available and shows a small `Будет доступно позже` block for active menus scheduled later.
- Focused coverage lives in `tests/Feature/MenuScheduleTest.php`.

Rules:

- No new menu-type table was added; menu types such as main menu, breakfast, business lunch, bar menu, wine card, kids menu, seasonal menu, and special menu are represented by menu names, status, sort order, and optional schedules.
- Menu schedules still use `branches.timezone`.
- Menus with no schedule rows remain available all day for backward compatibility.
- Guest ordering remains blocked server-side for menus outside their active schedule.
- Branch cache invalidation still runs through `ForgetBranchCacheAction`; no Redis cache tags are used.

Next recommended prompt:

- Prompt 106: add a small menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Prompt 104 - Menu Schedules

Prompt 104 added branch-timezone menu availability schedules.

Implemented:

- New `menu_availability_schedules` table with `menu_id`, ISO weekday `day_of_week`, `starts_at`, and `ends_at`.
- New `App\Models\MenuAvailabilitySchedule` model, factory, `Menu::availabilitySchedules()` relationship, and `MenuAvailabilityScheduleObserver`.
- New `App\Actions\Menus\GetMenuAvailabilityStatusAction` for timezone-aware current/next menu availability.
- `App\Actions\Menus\GetGuestMenuForBranchAction` now returns only the first active menu currently available by schedule, keeps using the database cache store, and returns a guest-facing availability message when all active menus are outside schedule.
- Branch menu admin Livewire UI shows each menu schedule, current availability, and simple add/delete interval controls behind `manage_menu`.
- Guest menu empty state now shows schedule status such as `Меню сейчас недоступно` and `Будет доступно с 12:00`.
- Guest add-to-draft and send-to-waiter actions recheck menu availability server-side so stale tabs cannot order unavailable menus.
- Focused coverage lives in `tests/Feature/MenuScheduleTest.php`.

Rules:

- A menu with no schedule rows is available all day for backward compatibility.
- Schedule checks use `branches.timezone`; no external calendar, holiday, map, or paid service is used.
- End time earlier than or equal to start time is treated as an overnight interval, so `22:00-02:00` and all-day `00:00-00:00` work.
- If every active menu is unavailable right now, guests can still view the QR/table shell but cannot order from that menu window.
- Menu schedule create/update/delete clears centralized branch cache through `ForgetBranchCacheAction`.

Next recommended prompt:

- Prompt 106: add a small menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Daily Project Memory Update After Prompt 103 - 2026-06-04

This is a documentation-only memory refresh after Prompt 103. No code, routes, migrations, models, Livewire components, packages, services, or infrastructure were added in this update.

Current stack remains:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

Current state:

- Prompt 101 is complete: branch public profiles power the guest QR landing and guest table context.
- Prompt 102 is complete: branch opening hours power guest open/closed status and block ordering while a configured branch is closed.
- Prompt 103 is complete: temporary branch closed mode blocks new guest ordering while keeping QR and menu viewing available.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Local images remain in `storage/app/public/media/...`.

Next recommended prompt:

- Prompt 104: add a simple menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Prompt 103 - Branch Closed Mode

Prompt 103 added temporary closed mode for each branch without disabling permanent QR codes or menu browsing.

Implemented:

- New `branches` columns: `is_temporarily_closed`, `temporary_closed_reason`, and nullable `temporary_closed_until`.
- `Branch::temporaryClosedUntilForBranch()` resolves the stored UTC value into the branch timezone for guest/admin display.
- `App\Actions\Branches\UpdateBranchTemporaryClosureAction` saves or clears temporary closure state from validated UI data.
- `App\Actions\Branches\GetBranchOpeningStatusAction` now gives temporary closure priority over weekly opening hours and returns `can_accept_orders = false`.
- Existing branch settings UI now shows a temporary closure form, reason examples, optional until time, and admin warning.
- Public QR guest UI shows `Ресторан временно закрыт` with the reason while still allowing QR/menu viewing.
- Guest draft item creation and send-to-waiter backend actions now block while temporary closure is active.
- Waiter dashboard shows branch closure warnings and lets staff with order access reopen ordering.
- Focused coverage lives in `tests/Feature/BranchTemporaryClosedModeTest.php`.

Rules:

- Temporary closure does not revoke or disable QR codes.
- Guests can still open `/q/{public_token}` and view the public profile/menu.
- New draft items and sending draft orders to waiters are blocked while temporary closure is active.
- Admin can disable temporary closure from branch settings; waiter/order-access staff can disable it from the waiter dashboard.
- No external APIs, maps, Redis, WebSockets, S3, Docker, paid services, or online payments are used.

Next recommended prompt:

- Prompt 104: add a simple menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Daily Project Memory Update After Prompt 102 - 2026-06-04

This is a documentation-only memory refresh after Prompt 102. No code, routes, migrations, models, Livewire components, packages, services, or infrastructure were added in this update.

Current stack remains:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

Current state:

- Prompt 101 is complete: branch public profiles power the guest QR landing and guest table context.
- Prompt 102 is complete: branch opening hours power guest open/closed status and block ordering while a configured branch is closed.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Guests can still view QR/menu pages while the configured branch is closed.
- Local images remain in `storage/app/public/media/...`.

Next recommended prompt:

- Prompt 104: add a simple menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Prompt 102 - Branch Opening Hours

Prompt 102 added branch weekly opening hours and guest-facing open/closed status.

Implemented:

- New `branch_opening_hours` table for local SQLite schedules.
- New `App\Models\BranchOpeningHour` model and factory.
- `Branch::openingHours()` relationship ordered by weekday, sort order, time, and id.
- `App\Actions\Branches\UpdateBranchOpeningHoursAction` for replacing a branch weekly schedule from validated settings data.
- `App\Actions\Branches\GetBranchOpeningStatusAction` for timezone-aware status checks, including current open interval and next opening time.
- Existing branch settings page now edits opening hours with closed days and up to four intervals per day.
- Public QR landing/table UI shows opening status and keeps QR/menu viewing available while the branch is closed.
- Guest add-to-draft and send-to-waiter backend actions now block ordering when a configured branch schedule says the restaurant is closed.
- Focused coverage lives in `tests/Feature/BranchOpeningHoursTest.php`.

Rules:

- A closed branch does not disable the QR URL.
- Guests may still view the restaurant profile and menu when closed.
- Guest ordering actions are blocked while closed when opening hours are configured.
- If a branch has no opening-hours rows, ordering is not blocked by schedule.
- Status checks use `branches.timezone`; no external calendar, map, booking, or paid service is used.

Next recommended prompt:

- Prompt 104: add a simple menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Daily Project Memory Update After Prompt 101 - 2026-06-04

This is a documentation-only memory refresh after Prompt 101. No code, routes, migrations, models, Livewire components, packages, services, or infrastructure were added in this update.

Current stack remains:

- Laravel 13.13, PHP 8.5, Fortify, Boost, MCP.
- Livewire 4.3 + Blade + Flux UI Free.
- SQLite only.
- Database cache, database sessions, database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite; generated `public/build` remains uncommitted.

Current state:

- Prompt 101 is complete: branch public profiles power the guest QR landing and guest table context.
- Prompt 102 is complete: branch opening hours power guest open/closed status and block ordering while closed.
- Public QR URLs remain `/q/{public_token}` only and must not expose internal IDs.
- Guest-facing missing profile/contact data uses fallback text.
- Local images remain in `storage/app/public/media/...`.

Next recommended prompt:

- Prompt 104: add a simple menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Prompt 101 - Restaurant Public Profile

Prompt 101 added a branch-level public restaurant profile for the guest QR landing page and guest table header.

Implemented:

- New nullable `branches` columns: `public_name`, `public_description`, `cover_image_path`, `phone`, `email`, `website_url`, `instagram_url`, `facebook_url`, and `tiktok_url`.
- `Branch::publicDisplayName()` falls back to `branches.name`; `Branch::coverImageUrl()` returns a local public-storage URL.
- `App\Actions\Branches\UpdateBranchPublicProfileAction` saves public profile text/link fields.
- Existing branch settings page now edits public profile data and uploads local logo/cover images.
- Public QR `/q/{public_token}` landing now shows venue name, description, local logo, local cover, address, contact links, social links, default language, default currency, current zone, and current service point.
- Guest-facing fallbacks are shown when description or contact details are missing.
- Branch cache invalidation now includes public profile/contact/media field changes.
- Focused coverage lives in `tests/Feature/RestaurantPublicProfileTest.php`.

Constraints kept:

- No maps, external APIs, paid services, S3, WebSockets, Redis, Docker requirement, React/Vue SPA, or social-platform integrations.
- Public QR URLs remain token-only and do not expose organization, branch, area, service point, table, or session IDs.
- Images remain local in `storage/app/public/media/...`.

Next recommended prompt:

- Prompt 104: add a simple menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

## Daily Project Memory Update - 2026-06-04

This is a documentation-only memory refresh. No product features, routes, migrations, models, Livewire components, packages, services, or infrastructure were added in this update.

Current stack:

- Laravel 13.13, PHP 8.5, Laravel Fortify, Laravel Boost, Laravel MCP.
- Livewire 4.3 with Blade server-rendered UI and Flux UI Free 2.14.
- SQLite only.
- Database cache, database sessions, and database queue.
- Local public storage in `storage/app/public`.
- Tailwind CSS 4 / Vite for assets; generated `public/build` remains uncommitted.
- Pest 4 for tests; feature tests disable Vite asset resolution through `Tests\TestCase::withoutVite()`.

Already implemented:

- Auth, profile/security settings, fixed roles, flexible permissions, role permissions, user overrides, and superadmin access.
- Organizations, one-plan local SaaS subscription status, organization users, brands, branches, branch users, branch settings, staff invitations, staff UI, and permission override UI.
- Local media storage for organization, brand, branch logos, branch public profile cover images, and dish images.
- Branch public restaurant profiles for guest QR landing and guest table context.
- Branch opening hours for timezone-aware guest open/closed status and ordering guardrails.
- Temporary branch closed mode for operational closures that keep QR/menu viewing available while blocking new guest ordering.
- Nested `area_nodes`, `service_points`, service point statuses, permanent QR schema/generation/admin display/print/bulk print, and public `/q/{public_token}` guest route.
- Guest table flow: QR entry by name, guest token persistence, guest-created pending sessions, table session guests, join requests, invite links, guest approval UI, isolated polling blocks, guest notifications, guest menu, shared cart, ready status, waiter call, bill request, and guest error pages.
- Menu flow: multiple active branch menus, menu availability schedules, categories, items, local images, database-cached guest menu, ru/en/lt translations for display, modifiers, kitchen departments, department assignment, stop-list, currency display, and centralized branch cache invalidation.
- Order flow: shared `draft_orders`, guest-owned draft items, waiter dashboard, waiter table detail, waiter draft editing/confirm/reject, real `orders` and snapshot `order_items`, order status logs, explicit kitchen/bar dispatch, department tickets, kitchen/bar screens, ready/served handoff, repeat orders, manual payments, table-session close, analytics, restaurant dashboard, audit logs, database notifications, CSV exports, demo seed, smoke checklist, shared-hosting deployment notes, and current-version docs.

Current tables:

- `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `passkeys`.
- `roles`, `permissions`, `permission_role`, `role_user`, `permission_user_overrides`.
- `organizations`, `organization_subscriptions`, `organization_users`, `brands`, `branches` with public profile and temporary closure fields, `branch_users`, `branch_settings`, `invitations`.
- `branch_opening_hours`.
- `area_nodes`, `service_points`, `qr_codes`.
- `menus`, `menu_availability_schedules`, `menu_categories`, `menu_category_translations`, `menu_items`, `menu_item_translations`, `modifier_groups`, `modifier_options`, `menu_item_modifier_groups`, `kitchen_departments`.
- `table_sessions`, `table_session_guests`, `table_session_join_requests`, `waiter_calls`.
- `draft_orders`, `draft_order_items`, `orders`, `order_items`, `order_status_logs`, `kitchen_tickets`, `kitchen_ticket_items`, `manual_payments`, `audit_logs`, `migrations`.

Current application routes:

- Public/auth base: `home`, `guest.home`, `public.qr.show`, `dashboard`, Fortify `login`, `register`, password reset, logout, and password confirmation routes.
- Settings: `profile.edit`, `appearance.edit`, `security.edit`.
- Onboarding: `onboarding.restaurant`.
- Organization admin: `organizations.index`, `organizations.staff.index`, `organizations.staff.permissions`, `organizations.brands.index`, `organizations.brands.branches.index`.
- Branch admin: `organizations.brands.branches.areas.index`, `organizations.brands.branches.menu.index`, `organizations.brands.branches.qr.print`, `organizations.brands.branches.service-points.index`, `organizations.brands.branches.service-points.qr.show`, `organizations.brands.branches.service-points.qr.print`, `organizations.brands.branches.staff.index`, `organizations.brands.branches.settings.index`.
- Restaurant operations: `restaurant.dashboard`, `restaurant.audit-log.index`, `restaurant.exports.index`, `restaurant.exports.download`, `restaurant.kitchen.dashboard`, `restaurant.bar.dashboard`, `restaurant.waiter.dashboard`, `restaurant.waiter.tables.show`.
- Superadmin: `superadmin.dashboard`, `superadmin.backups.sqlite.download`.

Current Livewire components:

- `App\Livewire\Actions\Logout`.
- `App\Livewire\AuditLogs\Index`, `App\Livewire\Exports\Index`, `App\Livewire\Notifications\UnreadCount`.
- `App\Livewire\Onboarding\RestaurantSetup`.
- `App\Livewire\Organizations\Index`, `App\Livewire\Organizations\Staff\Index`, `App\Livewire\Organizations\Staff\Permissions`, `App\Livewire\Organizations\Brands\Index`, `App\Livewire\Organizations\Brands\Branches\Index`, `App\Livewire\Organizations\Brands\Branches\Areas`, `App\Livewire\Organizations\Brands\Branches\Menu\Index`, `App\Livewire\Organizations\Brands\Branches\Qr\BulkPrint`, `App\Livewire\Organizations\Brands\Branches\ServicePoints\Index`, `App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\Show`, `App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\PrintTemplate`, `App\Livewire\Organizations\Brands\Branches\Staff\Index`, `App\Livewire\Organizations\Brands\Branches\Settings`.
- `App\Livewire\PublicQr\Show`, `App\Livewire\PublicQr\TableGuests`, `App\Livewire\PublicQr\JoinRequests`, `App\Livewire\PublicQr\Notifications`, `App\Livewire\PublicQr\GuestMenu`, `App\Livewire\PublicQr\DraftOrder`, `App\Livewire\PublicQr\DraftTotals`, `App\Livewire\PublicQr\OrderStatuses`.
- `App\Livewire\Waiter\Dashboard`, `App\Livewire\Waiter\TableDetail`.
- `App\Livewire\Departments\Dashboard`, `App\Livewire\Kitchen\Dashboard`, `App\Livewire\Bar\Dashboard`.
- `App\Livewire\Superadmin\Dashboard`.
- `App\Livewire\Settings\Profile`, `App\Livewire\Settings\Appearance`, `App\Livewire\Settings\Security`, `App\Livewire\Settings\DeleteUserForm`, `App\Livewire\Settings\TwoFactor\RecoveryCodes`.

Mandatory business rules:

- One physical service point has one active permanent QR. QR URL is `/q/{public_token}` and must not expose organization, branch, service point, table, area, number, or name IDs/data.
- QR identity must not change on service point rename, move, session close, or ordinary edits. Manual reissue is the only intentional QR identity change.
- Guests are not user accounts. Guest access uses unguessable guest tokens stored in browser cookie/session flow.
- Active guests see each other alphabetically and see the same shared draft/cart with per-guest and table totals.
- New guests require approval by current active guests when active guests already exist.
- Guests can edit only their own draft items and only while the draft is still `draft`.
- If branch opening hours are configured and the branch is currently closed, guests can still open QR/menu pages but cannot add draft items or send a draft to the waiter.
- If a branch is temporarily closed, temporary closure takes priority over opening hours: QR/menu viewing still works, but guests cannot add draft items or send a draft to the waiter until admin/waiter reopens ordering or the optional closure time has passed.
- If menu schedules are configured and the active menu is currently outside its branch-timezone interval, guests can still keep the QR/table page open but cannot add or send draft items from that unavailable menu.
- Every draft, including repeat orders, must be sent to and confirmed by a waiter before becoming a real order.
- Kitchen/bar sees only explicitly dispatched confirmed orders, never guest drafts or merely confirmed-but-not-dispatched orders.
- Order items must keep immutable snapshots of guest/item/modifier/price data.
- Payments are manual/offline only. Closing a table frees the service point, blocks old guest ordering, preserves old history, and keeps the QR unchanged.

Shared-hosting constraints:

- Web root must be `public`; SQLite file, `.env`, storage, and app code stay outside public web root.
- SQLite database file lives in the project, normally `database/database.sqlite`, and must not be committed.
- Writable paths include `database`, `storage/app/public`, `storage/framework/*`, `storage/logs`, and `bootstrap/cache`.
- Use `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=public`, and `BROADCAST_CONNECTION=log`.
- Media stays local on the public disk. `public/storage` should link or map to `storage/app/public`.
- Realtime remains Livewire polling with isolated visible polling blocks and branch polling interval from `branch_settings`.
- Dashboard/analytics/menu cache remains database-backed with explicit invalidation.

Do not use:

- Redis, WebSockets, Reverb/Pusher, S3, Docker as a requirement, external queue/cache/storage services, Push/SMS/Telegram API, Stripe, PayPal, online acquiring, AI translation, paid APIs, paid PDF/export libraries, React, Vue, Inertia, or a separate SPA.
- Raw SQL strings, committed `.env`, SQLite databases, backups, `vendor`, `node_modules`, `public/build`, `public/storage`, local uploads, or generated exports.

Next recommended prompt:

- Prompt 106: add a simple menu translation admin editor for existing `menu_category_translations` and `menu_item_translations` inside the current branch menu UI, limited to `ru`, `en`, and `lt`, with database cache invalidation through `ForgetBranchCacheAction`. Do this only when explicitly requested; do not add AI translation or external services.

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
- Filesystem config resolves to local disks only (`local` and `public`); no S3 disk is exposed.
- Fixed interface locales: `ru`, `en`, `lt`
- Fixed branch currencies through `App\Enums\SupportedCurrency`
- Small Blade design-system primitives in `resources/views/components/ui`
- Polished mobile-first guest QR/table/menu UI layered on existing Livewire components
- Polished waiter dashboard with zone grouping, priority work queues, and quick table actions
- Polished shared kitchen/bar production screen with large ticket cards and Livewire polling
- Pest 4
- Vite / Tailwind CSS 4

## Main Business Rules

- The product is a SaaS platform for restaurants, cafes, bars, hotels, food courts, and similar venues.
- It must grow beyond a simple QR menu, but each prompt must stay small.
- The SaaS billing model currently has one plan for all organizations, no tariff limits, no online billing provider, and manual superadmin activation/suspension only.
- One physical table / place / service point should have one active permanent QR code.
- QR links must not expose restaurant IDs, branch IDs, table IDs, or table numbers.
- QR `public_token`, guest `guest_token`, and table-session guest invite tokens must stay random, hidden, and unguessable.
- Closed/cancelled table sessions, rejected/removed guests, expired join requests, and inactive service points must not accept guest ordering actions.
- Public QR/session error pages must stay guest-friendly, mobile-first, and must not expose organization IDs, branch IDs, service point IDs, table session IDs, table numbers, or internal tokens.
- Orders must require waiter confirmation by default.
- Each repeat order in the same table session must create a new draft, require waiter confirmation, and preserve previous confirmed orders.
- New guests must require approval by default.
- Branch realtime behavior must use Livewire polling, not WebSockets.
- Operational notifications must use Laravel database notifications stored in SQLite and Livewire polling for unread counts; do not add Push, WebSockets, Redis, SMS, Telegram API, or paid notification providers.
- Active guest waiter calls must use local database state and database notifications only.
- Active guest bill requests must use `table_sessions.status = payment_requested`, local service point status, database notifications, and Livewire polling only.
- Manual payments must be offline staff-entered records only; no Stripe, PayPal, online acquiring, or external payment service is connected.
- Basic analytics must stay lightweight, branch-scoped, and cached through the database cache store; no Redis, external BI service, or heavy refresh query loop.
- Audit logs must stay local in SQLite and be visible only through `view_audit_log` access.
- SQLite on shared hosting must stay protected by explicit indexes on hot foreign-key/status/polling paths, bounded polling queries, database-cache dashboards, and pagination for growing history/list screens.
- Local backups must stay shared-hosting friendly: superadmin-only SQLite download, no S3, no paid backup services, no Docker, and no committed backup files.
- Data exports must stay branch-scoped, CSV-first, protected by `export_data`, and must not leak another branch's orders, payments, menu, or service points.
- Localization must stay local and fixed to `ru`, `en`, and `lt`; do not add AI translation, paid translation APIs, or external localization services.
- Currency handling must stay local: no exchange-rate APIs, no paid currency services, and no automatic conversion of stored menu/order/payment amounts.
- Access control must stay organization-scoped first and branch-scoped when active `branch_users` assignments exist. A staff member assigned to one branch must not see or open another branch's admin/operational data unless they are superadmin or have no branch assignment narrowing in that organization.

## What Is Already Done

- Laravel + Livewire project foundation.
- SQLite-only database configuration.
- Database-backed cache, sessions, and queues.
- Shared-hosting deployment notes in `docs/DEPLOY_SHARED_HOSTING.md`.
- Current-version developer snapshot in `docs/CURRENT_VERSION.md`, covering the domain terms, permanent QR rule, table-session/guest/draft/order flow, shared-hosting mode, limits, and next-step guardrails.
- Bugfix after Prompt 100: `Tests\TestCase` disables Vite asset resolution with Laravel's `withoutVite()` helper so feature tests do not crash when ignored `public/build` font assets are missing or stale.
- Prompt 099 first vertical-slice regression: `tests/Feature/VerticalSliceFlowTest.php` covers registration, organization/brand/branch/zone/service point setup, permanent QR, public QR guest entry, invite approval for the second guest, shared draft items, waiter confirmation, kitchen/bar dispatch, ready/served handoff, bill request, manual payment, table-session close, and permanent QR stability.
- Prompt 098 project cleanup: removed starter placeholder pages/copy, removed default `test@example.com` seeding, removed unused starter header/icon overrides, removed `laravel/sail`, and added focused cleanup regression coverage.
- Guest, auth, restaurant dashboard, and superadmin dashboard layout zones.
- Fortify-backed authentication.
- Fixed system roles.
- Flexible permissions with role permissions and user overrides.
- Prompt 096 access-control audit with regression coverage for organization isolation, branch assignment isolation, waiter price restrictions, cook staff restrictions, marketer order-confirmation restrictions, accountant payment visibility without menu editing, and superadmin bypass.
- Organizations.
- Simple one-plan SaaS subscriptions for organizations with local status/payment fields and superadmin manual activation/suspension.
- Organization user memberships.
- Brands.
- Branches.
- Branch settings.
- Branch public restaurant profile for QR landing and guest UI: public venue name, description, local logo/cover, address, contacts, social links, default language, and default currency.
- Centralized branch cache invalidation through `App\Actions\Branches\ForgetBranchCacheAction` for database-cache guest menu, legacy menu, and polling interval keys.
- Restaurant onboarding wizard at `/onboarding/restaurant` for creating a starter organization, brand, branch, first zone, first service points, permanent QR codes, first active menu, and a test public guest page.
- Explicit demo restaurant seed through `Database\Seeders\DemoRestaurantSeeder` for local QA and first-run testing.
- Manual main-flow smoke checklist in `docs/TEST_CHECKLIST.md`.
- Simplified branch setup UI with the `Настроить ресторан` wizard for branch, zones, service points, QR generation, QR print, and guest-menu opening.
- Local media storage for organization, brand, and branch logos.
- Area nodes nested branch schema and CRUD UI.
- Service points schema and CRUD UI.
- Service point operational statuses and manual status changes.
- Branch menu CRUD with branch menus, nested menu categories, menu items, local dish photos, menu category/item translation tables, branch-level kitchen departments, menu-item department assignment with default kitchen fallback, branch-level menu modifiers, permission-gated price/availability changes, and a `change_availability` stop-list workflow backed by `menu_items.is_available`.
- Table sessions schema for branch/service point lifecycle tracking.
- Waiter/admin open-table action and service point UI for creating active table sessions.
- Guest-created pending table sessions from the public QR landing.
- Table session guests with guest names, random browser guest tokens, cookie restore, statuses, and alphabetical ordering.
- Table session join requests with backend create / approve / reject logic, guest approval UI, guest invite share links, guest table page shell, and database-cached guest menu display with modifier selection.
- Draft order schema with repeat draft history per table session, latest/current draft access, guest-owned draft items with price snapshots, guest add/edit/delete UI for own positions, guest ready status, send-to-waiter handoff, waiter edit/confirm/reject actions, and split isolated guest polling blocks for draft items, draft totals, and order statuses.
- Real order snapshot schema in `orders` and `order_items`, created only after waiter confirmation, with explicit order item snapshots for source menu item id, guest name, item name/description, unit price, selected modifiers, future tax/service payloads, and kitchen department snapshots.
- Order status log schema in `order_status_logs` for persistent draft/order history.
- Polished waiter dashboard with branch-scoped zone grouping, priority queues for new orders/calls/bills/ready items, color-coded service point cards, quick open-table action, close links for permitted users, and waiter table detail with sent/waiter-review draft visibility, guest positions, modifiers, comments, totals, edit controls, and confirm/reject controls through Livewire polling.
- Kitchen/bar dispatch for confirmed orders with department-split `kitchen_tickets`, explicit `send_to_kitchen` permission checks, service point status updates, guest accepted state, and order status logging.
- Polished kitchen and bar production screens for dispatched department tickets with department filtering, oldest-first sorting, large service point cards, timers, modifiers, comments, and `Начать` / `Готово` item actions.
- Waiter ready/served handoff: kitchen/bar ready items appear in waiter table detail, waiters can mark ready items served, service point status can move to `ready_to_serve`, and guests see `Принято` / `Готовится` / `Готово` / `Подано`.
- Guest waiter-call button on the public QR table shell with `waiter_calls`, database notifications, waiter dashboard polling, and handled state.
- Guest request-bill button on the public QR shared basket with `table_sessions.status = payment_requested`, `service_points.status = payment_requested`, database notifications, waiter dashboard polling, and per-guest/table totals.
- Database notifications for new join requests, drafts sent to waiters, guest waiter calls, bill requests, waiter-confirmed draft orders, kitchen/bar item cooking/ready states, and rejected draft orders, plus authenticated and guest Livewire notification UI blocks that poll the local database.
- SQLite performance guardrails for shared hosting: extra hot-path indexes, visible-only polling attributes, database-cache dashboard preservation, and cursor-paginated audit log history.
- Livewire guest polling optimization: the active public QR table page now polls guests, notifications, join requests, order statuses, draft positions, and draft totals as separate visible isolated components using the branch settings polling interval.
- QR and guest session security hardening: inactive service points are rejected by guest entry, invite, join-request, draft item, and send-to-waiter backend paths; expired join requests are marked expired during guest restore/polling; disabled QR codes keep a public error state.
- Guest error pages for public QR/session problems: QR not found, QR disabled/revoked, inactive service point, closed table session, rejected/removed/left guest entry, stale/closed invite links, and inactive restaurant subscription are shown through a mobile-first Blade component with clear text and safe return actions.
- Soft deletes for important restaurant/menu entities: organizations, brands, branches, area nodes, service points, menus, menu categories, and menu items. Normal lists hide archived rows, while historical order/draft/kitchen links can still load archived context where needed.
- Manual payment flow with local `manual_payments`, whole-table and per-guest staff payment actions, `manage_payments` permission, fixed cashier access, paid session status, and table-session close action.
- Manual table-session close with the critical `close_table_sessions` permission; closing moves the session to `closed`, frees the service point, blocks old guest ordering, preserves old orders, and keeps the permanent QR unchanged.
- Basic restaurant dashboard analytics with `view_reports` access, SQLite/database-cache snapshots, and cache invalidation on order, order item, payment, and session changes.
- Branch/restaurant dashboard with active tables, new waiter drafts, cooking orders, ready positions, today amount, popular dishes, and role-aware quick actions.
- General audit logs with a `view_audit_log`-guarded viewer for menu, service point, QR, staff permission, order, payment, and table-session control events.
- CSV data exports for branch orders, manual payments, menu items, and service points through streamed responses guarded by `export_data`.
- Basic localization foundation with `SupportedLocale`, `users.locale`, `SetInterfaceLocale` web middleware, profile language selection, guest QR language selection, and local JSON strings in `lang/en.json`, `lang/ru.json`, and `lang/lt.json`.
- Basic currency settings with `SupportedCurrency`, `MoneyFormatter`, branch/settings currency selectors, settings-to-branch currency sync, and formatted guest/menu price display.
- Simple reusable Blade design system for lightweight buttons, cards, status badges, form fields, empty states, alerts/warnings, mobile guest bottom action bars, and clear zone/service-point icons. The first applied screens are public QR guest table/menu actions, branch area management, and branch service point management.
- Prompt 090 guest mobile UI polish: improved the public QR name-entry screen, active guest table context, waiter-call action, guest list, mobile dish cards, item edit/configuration bottom sheets, shared cart grouping, per-guest totals, and sticky table action bar without changing backend business logic.
- Superadmin-only local SQLite backup download from the platform dashboard, with a sensitive-data warning and a reserved media ZIP follow-up.
- Permanent QR schema, generation action, admin display page, simple and bulk browser print templates, and public QR guest landing with name entry.
- Basic superadmin access for the platform dashboard.
- Staff invitation backend foundation.
- Simple organization and branch staff management UI.
- Staff permission override UI.

No menu translation admin editor, QR PDF generation, CSV-to-PDF export, online payment provider, or advanced kitchen production history has been implemented yet.

## Current Cleanup Guardrails

- Default package metadata is `goleaf/restaurant-menu`, not the original Livewire starter-kit package name.
- `laravel/sail` is not installed; Docker must remain optional and unnecessary for development, deployment, or verification.
- Runtime filesystem disks are forced to local-only `local` and `public`; do not expose an S3 disk.
- `DatabaseSeeder` seeds system permissions, first superadmin when configured, and kitchen departments only. It must not create starter/demo users such as `test@example.com`.
- Demo restaurant data remains opt-in through `Database\Seeders\DemoRestaurantSeeder`.
- Public fallback pages (`/` and `/guest`) are real entry/fallback screens and must not reintroduce "placeholder" or "not implemented yet" copy.
- No dedicated policy classes currently exist; access control is enforced through middleware, Actions, `User` access helpers, and the permission system. Do not add a policy layer unless a future prompt asks for it or a focused refactor needs it.
- `tests/Feature/VerticalSliceFlowTest.php` covers the first end-to-end restaurant flow and must stay green when editing guest entry, invite approval, shared draft, waiter review, kitchen/bar tickets, manual payments, table-session close, or permanent QR behavior.
- `tests/Feature/ProjectCleanupConsistencyTest.php` covers the cleanup guardrails.
- Feature tests should not require generated Vite assets. `Tests\TestCase::setUp()` calls `withoutVite()` because `public/build` is ignored and may be absent or stale before `npm run build`.

## Tables

- `users`
  - Includes `locale` for authenticated admin interface language.
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `notifications`
  - Laravel database notifications for users and `table_session_guests`.
  - Prompt 083 added composite indexes for unread count/list polling by notifiable, type, read state, and creation time.
- `passkeys`
- `roles`
- `permissions`
- `permission_role`
- `role_user`
- `permission_user_overrides`
- `organizations`
  - Soft delete through `deleted_at`.
- `organization_subscriptions`
- `organization_users`
- `brands`
  - Soft delete through `deleted_at`.
- `branches`
  - Soft delete through `deleted_at`.
  - Includes public restaurant profile fields: `public_name`, `public_description`, `cover_image_path`, `phone`, `email`, `website_url`, `instagram_url`, `facebook_url`, and `tiktok_url`.
- `branch_opening_hours`
  - Stores weekly branch schedules with closed days and multiple intervals per day.
- `menus`
  - Soft delete through `deleted_at`.
- `menu_categories`
  - Soft delete through `deleted_at`.
- `menu_category_translations`
- `menu_items`
  - Soft delete through `deleted_at`.
- `menu_item_translations`
- `kitchen_departments`
- `modifier_groups`
- `modifier_options`
- `menu_item_modifier_groups`
- `area_nodes`
  - Soft delete through `deleted_at`.
- `service_points`
  - Soft delete through `deleted_at`.
- `table_sessions`
- `table_session_guests`
- `table_session_join_requests`
- `waiter_calls`
- `manual_payments`
- `draft_orders`
- `draft_order_items`
- `orders`
- `order_items`
  - Includes explicit Prompt 088 snapshot columns for original menu item id, guest/item text, unit price, modifiers, and future tax/service payloads.
- `kitchen_tickets`
- `kitchen_ticket_items`
- `order_status_logs`
- `audit_logs`
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
- Has many service points through branches.
- Has many orders through branches.
- Has many users through `organization_users`.
- Has one SaaS subscription through `organization_subscriptions`.
- New organizations created through `CreateOrganizationAction` receive a default active subscription.
- If an organization subscription is explicitly inactive, regular users cannot access that organization workspace; superadmins bypass this so they can reactivate it.
- Stores optional `logo_path` for a locally stored logo.
- Supports soft delete through `deleted_at`; ordinary organization lists hide soft-deleted organizations.

Brand:

- Belongs to an organization.
- Has many branches.
- Stores optional `logo_path` for a locally stored logo.
- Supports soft delete through `deleted_at`; its organization relationship can still load an archived organization for historical context.

Branch:

- Belongs to a brand and an organization.
- Is the current working unit for future menu, zones, service points, and orders.
- Has one settings record.
- Stores the public restaurant profile used by guest QR screens: public venue name, short description, local logo, local cover image, address/city/country, phone, email, website, Instagram, Facebook, TikTok, default language, and default currency.
- Public venue name falls back to `branches.name`; missing description/contact data is shown to guests with tidy fallback text.
- Has many opening-hour rows through `branch_opening_hours`.
- Opening hours use `branches.timezone` for open/closed status and may store multiple intervals per day or a closed day row.
- If opening hours are configured and the branch is currently closed, public QR and guest menu viewing still work, but guest draft item creation and sending a draft to the waiter are blocked.
- If no opening-hour rows exist for a branch, ordering is not blocked by schedule.
- Has many menus.
- Has many kitchen departments.
- Has many modifier groups.
- Has many nested area nodes.
- Has many service points.
- Has many branch staff assignments through `branch_users`.
- Active branch staff assignments narrow branch-scoped pages and resolvers through `User::canAccessBranch()` and `User::accessibleBranchIdsForOrganization()`.
- If a regular user has active `branch_users` rows in an organization, branch lists and branch-scoped pages only expose those assigned branches. If they have no active branch assignment, organization-level access continues to cover all branches in that organization.
- Stores optional `logo_path` for a locally stored logo.
- Stores operational `currency`; branch settings store `default_currency`, and both are kept synced by branch/settings update actions.
- New branches created through `CreateBranchAction` receive standard kitchen departments through `SeedKitchenDepartmentsForBranchAction`.
- The branch list UI includes a `Настроить ресторан` setup wizard. It prepares zone, service point, and active QR counts in `App\Livewire\Organizations\Brands\Branches\Index` using Eloquent counts/eager loading and links only to existing routes.
- The setup wizard steps are `Создать филиал`, `Добавить зоны`, `Добавить столы`, `Сгенерировать QR`, `Напечатать QR`, and `Открыть гостевое меню`.
- Supports soft delete through `deleted_at`; branch links from orders, menus, areas, and service points can still load archived branch context when needed.

Restaurant onboarding wizard:

- Route is `GET /onboarding/restaurant`.
- Component is `App\Livewire\Onboarding\RestaurantSetup`.
- The wizard is an authenticated first-run helper for a new restaurant and uses simple labels instead of exposing organization/brand/service-point terminology wherever possible.
- It creates organization, brand, branch, first area node, first service points, active QR codes, and a first active menu.
- Organization, brand, branch, area node, service point, and QR creation reuse existing Actions.
- Starter menu creation is isolated in `App\Actions\Onboarding\CreateStarterMenuAction` and writes the existing `menus`, `menu_categories`, and `menu_items` tables only.
- The final test guest link uses the existing public QR route and remains `/q/{public_token}`.
- The wizard does not replace ordinary CRUD screens.

Demo restaurant seed:

- Seeder class is `Database\Seeders\DemoRestaurantSeeder`.
- It is run explicitly with `php artisan db:seed --class=DemoRestaurantSeeder`.
- It is not called from `DatabaseSeeder`, so production/base seeding does not automatically create demo restaurant data.
- It creates `Demo Food Group`, `Bella Pizza`, and `Demo Old Town`.
- It creates zones `Главный зал`, `Терраса`, and `Бар`.
- It creates seven service points with stable demo `internal_code` values and one active permanent QR each.
- It creates `Bella Pizza Demo Menu` with pizza, drinks, and dessert categories, seven dishes, kitchen/bar/dessert department assignments, and ru/en/lt menu/category translations.
- It creates demo users `demo.owner@example.com`, `demo.admin@example.com`, `demo.waiter@example.com`, `demo.chef@example.com`, `demo.bartender@example.com`, and `demo.cashier@example.com` with default password `password`.
- Demo access uses organization/branch memberships plus user-level permission overrides, not global role-permission changes.
- Re-running the seeder should update the same demo rows and must not duplicate the demo organization, brand, branch, service points, menu, menu items, or active QR codes.

Smoke test checklist:

- Manual checklist path is `docs/TEST_CHECKLIST.md`.
- It covers the main branch setup, QR, guest session, invite/approval, shared draft, waiter confirmation, kitchen/bar dispatch, ready/served handoff, bill request, manual payment, and table close flow.
- It is intentionally documentation-only and does not add a browser E2E framework or heavy test dependency.
- Optional command checks are limited to existing Pest commands such as `php artisan test --compact --filter=DemoRestaurantSeederTest`.

Menu:

- Stored in `menus`.
- Belongs to one branch through `branch_id`.
- Status is cast to `MenuStatus`.
- Current status values are `draft`, `active`, and `archived`.
- Stores `name` and `sort_order`.
- Has many categories and items.
- Managed from the branch menu page guarded by `manage_menu`.
- The menu admin UI can create, edit, sort, and delete menus.
- Supports soft delete through `deleted_at`.
- Soft deleting a menu soft-deletes its categories, which soft-delete their child categories and menu items. The visible UI hides them, but the rows remain available through `withTrashed()` for history-safe relationships.
- Active guests see the current branch's first active menu on the public QR table page.
- Guest menu payloads are cached through the explicit `database` cache store with the key `guest-menu:branch:{branch_id}:language:{language_code}`.
- `GetGuestMenuForBranchAction` builds and caches the guest menu payload for five minutes.
- Guest menu cache rebuilds use the database-backed lock key `guest-menu:branch:{branch_id}:language:{language_code}:lock`.
- Branch cache invalidation is centralized in `App\Actions\Branches\ForgetBranchCacheAction`.
- `ForgetBranchCacheAction` clears guest menu payload keys for every supported language, the legacy `guest-menu:branch:{branch_id}` key, and the cached branch polling interval key.
- Guest menu and branch cache invalidation must use the database cache store and must not use Redis or cache tags.
- Supported guest menu languages are `ru`, `en`, and `lt`.
- If no guest language is selected, `branch_settings.default_language` is used.
- If a selected category or item translation is missing, the guest menu falls back to the base category/item `name` and `description`.
- Supported language codes come from `App\Enums\SupportedLocale`; do not hardcode a separate language list in UI-only code.

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
- Supports soft delete through `deleted_at`.
- Soft deleting a category soft-deletes child categories and menu items. Translations remain attached to the archived base category.

Menu item:

- Stored in `menu_items`.
- Belongs to one menu through `menu_id`.
- Belongs to one category through `category_id`.
- Can belong to one branch kitchen department through `kitchen_department_id`; the admin form's empty `Default kitchen` choice resolves to the branch's default `kitchen` department before saving.
- Stores `name`, optional `description`, `price`, optional `image`, optional `weight`, optional `volume`, optional `calories`, `is_available`, and `sort_order`.
- `price`, `weight`, and `volume` are decimal casts; `is_available` is a boolean cast.
- Managed from the branch menu page guarded by `manage_menu`.
- Temporary stop-list state uses `is_available = false`; no separate stop-list table exists.
- Users with `change_availability` can access the branch menu page for the stop-list even without `manage_menu`; full menu CRUD still requires `manage_menu`.
- Dish photo upload/removal is implemented with local public storage only.
- Dish photos are stored under `media/organizations/{organization}/brands/{brand}/branches/{branch}/menu-items/{item}/images`.
- Creating or editing dish price requires `change_prices`; without it, price edits are preserved as the current value.
- Creating or editing dish availability requires `change_availability`; without it, availability edits are preserved as the current value.
- Guest menu display shows item price, photo when present, and unavailable state as `Нет в наличии`.
- Guest menu price display uses the current branch currency formatter; stored menu item prices are not converted.
- Unavailable dishes remain visible to guests by default but cannot be opened or added to `draft_order_items`.
- Has many translations through `menu_item_translations`.
- Has many reusable modifier groups through `menu_item_modifier_groups`.
- Has many draft order items through `draft_order_items`.
- Has many confirmed order item records through `order_items`.
- Translation support exists for guest display, but a full admin editor for translations is not implemented yet.
- Modifier assignment exists in admin CRUD and the guest UI can configure available modifiers and persist configured selections into `draft_order_items`.
- Changing the dish department assignment clears the branch guest-menu database cache through `MenuItemObserver`.
- Supports soft delete through `deleted_at`; normal menu/admin/guest queries hide deleted dishes.
- Draft, order, and kitchen ticket item relationships to `menu_items` use archived context where needed, while confirmed `order_items` remain readable from stored snapshots even if the source dish is soft-deleted.

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

Localization:

- Supported interface languages are fixed in `App\Enums\SupportedLocale`: `ru`, `en`, and `lt`.
- Admin interface language is stored on `users.locale`.
- `App\Http\Middleware\SetInterfaceLocale` is appended to the `web` middleware group and applies authenticated user locale first, then supported `lang` query/session locale, then app fallback.
- Profile settings expose a simple language selector for the authenticated user's admin interface language.
- Branch settings expose `branch_settings.default_language` as a fixed ru/en/lt selector.
- Branch settings create/update/delete events clear centralized branch cache through `BranchSettingObserver`.
- Public QR page exposes a guest language selector and defaults to the branch language when no `lang` query is present.
- Guest invite links include the current `lang` query so invited guests keep the selected language.
- Guest menu receives the selected language and still falls back to base menu/category/item text if translations are missing.
- Baseline UI strings live in `lang/en.json`, `lang/ru.json`, and `lang/lt.json`.
- This is not a complete translation pass for every historical UI string; future prompts may expand the local JSON files.

Currency:

- Supported branch currencies are fixed in `App\Enums\SupportedCurrency`.
- Default currency is `EUR`.
- `branch_settings.default_currency` and `branches.currency` must stay synced.
- `App\Support\MoneyFormatter` formats display strings such as `€14.50`, `$14.50`, or `14.50 PLN`.
- Currency settings do not change stored menu item prices, modifier price deltas, draft item totals, order totals, or manual payment values.
- There is no exchange-rate API, no paid currency provider, and no automatic conversion.

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
- The area UI uses simplified visible labels for non-technical staff, including `Зоны ресторана`, `Шаг 2: добавьте зоны`, large preset buttons, and `Список зон`.
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
- The service point UI uses simplified visible labels for non-technical staff, including `Столы и места`, `Шаг 3: добавьте столы`, large preset buttons for table/bar seat/room/other place, and plain actions for QR and opening tables.
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
- `CreateGuestPendingTableSessionAction` returns `service_point_unavailable` and does not create a session when the service point is inactive.
- If an active or pending session already exists and has active guests, guest QR entry creates a pending table session join request instead of a guest.
- If an active or pending session already exists without active guests, guest QR entry returns the existing-session message without creating a join request.
- `CreateGuestInviteLinkAction` creates or reuses one hidden invite token for the current table session.
- Only an active guest in the same table session can create the guest invite link.
- Guest invite links respect `branch_settings.allow_guest_invite_links`.
- Guest invite link creation and join request creation are blocked when the service point is inactive.
- Guest invite URLs use `/q/{public_token}?invite={guest_invite_token}` and must not expose table session IDs, service point IDs, branch IDs, table numbers, or area names.
- Opening a guest invite link asks the invited person for a name and creates a pending join request for the invited table session.
- Draft order schema, guest add-to-draft UI, send-to-waiter handoff, waiter dashboard visibility, waiter draft editing, waiter confirm/reject actions, request-bill status flow, manual offline payment flow, and explicit kitchen/bar dispatch exist. Confirmed orders are stored in `orders` and `order_items`; dispatch creates `kitchen_tickets` and `kitchen_ticket_items`. Online payment provider logic does not exist yet.

Table session guest:

- Stored in `table_session_guests`.
- Belongs to one table session through `table_session_id`.
- Stores `guest_name`, `guest_token`, `status`, optional `ready_at`, `joined_at`, optional `left_at`, optional JSON `metadata`, and timestamps.
- `guest_token` is a random 64-character token and is unique.
- Guests are not `users` records and do not require registration.
- `TableSessionGuest` uses Laravel's `Notifiable` trait only for local database notifications; this does not make guests authenticated users.
- The public QR flow stores `guest_token` in a browser cookie named `guest_token_{hash}`.
- Refreshing the public QR page restores the same guest and table session from that cookie when the token still belongs to the current service point.
- Status is cast to `TableSessionGuestStatus`.
- Status values are `pending_approval`, `active`, `rejected`, `left`, and `removed`.
- The first guest from a guest-created pending session is stored as `active`.
- Rejected and removed guests are restored for messaging but cannot use the future item-adding path.
- Closed or cancelled table sessions are restored for messaging but cannot use the future item-adding path.
- Inactive service points block backend guest ordering/invite actions even if a stale guest component still has saved state.
- `TableSession::guests()` returns all session guests ordered by `guest_name` and id.
- `TableSession::activeGuests()` returns active guests ordered by `guest_name` and id.
- `TableSessionGuest::approvedJoinRequests()` and `TableSessionGuest::rejectedJoinRequests()` expose join request moderation history.
- `TableSessionGuest::waiterCalls()` exposes waiter calls requested by that guest.
- `TableSessionGuest::draftOrderItems()` exposes draft items owned by the guest.
- `ready_at` marks that an active guest is ready; `null` means not ready.
- Active guests can approve or reject new guest join requests from the public QR UI.
- `App\Livewire\PublicQr\TableGuests` renders the guest list for active guests and polls only that block.
- The guest list shows guest names alphabetically, human-readable guest statuses, and ready/not-ready labels.

Database notification:

- Stored in Laravel's existing `notifications` table.
- Uses only the `database` channel.
- Notifiable models are currently `App\Models\User` and `App\Models\TableSessionGuest`.
- Current notification database types are `join_request_created`, `draft_order_sent_to_waiter`, `waiter_called`, `bill_requested`, `draft_order_confirmed`, `kitchen_item_cooking`, `kitchen_item_ready`, and `draft_order_rejected`.
- `CreateTableSessionJoinRequestAction` sends `JoinRequestCreatedNotification` to active guests in the same table session.
- `SendDraftOrderToWaiterAction` sends `DraftOrderSentToWaiterNotification` to branch waiter recipients resolved by `ResolveWaiterNotificationRecipientsAction`.
- `RequestWaiterForTableSessionAction` sends `WaiterCalledNotification` to branch waiter recipients.
- `RequestBillForTableSessionAction` sends `BillRequestedNotification` to branch waiter recipients.
- `ConfirmDraftOrderByWaiterAction` sends `DraftOrderConfirmedNotification` to active guests in the same table session after a draft is converted to an order.
- `UpdateDepartmentTicketItemStatusAction` sends `KitchenItemCookingNotification` to the item owner when an item changes to `in_progress`.
- `UpdateDepartmentTicketItemStatusAction` sends `KitchenItemReadyNotification` to branch waiter recipients and the item owner when an item changes to `ready`.
- `RejectDraftOrderByWaiterAction` sends `DraftOrderRejectedNotification` to active guests in the same table session.
- `App\Livewire\Notifications\UnreadCount` shows authenticated users an unread notification count and event list for waiter-facing events; it updates through `wire:poll.1s` and can mark one or all current-user notifications read locally.
- `App\Livewire\PublicQr\Notifications` shows active guests their unread join/order/kitchen notifications on the public QR table page; it validates the browser `guest_token`, uses the branch polling interval, and can mark one or all current-guest notifications read locally.
- Guests are still not authenticated users; guest-facing notifications are attached to `table_session_guests`.
- Do not add Push, WebSocket, Redis, SMS, Telegram API, mail delivery, or paid notification services for these operational events.

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

Waiter dashboard:

- Route is `GET /restaurant/waiter/dashboard`.
- Component is `App\Livewire\Waiter\Dashboard`.
- Payload builder is `App\Actions\Waiter\BuildWaiterDashboardAction`.
- Access requires branch-level `view_orders`; superadmin bypass and active branch assignments are resolved through `ResolveWaiterAccessibleBranchIdsAction`.
- The dashboard uses `wire:poll.visible.1s="refreshDashboard"` and no WebSockets.
- Prompt 091 groups service point cards by their current `area_node_id` / area name in `service_point_zones`.
- Priority blocks at the top show new sent/waiter-review drafts first, then pending waiter calls, bill-request sessions, and unserved ready kitchen/bar items.
- Ready items are read from `kitchen_ticket_items.status = ready` with `served_at = null` through selected/eager-loaded Eloquent data; the dashboard only links to the table detail where staff can mark them served.
- Service point cards include color-coded status/urgency, active session details, guest count, draft total, payment-request state, and open/close/detail actions.
- The dashboard `openTable()` method reuses `OpenTableSessionForServicePointAction`, checks branch access through `view_orders` or `confirm_orders`, and refreshes the same dashboard payload.
- `Close table` on the dashboard is only a link to the existing waiter table detail close block and is shown only when the user has `close_table_sessions`; the actual close action remains `CloseTableSessionAction`.
- The dashboard must not confirm drafts, reject drafts, send orders to kitchen/bar, record payments, or mark ticket items served directly; those actions stay on `App\Livewire\Waiter\TableDetail`.

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
- `App\Actions\Dashboard\BuildRestaurantDashboardAction` builds the Prompt 070 branch/restaurant dashboard payload for operational and reporting users.
- Superadmins see analytics for all branches through the same branch resolver bypass used by waiter/report access.
- The action currently computes today's order count, today's order amount, average check, popular dishes, active table count, closed sessions today, and cancelled orders today.
- The restaurant dashboard action currently computes active tables, new `sent_to_waiter` drafts, cooking orders, ready unserved kitchen/bar positions, today amount, popular dishes, and quick-action availability.
- Restaurant dashboard amount and popular dishes require `view_reports`; waiters and other operational users can still see the operational cards without seeing report-sensitive totals.
- Orders today and amount exclude `orders.status = cancelled` and use `orders.confirmed_at` within the current application day.
- Popular dishes are based on confirmed `order_items` snapshots from today's non-cancelled orders, so later menu name/description/price/modifier changes do not rewrite old analytics history.
- Active tables include table sessions in `pending`, `active`, `waiting_waiter_confirmation`, and `payment_requested`.
- Closed sessions use `table_sessions.status = closed` with `ended_at` during the current application day.
- Cancelled orders use `orders.status = cancelled` with `updated_at` during the current application day because there is no separate `cancelled_at` field yet.
- Analytics cache keys are grouped by sorted branch ids and current date, for example `analytics:dashboard:branches:{sha1}:today:{date}`.
- Restaurant dashboard cache keys are grouped by access-specific sorted branch id sets and current date, for example `restaurant-dashboard:{sha1}:today:{date}`.
- Branch cache-key indexes are also stored in the database cache so changing one branch can forget dashboard snapshots that include that branch without Redis cache tags.
- `OrderObserver`, `OrderItemObserver`, `ManualPaymentObserver`, and `TableSessionObserver` invalidate affected branch analytics cache.
- `DraftOrderObserver`, `KitchenTicketObserver`, and `KitchenTicketItemObserver` invalidate affected branch restaurant dashboard cache.
- Dashboard analytics must not be added to 1-second waiter/kitchen/bar polling loops; keep it on the restaurant dashboard or use explicit short-lived database cache.

SQLite performance guardrails:

- Added in Prompt 083 through `2026_06_04_053034_add_sqlite_performance_guardrail_indexes.php`.
- The migration adds hot-path composite indexes for `notifications`, `service_points`, `table_sessions`, `table_session_join_requests`, `draft_orders`, `draft_order_items`, `orders`, `kitchen_tickets`, `kitchen_ticket_items`, and `audit_logs`.
- These indexes support unread notification polling, waiter/service-point lists, active session scans, join approval polling, latest/sent draft lookup, shared cart item ordering, restaurant dashboard order reads, department ticket polling, ready unserved ticket checks, and audit history browsing.
- `App\Actions\Dashboard\BuildRestaurantDashboardAction` still uses `Cache::store('database')->remember(...)` and branch cache-key indexes; do not move it to Redis or un-cached per-refresh queries.
- `App\Actions\AuditLogs\BuildAuditLogIndexAction` now returns a cursor-paginated history payload with prepared rows; it no longer loads a fixed latest 200-row list.
- Staff notification UI polling is `wire:poll.visible.5s`; guest notification UI polling uses `branch_settings.polling_interval_seconds`.
- Waiter and kitchen/bar live blocks keep 1-second polling; guest table live blocks use `branch_settings.polling_interval_seconds` with the `visible` modifier and selected/bounded Eloquent queries.
- Do not remove existing `select([...])`, `limit(...)`, eager loads, or cursor pagination from polling/dashboard/history paths without replacing them with an equal or lighter query shape.

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
- `ConfirmDraftOrderByWaiterAction` requires `confirm_orders`, converts a `sent_to_waiter` or `waiter_review` draft to `converted_to_order`, creates one `orders` row with status `confirmed_by_waiter`, and copies draft items into `order_items` snapshots, including original menu item id, guest name, item name/description, unit price, selected modifiers, future tax/service JSON placeholders, and kitchen department id/type/name when the source menu item has a department.
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
- Stores legacy snapshot/display fields copied from `draft_order_items` and the source menu item: `guest_name`, `item_name`, `kitchen_department_type`, `kitchen_department_name`, `quantity`, `unit_price`, `modifier_total`, `total_price`, selected modifiers, and optional comment.
- Stores explicit immutable snapshot fields from Prompt 088: `original_menu_item_id`, `guest_name_snapshot`, `item_name_snapshot`, `item_description_snapshot`, `unit_price_snapshot`, `modifiers_snapshot`, `tax_snapshot`, and `service_snapshot`.
- `tax_snapshot` and `service_snapshot` currently default to empty arrays and reserve the future tax/service payload without adding tax, service-charge, or payment logic in Prompt 088.
- `OrderItem::historicalGuestName()`, `OrderItem::historicalItemName()`, and `OrderItem::historicalModifiers()` are the snapshot-first helpers for reads that need the original order content.
- Snapshot fields must remain unchanged if the source menu item name, description, price, modifier options, guest name, or kitchen department name/type change later.
- CSV order export, dashboard/analytics popular item summaries, and kitchen/bar dispatch should prefer explicit `order_items` snapshots through the `historical*` helpers.
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

Audit log:

- Stored in `audit_logs`.
- This is the general control journal and is separate from `order_status_logs`.
- Belongs optionally to organization, branch, actor user, and table session guest.
- Stores optional `guest_token` for future guest-originated audit actions that do not have a guest row loaded.
- Stores `action`, `entity_type`, `entity_id`, optional JSON `old_values`, optional JSON `new_values`, and `created_at`.
- Current action enum values are `menu_price_changed`, `menu_availability_changed`, `menu_item_deleted`, `service_point_moved`, `qr_reissued`, `staff_permission_changed`, `order_confirmed`, `order_cancelled`, `table_session_closed`, and `payment_recorded`.
- `RecordAuditLogAction` is the shared writer. It normalizes enum/date/model values before storing JSON snapshots.
- `BuildAuditLogIndexAction` prepares the cursor-paginated `/restaurant/audit-log` payload and resolves access through `view_audit_log`.
- Superadmins can view all audit rows. Regular users see only branch rows and organization-level rows where they have `view_audit_log`.
- Audit UI is intentionally simple and local; no external logging service, Redis, WebSockets, S3, or Docker is used.

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
- The QR print template supports `minimal`, `classic`, `restaurant`, `bar`, `hotel`, and `premium` label design presets through `App\Enums\QrLabelPreset`.
- The QR print template does not print service point number or area by default.
- The `print_table_number` URL setting can include the service point display number or name in the sticker.
- When `print_table_number` is enabled, the UI shows the warning: `Если вы потом переименуете или перенесёте стол, текст на наклейке может устареть.`
- Toggling `print_table_number` or changing the label preset must not change QR identity.
- The branch bulk QR print page is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/qr/print` and is guarded by `generate_qr` in the current organization context.
- The bulk print page can show all areas, one area node, or service points without an area.
- The bulk print page lets users select service points with active QR codes and prints multiple stickers in the browser print view.
- The bulk print page offers single and visible-batch creation for service points that do not have an active QR yet.
- Bulk QR creation reuses `GenerateQrCodeForServicePointAction`, so it does not create a second active QR for a service point that already has one.
- Bulk printing uses the same local SVG QR renderer, same optional local logo resolver, and same QR label presets as the one-sticker print template.
- Manual reissue is the only current UI path that intentionally changes the QR identity.
- Public route `GET /q/{token}` resolves `public_token` without exposing organization IDs, branch IDs, service point IDs, or table numbers.
- Public QR route accepts active, disabled, revoked, and unknown token states.
- Active QR codes load the current service point, current area, branch, brand, organization, and local logo path for the guest landing page.
- Disabled and revoked QR codes show public error messages instead of opening the guest landing state.
- Active QR codes attached to inactive service points show a public unavailable message.
- Inactive service points are also rejected by backend guest entry, invite, join-request, draft item, and send-to-waiter actions.
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
- The guest table shell shows venue name, current service point, saved entry state, invite action, guest list, cached active branch menu, order status, draft positions, and draft totals.
- The guest table shell can add menu items to `draft_order_items`, shows a shared cart grouped by guests alphabetically, lets active guests edit/delete only their own draft positions, and lets any active guest send the shared draft to waiter review, but it does not create final orders, payments, kitchen tasks, or bar tasks.
- On page refresh, `App\Livewire\PublicQr\Show` reads `guest_token_{hash}` and restores the matching guest only when the guest belongs to a table session for the current service point.
- If no guest matches the cookie token, `App\Livewire\PublicQr\Show` can restore a matching join request for the current service point and show pending/rejected/expired messaging.
- Expired pending join requests are marked `expired` when restored or polled by the waiting guest.
- Active guests see pending join requests in `App\Livewire\PublicQr\JoinRequests`, which refreshes with Livewire polling and does not require WebSockets.
- The waiting guest status block in `App\Livewire\PublicQr\Show` polls only the join request status and turns approved requests into active guest state.
- Restored active guests get `guestCanAddItems = true` only when the table/session/guest state allows ordering and the configured branch opening hours currently accept orders.
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
- Superadmins can see top-level organization, brand, branch, service point, order, and user counts on the platform dashboard.
- Superadmins can see organization subscription status, activity state, started date, next payment date, manual payment status, brand count, total/active branch count, service point count, and order count on the platform dashboard.
- Superadmins can open existing organization details, open the audit log, suspend an organization subscription, and reactivate it from the platform dashboard.
- Superadmin impersonation is not implemented yet.
- Superadmins bypass organization and branch-level access checks.
- Regular users keep organization-scoped access, narrowed by active `branch_users` assignments when those assignments exist in the organization.
- `tests/Feature/AccessControlAuditTest.php` is the focused Prompt 096 regression test for normal organization visibility, branch assignment visibility, role boundary checks, and superadmin bypass.

SaaS subscription:

- Stored in `organization_subscriptions`.
- Belongs to exactly one organization through unique `organization_id`.
- There is one SaaS plan for everyone; no plan table and no tariff limit system exists.
- Status values are `active` and `inactive`, cast by `OrganizationSubscriptionStatus`.
- Payment status values are `pending`, `paid`, `overdue`, and `failed`, cast by `OrganizationSubscriptionPaymentStatus`.
- Stores nullable `started_at` and `next_payment_at`.
- `EnsureOrganizationSubscriptionAction` creates the default active local subscription with `payment_status = pending`, `started_at = now()`, and `next_payment_at = now() + 1 month`.
- `SetOrganizationSubscriptionStatusAction` is the manual superadmin action used by the platform dashboard to activate or suspend organizations.
- Inactive subscriptions block regular organization access through `User::canAccessOrganization()` and `User::hasOrganizationRole()`.
- Missing subscription rows are treated as active for legacy records until a subscription is created, so old local data is not locked by the migration alone.
- No Stripe, PayPal, online acquiring, invoices, webhooks, external billing provider, or paid billing service exists.

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
- Permission override changes create `staff_permission_changed` audit rows with the target staff user, permission code, previous state, and new state.

Local media storage:

- Uses Laravel's `public` disk only.
- Public disk root is `storage/app/public`.
- Public browser path is `public/storage`.
- Shared-hosting deployment notes live in `docs/DEPLOY_SHARED_HOSTING.md`.
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
- Updating a branch logo clears that branch cache; updating a brand or organization logo clears cache for the related branches.
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
- `GET /onboarding/restaurant` -> `onboarding.restaurant`
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
- `GET /restaurant/audit-log` -> `restaurant.audit-log.index`
- `GET /restaurant/exports` -> `restaurant.exports.index`
- `GET /restaurant/exports/branches/{branch}/{export}` -> `restaurant.exports.download`
- `GET /restaurant/bar/dashboard` -> `restaurant.bar.dashboard`
- `GET /restaurant/kitchen/dashboard` -> `restaurant.kitchen.dashboard`
- `GET /restaurant/waiter/dashboard` -> `restaurant.waiter.dashboard`
- `GET /restaurant/waiter/tables/{tableSession}` -> `restaurant.waiter.tables.show`
- `GET /superadmin/dashboard` -> `superadmin.dashboard` guarded by `auth` + `superadmin`
- `GET /superadmin/backups/sqlite` -> `superadmin.backups.sqlite.download` guarded by `auth` + `superadmin`
- Auth and profile routes are provided by Fortify and `routes/settings.php`.

## Livewire Components

- `resources/views/pages/restaurant/dashboard.blade.php` is the restaurant dashboard Livewire single-file component and now shows the cached branch/restaurant overview for operational and reporting users.
- `resources/views/components/guest-error-panel.blade.php` renders mobile-first public guest error panels from prepared Livewire state only.
- `resources/views/components/ui/button.blade.php` is the shared Blade button primitive. It forwards normal HTML and `wire:*` attributes.
- `resources/views/components/ui/card.blade.php` is the shared card/surface primitive.
- `resources/views/components/ui/status-badge.blade.php` is the shared status badge primitive.
- `resources/views/components/ui/form-field.blade.php` is the shared simple form field wrapper.
- `resources/views/components/ui/empty-state.blade.php` is the shared empty-state primitive.
- `resources/views/components/ui/alert.blade.php` is the shared warning/success/error/info message primitive.
- `resources/views/components/ui/mobile-bottom-actions.blade.php` is the shared guest mobile bottom action primitive.
- `resources/views/components/ui/area-icon.blade.php` and `resources/views/components/ui/service-point-icon.blade.php` map existing zone/service point types to clear Flux icons.
- `App\Livewire\AuditLogs\Index`
- `App\Livewire\Exports\Index`
- `App\Livewire\Settings\Profile` now includes admin interface language selection.
- `App\Livewire\PublicQr\Show` now includes guest language selection and branch-default language resolution.
- `App\Livewire\PublicQr\GuestMenu` receives/applies the selected guest language.
- `App\Livewire\Notifications\UnreadCount` shows authenticated unread database notification counts and event lists in the app layout and polls its visible block every 5 seconds.
- `App\Livewire\PublicQr\Notifications` shows active guest unread notification counts and event lists on the public QR table page and polls its visible block with `branch_settings.polling_interval_seconds`.
- `App\Livewire\Organizations\Brands\Branches\Index` and `App\Livewire\Organizations\Brands\Branches\Settings` expose supported currency selectors.
- `App\Livewire\PublicQr\GuestMenu` formats guest-facing menu prices with the current branch currency.
- `App\Livewire\Onboarding\RestaurantSetup`
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
- `App\Livewire\PublicQr\JoinRequests` polls visible pending-join blocks with selected columns and bounded result sets.
- `App\Livewire\PublicQr\TableGuests` polls visible guest-list blocks with selected columns and bounded result sets.
- `App\Livewire\PublicQr\GuestMenu`
- `App\Livewire\PublicQr\OrderStatuses` polls only guest-facing draft/order/kitchen-ticket status state.
- `App\Livewire\PublicQr\DraftOrder` polls the visible draft item block and can run without status/totals queries on the active guest page.
- `App\Livewire\PublicQr\DraftTotals` polls guest readiness, per-guest totals, draft/table totals, send-to-waiter, and request-bill controls.
- `App\Livewire\Departments\Dashboard` shared abstract department ticket screen with visible-only 1-second polling for polished kitchen/bar production cards.
- `App\Livewire\Bar\Dashboard`
- `App\Livewire\Kitchen\Dashboard`
- `App\Livewire\Superadmin\Dashboard` shows platform records, service point/order stats, local SQLite backup action, organization aggregate counters, detail/audit links, and organization subscription activate/suspend controls.
- `App\Livewire\Waiter\Dashboard` keeps visible-only 1-second polling for live zone-grouped service-point cards, sessions, sent drafts, waiter calls, bill requests, ready items, and quick open-table actions.
- `App\Livewire\Waiter\TableDetail` keeps visible-only 1-second polling for the current table detail.
- `App\Livewire\Settings\Profile`
- `App\Livewire\Settings\Security`
- `App\Livewire\Settings\Appearance`
- `App\Livewire\Settings\DeleteUserForm`
- `App\Livewire\Settings\TwoFactor\RecoveryCodes`
- `App\Livewire\Actions\Logout`

## Current Design System

- Design primitives are anonymous Blade components under `resources/views/components/ui`.
- The system uses Tailwind utility classes and the existing Flux icon components only.
- No package, route, database table, business action, or Livewire class was added for Prompt 089.
- `x-ui.button` keeps Livewire-friendly attribute forwarding, so `wire:click`, `wire:loading`, `href`, `type`, and `disabled` continue to work from the calling view.
- `x-ui.mobile-bottom-actions` is for guest mobile screens where primary actions should stay easy to reach; Prompt 090 uses it on guest entry, dish configuration, own-item editing, and draft totals.
- `x-ui.area-icon` and `x-ui.service-point-icon` map type values like `hall`, `terrace`, `vip_room`, `table`, `bar_seat`, and `pickup_window` to existing Flux icons.
- First applied screens:
  - public QR guest landing/table/menu views;
  - public QR draft totals bottom actions;
  - branch area CRUD;
  - branch service point CRUD.
- Prompt 090 refined guest-facing views only:
  - `resources/views/layouts/guest.blade.php`;
  - `resources/views/livewire/public-qr/show.blade.php`;
  - `resources/views/livewire/public-qr/guest-menu.blade.php`;
  - `resources/views/livewire/public-qr/table-guests.blade.php`;
  - `resources/views/livewire/public-qr/draft-order.blade.php`;
  - `resources/views/livewire/public-qr/draft-totals.blade.php`;
  - `resources/views/components/ui/mobile-bottom-actions.blade.php`.
- The design system must stay lightweight and shared-hosting friendly. Do not add React, Vue, Inertia, a SPA frontend, heavy UI libraries, WebSockets, Redis, S3, Docker, or external services for UI polish.
- Keep Blade display-only: data must still be prepared by Livewire components/actions, not queried from Blade templates.

## Local Backup Access

- `App\Actions\Backups\ResolveSqliteBackupFileAction` resolves the configured SQLite file and rejects non-SQLite, `:memory:`, missing, or unreadable database paths.
- `App\Http\Controllers\Superadmin\DownloadSqliteBackupController` streams the current SQLite file through `response()->download()` with an ASCII filename.
- The download route is `/superadmin/backups/sqlite` and is named `superadmin.backups.sqlite.download`.
- Access is only through `auth` + `superadmin`; ordinary users must receive `403 Forbidden`.
- The platform dashboard shows a sensitive-data warning and a download button only inside the superadmin area.
- The action does not create backup files on the server. If manual backup copies are created later, keep them outside `public/` and out of git.
- Media ZIP export is not implemented yet; future work should read local files from `storage/app/public` and stay local-only.

## Shared Hosting Deployment Notes

- Deployment guide path is `docs/DEPLOY_SHARED_HOSTING.md`.
- It documents the intended shared-hosting profile: web root at `public`, SQLite file outside public web root, writable `database`, `storage`, and `bootstrap/cache` paths, local public storage, database cache, database sessions, database queue, and optional cron for Laravel scheduler/queue fallback.
- SQLite should use `DB_CONNECTION=sqlite` and a clear absolute `DB_DATABASE` path on shared hosting.
- Cache, sessions, and queues should stay on `CACHE_STORE=database`, `SESSION_DRIVER=database`, and `QUEUE_CONNECTION=database`.
- `public/storage` should point to `storage/app/public` through `php artisan storage:link` when symlinks are available. If symlinks are unavailable, hosting must expose the same files through a control-panel mapping or another local equivalent.
- The guide explicitly rejects Redis, WebSockets, S3, Docker as a required deployment path, external queue services, paid storage, and paid backup/PDF/SMS/push/payment services for this baseline.
- Future deployment-affecting changes should update this document in the same prompt.

## Current Data Export UI

- Data export route is `GET /restaurant/exports` and is named `restaurant.exports.index`.
- CSV download route is `GET /restaurant/exports/branches/{branch}/{export}` and is named `restaurant.exports.download`.
- Allowed export values are `orders`, `payments`, `menu`, and `service-points`.
- Livewire component is `App\Livewire\Exports\Index`.
- Data is prepared by `App\Actions\Exports\BuildDataExportsIndexAction`; Blade receives arrays and must not query the database.
- Branch access is resolved by `App\Actions\Exports\ResolveExportAccessibleBranchIdsAction`, which reuses the existing branch resolver with `SystemPermission::ExportData`.
- Active `branch_users` assignments still narrow export access inside organizations.
- Superadmins can export all branches through the existing superadmin permission bypass.
- `App\Http\Controllers\Restaurant\DownloadBranchCsvExportController` streams CSV downloads through `App\Actions\Exports\StreamBranchCsvExportAction`.
- CSV is generated with `response()->streamDownload()` and `fputcsv`; no export files are written to local storage.
- Current CSV exports cover confirmed order snapshots, manual payments, branch menu items, and service points/tables.
- PDF export is not implemented yet.

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
- Public QR error states now use `resources/views/components/guest-error-panel.blade.php`, with `data-component="guest-error-page"` and a `data-error-state` value for regression coverage.
- If the QR resolves to an organization with an explicitly inactive subscription, the public guest page shows the `restaurant_unavailable` error instead of opening guest ordering.
- Restored guest/session problems such as closed sessions, rejected guests, removed/left guests, and stale invite links use the same guest error component while keeping the existing backend blocking rules intact.
- Public QR route accepts a guest name and can create a pending guest-created table session plus the first active table session guest.
- Public QR route queues a browser cookie with the guest token after creating that first guest.
- Public QR route creates a pending join request instead of a guest when the current table session already has active guests.
- Public QR route creates a pending join request for a specific table session when opened with a valid guest invite token.
- Active guests can create the invite link from the public QR page, share through native browser sharing, or copy the link manually.
- Active guests see a guest table page shell with the venue, current service point, guests, invite action, cached active branch menu with modifier selection, order status, draft positions, and draft totals.
- Active guests can press `Позвать официанта` from the shell; this calls `RequestWaiterForTableSessionAction`, creates or reuses a pending waiter call, and shows a local confirmation.
- Active guests can press `Попросить счёт` from the draft totals block; this calls `RequestBillForTableSessionAction`, changes the session/service point to `payment_requested`, and shows the table total plus per-guest totals.
- The guest list in the shell is rendered by isolated `App\Livewire\PublicQr\TableGuests` and uses `branch_settings.polling_interval_seconds` so the whole page is not refreshed.
- The guest list shows each guest's ready/not-ready state from `table_session_guests.ready_at`.
- The menu in the shell is rendered by `App\Livewire\PublicQr\GuestMenu` and reads active branch menu data through the explicit database cache store.
- Join approvals are rendered by isolated `App\Livewire\PublicQr\JoinRequests` and use the same branch polling interval.
- Guest notifications are rendered by isolated `App\Livewire\PublicQr\Notifications` and use the same branch polling interval.
- Guest order state is rendered by isolated `App\Livewire\PublicQr\OrderStatuses`, which polls only draft/order/kitchen-ticket status fields.
- Draft positions are rendered by isolated `App\Livewire\PublicQr\DraftOrder`, which polls only the draft item list on the active guest page.
- Ready state, draft totals, confirmed-order totals, send-to-waiter, and request-bill controls are rendered by isolated `App\Livewire\PublicQr\DraftTotals`.
- `App\Actions\Branches\GetBranchPollingIntervalAction` reads the branch interval from the SQLite-backed database cache and falls back to 1 second.
- Public QR route restores a guest from that cookie after page refresh and shows closed/blocked status messages when needed.
- Public QR route can also restore a join request from that cookie and show pending/rejected/expired request messages.
- Active guests get a separate polled join-request block for accepting or rejecting waiting guests.
- Waiting guests stay on a clear waiting screen until polling sees approval, rejection, or expiration.
- Public QR route shows the active branch menu for active guests and allows item modifier/comment configuration that persists into `draft_order_items`.
- Public QR route shows the shared table cart grouped by guests alphabetically.
- Public QR route lets active guests edit or delete only their own draft positions from the basket before the draft is sent to waiter review.
- Public QR route lets any active guest send the shared draft to waiter review from the draft totals block.
- Public QR route lets active guests request waiter help, but rejected/removed/left/pending guests cannot request waiter help.
- Public QR route lets active guests request the bill from the draft totals block, but rejected/removed/left/pending guests cannot request the bill.
- Public QR route does not create final orders directly, create payment records, or send anything to kitchen/bar.

## Current Guest Menu Display

- `App\Livewire\PublicQr\GuestMenu` renders the guest menu block inside the active guest table shell.
- `App\Actions\Menus\GetGuestMenuForBranchAction` loads the first active menu for the current branch, sorted by `sort_order`, `name`, and `id`.
- The component exposes a compact `RU` / `EN` / `LT` selector and stores the selected guest language in the `lang` query parameter.
- Guest menu payloads are cached in Laravel's explicit `database` cache store for 300 seconds, even if the default cache store is changed in a test or environment.
- Cache key format is `guest-menu:branch:{branch_id}:language:{language_code}`.
- Rebuild lock key format is `guest-menu:branch:{branch_id}:language:{language_code}:lock` and uses the SQLite-backed `cache_locks` table.
- `ForgetBranchCacheAction` is the central invalidation point for branch-scoped database cache keys.
- `MenuObserver`, `MenuCategoryObserver`, `MenuItemObserver`, `MenuCategoryTranslationObserver`, `MenuItemTranslationObserver`, `KitchenDepartmentObserver`, `ModifierGroupObserver`, `ModifierOptionObserver`, `BranchSettingObserver`, `BranchObserver`, `BrandObserver`, and `OrganizationObserver` route relevant changes through the central branch cache action.
- Updating a dish price, availability, department assignment, kitchen department, modifier, translation, branch setting, or local logo clears branch cache, so the next guest read rebuilds the payload with the current content.
- Guest menu display shows only active categories.
- Guest menu display shows both available and unavailable dishes.
- Unavailable / stop-listed dishes are visually dimmed and marked `Нет в наличии`; they cannot be opened or added to the draft.
- Dish cards are mobile-first cards with a stable image/photo-placeholder area, visible price, availability badge, and large `Добавить` action for active guests.
- Tapping `Добавить` opens a mobile-first bottom sheet inside `App\Livewire\PublicQr\GuestMenu`.
- The item bottom sheet shows the selected dish preview, description when available, modifier groups, comment field, computed item total, and sticky add action.
- The bottom sheet shows assigned modifier groups and only available modifier options.
- Required modifier groups validate `min_select` before the guest can complete the local configuration.
- `price_delta` values from selected options affect the displayed local item total.
- Guests can add a local dish comment up to 500 characters.
- Completing the sheet creates a `draft_order_items` row through `AddGuestDraftOrderItemAction` and shows a local confirmation on the dish card.
- `AddGuestDraftOrderItemAction` rechecks the guest token, active guest status, table session status, service point activity, menu item availability, active category, active menu, and selected modifier option availability before writing.
- Add/update draft item modifier snapshots must use `BuildDraftOrderItemModifierSnapshots` so add and edit rules stay aligned.
- Changing guest menu language clears local confirmation summaries to avoid mixed translated labels.
- The guest menu block is mobile-first and uses stable image dimensions.
- The guest menu block must not poll; menu freshness comes from cache invalidation on admin/backend changes.
- The guest menu block does not create final orders, kitchen tasks, bar tasks, or payment records; it only adds positions to the shared draft.

## Current Guest Draft Basket

- `App\Livewire\PublicQr\DraftOrder` renders the shared draft positions block inside the active guest table shell.
- On the active guest page it is mounted with totals, controls, and status queries disabled; those concerns are handled by separate polling blocks.
- The component is isolated and uses the branch polling interval for `refreshDraft`.
- The component eager-loads only draft items with their guest records before rendering; Blade does not query the database.
- The draft positions block groups guests alphabetically by `guest_name` in `guestSections`.
- Each guest section shows that guest's ready/not-ready state, item count, positions, line prices, selected modifier names, optional comments, quantity, and current-draft guest total.
- `App\Livewire\PublicQr\DraftTotals` renders per-guest totals, current draft total, already confirmed non-cancelled order totals, table total, ready counts, send-to-waiter, and request-bill controls.
- Prompt 090 keeps the primary guest actions in the shared `x-ui.mobile-bottom-actions` bar: `Я готов`, `Отправить официанту`, and `Попросить счёт` remain easy to reach on mobile screens.
- `App\Livewire\PublicQr\OrderStatuses` renders waiter rejection, waiter confirmation, kitchen/bar accepted, cooking, ready, and served guest-facing status.
- All active guests see the same grouped draft and totals information.
- The current active guest can toggle readiness through `DraftTotals`, which calls `ToggleTableSessionGuestReadyAction` and sets or clears `table_session_guests.ready_at`.
- Any active guest can send the shared draft to waiter review through `DraftTotals`, which calls `SendDraftOrderToWaiterAction`.
- If not all active guests have `ready_at` set, `DraftTotals` shows inline confirmation before sending.
- Sending clears active guests' `ready_at`, sets the draft to `sent_to_waiter`, stores sender/timestamp, and updates the service point to `has_new_order`.
- Draft position and totals components receive the public QR token so actions can recheck the saved browser guest token.
- Active guests can edit only their own positions from the draft positions block.
- Editing own positions supports quantity, comment, and currently available modifier selections.
- Active guests can delete only their own positions from the basket.
- Other guests' positions are read-only.
- If the draft status is no longer `draft`, the draft positions block does not expose edit/delete actions.
- If the draft status is `rejected`, `OrderStatuses` shows the waiter rejection reason.
- If the draft status is `converted_to_order`, `OrderStatuses` tells guests that the order was confirmed and editing is closed.
- After a converted draft, adding a new guest menu position creates a new latest draft for the same table session so guests can make a repeat order.
- `UpdateGuestDraftOrderItemAction`, `DeleteGuestDraftOrderItemAction`, and `SendDraftOrderToWaiterAction` enforce the same active guest and draft status checks on the backend.
- Draft item state is read fresh from SQLite on polling refresh and is not cached; database cache is used for menu payloads and the rarely changed branch polling interval.
- Guest totals include confirmed non-cancelled `order_items` snapshots plus the current open draft items.
- Table total includes confirmed non-cancelled `orders.total_price` plus the current open draft total and does not double-count converted drafts.
- Active guests can request the bill from `DraftTotals`; this sets the table session to `payment_requested` and notifies waiters through the database notification table.
- After a draft is converted to an order, `OrderStatuses` shows a guest-facing service status from the confirmed order and ticket items: `Принято`, `Готовится`, `Готово`, or `Подано`.
- Guests only see the shared status. They cannot mark kitchen/bar ticket items ready or served.
- The guest table can submit the draft only to waiter review and does not create final orders or online payments directly.

## Current Restaurant Dashboard

- Restaurant dashboard route is `GET /restaurant/dashboard`.
- The Livewire single-file component is `resources/views/pages/restaurant/dashboard.blade.php`.
- Data is prepared by `App\Actions\Dashboard\BuildRestaurantDashboardAction`; Blade receives arrays and must not query the database.
- Access requires at least one branch resolved through operational or reporting access: `view_orders`, `confirm_orders`, `view_reports`, `manage_menu`, `manage_service_points`, `generate_qr`, kitchen access, or bar access.
- Superadmins keep platform-wide access through the existing branch/department resolvers.
- The dashboard shows active tables, new drafts sent to waiter, cooking orders, ready unserved positions, today's amount, and popular dishes.
- Today's amount and popular dishes are visible only when the user has `view_reports` for at least one branch.
- Waiter/operational users without `view_reports` still see active tables, new waiter drafts, cooking orders, and ready positions.
- Quick actions include menu, tables/service points, QR print, waiter screen, kitchen screen, and reports.
- Quick actions link only when the user has matching access; unavailable actions are disabled instead of linking to forbidden routes.
- The dashboard header and sidebar show an audit log link only when `BuildAuditLogIndexAction::userHasAccess()` allows the current user.
- Dashboard data uses Laravel's explicit `database` cache store for a short operational snapshot and has a manual refresh button.
- The dashboard does not poll every second; keep 1-second polling isolated to waiter/kitchen/bar/guest blocks that need it.
- Cache is invalidated by observers on `orders`, `order_items`, `manual_payments`, `table_sessions`, `draft_orders`, `kitchen_tickets`, and `kitchen_ticket_items`.
- Keep this dashboard shared-hosting friendly: SQLite, database cache, no Redis, no cache tags, no WebSockets, no external BI service.

## Current Audit Log

- Audit log route is `GET /restaurant/audit-log`.
- Livewire component is `App\Livewire\AuditLogs\Index`.
- Data is prepared by `App\Actions\AuditLogs\BuildAuditLogIndexAction`; Blade receives arrays and must not query the database.
- Access requires `view_audit_log` in an active organization context. Active branch assignments limit branch-level audit visibility through the existing branch resolver.
- Superadmins can view all audit rows.
- Sidebar navigation and the restaurant dashboard header show `Audit log` only when the current user has audit access.
- The viewer shows the latest local audit rows with time, actor, action label, entity, organization/branch, and short before/after summaries.
- Logged event types currently include menu price changes, menu availability changes, dish deletion, service point moves, manual QR reissue, staff permission override changes, waiter order confirmation, order cancellation, manual payment recording, and table-session close.
- The audit table is append-only at the application level for this stage; no delete UI exists.
- Audit logs stay in SQLite and do not use external logging, Redis, WebSockets, S3, Docker, or paid services.

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
- Waiter confirmation creates an `order_confirmed` audit row in addition to the detailed `order_status_logs` entry.
- The table detail page uses the latest draft for review/confirm/send-to-kitchen actions, while older converted drafts remain available through order history.
- The table detail page can send a confirmed order to kitchen/bar for users with `send_to_kitchen`. This creates `kitchen_tickets` grouped by department, changes the order to `sent_to_kitchen_bar`, and moves the service point status to `cooking`.
- The table detail page shows dispatched kitchen/bar ticket items, ready count, served count, department names, modifiers, comments, and item status once an order has been sent to kitchen/bar.
- The table detail page lets a waiter with `view_orders` mark ready ticket items as served. This fills `kitchen_ticket_items.served_at` and `served_by_user_id`, updates the order status when all items are served, and refreshes through polling.
- The table detail page shows a manual payment summary for users with `view_payments`, `manage_payments`, or fixed `cashier` access.
- The table detail page can record whole-table or per-guest manual payment for users with `manage_payments` or fixed `cashier` access.
- The table detail page can close a fully paid session for payment managers/cashiers or manually close an unpaid session for users with `close_table_sessions`.
- Manual payment recording creates a `payment_recorded` audit row.
- Closing a table session creates a `table_session_closed` audit row.
- Manual order cancellation through `ChangeOrderStatusAction` creates an `order_cancelled` audit row.
- Closed sessions keep old order history but old guest cookies/invite links cannot add positions because guest draft actions refuse `closed` sessions.
- Confirmed order snapshots keep the original dish names, descriptions, prices, selected modifiers, comments, guest name, and totals even if menu data changes later.
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
- The screen shows large production cards sorted oldest first by `sent_at` and `id`.
- Each card shows service point display/name, zone, ticket creation time, live timer tone, item name, quantity, guest name, modifiers, comments, and item status.
- Visible item actions are intentionally simple: `Начать` moves a new item to `in_progress`; `Готово` moves any not-ready item to `ready`.
- `App\Actions\Kitchen\UpdateKitchenTicketItemStatusAction` changes `kitchen_ticket_items.status` to `new`, `in_progress`, or `ready`.
- Kitchen item status changes call shared order/ticket sync, so waiter and guest polling see `Готовится` or `Готово` without WebSockets.
- Kitchen cannot change a ticket item after the waiter marks it served.
- Ticket-level work status is computed from item statuses for display only.
- The shared screen uses `wire:poll.visible.1s="refreshDepartment"` and does not use WebSockets.
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
- The screen reuses the same large production cards as kitchen, sorted oldest first by `sent_at` and `id`.
- Each bar card shows service point display/name, zone, ticket creation time, live timer tone, drink item name, quantity, guest name, modifiers, comments, and item status.
- Visible item actions are intentionally simple: `Начать` moves a new drink to `in_progress`; `Готово` moves any not-ready drink to `ready`.
- `App\Actions\Bar\UpdateBarTicketItemStatusAction` changes `kitchen_ticket_items.status` to `new`, `in_progress`, or `ready`.
- Bar item status changes use the same shared order/ticket sync as kitchen, so waiter and guest polling see `Готовится` or `Готово` without WebSockets.
- Bar cannot change a ticket item after the waiter marks it served.
- Ticket-level work status is computed from item statuses for display only.
- The shared screen uses `wire:poll.visible.1s="refreshDepartment"` and does not use WebSockets.
- The restaurant sidebar and restaurant dashboard show a bar link only when the current user can access at least one active bar department.
- The screen does not expose unconfirmed drafts, merely confirmed orders, or non-bar department tickets; it reads only bar tickets created by explicit waiter dispatch.

## Current Branch Menu UI

- Branch menu route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/menu`.
- Route model nesting is checked in the Livewire component: brand must belong to organization, and branch must belong to the route brand and organization.
- Access requires branch-level access plus either `manage_menu` or `change_availability` in the current organization context; superadmin bypass still works through computed permissions.
- Full menu CRUD still requires `manage_menu`.
- The stop-list section is visible to users with `change_availability` and lets them temporarily mark dishes out of stock or return them to the menu without exposing menu CRUD.
- Availability-only users can reach this same route from the branch list as `Stop-list`.
- The UI uses Blade + Livewire + Flux components and does not use React, Vue, WebSockets, Redis, S3, Docker, or external media services.
- The page can create, edit, manually sort, and delete menus.
- The page can create, edit, manually sort, activate/deactivate, and delete categories.
- The page can create, edit, manually sort, and delete dishes.
- Dish photo upload/removal uses `StoreLocalImageAction` and `DeleteLocalMediaFileAction` on Laravel's local `public` disk.
- The page eager-loads categories, items, item category labels, item kitchen department labels, item modifier groups, modifier options, modifier item counts, kitchen department item counts, and menu counts; Blade must not query the database.
- Price fields are only shown and applied for users with `change_prices`.
- Availability switches and manual availability changes are only shown and applied for users with `change_availability`.
- Deleting dishes, categories, or menus removes related local dish photos and soft-deletes the database rows so ordinary lists hide them.
- Dish price changes, dish availability changes, and dish deletion create `audit_logs` rows through `MenuItemObserver`.
- Menu/category/item/translation/modifier model observers forget guest menu cache after menu changes.
- The branch list shows a `Menu` action to users with `manage_menu` and a `Stop-list` action to users with `change_availability` only.
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
- Users can access the page when they have branch-level access and can change service point statuses, open tables, or have `generate_qr` in the current organization context.
- CRUD actions still require `manage_service_points`.
- Manual status changes require `manage_service_points` or the fixed `waiter` organization role.
- Opening a table requires `view_orders` or `confirm_orders` in the current organization context.
- The `Open table` button creates or returns the active table session and marks the service point `occupied`.
- Service points with an active table session show an `Active session` badge and a disabled `Table opened` button.
- QR generation and QR detail display require `generate_qr`.
- The UI eager-loads `areaNode`, `activeQrCode`, and `activeTableSession`; Blade must not query the database.
- The QR panel displays `short_code`, status, and `/q/{public_token}` only. It must not expose service point IDs, branch IDs, area names, or table numbers in the QR URL.
- The `Show QR` action opens the QR admin page for the active QR record.
- Moving a service point to another area creates a `service_point_moved` audit row through `UpdateServicePointAction`; renaming without a move does not create that specific audit action.

## Current QR Admin Page

- QR admin route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points/{servicePoint}/qr/{qrCode}`.
- Access requires auth, branch-level access, and `generate_qr` in the current organization context.
- Route model nesting is checked: brand must belong to organization, branch must belong to brand and organization, service point must belong to branch, and QR must belong to service point.
- The page eager-loads current service point and current area before rendering.
- Blade displays prepared state only and must not query the database.
- The page shows branch, current area, current service point, public URL, SVG QR image, short code, status, and creation date.
- `downloadQrImage` streams a local SVG file generated from the public URL.
- `disableQr` changes active QR status to `disabled`.
- `reissueQr` is intentionally dangerous, requires a warning confirmation, revokes the current active QR, and creates one new active QR.
- Manual reissue creates a `qr_reissued` audit row with revoked QR short codes and the new active QR short code.
- The page links to the print template for the same QR record.
- Normal service point edit actions must not call reissue or create a new QR.

## Current QR Print Template

- QR print route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points/{servicePoint}/qr/{qrCode}/print`.
- Access requires auth, branch-level access, and `generate_qr` in the current organization context.
- Route model nesting is checked: brand must belong to organization, branch must belong to brand and organization, service point must belong to branch, and QR must belong to service point.
- The page uses `resources/views/layouts/print.blade.php` instead of the normal admin sidebar layout.
- The sticker is built for browser print first, not PDF generation.
- The printed sticker shows brand/logo, `Сканируйте, чтобы открыть меню`, the QR image, and `short_code`.
- The page has a `preset` URL-backed Livewire setting for `minimal`, `classic`, `restaurant`, `bar`, `hotel`, and `premium`.
- Unknown preset values are normalized back to `minimal`.
- Area name is not printed.
- Service point display number is not printed by default.
- `print_table_number` is a URL-backed Livewire setting for including the display number or service point name.
- When `print_table_number` is enabled, the warning about stale sticker text is visible on screen and hidden in print media.
- Print CSS lives in `resources/css/app.css`; preset classes are named like `qr-sticker-preset-minimal`, and the admin toolbar and warning are hidden in `@media print`.
- No paid PDF service, external QR service, S3, WebSockets, Redis, or Docker is used.

## Current Bulk QR Print Page

- Bulk QR print route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/qr/print`.
- Access requires auth, branch-level access, and `generate_qr` in the current organization context.
- Route model nesting is checked: brand must belong to organization, and branch must belong to brand and organization.
- The page uses `resources/views/layouts/print.blade.php` and remains browser print-friendly first.
- The page lets users filter by all areas, one area node, or service points without an area.
- The page lists service points with prepared `areaNode` and `activeQrCode` relationships; Blade must not query the database.
- Users can select service points that already have an active QR.
- If a shown service point has no active QR, the page offers to create one.
- `createMissingQrForVisible` creates active QR codes for the currently shown missing service points and selects them for print.
- Existing active QR records are reused through `GenerateQrCodeForServicePointAction`; bulk print must not create duplicate active QR codes.
- The printable grid uses local SVG QR images and the same optional local logo behavior as the single sticker template.
- Bulk print uses the same `preset` URL-backed Livewire setting and fixed label preset list as the single sticker page.
- Service point display number is not printed by default.
- `print_table_number` is available on the bulk page too and shows the same stale-label warning when enabled.
- PDF export is still not implemented.

## Current Branch Setup UI

- Branch setup starts at `GET /organizations/{organization}/brands/{brand}/branches`.
- `App\Livewire\Organizations\Brands\Branches\Index` renders the branch list and the `Настроить ресторан` wizard.
- The wizard is a UI guide only; it does not create new models, routes, tables, or background jobs.
- Wizard progress uses existing `area_nodes`, `service_points`, and active `qr_codes` through Eloquent counts/eager loading.
- The wizard links to existing area, service point, bulk QR print, settings, and public QR guest routes.
- Keep the wizard copy simple for non-technical restaurant staff and avoid exposing internal IDs or table/service-point identifiers in public URLs.

## Current Restaurant Onboarding UI

- New restaurant onboarding starts at `GET /onboarding/restaurant`.
- The sidebar and main dashboard expose the entry as `Настроить ресторан`.
- `App\Livewire\Onboarding\RestaurantSetup` stores only wizard step state and created record IDs during the Livewire session.
- The flow is company -> restaurant -> first branch -> first zone -> first tables -> QR -> first menu -> guest-page check.
- The final guest page link is generated from the first active QR code and uses only `/q/{public_token}`.
- The wizard should stay a starter path over existing Actions and models; normal organization, brand, branch, area, service point, QR, and menu CRUD remain the canonical maintenance screens.

## Current Branch Area UI

- Branch area route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/areas`.
- Route model nesting is checked in the Livewire component: branch must belong to the route brand and organization.
- Access requires `manage_zones` in the current organization context; superadmin bypass still works through computed permissions.
- The UI uses Blade + Livewire + Flux components.
- The visible UI copy is simplified: `Зоны ресторана`, `Шаг 2: добавьте зоны`, large preset buttons, and `Список зон`.
- The tree is built in the Livewire component from one eager collection; Blade does not query the database.
- The UI does not show technical IDs to users.
- QR is intentionally not part of this step.

## Current Branch Service Point UI

- Branch service point route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/service-points`.
- Route model nesting is checked in the Livewire component: branch must belong to the route brand and organization.
- Access requires branch-level access and is split between `manage_service_points`, status-changing waiter access, table-opening access, and `generate_qr` access.
- The visible UI copy is simplified: `Столы и места`, `Шаг 3: добавьте столы`, large preset buttons, and plain QR/table actions.
- The same page has a bulk creation section where managers choose zone, type, prefix, number range, and capacity, then preview generated labels before confirming.
- Bulk creation writes generated labels such as `T1` as `name`, `display_number`, and `internal_code`, skips duplicate branch `internal_code` values, and does not create QR automatically.
- The list has server-side search/filter state backed by the URL query string. It searches `name`, `display_number`, `internal_code`, and active QR `short_code`; filters by current route branch, zone, type, status, active/inactive, and active QR presence; and uses pagination rather than loading all branch service points.
- Service point editing still must not change `internal_code` or reissue QR codes when a place is renamed or moved.
- The UI does not show technical IDs to users.

## Next Step

The next recommended prompt is tracked in `docs/NEXT_STEPS.md`. Current recommendation: wait for the next explicit user prompt and do not continue feature work automatically. Keep Prompt 083 SQLite performance guardrails, Prompt 084 split guest polling, Prompt 085 QR/guest session hardening, Prompt 087 important-entity soft deletes, Prompt 088 explicit order item snapshots, Prompt 089 lightweight Blade design-system primitives, Prompt 090 polished guest mobile UI, Prompt 091 polished waiter dashboard UX, Prompt 092 polished shared kitchen/bar UX, Prompt 093 centralized branch cache invalidation, Prompt 094 explicit idempotent demo seed, Prompt 095 manual smoke checklist, Prompt 096 access-control audit guardrails, Prompt 097 shared-hosting deployment notes, Prompt 098 project cleanup guardrails, Prompt 099 vertical-slice regression, Prompt 100 current-version snapshot, Prompt 108 QR label presets, Prompt 109 QR short-code lookup, Prompt 110 service point search filters, and the daily project memory update intact during future feature work.

## Do Not Break

- Do not rewrite architecture.
- Do not add unrelated future features.
- Do not loosen branch-level access: active `branch_users` assignments must continue to narrow branch lists, branch admin pages, waiter/payment/department/export resolvers, QR pages, and branch staff screens unless the user is superadmin.
- Do not add tariff limits, Stripe, PayPal, webhooks, online billing, paid billing providers, external subscription services, or superadmin impersonation unless a future prompt explicitly asks for that exact step.
- Do not delete organization data, restaurants, QR codes, orders, payments, or audit logs when a subscription is deactivated; it is an access/status toggle only.
- Do not physically delete organizations, brands, branches, area nodes, service points, menus, menu categories, or menu items through ordinary UI actions; these important entities use soft deletes.
- Do not turn the `/onboarding/restaurant` wizard into a separate onboarding database schema or duplicate CRUD engine; it must remain a simple starter flow over existing Actions, models, and routes.
- Do not call `DemoRestaurantSeeder` from `DatabaseSeeder` unless a future prompt explicitly changes the seeding strategy; demo restaurant data must stay opt-in.
- Do not reintroduce starter-kit test users in `DatabaseSeeder`; `test@example.com` must stay out of default production seed data.
- Do not make the demo seed change global role-permission defaults for real users; keep demo access scoped to demo users and memberships.
- Do not replace the manual smoke checklist with a heavy browser/E2E dependency unless a future prompt explicitly asks for that testing layer.
- Do not remove or weaken the Prompt 099 vertical-slice regression when editing the main guest/waiter/kitchen/payment/session flow; update it only when a future prompt intentionally changes the flow.
- Do not let `docs/CURRENT_VERSION.md` drift when a future prompt changes the domain map, QR/session flow, shared-hosting mode, current limits, or next-step guardrails.
- Do not turn the `Настроить ресторан` wizard into a separate setup engine unless a future prompt explicitly asks for it; it is currently a simple guide over existing routes and permissions.
- Do not add Redis, WebSockets, S3, Docker, paid services, React, Vue, Inertia, or a separate SPA.
- Do not reintroduce `laravel/sail`, Docker compose files, an S3 filesystem disk, starter-kit repository/documentation links, or placeholder public pages during cleanup.
- Do not replace the small Blade design-system primitives with a heavy UI framework or a client-side SPA.
- Do not send operational notifications through Push, WebSockets, Redis, SMS, Telegram API, mail delivery, or paid notification providers; keep them in Laravel database notifications.
- Do not replace notification UI polling with full-page refreshes or WebSockets; keep updates scoped to Livewire notification blocks.
- Do not remove `wire:poll.visible` from hot polling blocks or increase polling query payloads without a clear reason.
- Do not turn the waiter dashboard back into a flat ungrouped service point list unless a future prompt explicitly asks for that; Prompt 091 groups tables by current zones for restaurant work.
- Do not move draft confirmation/rejection, kitchen/bar dispatch, manual payment, close execution, or served-item execution into waiter dashboard priority cards; the dashboard may link to the existing table detail actions.
- Do not show dashboard close-table links to users without `close_table_sessions`; actual close enforcement must stay in `CloseTableSessionAction`.
- Do not make waiter dashboard polling load unbounded history/logs or analytics; keep its queries selected, eager-loaded, bounded, and focused on current operational state.
- Do not move restaurant dashboard analytics away from SQLite/database cache or make analytics refresh with 1-second polling.
- Do not turn audit/history screens back into unpaginated fixed-size lists; keep cursor pagination or another bounded pagination strategy.
- Do not use Redis cache tags for analytics invalidation; use explicit database-cache keys and model observers.
- Do not send waiter calls through SMS, push, Telegram API, WebSockets, Redis, or an external notification provider.
- Do not create more than one pending waiter call for the same service point.
- Do not send bill requests through online payments, SMS, push, Telegram API, WebSockets, Redis, or an external notification provider.
- Do not create payment records from the guest `Попросить счёт` button; it is only a `payment_requested` status and database notification flow.
- Do not add Stripe, PayPal, online acquiring, or paid payment providers to the manual payment flow.
- Do not allow manual payment while the latest draft is still `draft`, `sent_to_waiter`, or `waiter_review`.
- Do not allow opening a second table session for a service point while its current session is `payment_requested`.
- Do not let closed table sessions accept guest draft items, guest invite joins, or any new guest ordering.
- Do not let configured closed branch hours accept guest draft items or send-to-waiter actions; QR and menu viewing must still stay available.
- Do not let inactive service points accept guest-created sessions, guest invite links, join requests, draft item changes, or send-to-waiter actions.
- Do not replace random hidden QR, guest, or invite tokens with visible IDs, table numbers, names, or short codes.
- Do not reissue, disable, revoke, or regenerate a permanent QR when closing a table session.
- Do not delete or overwrite old orders, order items, manual payments, or order status logs when closing a table session.
- Do not delete or overwrite `audit_logs`; they are the local control journal.
- Do not show audit log rows to users without `view_audit_log` or superadmin access.
- Do not show or allow CSV exports to users without `export_data` or superadmin access.
- Do not export orders, payments, menu, or service points from branches outside the user's resolved export branch set.
- Do not write generated CSV exports to storage unless a future prompt explicitly asks for stored exports.
- Do not add paid CSV/PDF libraries for exports.
- Do not add AI translation, external translate APIs, or paid localization services.
- Do not add unsupported interface languages outside `ru`, `en`, and `lt` without an explicit prompt.
- Do not add exchange-rate APIs, paid currency services, or automatic currency conversion.
- Do not let `branch_settings.default_currency` drift away from `branches.currency` when branch/settings actions change currency.
- Do not expose internal IDs in future QR/public guest URLs.
- Keep public QR URLs token-only as `/q/{public_token}`.
- Do not expose table session IDs in guest invite links.
- Keep guest list polling isolated to the guest list block; do not make the whole guest table page poll.
- Keep guest draft item polling, draft totals polling, join request polling, and order status polling in separate visible Livewire components.
- Keep guest polling intervals sourced from `branch_settings.polling_interval_seconds`; do not hardcode a new interval in guest polling views.
- Do not make the guest menu block poll; menu freshness should come from database cache invalidation.
- Do not add new branch-scoped public cache keys without adding them to `ForgetBranchCacheAction`.
- Do not reintroduce direct scattered guest-menu/polling cache clearing in new code; route branch cache invalidation through `ForgetBranchCacheAction`.
- Do not use Redis cache tags for branch cache invalidation; this project uses explicit database-cache keys.
- Do not add a separate stop-list table while `menu_items.is_available` is enough for temporary dish availability.
- Do not let stop-listed / unavailable menu items be opened or added to guest draft orders.
- Do not change dish availability without clearing guest menu database cache and writing `menu_availability_changed` audit logs.
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
- Do not turn the kitchen/bar production screen back into a dense technical status list unless a future prompt explicitly asks for that; Prompt 092 uses large cards and `Начать` / `Готово` actions for cooks and bartenders.
- Do not switch kitchen or bar screens away from Livewire polling or add WebSockets.
- Do not recalculate old `order_items` from live menu data; confirmed orders must keep immutable snapshots.
- Do not read live `menu_items.name`, `menu_items.description`, `menu_items.price`, or live modifier names/prices when showing old confirmed order content; prefer `OrderItem` explicit snapshots or `historical*` helpers.
- Do not require a live, non-deleted menu item for old confirmed orders to display; deleted dishes must remain visible in old orders through `order_items` snapshots.
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
- Do not add Redis, WebSockets, S3, Docker, external queues, paid storage, or paid deployment services to the shared-hosting deployment notes unless a future prompt explicitly changes the deployment target.
- Do not forget to update `docs/DEPLOY_SHARED_HOSTING.md` when future changes affect writable paths, storage, database drivers, queue/cache/session drivers, scheduler needs, or deployment commands.
- Do not commit `.env`, SQLite database files, backup files, `vendor`, `node_modules`, or storage uploads.
- Do not expose backup downloads outside the `superadmin` middleware.
- Do not add S3, paid backup services, Docker, or external backup storage.
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

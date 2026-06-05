# Smoke Test Checklist

This checklist verifies the main restaurant flow without adding a heavy browser
test framework. Use it after migrations on a local or shared-hosting-like
SQLite setup.

## Rules

- Use SQLite only.
- Use database cache, database sessions, and database queue.
- Do not enable Redis, WebSockets, S3, Docker, Push, SMS, Telegram API, Stripe,
  PayPal, or paid services.
- Do not commit `.env`, `database/database.sqlite`, local uploads, backups,
  `vendor`, or `node_modules`.

## Prompt 124 Guest Order Status Screen Results

Programmatic coverage was added for the existing public QR guest order status
block. The feature currently verifies:

- guests see friendly draft, sent-to-waiter, and waiter-review labels;
- draft positions show readable per-item labels;
- confirmed order positions show accepted, cooking, ready, and served labels
  from existing order and kitchen ticket item state;
- whole-table payment states show `Счёт запрошен` and `Оплачено`;
- the existing isolated Livewire polling block remains in place.

Focused command:

```bash
php artisan test --compact tests/Feature/GuestOrderStatusScreenTest.php
```

Related regression command:

```bash
php artisan test --compact tests/Feature/GuestOrderStatusScreenTest.php tests/Feature/GuestTablePageShellTest.php tests/Feature/ReadyItemsToWaiterTest.php tests/Feature/RepeatOrdersTest.php tests/Feature/OrderCancellationTest.php
```

Manual check:

1. Open an active public QR guest table.
2. Add at least one draft item and confirm the status block shows `Вы выбираете`
   and `В черновике`.
3. Send the draft to the waiter and confirm guests see `Отправлено официанту`
   and item status `Ждёт официанта`.
4. Move the draft through waiter review and confirmation.
5. Send the order to kitchen/bar, mark one item ready, then mark it served.
6. Confirm the guest block shows friendly accepted, cooking, ready, and served
   labels without a full-page refresh.
7. Request the bill and record payment manually; confirm the whole-table status
   shows `Счёт запрошен` and then `Оплачено`.

## Daily Memory Update - 2026-06-05 After Prompt 124

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, and `docs/NEXT_STEPS.md` after
Prompt 124. Guest order statuses are now part of the public QR table baseline.
Future prompts must preserve friendly guest status text, isolated Livewire
polling, waiter confirmation before kitchen/bar dispatch, and SQLite/shared
hosting constraints.
## Rescue Mode Before Prompt 123 Results

The pre-Prompt 123 health check found the waiter table detail/payment flow was
broken before new feature work started:

- failing check: `php artisan test --compact tests/Feature/ManualPaymentTest.php tests/Feature/OrderCancellationTest.php`;
- error: `syntax error, unexpected token "endif"` from the compiled waiter table detail Blade view;
- fix: clear stale compiled Blade views after validating the current source view;
- Prompt 123 manual payment correction was not implemented during rescue mode.

Focused verification after the fix:

```bash
php artisan migrate --no-interaction
php artisan route:list --except-vendor
php artisan config:show database.default
php artisan test --compact tests/Feature/ManualPaymentTest.php tests/Feature/OrderCancellationTest.php
```

Manual check:

1. Open `/restaurant/waiter/tables/{tableSession}` as staff with payment access.
2. Confirm the payment block renders.
3. Confirm order cancellation still shows its confirmation flow and does not break the page.

## Rescue Mode Before Prompt 122 Results

The pre-Prompt 122 health check found the waiter table detail component was
broken before new feature work started:

- failing check: `php artisan test --compact tests/Feature/OrderCancellationTest.php tests/Feature/ManualPaymentTest.php`;
- error: `Class "App\\Livewire\\Waiter\\Flux" not found`;
- fix: add the missing `Flux\\Flux` import to
  `App\\Livewire\\Waiter\\TableDetail`;
- Prompt 122 order item void flow was not implemented during rescue mode.

Focused verification after the fix:

```bash
php artisan migrate --no-interaction
php artisan test --compact tests/Feature/OrderCancellationTest.php tests/Feature/ManualPaymentTest.php
```

Manual check:

1. Open `/restaurant/waiter/tables/{tableSession}` as staff with payment access.
2. Close a paid table session or use the close confirmation flow.
3. Confirm no Flux namespace error appears and the waiter table detail refreshes.

## Prompt 121 Order Cancellation With Reason Results

Programmatic coverage was added in `tests/Feature/OrderCancellationTest.php`.
The feature currently verifies:

- waiter table detail shows `Cancel order` only to staff with cancellation
  access;
- cancellation requires a non-empty reason;
- cancelled orders keep the order row and change `orders.status` to
  `cancelled`;
- `orders.metadata`, `order_status_logs`, and `audit_logs` store the reason and
  ready/served warning counts;
- directors and shift managers can cancel without an explicit `cancel_orders`
  role permission;
- guests see `Заказ отменён.` and the cancellation reason;
- kitchen dashboards hide tickets for cancelled orders;
- direct kitchen/bar ticket item status updates are rejected after cancellation.

Focused command:

```bash
php artisan test --compact tests/Feature/OrderCancellationTest.php
```

Related regression command:

```bash
php artisan test --compact tests/Feature/OrderCancellationTest.php tests/Feature/KitchenScreenTest.php tests/Feature/KitchenTicketDispatchTest.php tests/Feature/WaiterDraftReviewTest.php tests/Feature/WaiterTableDetailTest.php
```

Manual check:

1. Open a table with an order already sent to kitchen/bar.
2. Mark one kitchen/bar position ready.
3. Open `/restaurant/waiter/tables/{tableSession}` as a waiter with
   `cancel_orders`.
4. Confirm the ready/served warning appears.
5. Try cancelling without a reason and confirm validation blocks it.
6. Enter a clear reason and cancel.
7. Confirm guests see the cancelled status and reason in the QR table UI.
8. Confirm kitchen/bar screens no longer show the ticket and direct status
   changes are rejected.

## Daily Memory Update - 2026-06-05 After Prompt 121

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, and `docs/NEXT_STEPS.md` after
Prompt 121. Order cancellation with required reason is now part of the
waiter/order/kitchen/guest baseline. Future prompts must preserve the guardrail
that cancellation keeps order history, status logs, audit logs, guest-facing
reason text, and blocks kitchen/bar work for cancelled orders.

## Prompt 120 Manual Service Charge And Tips Results

Programmatic coverage was extended in `tests/Feature/BranchSettingsTest.php`
and `tests/Feature/ManualPaymentTest.php`. The feature currently verifies:

- `branch_settings.service_charge_percent` exists and defaults to `0.00`;
- branch settings UI can enable service charge, save a percentage, and enable
  manual tips;
- service charge percentage validation rejects values over 100;
- waiter payment summary includes confirmed subtotal, service charge, tips
  enabled state, and remaining bill total;
- recording table payment with tips stores `covered_subtotal_amount`,
  `service_charge_percent`, `service_charge_amount`, `tips_amount`, and total
  collected `amount`;
- payment metadata stores a stable `bill_snapshot`.

Focused command:

```bash
php artisan test --compact tests/Feature/BranchSettingsTest.php tests/Feature/ManualPaymentTest.php
```

Related regression command:

```bash
php artisan test --compact tests/Feature/BranchSettingsTest.php tests/Feature/ManualPaymentTest.php tests/Feature/TableSessionCloseTest.php tests/Feature/VerticalSliceFlowTest.php tests/Feature/AuditLogTest.php tests/Feature/DataExportsTest.php tests/Feature/BasicAnalyticsTest.php
```

Manual check:

1. Open branch settings as staff with branch settings access.
2. Enable service charge, set a percent such as `10.00`, enable tips, and save.
3. Open a payment-requested table in `/restaurant/waiter/tables/{tableSession}`
   as a cashier or staff user with payment access.
4. Confirm the bill summary shows confirmed subtotal, service charge, paid
   total, tips recorded, and remaining total.
5. Enter a tips amount and record a table or guest payment.
6. Confirm the payment history shows subtotal, service charge, tips, payment
   method, and stable paid amount.
7. Confirm no online payment provider, tax logic, external service, Redis,
   WebSocket, S3, Docker, or paid service is involved.

## Daily Memory Update - 2026-06-05 After Prompt 120

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, and `docs/NEXT_STEPS.md` after
Prompt 120. Manual service charge and tips snapshots are now part of the manual
offline payment baseline. Future prompts must preserve the guardrail that tips
are optional extras and do not reduce the required subtotal/service-charge
balance.

## Prompt 119 Split Bill By Guests Results

Programmatic coverage was extended in `tests/Feature/ManualPaymentTest.php`.
The feature currently verifies:

- confirmed payment totals are calculated from confirmed guest `order_items`;
- the waiter table detail payment block exposes per-guest balances;
- unpaid guests are listed after one guest pays;
- guest-scoped `manual_payments` store `table_session_guest_id`;
- when every guest balance is paid, the table session becomes `paid`;
- whole-table payment remains available as a manual offline action.

Focused command:

```bash
php artisan test --compact tests/Feature/ManualPaymentTest.php
```

Related regression command:

```bash
php artisan test --compact tests/Feature/ManualPaymentTest.php tests/Feature/TableSessionCloseTest.php tests/Feature/VerticalSliceFlowTest.php
```

Manual check:

1. Open a payment-requested table in `/restaurant/waiter/tables/{tableSession}`
   as a cashier or staff user with `manage_payments`.
2. Confirm the payment block shows confirmed total, paid total, remaining
   total, per-guest balances, and unpaid guests.
3. Mark one guest paid with cash or card terminal.
4. Confirm the payment history stores that guest and the unpaid guests list now
   shows only the remaining guest.
5. Mark the remaining guest paid.
6. Confirm unpaid guests count is zero and the table session becomes `paid`.
7. Confirm no online payment provider, external service, Redis, WebSocket, S3,
   or Docker dependency is involved.

## Daily Memory Update - 2026-06-05 After Prompt 119

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, and `docs/NEXT_STEPS.md` after
Prompt 119. Split bill by guests is now part of the manual offline payment
baseline. Future prompts must preserve the guardrail that guest payment records
store `manual_payments.table_session_guest_id` and that no online provider is
used unless explicitly requested.

## Prompt 118 Merged Table Sessions Results

Programmatic coverage was added in `tests/Feature/TableSessionMergeTest.php`.
The feature currently verifies:

- a waiter/order staff user can link a free service point to an active table
  session;
- `table_session_service_points` stores the active link and the linked service
  point becomes `occupied`;
- QR public tokens and QR `service_point_id` values for main and linked service
  points do not change;
- an occupied or otherwise unavailable service point cannot be linked;
- waiter table detail exposes `Объединить столы`, available free places, and
  the linked-place list;
- a guest scanning the linked service point QR creates a join request for the
  main active session;
- closing a merged session frees both the main and linked service points and
  marks the link inactive.

Focused command:

```bash
php artisan test --compact tests/Feature/TableSessionMergeTest.php
```

Related regression command:

```bash
php artisan test --compact tests/Feature/TableSessionMergeTest.php tests/Feature/TableSessionTransferTest.php tests/Feature/WaiterOpenTableActionTest.php tests/Feature/TableSessionCloseTest.php tests/Feature/GuestCreatedPendingSessionTest.php tests/Feature/GuestTablePageShellTest.php
```

Manual check:

1. Open an active table in `/restaurant/waiter/tables/{tableSession}` as staff
   with `view_orders` or `confirm_orders`.
2. Confirm the `Объединить столы` block lists only active free places from the
   same branch.
3. Choose a free target place and merge.
4. Confirm the main and linked service points both show `occupied`.
5. Confirm the linked place appears in the waiter table summary and the branch
   service point list/board no longer offers `Open table` for that linked place.
6. Open the linked service point `/q/{public_token}` in a fresh browser, enter a
   guest name, and confirm the guest asks to join the same active session.
7. Close the session and confirm the main and linked service points become
   `free`.
8. Confirm QR public tokens and QR `service_point_id` values for both physical
   places are unchanged.

## Daily Memory Update - 2026-06-05 After Prompt 118

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, and `docs/NEXT_STEPS.md` after
Prompt 118. Merged table sessions are now part of the waiter/guest/QR baseline.
Future prompts must preserve the guardrail that linking physical tables to one
active session never changes permanent QR identity.

## Prompt 117 Active Table Session Transfer Results

Programmatic coverage was added in `tests/Feature/TableSessionTransferTest.php`.
The feature currently verifies:

- a waiter/order staff user can transfer an active session to another free
  service point in the same branch;
- `table_sessions.service_point_id` and `active_service_point_id` move to the
  new service point;
- the old service point becomes `free` and the new service point becomes
  `occupied`;
- QR public tokens and QR `service_point_id` values for both service points do
  not change;
- transfer to an occupied target is rejected and leaves the session unchanged;
- already-entered guests restored from the original QR cookie see the current
  transferred service point;
- a `table_session_transferred` audit event is written.

Focused command:

```bash
php artisan test --compact tests/Feature/TableSessionTransferTest.php
```

Related regression command:

```bash
php artisan test --compact tests/Feature/TableSessionTransferTest.php tests/Feature/TableSessionCloseTest.php tests/Feature/GuestTablePageShellTest.php tests/Feature/AuditLogTest.php tests/Feature/WaiterOpenTableActionTest.php
```

Manual check:

1. Open an active table in `/restaurant/waiter/tables/{tableSession}` as staff
   with `view_orders` or `confirm_orders`.
2. Confirm the `Перенести стол` block lists only active free places from the
   same branch.
3. Choose a free target place and transfer.
4. Confirm the old service point is `free` and the new service point is
   `occupied`.
5. Refresh the guest table page for an already-entered guest and confirm it
   shows the new service point and zone.
6. Confirm existing guests, drafts, orders, and payments remain under the same
   table session.
7. Confirm old and new service point QR public tokens are unchanged.
8. Confirm audit log contains `Table session transferred`.

## Daily Memory Update - 2026-06-04 After Prompt 117

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, and `docs/NEXT_STEPS.md` after
Prompt 117. Active table-session transfer is now part of the waiter/guest/QR
baseline. Future prompts must preserve the guardrail that transferring a
session changes the current session place but never changes permanent QR
identity.

## Prompt 116 Session Inactivity Cleanup Results

Programmatic coverage was added in `tests/Feature/SessionInactivityCleanupTest.php`.
The feature currently verifies:

- a stale empty `pending` table session is marked `cancelled`;
- cleanup metadata is stored under `table_sessions.metadata.cleanup`;
- a pending session with an unpaid order is skipped;
- an inactive `active` session returns a waiter warning but is not closed;
- the manual shared-hosting command `table-sessions:cleanup-inactive` runs successfully.

Focused command:

```bash
php artisan test --compact tests/Feature/SessionInactivityCleanupTest.php
```

Manual check:

1. Open branch settings as a manager who can manage branch settings.
2. Set `Warn waiter after inactivity` and `Cancel empty pending session after`
   to short test values, save, and confirm validation accepts the values.
3. Create or locate an old empty `pending` session and press `Run cleanup now`.
4. Confirm that session becomes `cancelled` and its permanent QR still opens the
   current service point.
5. Create or locate an active table with no recent activity and confirm the
   waiter dashboard shows a `No activity` warning.
6. Confirm the active table is not closed automatically.
7. Create or locate a session with unpaid orders and confirm cleanup skips it.
8. As superadmin, run global cleanup from the platform dashboard.
9. On shared hosting with cron, confirm cron runs `php artisan schedule:run`;
   without cron, use the manual buttons or `php artisan table-sessions:cleanup-inactive`.

## Daily Memory Update - 2026-06-04 After Prompt 116

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, `docs/NEXT_STEPS.md`, and
`docs/DEPLOY_SHARED_HOSTING.md` after Prompt 116. Session inactivity cleanup is
now part of the shared-hosting operations baseline. Future prompts must preserve
the guardrail that active sessions are warned, not auto-closed.

## Prompt 114 Guest Name Conflict Handling Results

Programmatic coverage was added in `tests/Feature/GuestCreatedPendingSessionTest.php`.
The feature currently verifies:

- a duplicate guest display name shows a warning before a join request is
  created;
- suggested names include `Анна 2` and `Анна К.` for an existing active guest
  named `Анна`;
- choosing a suggestion creates a pending join request with the selected display
  name;
- intentionally continuing with the same display name still creates a pending
  join request;
- duplicate display names do not create user accounts and do not change QR,
  invite, table-session, waiter-confirmation, or kitchen/bar rules.

Focused command:

```bash
php artisan test --compact tests/Feature/GuestCreatedPendingSessionTest.php
```

Related guest-flow command:

```bash
php artisan test --compact tests/Feature/GuestCreatedPendingSessionTest.php tests/Feature/GuestInviteShareLinkTest.php tests/Feature/GuestJoinApprovalUiTest.php tests/Feature/PublicQrRouteTest.php
```

Manual check:

1. Open a table with an active guest named `Анна`.
2. Open the same `/q/{public_token}` or invite link in a fresh browser/session.
3. Enter `Анна`.
4. Confirm the warning appears before a join request is created.
5. Confirm `Анна 2`, `Анна К.`, and `Войти как Анна` are visible.
6. Choose `Анна 2` and confirm active guests receive the normal join request.
7. Repeat the flow and choose `Войти как Анна`; confirm identical display names
   are allowed intentionally.
8. Confirm the guest list remains sorted by display name and no public URL
   exposes branch, service point, table, or guest IDs.

## Prompt 113 Manual Waiter Order Entry Results

Programmatic coverage was added in `tests/Feature/WaiterDraftEditingTest.php`.
The feature currently verifies:

- an authorized waiter can open an active table with no current draft;
- the waiter table detail screen shows `Manual waiter order`;
- the waiter can type a new guest name and add a dish with required modifiers;
- the system creates an active `table_session_guest` with manual-entry metadata;
- the system creates a waiter-review `draft_order` with no `sent_by_guest_id`;
- the normal waiter confirmation action converts the draft into an `order`;
- order item snapshots preserve guest name, item name, unit price, modifiers,
  and comment.

Focused command:

```bash
php artisan test --compact tests/Feature/WaiterDraftEditingTest.php
```

Manual check:

1. Open `/restaurant/waiter/tables/{tableSession}` as staff with
   `confirm_orders` for an active table that has no current draft.
2. Confirm the `Manual waiter order` block appears.
3. Type a new guest name, choose a dish, choose required modifiers, set
   quantity/comment, and add the position.
4. Confirm the guest appears in the table list and the draft status becomes
   `Waiter review`.
5. Confirm an active guest already in the same session sees the updated draft
   through polling.
6. Confirm the waiter can press `Confirm order` and a real order is created.
7. Confirm kitchen/bar still does not see the order until `Send to kitchen/bar`
   is pressed.
8. Confirm a user with only `view_orders` cannot manually add a position.

## Daily Memory Update - 2026-06-04 After Prompt 113

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, `docs/TEST_CHECKLIST.md`, and `docs/NEXT_STEPS.md` after
Prompt 113. Manual waiter order entry is now part of the waiter/order-review
baseline. Future prompts must still preserve waiter confirmation before
kitchen/bar dispatch.

## Prompt 112 Waiter Zone Assignment Results

Programmatic coverage was added in `tests/Feature/WaiterZoneAssignmentsTest.php`
and related staff/waiter tests were re-run. The feature currently verifies:

- branch staff managers with `manage_staff` can assign a fixed waiter to active
  branch zones;
- assignments are saved in `area_node_waiters`;
- the waiter dashboard defaults to `My zones`;
- `My zones` hides unassigned-zone service points and related urgent work when
  assignments exist;
- `All zones` shows all accessible branch zones again;
- existing branch staff management and waiter dashboard flows still pass.

Focused command:

```bash
php artisan test --compact tests/Feature/WaiterZoneAssignmentsTest.php tests/Feature/StaffManagementUiTest.php tests/Feature/WaiterDashboardTest.php
```

Manual check:

1. Open branch staff at
   `/organizations/{organization}/brands/{brand}/branches/{branch}/staff` as a
   manager with `manage_staff`.
2. Confirm only fixed `waiter` staff rows show the `Waiter zones` block.
3. Assign the waiter to one zone, save, and reload the page.
4. Log in as that waiter and open `/restaurant/waiter/dashboard`.
5. Confirm `My zones` shows only the assigned zone's service points, sessions,
   sent drafts, waiter calls, bill requests, and ready items.
6. Switch to `All zones` and confirm the other accessible branch zones appear.
7. Remove all zone assignments and confirm `My zones` falls back to all
   accessible branch places with a hint.
8. Confirm superadmin still sees all zones.

## Prompt 111 Simple Visual Floor Board Results

Programmatic coverage was added in `tests/Feature/ServicePointCrudTest.php`.
The feature currently verifies:

- the branch service point page shows the `Визуальный зал` block;
- visible service points are grouped by zone sections, including `Без зоны`;
- cards show service point names, status badges, active QR short code, and
  quick actions;
- the board edit action focuses the existing edit form through
  `startEditingFromBoard`;
- the existing service point search/filter/pagination tests still pass.

Focused command:

```bash
php artisan test --compact tests/Feature/ServicePointCrudTest.php
```

Manual check:

1. Open a branch service point page.
2. Confirm `Визуальный зал` appears above the paginated list.
3. Confirm visible places are grouped into zone sections and cards show type
   icon, status badge, active/disabled state, and QR badge.
4. Use quick actions: open table, show/create QR, and edit.
5. Confirm edit focuses the existing form and does not change QR identity.
6. Search/filter the list and confirm the board follows the same loaded page.
7. Use pagination and confirm the page does not load every service point at
   once.

## Prompt 110 Service Point Search Filters Results

Programmatic coverage was added in `tests/Feature/ServicePointCrudTest.php`.
The feature currently verifies:

- branch service point search works by active QR `short_code`;
- filters work for zone, type, status, active/inactive state, and active QR
  presence;
- the list stays on the current branch route;
- pagination limits the initial result set and can move to the next page.

Focused command:

```bash
php artisan test --compact tests/Feature/ServicePointCrudTest.php
```

Manual check:

1. Open a branch service point page.
2. Confirm the visible branch filter matches the current route branch.
3. Search by service point name, display number, internal code, and printed QR
   `short_code`.
4. Filter by zone, type, status, active/inactive state, and has QR / no QR.
5. Confirm only matching rows are shown and no technical IDs appear in the UI.
6. Confirm pagination next/previous links work without loading all rows.
7. Confirm create, edit, status change, open table, show QR, and create QR
   actions still work after filters are reset.

## Prompt 109 QR Short Code Lookup Results

Programmatic coverage was added in `tests/Feature/QrShortCodeLookupTest.php`.
The feature currently verifies:

- `/restaurant/qr-lookup` requires authentication and `generate_qr` access;
- a manager can find an existing QR by printed `short_code`;
- lookup shows branch, current zone, service point, QR status, and public URL;
- lookup is scoped to accessible branches and does not reveal another branch;
- searching does not change QR token, short code, status, or create a second QR;
- disable and manual reissue actions work from the lookup page and keep the
  one-active-QR rule.

Focused command:

```bash
php artisan test --compact tests/Feature/QrShortCodeLookupTest.php
```

Manual check:

1. Open `/restaurant/qr-lookup` as a user without `generate_qr` and confirm
   access is forbidden.
2. Open the same page as a user with `generate_qr`.
3. Search a printed short code such as `QR-8F92`.
4. Confirm branch, zone, service point, QR status, and public URL are shown.
5. Open the QR admin page and guest URL from the result.
6. Confirm repeating the search does not change `public_token`, `short_code`,
   QR status, or active QR count.
7. Disable an active QR from the lookup page and confirm the status changes to
   disabled.
8. Reissue a QR only after the warning and confirm the old QR is revoked and one
   new active QR exists.

## Prompt 108 QR Label Design Presets Results

Programmatic coverage was added to existing QR print tests. The feature
currently verifies:

- single QR print defaults to the `minimal` preset;
- all fixed presets render: `minimal`, `classic`, `restaurant`, `bar`, `hotel`,
  and `premium`;
- preset switching does not print mutable service point/table text by default;
- preset switching does not change QR token, short code, status, or create a
  second QR;
- branch bulk QR print applies the selected preset to selected stickers.

Focused command:

```bash
php artisan test --compact tests/Feature/QrPrintTemplateTest.php tests/Feature/BulkQrPrintTest.php
```

Manual check:

1. Open a service point QR print page.
2. Switch through all six label design presets.
3. Confirm the logo or brand text, `Сканируйте, чтобы открыть меню`, QR image,
   and `short_code` remain visible.
4. Confirm service point number and area are hidden by default.
5. Enable `Print table number` and confirm the stale-sticker warning appears.
6. Use browser print preview and confirm only the sticker prints, not the admin
   toolbar.
7. Open branch bulk QR print, select several service points with active QR, pick
   a preset, and confirm the same design is applied to every selected sticker.
8. Confirm no PDF service, external QR service, Redis, WebSockets, S3, Docker,
   or heavy print library is involved.

## Prompt 280 Functional Consistency Results

Programmatic coverage was re-run for menu, guest, staff, departments, payments,
access control, and the vertical slice. A small waiter-side consistency fix was
added so items from an active but currently unavailable scheduled menu are not
offered in the waiter add-item list and cannot be added through the backend
Action.

Focused command:

```bash
php artisan test --compact tests/Feature/MenuScheduleTest.php tests/Feature/GuestMenuDisplayTest.php tests/Feature/MenuCrudTest.php tests/Feature/BranchCacheInvalidationTest.php tests/Feature/GuestTablePageShellTest.php tests/Feature/VerticalSliceFlowTest.php tests/Feature/WaiterDraftReviewTest.php tests/Feature/WaiterDraftEditingTest.php tests/Feature/KitchenTicketDispatchTest.php tests/Feature/KitchenScreenTest.php tests/Feature/BarDepartmentScreenTest.php tests/Feature/ManualPaymentTest.php tests/Feature/TableSessionCloseTest.php tests/Feature/AccessControlAuditTest.php
```

Manual check:

1. Create or choose an active breakfast menu with an `08:00-12:00` schedule.
2. Open a guest QR page outside that interval and confirm breakfast dishes are
   not orderable.
3. Open waiter table detail for a sent draft outside that interval and confirm
   breakfast dishes are not offered in `Add item`.
4. Confirm modifier-required dishes still require a selected option before
   adding.
5. Confirm the guest cart groups current draft items by guest and shows
   confirmed order totals separately.
6. Confirm kitchen and bar screens show only tickets for their accessible
   departments.
7. Confirm cashier/payment staff can record whole-table and guest payments, then
   close the table session manually.

Current known future-product gaps from the Prompt 280 checklist:

- dedicated menu variants are not separate records yet; modifier groups/options
  are the current variant-like mechanism;
- dedicated menu tags/allergens are not implemented yet;
- shared payment allocations are not implemented yet.

Do not add those during consistency/bugfix prompts without a separate explicit
scope.

## Daily Memory Update - 2026-06-04 After Prompt 110

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, and `docs/NEXT_STEPS.md` after Prompt 110. Branch service
modes, bulk service point creation, QR label design presets, QR short-code
lookup, and branch service point search/filter pagination are now part of branch
setup and QR administration. The next step should come from the next explicit
user prompt.

After Prompt 102, treat branch opening hours as part of the normal guest QR
smoke flow: QR/menu viewing stays available while closed, but ordering is
blocked when a configured schedule says the branch is closed.

After Prompt 101, include the public restaurant profile in setup smoke checks:
branch settings should save public name, description, local logo/cover, contact
links, default language, and default currency, and the QR landing should show
those values or polished fallback text.

After Prompt 102, include branch opening hours in setup and guest smoke checks:
branch settings should save closed days and several intervals per day, the QR
landing should show whether the restaurant is open or closed, and guest ordering
should be blocked while a configured branch is closed.

After Prompt 103, include temporary branch closed mode in setup and guest smoke
checks: branch settings should save a closure reason and optional until time,
the QR landing should still open, menu browsing should remain available, new
guest ordering should be blocked, and waiter/order-access staff should be able
to reopen ordering.

After Prompt 104, include menu schedules in menu and guest smoke checks:
branch menu admin should save weekday intervals per menu, the guest menu should
show only menus available in the branch timezone, and adding/sending draft items
should be blocked when the selected menu is outside its schedule.

After Prompt 105, include multiple branch menus in menu and guest smoke checks:
create several active menus, confirm the guest UI groups currently available
menus by menu name and sort order, confirm inactive menus are hidden, and
confirm menus scheduled for later show only a next-availability hint.

After Prompt 106, include service modes in branch settings smoke checks:
confirm `dine_in` is enabled by default, enable several service modes, save,
reload the page, and confirm selections persist without changing QR/table
behavior.

After Prompt 107, include bulk service point creation in setup smoke checks:
preview a range such as `T1..T20`, confirm duplicates are skipped, create the
missing service points, and confirm QR codes are not generated automatically.

After Prompt 108, include QR label presets in QR print smoke checks: open single
and bulk QR print pages, switch through `minimal`, `classic`, `restaurant`,
`bar`, `hotel`, and `premium`, confirm browser print preview works, and confirm
service point number/area are not printed unless `print_table_number` is enabled.

After Prompt 109, include QR short-code lookup in QR admin smoke checks: search
an existing printed code, confirm the result is branch-scoped and current, and
confirm lookup itself does not change QR identity.

Use these focused checks after documentation-only maintenance:

```bash
php artisan migrate --no-interaction
php artisan route:list
php artisan test --compact tests/Feature/ProjectCleanupConsistencyTest.php tests/Feature/VerticalSliceFlowTest.php
```

Use the full checks before larger code changes or before handing off a release:

```bash
php artisan test --compact
npm run build
```

The next recommended prompt is documented in `docs/NEXT_STEPS.md`. Do not
implement it until the user explicitly requests it.

## Prompt 106 Service Modes Results

Programmatic coverage was added in `tests/Feature/BranchSettingsTest.php`.
The feature currently verifies:

- `branch_settings` has a `service_modes` column;
- new branches default to `['dine_in']`;
- branch settings UI shows service modes;
- branch owners can save pickup, delivery, hotel room service, bar-only, and
  custom modes;
- unknown service mode values are rejected by validation.

Focused command:

```bash
php artisan test --compact tests/Feature/BranchSettingsTest.php
```

Manual check:

1. Open branch settings.
2. Confirm `Dine-in` is selected by default.
3. Enable pickup, delivery, hotel room service, bar only, or custom modes.
4. Save settings.
5. Reload the settings page and confirm selections persist.
6. Open an existing QR guest page and confirm normal dine-in table behavior is
   unchanged.

## Prompt 107 Bulk Service Point Creation Results

Programmatic coverage was added in `tests/Feature/ServicePointCrudTest.php`.
The feature currently verifies:

- a manager with `manage_service_points` can preview generated service point
  codes before creating them;
- duplicate branch `internal_code` values are shown as already existing and are
  skipped;
- created service points store the generated code as `name`, `display_number`,
  and `internal_code`;
- created service points do not get QR codes automatically;
- a waiter without `manage_service_points` cannot run bulk creation.

Focused command:

```bash
php artisan test --compact tests/Feature/ServicePointCrudTest.php
```

Manual check:

1. Open branch service points.
2. Choose a zone.
3. Choose `table`.
4. Enter prefix `T`, from `1`, to `20`, and capacity `4`.
5. Click preview and confirm `T1..T20` are shown.
6. Create one duplicate manually and confirm the preview marks it as already
   existing.
7. Confirm creation creates only missing service points.
8. Confirm no QR is created automatically.
9. Open the existing bulk QR print page when ready to generate QR.

## Prompt 105 Multiple Menus Results

Programmatic coverage was added to `tests/Feature/MenuScheduleTest.php`.
The feature currently verifies:

- a branch can expose several active menus to the guest UI at the same time;
- available menus are sorted by `sort_order`, `name`, and `id`;
- guest menu data is grouped by menu while keeping the old first-menu
  `menu`/`categories` payload for compatibility;
- draft or inactive menus are hidden from guests;
- active menus outside their schedule do not expose dishes but can show a next
  availability hint;
- cached guest menu data still uses the database cache store.

Focused command:

```bash
php artisan test --compact tests/Feature/MenuScheduleTest.php tests/Feature/GuestMenuDisplayTest.php
```

Manual check:

1. Open a branch menu page.
2. Create several active menus such as Main, Breakfast, Business lunch, Bar,
   Wine card, Kids, Seasonal, and Special.
3. Give them different sort orders.
4. Add a schedule to Breakfast and Business lunch.
5. Open a QR guest page during the breakfast interval.
6. Confirm Breakfast and unscheduled active menus are visible and grouped by
   menu.
7. Confirm Business lunch shows only `Будет доступно позже` before lunch.
8. Confirm draft or archived menus are hidden from guests.
9. Confirm dishes from a scheduled-later menu cannot be added from stale tabs.

## Prompt 104 Menu Schedules Results

Programmatic coverage was added in `tests/Feature/MenuScheduleTest.php`.
The feature currently verifies:

- `menu_availability_schedules` stores weekday menu intervals;
- availability checks respect `branches.timezone`;
- the guest menu returns only menus available right now;
- unavailable menus show a next availability message;
- menu schedule changes clear the SQLite database cache;
- branch menu admin can add and delete intervals;
- guest draft item creation and send-to-waiter are blocked when a scheduled
  menu is unavailable.

Focused command:

```bash
php artisan test --compact tests/Feature/MenuScheduleTest.php
```

Manual check:

1. Open a branch menu page.
2. Create or choose an active breakfast menu and add a Monday `08:00-12:00`
   interval.
3. Create or choose an active lunch menu and add a Monday `12:00-16:00`
   interval.
4. Open a QR guest page during the breakfast interval and confirm only the
   breakfast menu appears.
5. Open the same QR page outside both intervals and confirm it shows
   `Меню сейчас недоступно` with the next availability time.
6. Try to add a breakfast item after the breakfast window ends and confirm it
   is blocked.
7. Try to send an old draft after the menu window ends and confirm it is
   blocked before reaching the waiter.

## Prompt 103 Temporary Branch Closed Mode Results

Programmatic coverage was added in `tests/Feature/BranchTemporaryClosedModeTest.php`.
The feature currently verifies:

- `branches` stores temporary closure fields;
- branch settings can enable and disable temporary closure;
- temporary closure takes priority over opening hours;
- expired temporary closure no longer blocks opening-hours status;
- public QR still opens while temporarily closed;
- guests can view the menu but cannot add draft items while temporarily closed;
- sending a draft to the waiter is blocked while temporarily closed;
- waiter dashboard shows the closure warning and can reopen ordering.

Focused command:

```bash
php artisan test --compact tests/Feature/BranchTemporaryClosedModeTest.php
```

Manual check:

1. Open branch settings.
2. Enable temporary closure.
3. Enter a reason such as `Технические работы`.
4. Optionally enter a `closed until` date/time in the branch timezone.
5. Save settings.
6. Open a QR URL for the branch.
7. Confirm the QR landing shows `Ресторан временно закрыт` and the reason.
8. Confirm the menu remains visible.
9. Confirm adding a dish and sending a draft to the waiter are blocked.
10. Open the waiter dashboard and confirm the branch warning is visible.
11. Click `Открыть заказы` and confirm new ordering is available again.

## Prompt 102 Branch Opening Hours Results

Programmatic coverage was added in `tests/Feature/BranchOpeningHoursTest.php`.
The feature currently verifies:

- `branch_opening_hours` stores weekly branch schedules;
- one day can have several opening intervals;
- a day can be marked closed;
- branch settings can save the schedule;
- status checks respect the branch timezone;
- the public QR page still opens when the branch is closed;
- guests can view the table/menu while closed;
- guest draft item creation and send-to-waiter are blocked while a configured
  branch schedule is closed.

Focused command:

```bash
php artisan test --compact tests/Feature/BranchOpeningHoursTest.php
```

Manual check:

1. Open branch settings.
2. Enable opening hours.
3. Configure Monday with two intervals, for example `10:00-14:00` and
   `18:00-22:00`.
4. Mark one weekday as closed.
5. Save settings.
6. Open a QR URL for the branch.
7. Confirm the QR landing/table UI shows `Сейчас открыто` during an open
   interval.
8. Confirm it shows `Сейчас закрыто` and `Откроется в ...` outside an open
   interval.
9. Confirm the menu remains visible while closed.
10. Confirm adding a dish and sending a draft to the waiter are blocked while
    closed.

## Prompt 101 Restaurant Public Profile Results

Programmatic coverage was added in `tests/Feature/RestaurantPublicProfileTest.php`.
The feature currently verifies:

- `branches` stores public profile fields for venue name, description, cover
  image, phone, email, website, Instagram, Facebook, and TikTok;
- a branch manager can update the profile from the existing branch settings
  page;
- logo and cover uploads are stored locally on the `public` disk;
- default language and default currency remain local branch/settings data;
- public `/q/{public_token}` landing shows the branch public profile;
- missing profile details show tidy fallback text;
- the QR URL stays token-only.

Focused command:

```bash
php artisan test --compact tests/Feature/RestaurantPublicProfileTest.php
```

Manual check:

1. Open branch settings.
2. Fill public venue name, short description, phone, email, website, and social
   links.
3. Upload a logo and cover image.
4. Save settings.
5. Open a service point QR URL `/q/{public_token}`.
6. Confirm the landing page shows the public profile, local images, default
   language, default currency, current zone, and current service point.
7. Clear optional profile/contact fields and confirm the guest fallback text is
   still polished.

## Prompt 096 Access Control Results

Programmatic audit coverage was added in `tests/Feature/AccessControlAuditTest.php`.
The audit currently verifies:

- ordinary users see only their own organizations;
- branch-assigned employees cannot see or open another branch without access;
- waiter-style users without `change_prices` cannot change menu item prices;
- cooks cannot open staff management;
- marketers cannot resolve order-confirmation access;
- accountants can view payment branches but cannot manage payments or edit menu;
- superadmins bypass organization and branch restrictions.

Focused command:

```bash
php artisan test --compact tests/Feature/AccessControlAuditTest.php
```

## Prompt 098 Cleanup Results

Programmatic cleanup coverage was added in `tests/Feature/ProjectCleanupConsistencyTest.php`.
The cleanup currently verifies:

- resolved infrastructure stays shared-hosting friendly: SQLite, local public storage, no Redis, no S3 disk, no Pusher/Reverb WebSocket driver;
- Composer and Node dependencies do not include Docker/Sail, Redis, Pusher, WebSocket, or S3 client packages;
- default `php artisan db:seed` does not create the starter `test@example.com` user;
- public entry pages do not show starter placeholder or "not implemented yet" copy.
- routes, migrations, model naming, seeders, and current policy usage were reviewed without adding new business routes or tables.

Focused command:

```bash
php artisan test --compact tests/Feature/ProjectCleanupConsistencyTest.php
```

## Prompt 099 Vertical Slice Results

Programmatic first-slice coverage was added in `tests/Feature/VerticalSliceFlowTest.php`.
The regression currently verifies:

- real user registration through Fortify;
- organization, brand, branch, zone, service point, and permanent QR setup;
- public QR URL shaped as `/q/{public_token}` without technical IDs;
- guest name entry and guest-created pending table session;
- second guest invite link, waiting state, and first-guest approval;
- guest menu item selection and shared draft totals by guest;
- sending the shared draft to the waiter;
- waiter confirmation and explicit kitchen/bar dispatch;
- kitchen and bar ready states;
- waiter served handoff;
- guest bill request;
- manual table payment;
- table-session close;
- service point returns to `free`;
- permanent QR token and active status stay unchanged after close.

Focused command:

```bash
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php
```

Affected-flow command used for Prompt 099:

```bash
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php tests/Feature/OnboardingRestaurantWizardTest.php tests/Feature/GuestCreatedPendingSessionTest.php tests/Feature/GuestInviteShareLinkTest.php tests/Feature/GuestJoinApprovalUiTest.php tests/Feature/GuestMenuDisplayTest.php tests/Feature/WaiterDraftReviewTest.php tests/Feature/KitchenTicketDispatchTest.php tests/Feature/KitchenScreenTest.php tests/Feature/BarDepartmentScreenTest.php tests/Feature/ReadyItemsToWaiterTest.php tests/Feature/BillRequestTest.php tests/Feature/ManualPaymentTest.php tests/Feature/TableSessionCloseTest.php
```

## Prepare

1. Run migrations:

```bash
php artisan migrate
```

2. Seed system data and demo restaurant:

```bash
php artisan db:seed
php artisan db:seed --class=DemoRestaurantSeeder
```

The default seeder creates system data only. Demo users are created only by
`DemoRestaurantSeeder`.

3. Confirm the expected drivers:

```bash
php artisan config:show database.default
php artisan config:show cache.default
php artisan config:show session.driver
php artisan config:show queue.default
```

Expected values:

- `database.default`: `sqlite`
- `cache.default`: `database`
- `session.driver`: `database`
- `queue.default`: `database`

4. Demo staff accounts:

```text
demo.owner@example.com
demo.admin@example.com
demo.waiter@example.com
demo.chef@example.com
demo.bartender@example.com
demo.cashier@example.com
```

Default password:

```text
password
```

## Setup Flow

Use `demo.admin@example.com` or `demo.owner@example.com`.

1. Log in.
2. Open `Organizations`.
3. Create or open an organization.
   - Demo seed already creates `Demo Food Group`.
4. Create or open a brand.
   - Demo seed already creates `Bella Pizza`.
5. Create a branch.
   - Example name: `Smoke Test Branch`.
   - Use `EUR`, `Europe/Vilnius`, active state.
6. Open branch settings.
7. Fill or confirm the public restaurant profile:
   - public venue name;
   - short description;
   - logo;
   - cover image;
   - phone/email/site/social links if available;
   - default language;
   - default currency.
8. Confirm safe defaults:
   - waiter confirmation required for orders;
   - guest-created sessions allowed;
   - waiter-opened sessions allowed;
   - guest invite links allowed;
   - new guests require approval;
   - polling interval is 1 second.
9. Configure branch opening hours or leave them disabled intentionally.
   - If enabled, include at least one open day with two intervals and one closed
     day.
10. Open branch zones.
11. Create a zone.
   - Example name: `Smoke Main Hall`.
12. Open branch service points.
13. Create a service point.
    - Type: table.
    - Example name: `Smoke Table 1`.
    - Capacity: 4.
    - Assign it to `Smoke Main Hall`.
14. Generate QR for the service point.
15. Confirm the QR page shows:
    - branch;
    - current zone;
    - current service point;
    - public URL;
    - QR image;
    - short code.
16. Open the public QR URL.
17. Confirm the URL is `/q/{public_token}` and does not expose organization,
    branch, service point, table ID, table number, or area ID.
18. Confirm the QR landing shows the public restaurant profile or tidy fallback
    text when optional profile details are empty.
19. Confirm the QR landing shows current opening-hours status when a schedule
    is configured.

## Menu Flow

Use `demo.admin@example.com` or `demo.owner@example.com`.

1. Open the branch menu page.
2. Create or confirm an active menu.
3. Create at least one category.
   - Example: `Pizza`.
4. Create at least two dishes.
   - Example: `Margherita`, `Pepperoni`.
5. Assign dishes to departments.
   - Food to kitchen.
   - Drink to bar if testing bar flow.
6. Confirm prices are visible in the branch currency.
7. Confirm unavailable dishes cannot be added by guests.

## Guest Flow

Use two different browser sessions. For example:

- normal browser window for Guest 1;
- private/incognito browser window for Guest 2.

1. Open the QR URL from the setup flow or from the demo seed.
2. Guest 1 enters a name.
   - Example: `Anna`.
3. Submit the guest entry form.
4. Confirm a table session is created.
5. Confirm Guest 1 sees the active table page.
6. Confirm the page shows:
   - venue name;
   - current zone;
   - current service point;
   - current opening-hours status;
   - guest list;
   - menu;
   - shared draft/cart area;
   - table total.
7. Guest 1 clicks `Invite guest`.
8. Copy or share the invite link.
9. Open the invite link in the second browser session.
10. Guest 2 enters a name.
    - Example: `Boris`.
11. Confirm Guest 2 sees a waiting screen.
12. Return to Guest 1.
13. Confirm Guest 1 sees the join request through polling.
14. Guest 1 approves Guest 2.
15. Confirm Guest 2 becomes active without creating a user account.
16. Confirm guests are listed alphabetically.
17. Guest 1 adds a dish.
18. Guest 2 adds a different dish.
19. Confirm both guests see the shared draft.
20. Confirm each guest sees:
    - own positions;
    - the other guest's positions;
    - own total;
    - other guest total;
    - full table total.
21. Confirm Guest 1 cannot edit Guest 2's positions.
22. Mark guests ready if needed.
23. Send the shared draft to the waiter.
24. Confirm guest editing is blocked after sending.
25. Confirm the service point status becomes `has_new_order` or an equivalent
    visible new-order state.

## Waiter Flow

Use `demo.waiter@example.com`.

1. Log in as waiter.
2. Open the waiter dashboard.
3. Confirm the branch is visible.
4. Confirm service points are grouped by zone.
5. Confirm the new draft/order appears through polling.
6. Open the table detail page.
7. Confirm the waiter sees:
   - branch;
   - zone;
   - service point;
   - session status;
   - guests alphabetically;
   - positions grouped by guest;
   - modifiers;
   - comments;
   - guest totals;
   - table total;
   - draft status.
8. Confirm the draft.
9. Confirm a real order is created.
10. Confirm the order status is `confirmed_by_waiter`.
11. Send the order to kitchen/bar.
12. Confirm the order status is `sent_to_kitchen_bar`.
13. Confirm service point status moves to a cooking/in-progress state.

## Kitchen Or Bar Flow

Use the right staff account:

- kitchen: `demo.chef@example.com`;
- bar: `demo.bartender@example.com`.

1. Log in as kitchen or bar staff.
2. Open the kitchen or bar dashboard.
3. Confirm only relevant department tickets are visible.
4. Confirm each ticket shows:
   - service point;
   - zone;
   - item names;
   - modifiers;
   - comments;
   - timer;
   - status.
5. Start an item.
6. Mark the item ready.
7. Confirm the waiter dashboard/table detail sees the ready item through
   polling.

## Served Flow

Use `demo.waiter@example.com`.

1. Open the waiter table detail.
2. Confirm ready items are visible.
3. Mark ready items as served.
4. Confirm guests see an accepted/cooking/ready/served style status update.
5. Confirm service point status updates away from ready-to-serve when all ready
   items are served.

## Bill And Close Flow

Use the guest page and then waiter/cashier.

1. Guest clicks `Request bill`.
2. Confirm guests still see:
   - table total;
   - each guest total.
3. Confirm waiter dashboard shows a bill request through polling.
4. Log in as `demo.cashier@example.com` or a waiter with payment rights.
5. Record manual payment.
   - Method: cash, card terminal, or other.
6. Confirm the table session moves to `paid` after full payment.
7. Close the table session.
8. Confirm:
   - session status is `closed`;
   - service point status is `free`;
   - old guest page cannot add more positions;
   - QR public URL still opens the same permanent QR entry point;
   - a new seating creates a new session for the same service point.

## Pass Criteria

- No technical IDs are exposed in guest QR URLs.
- One service point has only one active permanent QR.
- QR does not change after service point rename or area move.
- Guest tokens do not require user accounts.
- New guests require approval when active guests already exist.
- Shared draft is visible to all active guests.
- Guests can edit only their own draft items.
- Draft does not reach kitchen/bar until waiter confirmation and dispatch.
- Kitchen/bar sees only dispatched department tickets.
- Ready items reach waiter through polling.
- Payment and close are manual/offline only.
- Realtime behavior is Livewire polling only.
- No Redis, WebSockets, S3, Docker, or paid services are required.

## Quick Regression Commands

These are optional command-line checks and do not replace the manual flow:

```bash
php artisan test --compact tests/Feature/AccessControlAuditTest.php
php artisan test --compact --filter=DemoRestaurantSeederTest
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php
php artisan test --compact
```

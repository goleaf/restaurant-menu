# Functional Test Strategy

This document defines the mandatory feature-test coverage for the core
restaurant flow. It is a testing strategy only; it does not introduce product
features or change business behavior.

## Purpose

The project is too large to validate only by manual smoke checks. Every core
business scenario must have automated feature coverage in `tests/Feature`,
preferably through Pest tests that exercise the same HTTP routes, Livewire
components, Actions, models, and policies used by production flows.

Functional tests must protect the full guest-to-payment lifecycle:

1. Permanent QR opens the correct service point.
2. Guests enter and moderate table access safely.
3. Guests build a shared draft without editing each other's items.
4. Waiters review, edit, reject, or confirm submitted drafts.
5. Kitchen and bar departments process only their own work.
6. Manual payment and table close preserve correct totals.
7. Permissions keep staff inside their branch and role boundaries.

## Test Rules

- Use feature tests for user-visible flows and unit tests only for isolated
  calculation or Action edge cases.
- Use `RefreshDatabase` and model factories. Do not manually insert rows with
  raw SQL.
- Prefer Livewire component tests for Livewire screens and HTTP tests for
  public or routed page access.
- Keep test setup explicit: organization, brand, branch, area, service point,
  QR code, menu, departments, guests, draft, order, payment, and staff role.
- Exercise both successful and denied paths for every guarded workflow.
- Assert database state, rendered messages, and authorization results when a
  scenario crosses UI, business logic, and persistence.
- Keep tests shared-hosting friendly: SQLite, database cache, database sessions,
  database queue, local storage, and Livewire polling only.
- Do not require Redis, WebSockets, S3, Docker, Stripe, PayPal, paid services,
  React, Vue, Inertia, or a separate browser test framework for baseline
  functional coverage.

## Fixture Baseline

Most flow tests should start from a small reusable restaurant fixture:

- one organization with one brand;
- two branches for access-isolation checks;
- one area and at least two service points;
- one active permanent QR code per service point;
- active branch settings that allow the tested guest or waiter behavior;
- one active menu with available items assigned to kitchen and bar departments;
- staff users for waiter, cook, bartender, cashier, branch manager, and
  superadmin roles;
- table-session guests with stable names and tokens;
- a shared draft, confirmed order, kitchen tickets, and manual payment records
  only when required by the scenario.

The fixture must stay small enough to make failures readable. Add only the
records needed by the scenario under test.

## Required Scenario Matrix

### 1. QR

Target files:

- `tests/Feature/PublicQrRouteTest.php`
- `tests/Feature/QrCodeGenerationTest.php`
- `tests/Feature/QrCodeSchemaTest.php`
- `tests/Feature/TableSessionTransferTest.php`
- `tests/Feature/TableSessionMergeTest.php`

Required coverage:

- QR opens the current `service_point` through `/q/{public_token}`.
- QR token and short code do not change after the service point is renamed.
- QR token and short code do not change after the service point is moved to
  another area, zone, or active table-session context.
- Disabled QR codes show a clear guest-facing error.
- Revoked QR codes show a clear guest-facing error.
- Unknown QR tokens do not leak organization, branch, service point, or session
  IDs.

Acceptance signals:

- `qr_codes.public_token`, `qr_codes.short_code`, and `qr_codes.service_point_id`
  remain stable unless a dedicated reissue action is tested.
- The page renders current service-point data, not stale labels copied into the
  QR record.
- Disabled and revoked states are distinguishable in database assertions and
  visible messages.

### 2. Guests

Target files:

- `tests/Feature/GuestCreatedPendingSessionTest.php`
- `tests/Feature/TableSessionJoinRequestTest.php`
- `tests/Feature/GuestJoinApprovalUiTest.php`
- `tests/Feature/GuestTablePageShellTest.php`
- `tests/Feature/GuestSessionSecurityHardeningTest.php`

Required coverage:

- The first guest scan and name submit creates a table session and an active
  `table_session_guest`.
- The second guest creates a pending `table_session_join_request` when active
  guests already exist.
- Any active guest can approve a pending join request.
- Any active guest can reject a pending join request.
- A rejected guest cannot see the table experience.
- A removed guest cannot add draft items or continue ordering after token
  restore.
- Pending, rejected, removed, left, expired, and closed-session states show
  understandable messages.

Acceptance signals:

- Guest tokens restore only the correct guest and table session.
- Moderation records the approving or rejecting guest.
- Non-active guests cannot approve, reject, view table content, or mutate the
  shared draft.

### 3. Draft

Target files:

- `tests/Feature/DraftOrderSchemaTest.php`
- `tests/Feature/GuestMenuDisplayTest.php`
- `tests/Feature/VerticalSliceFlowTest.php`
- `tests/Feature/BranchTemporaryClosedModeTest.php`
- `tests/Feature/WaiterDraftEditingTest.php`

Required coverage:

- An active guest can add an available menu item to the shared draft.
- A guest can edit only their own draft items.
- A guest cannot edit or delete another guest's draft items.
- A shared item can be split between guests and the allocation is visible in
  per-guest totals.
- Draft totals include item price, modifiers, quantity, guest ownership, and
  shared allocations.
- Sending the draft to the waiter changes the draft status and blocks further
  guest edits for that draft.
- Closed, temporarily closed, or non-ordering branch modes block new draft item
  creation and waiter submission as applicable.

Acceptance signals:

- Draft status transitions are asserted with `DraftOrderStatus`.
- Ownership and allocation assertions check database rows and rendered table
  totals.
- Locked drafts reject add, update, delete, and send operations from guests.

### 4. Waiter

Target files:

- `tests/Feature/WaiterDashboardTest.php`
- `tests/Feature/WaiterTableDetailTest.php`
- `tests/Feature/WaiterDraftReviewTest.php`
- `tests/Feature/WaiterDraftEditingTest.php`
- `tests/Feature/KitchenTicketDispatchTest.php`

Required coverage:

- A waiter with order access sees a draft sent to waiter review.
- A waiter with edit access can edit draft quantity, comment, modifiers, and
  positions before confirmation.
- A waiter can reject a sent draft only with a human-readable reason.
- Guests see the waiter rejection reason.
- A waiter can confirm a sent draft and create a real order.
- Confirmed orders snapshot guest names, item names, prices, modifiers, and
  department data.
- Confirmation does not send work to kitchen or bar until the explicit dispatch
  action is used.

Acceptance signals:

- Waiter access is branch-scoped.
- `draft_orders` move through the expected status values.
- `orders` and `order_items` preserve historical snapshots after confirmation.
- Rejection and confirmation write the expected user/guest/audit context when
  the related feature owns that history.

### 5. Departments

Target files:

- `tests/Feature/KitchenDepartmentTest.php`
- `tests/Feature/KitchenTicketDispatchTest.php`
- `tests/Feature/KitchenScreenTest.php`
- `tests/Feature/BarDepartmentScreenTest.php`
- `tests/Feature/ReadyItemsToWaiterTest.php`

Required coverage:

- Each department screen shows only ticket items assigned to that department.
- Kitchen users cannot see unrelated bar-only items unless their role or
  permission intentionally grants access.
- Bar users cannot see unrelated kitchen-only items unless their role or
  permission intentionally grants access.
- Department staff can mark their own items as in progress and ready.
- Department staff can use the "My part is ready" flow for their assigned work.
- The order becomes ready only after every required department item is ready.
- Cancelled orders and closed sessions do not remain actionable on department
  screens.

Acceptance signals:

- Ticket rows are split by `kitchen_department_id` and department type.
- Cross-department leakage is tested with at least two departments on one order.
- Order status remains not ready after only one department completes and changes
  to ready only after the final department completes.

### 6. Payment

Target files:

- `tests/Feature/ManualPaymentTest.php`
- `tests/Feature/TableSessionCloseTest.php`
- `tests/Feature/BillRequestTest.php`
- `tests/Feature/VerticalSliceFlowTest.php`
- `tests/Feature/OrderCancellationTest.php`

Required coverage:

- Staff with payment access can record payment for the whole table.
- Cashier or payment staff can record payment by guest.
- Guest payments are based on confirmed order items and not open draft items.
- Shared item allocations are included in each guest balance.
- Service charge and tips snapshots are preserved when those branch settings
  apply.
- Partial payment keeps the table session open or payment-requested.
- Full payment allows the table session to be closed manually.
- Manual close frees the service point, blocks old guest ordering, preserves
  orders, and does not change the permanent QR.

Acceptance signals:

- `manual_payments` store scope, guest, subtotal, service charge, tips, total,
  currency, method, note, and snapshot metadata where applicable.
- Payment totals do not mutate historical order snapshots.
- Open drafts cannot be paid.
- Session close assertions cover `table_sessions`, `service_points`, old guest
  tokens, and QR identity.

### 7. Permissions

Target files:

- `tests/Feature/AccessControlAuditTest.php`
- `tests/Feature/OrganizationAccessTest.php`
- `tests/Feature/PermissionSystemTest.php`
- `tests/Feature/PermissionOverrideUiTest.php`
- `tests/Feature/StaffManagementUiTest.php`
- `tests/Feature/SuperadminAccessTest.php`
- `tests/Feature/ManualPaymentTest.php`
- `tests/Feature/KitchenScreenTest.php`

Required coverage:

- Staff assigned to one branch cannot see or open another branch.
- A waiter without `change_prices` cannot change menu prices.
- A cook cannot see or use the payment screen.
- A cashier cannot manage menus.
- A superadmin can see all organizations, branches, platform records, and
  guarded management surfaces.
- Permission overrides can allow, deny, and return to role defaults.
- Critical permission changes show an explicit warning and cannot be self-edited
  by regular staff.

Acceptance signals:

- Every denied path asserts both UI visibility and direct action rejection where
  direct mutation is possible.
- Branch isolation is tested with two branches in the same organization and, for
  superadmin coverage, across organization boundaries.
- Permission tests use fixed `SystemRole` and `SystemPermission` values instead
  of hard-coded role assumptions hidden in setup.

## Regression Commands

Run the smallest relevant group while developing a scenario. Before merging a
change that touches the main flow, run the vertical slice and the affected
module files:

```bash
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php
php artisan test --compact tests/Feature/PublicQrRouteTest.php tests/Feature/GuestCreatedPendingSessionTest.php tests/Feature/TableSessionJoinRequestTest.php
php artisan test --compact tests/Feature/DraftOrderSchemaTest.php tests/Feature/WaiterDraftReviewTest.php tests/Feature/WaiterDraftEditingTest.php
php artisan test --compact tests/Feature/KitchenTicketDispatchTest.php tests/Feature/KitchenScreenTest.php tests/Feature/BarDepartmentScreenTest.php
php artisan test --compact tests/Feature/ManualPaymentTest.php tests/Feature/TableSessionCloseTest.php
php artisan test --compact tests/Feature/AccessControlAuditTest.php tests/Feature/PermissionSystemTest.php tests/Feature/SuperadminAccessTest.php
```

When a prompt touches QR, guest entry, shared draft, waiter confirmation,
department readiness, payment, close, or permissions, at least one focused test
from the touched area must be added or updated before the prompt is considered
ready.

## Coverage Gaps To Keep Visible

The strategy requires explicit coverage for these scenarios even if a prompt
implements them later:

- shared item allocation across multiple guests;
- payment balance calculation with shared allocations;
- one-click "My part is ready" department completion when the UI groups multiple
  ticket items into one department action;
- direct-action authorization checks for every UI-hidden permission boundary.

Do not remove these items from the strategy just because they are not part of a
single implementation prompt.

## Definition Of Ready

A functional scenario is ready when:

- the feature test names describe the business behavior, not implementation
  mechanics;
- the test proves both success and at least one important denial path;
- database assertions cover the durable state change;
- rendered UI assertions cover the user-facing message or visibility rule;
- branch, guest, role, and status boundaries are explicit in setup;
- the focused `php artisan test --compact ...` command passes locally.

## Definition Of Done

Before committing code that changes a covered flow:

- update or add the focused feature test;
- run the smallest relevant regression command;
- run `vendor/bin/pint --dirty --format agent` for PHP changes;
- avoid touching unrelated docs, generated files, local storage, or existing
  user changes;
- record any manual smoke check in `docs/TEST_CHECKLIST.md` only when the prompt
  explicitly asks for checklist updates or changes a user-facing flow.

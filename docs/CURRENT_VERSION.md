# Current Version

Short snapshot for the next developer or coding agent. This project is a Laravel + Livewire SaaS for restaurants on a shared-hosting-friendly stack: SQLite, database cache, database sessions, database queue, local storage, Blade UI, and Livewire polling. Do not add Redis, WebSockets, S3, Docker, paid services, React, Vue, or a separate SPA unless a future prompt explicitly changes that rule.

## Domain Map

Organization is the restaurant business owner or company. Example: `Food Group`. It owns brands, branches, users, subscription status, and platform-level access context.

Brand is a restaurant brand inside an organization. Example: `Bella Pizza`. One organization can have multiple brands.

Branch is a real venue or working location for a brand. Example: `Bella Pizza Old Town`. Branch is the main operational unit for settings, areas, service points, menu, orders, kitchen/bar departments, payments, analytics, and staff access.

Area Node is a nested branch zone: floor, hall, terrace, VIP room, bar area, pickup area, custom group, and so on. It organizes service points. It does not own QR identity.

Service Point is a physical place of service: table, bar seat, room, booth, hotel room, pickup window, delivery point, or other place. QR codes attach to service points, not to table names or zones.

## Permanent QR Rule

One physical service point should have one active permanent QR code. The public URL is `/q/{public_token}`.

The URL must not contain organization ID, branch ID, service point ID, area ID, table number, or table name. Renaming a table or moving it to another area must not change the QR. Reissue is a manual dangerous action only.

`public_token` is the only QR route credential. `short_code` is visible staff lookup/print text and must never authenticate the public QR route.

## Guest And Table Flow

`table_session` is one seating/service lifecycle for a service point. It belongs to a branch and service point and can be created by a waiter or by the first guest, depending on branch settings.

Guest entry does not create a user account. A guest scans QR, enters a required name, and receives a random `guest_token` stored in the browser cookie/session flow. Refresh should restore the guest while the session is valid.

If there are already active guests at the table, a new guest creates a `table_session_join_request`. Current active guests see the request through Livewire polling and any active guest can approve or reject it. Guests are shown alphabetically.

Guest tokens, staff invite tokens, and guest session invite tokens are bearer credentials. They must stay random, non-incremental, hidden from exports/logs/UI, and checked against QR/session/guest/invite status before any mutation.

The table has a shared `draft_order`. Each draft item belongs to the guest who added it. All active guests see the shared draft, per-guest totals, and the table total. A guest can edit only their own draft items. After the draft is sent to the waiter, guest editing is blocked for that draft.

Any active guest can send the shared draft to the waiter. The service point becomes a new-order state, but the order does not go to kitchen or bar yet.

## Waiter, Kitchen, Bar

The waiter must confirm the guest draft before it becomes a real order. This is a core safety rule: guests can build a draft, but staff controls the order entering production.

After waiter confirmation, a real `order` and snapshot `order_items` are created. Snapshots preserve item names, prices, guest names, modifiers, and department data even if the menu changes later.

The waiter then explicitly sends the confirmed order to kitchen/bar. Dispatch creates `kitchen_tickets` split by branch kitchen departments. Kitchen sees kitchen items; bar sees bar items. Staff update ticket item status through Livewire polling screens.

When kitchen/bar marks items ready, waiter table detail sees them and can mark them served. Guests see order status progress such as accepted, cooking, ready, and served.

## Payment And Close

Online payments are not implemented. Current payment is manual/offline: staff records cash, card terminal, or other payment. After full payment, the table session can be closed. Closing frees the service point, blocks old guest ordering, preserves old orders, and keeps the permanent QR unchanged for the next seating.

## Shared Hosting Mode

Runtime uses:

- SQLite database file outside the public web root.
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`
- `QUEUE_CONNECTION=database`
- local public storage in `storage/app/public`
- private `local` storage is not served through public storage routes
- browser/Livewire polling instead of WebSockets

Deployment notes are in `docs/DEPLOY_SHARED_HOSTING.md`. Main flow checks are in `docs/TEST_CHECKLIST.md`.

## Current Limits

- No Redis, WebSockets, S3, Docker-required setup, external queue, Push, SMS, Telegram API, Stripe, PayPal, online acquiring, or paid services.
- No separate SPA; UI is Blade + Livewire.
- Roles are fixed; permissions and user overrides are flexible. The staff permission UI must show grouped human labels/descriptions for directors, while raw technical keys are visible only to superadmin technical mode.
- Error handling uses a shared `ApplicationErrorType` catalog, controlled expected business errors, safe translated HTTP error pages, and Laravel logs for unexpected exceptions.
- User-entered guest, staff, menu, order, branch, reason, note, and notification text is plain text by default: normalize before storage, render escaped, preserve line breaks safely, and allow raw output only for audited generated QR SVG.
- Route protection keeps public QR/guest routes GET-only, staff/admin/waiter/kitchen/bar/export/settings routes behind authenticated web sessions, backup routes behind `auth` plus `superadmin`, and export downloads behind server-side `export_data` branch access.
- Token protection keeps QR public tokens, guest tokens, staff invitation tokens, and guest session invite tokens separated; staff invite acceptance must check pending status and future expiration.
- SaaS subscription is a simple one-plan/manual-status model.
- Guest users are not real user accounts.
- QR PDF generation is not implemented; browser print templates exist.
- CSV exports exist; PDF exports are later.
- Menu translation display exists, but a full translation admin editor is still later.
- SQLite is optimized with indexes, cache, bounded polling, and pagination, but this project intentionally targets small/shared-hosting deployments before enterprise infrastructure.

## What To Do Next

Use the next prompt as the source of truth. Good next candidates are local UI translation coverage, QR PDF export, media ZIP backup, staff invitation acceptance, menu translation admin UI, payment/reporting refinements, or more production history for kitchen/bar. Keep `tests/Feature/VerticalSliceFlowTest.php` green when touching the main guest/waiter/kitchen/payment/session flow.

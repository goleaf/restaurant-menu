# Next Steps

This file is a small queue for future prompts. It is not permission to implement
anything automatically. Use it only after reading `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, and `docs/TEST_CHECKLIST.md`.

Last memory refresh: 2026-06-05 after Prompt 333 token security rules.

## Next Recommended Prompt

Use only the user's next explicit prompt. If returning to the existing queue, start with a fresh health check because earlier rescue-mode notes mention Prompt 123, Prompt 125, and Prompt 126 as pending candidates.

Prompt 127 is complete. Kitchen/bar tickets now have a browser print-friendly
page through `restaurant.departments.tickets.print`.

Future ticket print work must preserve these guardrails:

- print pages are read-only and must not change orders, ticket items, payments,
  guests, service points, or QR codes;
- access must stay scoped to the same department visibility as kitchen/bar
  screens;
- no hardware printer integration, PDF service, paid service, Redis,
  WebSocket, S3, Docker requirement, or external infrastructure;
- keep Blade query-free by preparing ticket data in backend actions.

Prompt 333 is complete. Future QR, guest, staff invitation, session invite, export, or audit-log work must preserve token separation:

- `/q/{public_token}` uses the QR public token only;
- `short_code` is staff lookup/print text only;
- `guest_token` must never grant staff access or appear in UI, URLs, exports, or user-facing logs;
- staff invite acceptance must check a 64-character alphanumeric token, pending status, and future `expires_at`;
- revoked QR tokens, closed session invite tokens, expired invite tokens, inactive service points, and rejected/removed guests must not create ordering access.

Historical queued candidate: Prompt 126 - Waiter notification sound settings.

Prompt 126 was requested, but it was paused because the pre-prompt health check
found the project was already broken. Rescue mode restored branch staff
assignment mass-assignment fields first.

When Prompt 126 is started again, keep the step small:

- add a simple waiter dashboard sound settings UI;
- allow sound on/off, mute mode, and choosing from local sound options;
- store settings at user level or browser `localStorage`, choosing the lighter
  approach that matches current code;
- trigger sounds only for existing waiter dashboard events: new draft/order,
  waiter call, bill request, and ready item;
- show a clear browser-autoplay hint because sound may be blocked before the
  first user interaction;
- do not add external sound services, push providers, Redis, WebSockets, S3,
  Docker, paid services, or new realtime infrastructure.

Prompt 125 - Kitchen delay timers remains skipped/pending unless the user
explicitly asks to return to it.

Prompt 125 was requested, but it was paused because the pre-prompt health check
found the project was already broken. Rescue mode restored guest status
translations and Public QR polling-locale propagation first.

When Prompt 125 is started again, keep the step small:

- add `expected_prepare_minutes` to the simplest existing place, either
  kitchen department or menu item, based on current schema conventions;
- show elapsed time on kitchen/bar ticket cards from existing ticket timestamps;
- show a friendly delayed badge when elapsed minutes exceed the expected
  preparation time;
- surface delayed items to the waiter in the existing waiter dashboard/table
  detail;
- calculate delay at render/query time; do not add background workers,
  complex analytics, Redis, WebSockets, S3, Docker, or paid services.

Prompt 124 is complete. The guest order status block now shows a friendly
timeline, whole-table status, and per-position status labels through the
existing isolated Livewire polling component. Do not add more status states,
order lifecycle changes, or payment correction behavior unless a future prompt
explicitly asks for them.

The separate post-feature daily memory update after Prompt 124 is complete.
Keep this file as the source for next-prompt guardrails until the user gives a
new prompt. The update was documentation-only and did not add product behavior.

Prompt 343 is complete. Future validation, permission, branch, QR, guest,
draft, order, payment, file upload, and system error work must use
`ApplicationErrorType`, controlled translated messages, and safe error pages.
Unexpected exceptions must be logged by Laravel and must not create audit-log
rows unless a business action itself writes an audit entry.

Prompt 335 is complete. Future UI and storage work must treat guest names,
guest comments, order comments, waiter notes, menu descriptions, category
descriptions, branch profile text, reasons, notes, and notification text as
plain text by default. Do not use raw HTML for user content; use escaped Blade
output or `<x-ui.plain-text>`, and add explicit sanitization before allowing any
limited formatting.

Prompt 334 is complete. Future route work must keep public QR/guest routes as
guest-only GET surfaces, keep staff/admin/waiter/kitchen/bar/export/settings
routes behind authenticated web sessions, keep backup downloads behind
`auth` plus `superadmin`, and keep export downloads behind server-side
`export_data` branch access. Do not enable public private-storage routes or add
CSRF exclusions without a separate audited prompt.

Rescue mode ran before Prompt 123 because the project was already broken by a
compiled waiter table detail Blade parse error. The rescue fix cleared stale
compiled views and restored the focused manual payment/order cancellation
regression suite. Prompt 123 was not implemented.

When Prompt 123 is started again, keep the step small:

- add correction history for manual payments without deleting payment records;
- store reason, actor, old amount/method, and new amount/method;
- keep the flow manual/offline only, with no online refund provider;
- write audit logs for every correction;
- preserve existing split-bill, service charge, tips, and session paid/closed
  behavior with focused tests.

Prompt 122 - Void item before payment remains skipped/pending unless the user
explicitly asks to return to it.

Rescue mode ran before Prompt 122 because the project was already broken by a
missing `Flux\\Flux` import in `App\\Livewire\\Waiter\\TableDetail`. The rescue
fix restored the existing waiter table detail/payment regression suite and did
not implement item-level voiding.

When Prompt 122 is started again, keep the step small:

- add item-level void status/history without deleting `order_items`;
- require a reason;
- recalculate payment totals from non-voided items;
- update related kitchen ticket item only when it is not served;
- write both `order_status_logs` and `audit_logs`;
- authorize through `cancel_orders` or an explicitly existing/seeded
  `manage_orders` permission.

Prompt 345 is complete. Future permission UI work must preserve grouped
business labels/descriptions for directors and keep raw permission keys visible
only to superadmin technical mode.

Do not continue with new product behavior automatically without the user's next
explicit prompt.

Prompt 121 is complete; do not continue with new product behavior automatically.

Prompt 121 added order cancellation with required reason. Keep cancellation as
status/history, not deletion. Cancelled order tickets must remain hidden from
kitchen/bar work screens and blocked in ticket item update actions.

The separate post-feature daily memory update after Prompt 121 is complete.
Prompt 350 completed a technical architecture hygiene pass without adding
restaurant features. Keep the new translation and forbidden-module regression
guards in place.
Keep this file as the source for next-prompt guardrails until the user gives a
new prompt. The update was documentation-only and did not add product behavior.

If the next prompt touches table sessions, waiter dashboard, cleanup, payments,
or orders, first verify:

- `tests/Feature/GuestOrderStatusScreenTest.php`;
- `tests/Feature/TableSessionMergeTest.php`;
- `tests/Feature/TableSessionTransferTest.php`;
- `tests/Feature/SessionInactivityCleanupTest.php`;
- `tests/Feature/TableSessionCloseTest.php`;
- `tests/Feature/GuestTablePageShellTest.php`;
- `tests/Feature/WaiterDashboardTest.php`;
- `tests/Feature/ManualPaymentTest.php`;
- `tests/Feature/OrderCancellationTest.php`;
- `tests/Feature/VerticalSliceFlowTest.php` for broader flow changes.

Risky areas:

- Do not auto-close active sessions from cleanup.
- Do not cancel sessions with unpaid orders.
- Do not reissue permanent QR when a session is cancelled or closed.
- Do not reissue, disable, revoke, or regenerate permanent QR when an active
  table session is transferred to another service point.
- Do not reissue, disable, revoke, or regenerate permanent QR when a table
  session links additional service points.
- Merged-table links live in `table_session_service_points`; the main
  `table_sessions.service_point_id` remains the primary active service point.
- Linked service points must show `occupied`, but their QR records must remain
  attached to their own physical `service_point_id`.
- A guest scanning a linked QR should enter or request to join the same active
  session, not create a duplicate active session for that linked service point.
- Closing a merged session must free every active linked service point and mark
  those links inactive.
- Split bill guest balances are based on confirmed `order_items`, not a
  separately editable billing allocation table.
- Guest-scoped manual payments must keep storing
  `manual_payments.table_session_guest_id`.
- Manual service charge must stay percentage-based and stored as payment
  snapshots when a payment is recorded.
- Tips are manual optional extras and must not reduce the required subtotal or
  service-charge balance.
- Do not add online payments, tax logic, or shared allocation rules unless a
  future prompt explicitly asks for them.
- Do not delete cancelled orders or order items; cancellation must keep
  `order_status_logs`, `audit_logs`, and guest-facing reason history.
- Do not let kitchen/bar staff continue ticket work for cancelled orders.
- Do not show technical enum values or status keys in the public guest order
  status UI.
- Keep guest order status polling isolated in
  `App\Livewire\PublicQr\OrderStatuses`; do not refresh the whole QR table page
  for status changes.
- Do not move a table session to a non-free, inactive, cross-branch, pending,
  active, or payment-requested service point.
- Already-entered guests should follow the transferred `table_session`; fresh
  physical QR scans should still start from the QR's service point.
- Do not add Redis/WebSockets/S3/Docker/paid services for scheduling or realtime.
- Keep scheduler support optional for shared hosting; manual cleanup must remain available.

Architecture hygiene debts to handle only through small future refactors:

- Several Livewire components remain large orchestration surfaces, especially
  `App\Livewire\Organizations\Brands\Branches\Menu\Index`,
  `App\Livewire\PublicQr\Show`, `App\Livewire\Waiter\TableDetail`,
  `App\Livewire\Organizations\Brands\Branches\ServicePoints\Index`, and
  `App\Livewire\PublicQr\DraftOrder`. Do not rewrite them wholesale; extract
  one action/view-model at a time when touching the related flow.
- Guest shared draft totals still have duplicated shaping between
  `App\Livewire\PublicQr\DraftOrder` and `App\Livewire\PublicQr\DraftTotals`.
  Move that payload into a central Action before changing split totals again.
- A few Blade views still call Livewire methods or computed properties for
  presentation payloads, such as menu availability and service-point board
  sections. Keep Blade query-free, and move those values into prebuilt
  component state when touching the related screens.
- Money formatting is centralized in `App\Support\MoneyFormatter`, but some
  analytics, waiter, dashboard, and draft actions still keep private
  decimal/cent helpers. Replace those opportunistically when editing those
  files, with focused tests.
- Server-side permissions are enforced through `SystemPermission`, user
  membership checks, and action-specific guards. A full Laravel Policy layer is
  still not present; add policies only per aggregate when a future change needs
  them, not as a broad rewrite.
- Existing kitchen/bar ticket item statuses power the current production flow.
  Do not introduce additional item-level operational statuses; if the product
  later wants a coarser status model, migrate the kitchen/bar flow explicitly
  with regression coverage.
The implemented public restaurant profile, branch opening hours, temporary
branch closed mode, menu schedules, multiple active branch menus, branch service
modes, bulk service point creation, QR label design presets, QR short-code
lookup, branch service point search/filter pagination, the branch visual floor
board, waiter zone assignments, waiter manual order entry, waiter-side schedule
checks, guest duplicate-name handling, safe session inactivity cleanup, active
table-session transfer, merged table sessions, and split bill by guests should
be treated as current baseline for future guest UI, QR landing, ordering work,
staff review, payments, and branch setup. Prompt 120 manual service charge and
tips are also baseline for bill summaries and manual payment history.

## Current Recommended Prompt

Wait for the next explicit user prompt. Do not continue feature work
automatically. Prompt 114 is complete; duplicate guest-name handling is now
baseline behavior, not a pending prompt.

Alternative queued prompt:

Prompt 281: decide whether to add dedicated menu tags/allergens and shared
payment allocation foundations.

Prompt 281 scope, if requested:

- Keep it schema-first and small.
- Treat dedicated tags/allergens as new menu metadata, not as a consistency
  bugfix.
- Treat shared payment allocations as a new payment/cart rule, not as a hidden
  extension of manual payments.
- Preserve existing modifier groups/options as the current variant-like
  mechanism unless a separate variant model is explicitly requested.
- Keep guest ordering, waiter confirmation, kitchen/bar dispatch, and manual
  payment flows green.

Older queued prompt:

Add a simple menu translation admin editor.

Translation editor scope, if requested:

- Use the existing branch menu page.
- Edit existing `menu_category_translations` and `menu_item_translations`.
- Support only `ru`, `en`, and `lt`.
- Fall back to base category/item text when translation is missing.
- Clear branch menu cache through `App\Actions\Branches\ForgetBranchCacheAction`.
- Keep access behind existing menu-management permissions.

Risky places:

- QR print CSS is shared by single and bulk printing; keep class changes scoped to `qr-sticker` and print media.
- `App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\PrintTemplate` and `App\Livewire\Organizations\Brands\Branches\Qr\BulkPrint` both keep URL-backed print state; keep defaults safe.
- Permanent QR identity must never depend on print preset, print size, service point name, service point number, or area.
- QR short-code lookup must stay scoped to `generate_qr` and accessible branch
  ids; a printed `short_code` is not a public guest token.
- Branch service point search/filter UI is branch-scoped by the nested route;
  do not turn it into a cross-branch editing screen without a separate prompt.
- Keep service point lists paginated on SQLite.
- The simple visual floor board uses the current paginated service point page;
  do not turn it into a canvas, drag-and-drop editor, or all-rows floor plan
  without a separate prompt and a SQLite performance review.
- Waiter zone assignments live in `area_node_waiters` and should only narrow
  waiter dashboard visibility inside already accessible branches. Do not use
  them as a replacement for organization/branch access checks.
- If a waiter has no assigned zones, the current behavior is to show all
  accessible branch places with a hint.
- Manual waiter order entry creates or reuses a waiter-review draft for an
  active table and still requires normal waiter confirmation before kitchen/bar
  dispatch. Do not bypass confirmation in future waiter shortcuts.
- Guest duplicate-name handling is a UI clarity layer only. Do not treat
  `guest_name` as a unique key, and do not add guest registration to solve
  display-name collisions.
- `App\Livewire\Organizations\Brands\Branches\Menu\Index` is already large;
  keep the translation editor small and avoid broad refactors.
- Guest menu cache is language-specific; translation saves must clear every
  branch language cache key through `ForgetBranchCacheAction`.
- Supported languages must come from `App\Enums\SupportedLocale`, not a second
  hardcoded language list.
- Do not let translation editing change base menu item prices, availability,
  modifiers, kitchen department assignment, or menu schedules.

Do not add:

- AI translation.
- External translation APIs.
- Paid services.
- New frontend SPA.
- New menu schema.
- New language list outside the existing supported locales.

## Recently Completed

Prompt 105 improved multiple menus per branch: guest menu payloads now return
all active menus available right now, group dishes by menu, keep menu sorting,
hide draft/archived menus, respect menu schedules in the branch timezone, and
show later active menus only as next-availability hints without exposing their
dishes for ordering.

Prompt 106 added branch service modes: `dine_in`, `pickup`, `delivery`,
`hotel_room_service`, `bar_only`, and `custom` can be enabled from branch
settings and are stored in `branch_settings.service_modes` with safe default
`dine_in`. This is foundation only; no delivery workflow, maps, couriers, or
online payments were added.

Prompt 107 added bulk service point creation: managers can preview generated
labels such as `T1..T20`, skip duplicate branch `internal_code` values, create
only missing service points, and then use the existing bulk QR print flow when
they are ready to generate QR.

Prompt 108 added QR label design presets: single and bulk QR print pages can
switch between `minimal`, `classic`, `restaurant`, `bar`, `hotel`, and
`premium` browser print-friendly CSS presets. Preset changes are presentation
only and do not change QR identity or print mutable service point text by
default.

Prompt 109 added QR short-code lookup: users with `generate_qr` can open
`/restaurant/qr-lookup`, search a printed code such as `QR-8F92`, and see only
accessible branch QR details with branch, zone, service point, status, public
URL, and explicit open/disable/reissue actions.

Prompt 110 added service point search filters: the branch `Столы и места` page
can search by service point name, display number, stable internal code, and
active QR `short_code`, filter by the current route branch, zone, type, status,
active/inactive state, and active QR presence, and paginate results without
loading every service point at once.

Prompt 111 added a simple visual floor board to the same branch service point
page. It groups the currently loaded service point page by zone, shows cards
with type icons, status/QR/session badges, and reuses existing quick actions for
opening a table, QR, and editing. It does not add a canvas editor, drag-and-drop
logic, routes, schema, or heavy JavaScript.

Prompt 112 added waiter zone assignments: managers with `manage_staff` can
assign fixed branch waiters to active `area_nodes` from the branch staff page,
and the waiter dashboard can switch between `My zones` and `All zones`.

Prompt 113 added manual waiter order entry: on an active table, authorized
waiter staff can choose an active guest or type a new guest name, add dishes to
a waiter-review draft, preserve snapshots, and then confirm through the normal
order flow before any kitchen/bar dispatch.

Prompt 114 added guest duplicate-name handling: QR and invite entry now warn
when an active table already has the same display name, suggest names such as
`Анна 2` and `Анна К.`, and still allow intentionally identical display names.

Prompt 280 checked functional consistency across menu, guest, staff,
departments, payments, and access control. It fixed waiter-side adding of draft
items so menu schedules are respected in both the add-item UI and backend Action.
No dedicated variants/tags/allergens/shared-allocation schema was added.

Prompt 120 added manual service charge and tips: branch settings store an
optional service charge percent, waiter/cashier bill summaries show service
charge and recorded tips, and `manual_payments` store stable snapshots for
covered subtotal, service charge percent/amount, tips, and total collected
amount. This remains manual/offline only, with no tax logic or online payment
provider.

Prompt 102 added branch opening hours: weekly schedules with closed days,
several intervals per day, branch-timezone status checks, admin settings UI, and
guest open/closed messaging. QR pages and menu browsing still work while a
configured branch is closed, but guest draft item creation and send-to-waiter
actions are blocked.

Prompt 103 added temporary branch closed mode: branch settings can enable a
reason and optional until time, QR/menu viewing stays available, new guest
ordering is blocked while the mode is active, and order-access staff can reopen
ordering from the waiter dashboard.

Prompt 104 added menu schedules: each active menu can have weekday availability
intervals in the branch timezone, guest menu payloads show only menus available
right now, schedule changes clear database cache, and guest add/send draft
actions block unavailable menu windows server-side.

Prompt 101 added the branch public restaurant profile used by QR landing and
guest UI: public venue name, short description, local logo/cover image,
address/contact/social links, default language, and default currency. Profile
images stay local in `storage/app/public`; no maps, external APIs, S3,
WebSockets, paid services, React, or Vue were added.

Prompt 346 added shared dangerous-action confirmations. Future dangerous
actions must reuse `App\Enums\DangerousAction` and
`resources/views/components/dangerous-action-confirmation.blade.php`, explain
the consequence before execution, require a reason or typed confirmation when
the registry says so, check permissions server-side, and write audit logs after
the action. `void_order_item` and `clear_cache_all` are registry entries only
until an explicit future prompt adds those actual product actions.

## Other Good Future Prompts

- Add staff invitation acceptance flow using existing `invitations` records.
- Add QR PDF export using local/free tooling only, or keep browser print if PDF would add heavy dependencies.
- Add local media ZIP backup for superadmin without S3 or paid backup services.
- Add a small payment/reporting refinement around existing manual payments.
- Add kitchen/bar production history using existing order/ticket history patterns.
- Expand local UI translation coverage in JSON language files.

## Guardrails

- Keep Laravel + Livewire + Blade as the UI stack.
- Keep SQLite, database cache, database sessions, and database queue.
- Keep files local in `storage/app/public`.
- Keep realtime on Livewire polling.
- Do not add Redis, WebSockets, Reverb/Pusher, S3, Docker as a requirement,
  Stripe, PayPal, online acquiring, Push/SMS/Telegram API, AI translation,
  external paid services, React, Vue, Inertia, or a separate SPA.
- Do not expose internal IDs in public QR or guest invite URLs.
- Do not reissue QR during ordinary service point edits or table-session close.
- Do not let guest drafts reach kitchen/bar without waiter confirmation and
  explicit dispatch.
- Keep `tests/Feature/VerticalSliceFlowTest.php` green when touching the main
  guest/waiter/kitchen/payment/session flow.

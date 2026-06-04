# Next Steps

This file is a small queue for future prompts. It is not permission to implement
anything automatically. Use it only after reading `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, and `docs/TEST_CHECKLIST.md`.

Last memory refresh: 2026-06-04 after Prompt 113 manual waiter order entry.
The implemented public restaurant profile, branch opening hours, temporary
branch closed mode, menu schedules, multiple active branch menus, branch service
modes, bulk service point creation, QR label design presets, QR short-code
lookup, branch service point search/filter pagination, the branch visual floor
board, waiter zone assignments, waiter manual order entry, and waiter-side schedule checks should be
treated as current baseline for future guest UI, QR landing, ordering work,
staff review, and branch setup.

## Current Recommended Prompt

Wait for the next explicit user prompt. Do not continue feature work
automatically.

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

Prompt 280 checked functional consistency across menu, guest, staff,
departments, payments, and access control. It fixed waiter-side adding of draft
items so menu schedules are respected in both the add-item UI and backend Action.
No dedicated variants/tags/allergens/shared-allocation schema was added.

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

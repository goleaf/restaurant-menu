# Next Steps

This file is a small queue for future prompts. It is not permission to implement
anything automatically. Use it only after reading `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, and `docs/TEST_CHECKLIST.md`.

Last memory refresh: 2026-06-04 after Prompt 105 and the follow-up daily project memory
update. The implemented public restaurant profile, branch opening hours,
temporary branch closed mode, menu schedules, and multiple active branch menus
should be treated as current baseline for future guest UI, QR landing, and
ordering work.

## Current Recommended Prompt

Prompt 107: add a simple menu translation admin editor.

Scope:

- Use the existing branch menu page.
- Edit existing `menu_category_translations` and `menu_item_translations`.
- Support only `ru`, `en`, and `lt`.
- Fall back to base category/item text when translation is missing.
- Clear branch menu cache through `App\Actions\Branches\ForgetBranchCacheAction`.
- Keep access behind existing menu-management permissions.

Risky places:

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

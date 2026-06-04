# Next Steps

This file is a small queue for future prompts. It is not permission to implement
anything automatically. Use it only after reading `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, and `docs/TEST_CHECKLIST.md`.

## Current Recommended Prompt

Prompt 101: add a simple menu translation admin editor.

Scope:

- Use the existing branch menu page.
- Edit existing `menu_category_translations` and `menu_item_translations`.
- Support only `ru`, `en`, and `lt`.
- Fall back to base category/item text when translation is missing.
- Clear branch menu cache through `App\Actions\Branches\ForgetBranchCacheAction`.
- Keep access behind existing menu-management permissions.

Do not add:

- AI translation.
- External translation APIs.
- Paid services.
- New frontend SPA.
- New menu schema.
- New language list outside the existing supported locales.

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

# Codex Guardrails

Short hard rules for every Codex prompt in this project.

## Always

- Read `README.md`, `CHANGELOG.md`, and `docs/AI_CONTEXT.md` before changes.
- Make one small change per prompt.
- Update docs after each prompt.
- Commit after each prompt.
- Keep SQLite/shared hosting compatibility.
- Use database cache/session/queue.
- Use Blade + Livewire.
- Check permissions server-side.
- Keep guest and staff separated.
- Keep public QR routes guest-only.
- Keep token families separated: QR public token, QR short code, guest token, staff invite token, and guest session invite token.
- Check token status and expiration server-side before use.
- Use controlled translated errors.
- Log unexpected exceptions.
- Escape user/staff/guest content by default.
- Store guest comments and menu descriptions as plain text unless explicitly sanitized.

## Never

- No Redis.
- No WebSocket.
- No S3.
- No Docker requirement.
- No paid services.
- No Stripe/PayPal.
- No training mode.
- No pilot issue log.
- No live launch checklist.
- No safe mode module.
- No item-level operational statuses.
- No hardcoded UI strings.
- No business logic in Blade.
- No trusting frontend totals.
- No unsafe public admin POST/PATCH/DELETE routes.
- No global CSRF disable.
- No public sensitive backup/download routes.
- No incremental IDs as public tokens.
- No accepting `short_code` as a security token.
- No exposing `guest_token` in UI, URLs, exports, logs, or Livewire public properties.
- No accepting expired invite tokens, revoked QR tokens, or closed-session invite tokens.
- No stack traces or raw exception messages for normal users.
- No sensitive data in error messages.
- No unescaped output for user-entered content.
- No unsafe HTML storage without sanitization.

## Current Next Prompt

- Use only the user's next explicit prompt.
- Existing queued candidates in project docs include Prompt 123 payment correction and Prompt 125 kitchen delay timers; start either only after a fresh health check.
- Prompt 122 order item void flow remains skipped/pending unless explicitly requested.

## Current Critical Business Rules

- Guests are not staff users and never get staff permissions.
- Admin, waiter, kitchen/bar, export, settings, and superadmin routes require authenticated web sessions.
- Superadmin backup routes require `auth` plus `superadmin`.
- Export downloads require `auth` plus server-side `export_data` branch access.
- Private local storage must not register public download/upload routes.
- Public QR and guest invite URLs must not expose internal IDs.
- QR `public_token`, guest tokens, staff invite tokens, and guest invite tokens must be random, long, non-incremental, status-checked credentials.
- QR `short_code` is staff lookup/print text only.
- Staff invitation acceptance must require pending status and unexpired token.
- Closed/cancelled sessions and revoked/disabled QR codes must not create guest ordering access.
- Permanent QR identity belongs to physical service points; ordinary edits must not reissue QR.
- Orders, payments, QR changes, staff changes, permission changes, and dangerous actions must keep audit history.
- Order cancellation keeps order history and requires a reason.
- Permission UI must show grouped human labels/descriptions; raw keys only for superadmin technical mode.
- Critical permission changes require confirmation and reason.
- Expected business errors must use controlled messages; unexpected system errors stay in Laravel logs.
- Technical exceptions must not create activity/audit log noise.
- Guest comments, order comments, waiter notes, menu descriptions, category descriptions, branch profile text, and notification text must render as escaped plain text.
- Raw HTML is allowed only for audited generated output such as QR SVG, never for user content.
- Manual payments are offline only; guests never create payment records.
- Kitchen/bar work starts only after waiter confirmation and explicit dispatch.
- Menu availability changes must clear database cache and write audit logs.
- Money totals must be recalculated server-side from stored records.
- Important business records use status/history or soft deletes instead of destructive deletion.

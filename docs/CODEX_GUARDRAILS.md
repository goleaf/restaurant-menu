# Codex Guardrails

Short hard rules for coding-agent work in this repository. Read this file before touching code, then read the longer project memory.

## Always

- Read `README.md`, `CHANGELOG.md`, and `docs/AI_CONTEXT.md` before changes.
- Make one small change per prompt.
- Update docs after each prompt.
- Commit after each prompt.
- Keep SQLite and shared-hosting compatibility.
- Use database cache, database sessions, and database queue.
- Use Blade and Livewire.
- Check permissions server-side.
- Keep guest and staff flows separated.

## Never

- No Redis.
- No WebSocket.
- No S3.
- No Docker requirement.
- No paid services.
- No Stripe or PayPal.
- No training mode.
- No pilot issue log.
- No live launch checklist.
- No safe mode module.
- No item-level operational statuses.
- No hardcoded UI strings.
- No business logic in Blade.
- No trusting frontend totals.

## Current Next Prompt

- Wait for the next explicit user prompt.
- Do not continue feature work automatically.
- Keep `docs/NEXT_STEPS.md` as the source for queued ideas and guardrails.
- Alternative queued prompt, only if explicitly requested: Prompt 281, dedicated menu tags/allergens and shared payment allocation foundations.

## Current Critical Business Rules

- Payments are manual/offline only.
- Backend actions must calculate totals from confirmed server-side records.
- Open drafts cannot be paid.
- Every order must be confirmed by staff before kitchen/bar dispatch.
- Guest users are not staff accounts and must not receive staff access.
- Staff permissions must be checked on the server for every branch action.
- One physical service point owns one permanent QR code.
- QR identity must not change when service points are renamed, moved, transferred, merged, cancelled, paid, or closed.
- Guest QR URLs must not expose organization, branch, service point, table, session, or guest IDs.
- Table-session cleanup must not auto-close active sessions or cancel sessions with unpaid orders.
- Merged table sessions must keep the main `table_sessions.service_point_id` as the primary service point.
- Tips are optional extras and must not reduce the required subtotal or service-charge balance.
- Manual payment history must preserve service charge and tips snapshots after settings change.
- All visible UI text must be localization-backed.
- Blade views must receive prepared data and must not query or own business logic.
- SQLite queries must stay bounded, eager-loaded, indexed, and shared-hosting friendly.

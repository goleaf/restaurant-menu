<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Feature Specification: Unified preparation workspace — Prompt 10

**Branch**: `main` · **Created**: 2026-09-20 · **Status**: Implementation

**Authority**: `docs/requirements.md`, especially sys-department-001, sys-order-001, sys-waiter-001, sys-tenant-001, data-integrity-001, perf-query-001, ui-workspace-003, livewire-001, ui-flux-pro-001, i18n-001 and accessibility/responsive contracts. This is a scoped working specification, not another catalogue. Input: user Prompt 10 of 14.

## User Scenarios & Testing

### US1 — One entry, exact operational access (P1)
Cook and bartender open Preparation in the existing restaurant context; each sees only separately authorized departments. A permitted multi-department actor can choose one department or explicit All permitted departments. Kitchen/bar URLs remain compatible entries into this implementation.

Acceptance: kitchen-only, bar-only, both, restricted-branch and foreign-tenant fixtures preserve view/update/print decisions. Invalid IDs fail explicitly. Two tabs retain independent targets. Order links open the exact ticket; no role or seed permission expands.

### US2 — A truthful working queue (P1)
Staff switch between In progress, Ready for service and History. Active state filters change visible rows, never the full ticket/order result. Quantity, snapshot variant/modifiers/comments/allergens, ticket/order identity, department and current serving place are clear.

Acceptance: one ready row beside two cooking rows leaves the ticket cooking; served and cancelled rows are distinct; orders beyond 1000 rows remain accurate. New orders in the same visit remain separate. Full ticket details are bounded and addressable. Queue counters label tickets, rows and portions and match their navigation scope. Ready remains waiting for service. Historical data do not claim missing event times.

### US3 — Safe explicit transitions and handoff (P1)
Staff accept, start or mark an exact displayed row ready. Existing intentional shortcuts remain secondary. Only the waiter serves. Retries and simultaneous activity cannot repeat preparation, revert state or conceal failed notifications.

Acceptance: actor/restaurant/department/ticket/item and shown status/version are rechecked; cancellation, serving, closed orders and revoked access reject stale requests. Event identity persists; notification failure is visible and explicitly retryable without repeating a transition. A reviewed group has at most 24 visible selected rows from one ticket, with exact per-row outcomes and all portions per row.

### US4 — Sustained working screen and print (P2)
The single queue refreshes visibly without moving a selected command target. Offline/reconnect/session expiry preserve truthful state and never replay commands. Authorized print shows the full exact ticket and the same saved content.

Acceptance: selection freezes queue replacement until explicit refresh/cancel; successful checks show actual server time, recent cancellations remain visible, no queued Ready after reconnect. Compact mode, readable 56px actions, keyboards, all three locales/themes, five widths and 200% zoom work. Printing is read-only; a print dialog is not physical delivery.

### Edge cases
Disabled departments retain authorized unfinished/history visibility; new disabling/type changes cannot hide work or change historical family access. Missing routing is diagnosed without assigning another department. Catalogue edits never rewrite dispatched snapshots. Multiple orders in a moved visit derive current service location without changing historical source or QR. Legacy absent timestamps are explicitly unknown. Partial group results never claim full success.

## Requirements

The canonical Prompt 10 refinement is in docs/requirements.md. No new ordering, staffing, inventory, recipe, payment, public guest UI or production planning system is in scope. Existing transitions, serving, authentication and database-only notifications remain the boundaries. Read-only observer access is not invented: current view and mutation capability are the same.

## Success Criteria

- US1–US4 have passing positive/negative automated scenarios and actual browser evidence.
- No filter or 1000-row truncation can imply full readiness; complete database aggregates drive decisions.
- No production command sends a ticket twice, serves a row, alters payment or changes permanent QR identity.
- Queue page/card hydration is bounded; matched before/after SQL, hydrated models, memory, HTML/payload/network measurements are recorded without unsupported speed claims.
- Required PHP/backend/browser/JS/SCSS/localization/build/static gates are discovered and reported separately from coverage and PHP 8.6 platform limitations.

## Assumptions
Existing organization roles and permission overrides remain authoritative; all-permitted is the union of separately granted department IDs. User-authorized local work proceeds without further questions, preserving shared main and unrelated edits. No working database writes, deployment, dependency upgrade, hooks or GitHub operations except ordinary authorized push.

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Feature Specification: Restaurant management and safe preparation

**Feature ID**: `001-restaurant-center` (local feature selection; Git remains `main`)
**Created**: 2026-09-18
**Status**: Implementing and verifying existing partial work
**Input**: User Prompt 4 of 14, all 36 sections.

This is a scoped working view of `docs/requirements.md`, especially `sys-branch-001`, `sys-onboarding-001`, `sys-tenant-001`, `sys-admin-crud-001`, `sys-menu-001`, `sys-qr-001`, `ui-accessibility-001`, `ui-flux-pro-001`. Canonical ownership/status remains in `docs/IMPLEMENTATION_PLAN.md`. No second roadmap is introduced.

## User Scenarios & Testing

### US1 — Add another restaurant without changing the first (P1)
The permitted administrator selects or creates the visible organization and brand once, confirms the restaurant identity, and receives a private preparation attempt for that restaurant.
**Independent test**: snapshot all first restaurant/parent/preparation attributes, add the second, replay identical and changed requests and compare persisted identities and attributes.
**Acceptance**: first launch retains its eligibility rules; additional creation uses existing branch permissions and subscriptions; GET creates nothing; identical replay returns one result; changed replay conflicts; two tabs remain independent; a failed suboperation rolls back the graph. Starting staff gain no owner role on existing organizations.

### US2 — Find and edit a restaurant in one center (P1)
Restaurants and Business structure share canonical identity editors. The user can find same-name restaurants, access permitted empty parents and explicitly open the workspace or preparation.
**Independent test**: authorized and denied actors exercise URL filters, lazy children, empty states, edit/cancel/return, pagination after archive and direct legacy links.
**Acceptance**: title, parents, place, administrative state, image/placeholder and concise preparation hint fit a row; upload and detailed checks appear only in the selected editor. Incompatible org/brand URL pairs are cleared. Parent filters do not grant access. Viewing another restaurant never changes the working restaurant. Explicit archive/restore remains capability-bound and preserves history.

### US3 — Resume the exact preparation without resetting content (P1)
Four groups show saved results: restaurant data; rooms/tables/QR; menu; review. Menu and rooms are independent after restaurant creation.
**Independent test**: menu-first, tables-first, partial reload/relogin, completed reopen, legacy partial/completed/archived upgrade, file failure and stale editor tests.
**Acceptance**: no first-user-record save target, implicit restore, publication or reset. Existing menu metadata/translations/photos/order/availability remain intact; unchanged confirmation creates no duplicate or material audit. QR repair/printing preserves identity. History of completion differs from current readiness and schedule closure. Conflicts preserve input; skipped groups remain incomplete. Only explicit publication changes availability.

### US4 — Use the same process accessibly and predictably (P2)
All screens and errors work in EN/LT/RU with shared unsaved-change protection and bounded requests.
**Independent test**: real browser fixtures and viewed screenshots of list/card/wizard, widths 320/390/768/1024/1440, themes, long names, keyboard/focus, 200% zoom/reflow, reduced motion and forced colors.
**Acceptance**: dialogs have accessible names; controls associate errors and field labels; offline/failed operations show no false saved state; no duplicate mobile/desktop forms, unwanted polling or global overflow concealment. Account changes invalidate old forms.

## Requirements

- **FR-001** (`sys-onboarding-001`, US1): distinct first/additional/continue intent, private attempt and payload-bound replay, atomic creation, current authorization and existing domain Actions.
- **FR-002** (`sys-branch-001`, US2): one center with bounded authorized restaurant-first list, URL filters, empty lazy structure, canonical selected editors, compatible old entries and explicit workspace changes.
- **FR-003** (`sys-onboarding-001`, US3): independent four-group preparation, actual relationship-derived state, preserved upgrade progress/completion and restaurant identity, no automatic restore/activation.
- **FR-004** (`sys-menu-001`, `sys-qr-001`, US3): existing content and QR identity survive retries, partial saves and failures; explicit updates preserve unedited fields and no-op audit semantics.
- **FR-005** (`sys-tenant-001`, all): authorize current actor/resource/subscription on every operation, reject malformed transport identifiers before casts and stale versions, no hidden denied names/counts/snapshots.
- **FR-006** (`ui-accessibility-001`, `ui-flux-pro-001`, US4): localized accessible responsive interactions, installed suitable Flux controls and existing common navigation/dirty context.
- **FR-007** (`sys-admin-crud-001`, all): scoped fixtures and repeatable full verification, recorded scenario costs and actual runtime identity, no production-data tests or unsupported runtime bypass.

### Key Entities
Organization is the administrative parent; Brand is its trading group; Branch is the physical restaurant with address. RestaurantOnboarding is a private actor-owned attempt linked to its concrete restaurant. Creation receipts protect confirmed requests. Existing menu, area, service point, permanent QR and completion history retain their meanings.

### Edge cases
Revoked rights/subscription; wrong parent; archived/missing links; boolean/array IDs; duplicate names across tenants; no organizations/no results/no access; last-row archive; unsaved cancellation; stale revision; lost response; same/different-payload duplicate submit; concurrent SQLite writers; media/QR file failure; swapped account; two browser tabs.

## Success Criteria

- **SC-001**: Adding/retrying the second restaurant changes zero pre-existing first-restaurant or parent attributes and creates exactly one new restaurant for one confirmed request.
- **SC-002**: Menu preparation succeeds before tables; repeating completed preparation without edits changes zero content/publication fields and zero historical completion timestamps.
- **SC-003**: All existing center entry points reach the same identity/create/continue operations with no hidden persistence on navigation and no extra organization-to-brand search prerequisite for restaurant discovery.
- **SC-004**: All defined permission, concurrency, upgrade and localized browser cases are discovered and executed; outcomes, screenshots and costs are recorded without converting failures/skips into completion.
- **SC-005**: List work is page-bounded, avoids per-row full readiness and preserves agreed query/payload limits on matched fixtures; navigation/repeated-entry/confirmation costs are measured, not guessed.

## Assumptions
Preserve the existing Prompt3 workspace/guard and partial Prompt4 implementation after fresh verification. Existing domain rules decide publication and restore. No platform refresh, new controller, legal entity, global selector or whole menu/floor redesign. Physical-device evidence is distinct from desktop emulation. GitHub is push-only and user data is never a test fixture.

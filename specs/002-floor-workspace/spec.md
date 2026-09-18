<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Feature Specification: Prompt 5 — Rooms, tables and QR

**Feature identifier**: `002-floor-workspace` (local working directory; Git stays `main`).
**Created**: 2026-09-18. **Status**: analyzed, implementation authorized by the user.
**Authority**: `docs/requirements.md` owns requirements; `docs/IMPLEMENTATION_PLAN.md` P5R owns status. This scope refines `sys-area-001`, `sys-service-point-001`, `sys-qr-001`, `sys-tenant-001`, `data-integrity-001`, `livewire-001`, `i18n-001`, `ui-accessibility-001`, `ui-responsive-001` and `ui-workspace-003`.

## User Scenarios & Testing

### US1 — Select a room and edit the same physical table (P1)
The manager opens one restaurant workspace, selects All, No area or an exact room, chooses one card/list representation, and opens one table editor. The selected room, filters and object survive refresh/history and return. Mobile uses rooms → tables → one editor; desktop keeps context visible. Existing display numbers remain text, and save never rotates QR or creates another table. Serving is an explicit authorized transition to the existing service screen.

Acceptance: direct room URL and foreign-ID rejection; rename01/A-4 without token/code/history change; created room becomes the addressable selected record; cards/list share results and selection; page recovery after archive; cancel/conflict/offline preserves draft and context; two tabs/account change retain server isolation. Selected room stays visible outside searched/paginated/lifecycle results. Room paths/types and damaged hierarchy warnings are truthful and localized.

### US2 — Change space without disrupting service (P1)
The manager creates/edits/reparents rooms through one editor and moves tables through a separate reviewed operation. The server rejects self/cycle/foreign/archived parents, inconsistent depth, stale versions and unsafe moves/archive during direct or merged service or unfinished orders. No role, waiter assignment, order or session is silently changed. Archive/restore retains history and does not implicitly activate tables or QR.

Acceptance: maximum valid hierarchy depth, moving a deep subtree, corrupted-cycle termination, current parent/version recheck and rollback; physical-property editing cannot bypass the separate move preview; independent SQLite writers race service opening against move/archive. Existing first/additional restaurant and onboarding links continue into this same workspace.

### US3 — Create and select an exact bounded set (P2)
Bulk creation is an explicit preview/confirm flow capped at200, reserving archived internal codes. A changed payload invalidates confirmation; replay cannot duplicate the set. Zero-created/all-skipped is a truthful successful result, not a second event failure. Page selection adds only the current page; the bounded selected set persists across pages/modes/filters with hidden-selection disclosure and an explicit clear. No implicit select-all-results exists. The100-target action limit is shown; a200-created result explicitly explains the first100 suggestion without pretending every target was selected.

Acceptance: 1/200/201 limits, duplicates/archived reservations, changed preview/revoked permission, no-op and lost-response retry, foreign/duplicate/malformed IDs, atomic move and truthful per-item QR results. Filters never expand confirmed targets. Selected IDs, drafts and QR tokens are absent from URL state.

### US4 — Prepare, repair and print permanent QR (P1)
The selected table owns QR controls for create, repair image, download, add to print, disable with reason and explicit reissue with the current short-code/version confirmation. Repair, rename, move and reprint preserve identity. Print starts from the exact existing selection, lists unavailable codes with recovery, and prepares one immutable bounded set for screen/browser/PDF. Changed labels/QR/permission require fresh review. A damaged image is not a ready image; failed writes do not publish partial output or create replacement tokens.

Acceptance: real create→partial-file failure→same-code repair, mixed ready/missing/disabled/revoked targets, additive print selection, real Livewire panel asset loading and print-only output, trusted configured public URL despite request Host, decoded content, four-module quiet zone, long EN/LT/RU multipage templates and white print in dark mode. Observe100-label PDF/Base64 memory/size. No physical printer/phone scan is claimed from file tests.

## Requirements and edge cases

- Existing models, Policies, Actions and operation receipts remain authoritative. No controller, second switcher/router, booking/floorplan/service dashboard, new required worker or working-database mutation.
- Every use case validates original input types and repeats explicit actor, branch, parent and resource permission checks. Room editing, table editing, operational state, service opening and QR capabilities remain separate.
- GET/render/prefetch never creates data or QR. QR materialization stays outside long SQLite write transactions; bounded continuation rechecks permission and reports per-item failures without automatic dangerous reconnect replay.
- Search and pagination load only current rows, required paths, selected object and requested panel. No full restaurant graph, per-row Livewire editor, per-table poller or eagerly serialized SVG collection.
- Existing legacy URLs, onboarding, dashboard and QR lookup resolve the same scoped implementation. Remove only proven orphan duplicate views after migrating their tests.
- Room semantics remain exact direct-area, not descendants; All and No area are virtual. Child/ancestor paths handle invalid/cyclic data with a bounded warning and permitted repair.
- Stable service-point ID/internal code, permanent public QR, guest credentials and historical order facts remain distinct. Capacity is configured seats, not occupancy. Direct and merged nonterminal sessions both count as occupied; displayed state has an evaluation time.
- EN/LT/RU labels/errors/quantities, explicit print locale, preserved user names, shared dirty/history/offline behavior, keyboard focus, touch targets, five widths, themes, reduced motion and forced colors are required.
- One physical unit/template geometry and one reviewed data set serve preview/browser/PDF. Livewire downloads are bounded buffered/Base64 responses, not unbounded streaming.

## Key entities

Existing Restaurant/Branch, AreaNode, ServicePoint, QrCode, direct TableSession and TableSessionServicePoint links, FloorOperation and prepared print snapshot. No new business entity is planned.

## Success Criteria

1. Room → table → save → print completes without choosing the restaurant again or using a second table catalogue.
2. Renaming/moving/reprinting preserves table and QR identities in every acceptance fixture; unsafe concurrent service changes perform zero structural writes.
3. Every selected print target is accounted for as ready or explicitly blocked; decoded documents match the reviewed labels and URLs.
4. The same fixtures establish before/after transitions, repeated selections, queries/hydration, memory, HTML/payload and document size. Baseline5e2987c already contains a partial unified workspace; no reconstructed legacy speedup is claimed.
5. All discovered applicable tests execute; coverage remains at least90% PHP and100% included JavaScript lines. Unsupported PHP8.6, physical hardware and native zoom checks are reported separately.

## Assumptions

Retain the current64-node read-path limit and make writes consistent; retain exact-area semantics,200 bulk creation and100 selected-action limits. Supported runtime is PHP8.5; use only the actually available isolated PHP8.6 build without changing production or Composer constraints. User authorizes autonomous implementation, local commit and ordinary push after verification; no further design approval is required.

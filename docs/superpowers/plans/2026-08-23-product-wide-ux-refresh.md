# Product-wide UX refresh implementation plan

**Goal:** Apply the approved “The Calm Service Pass” system across guest, staff, management and governance surfaces without changing domain behavior, routes or database schema.

**Stack:** PHP 8.5, Laravel 13, class-based Livewire 4, Blade SSR, Flux Free 2, Tailwind CSS 4, Pest 4, SQLite, EN/LT/RU.

## Execution status — 2026-08-24

- Complete: design contract/foundation, waiter pilot, kitchen/bar queue hierarchy, table-detail context, guest journey, public/auth/error/superadmin perimeter, documentation, focused tests, production build and disposable-browser checks.
- Deferred by the plan's ownership dependency: management workspace files remain concurrently modified in the shared worktree, so this execution did not overwrite organization, onboarding, menu-management or translation work owned by another stream.
- Current evidence: the owned UI suite passes 122 tests and 1,081 assertions; waiter URL selection adds zero queries; mobile guest Lighthouse scores 100 in accessibility, best practices, SEO and agentic browsing.
- Repository-wide gates are not green while the concurrent stream is unfinished: the observed full-suite snapshot reports 1,130 passed, 14 failed, 2 errors and 9 skipped, and the latest global Larastan reports one error in its untracked menu-image Action. Scoped analysis for this slice and the latest translation audit pass.

## Ownership and execution rules

- Reconcile `HEAD`, staged, unstaged and untracked files before every slice.
- Never modify, format, stage or commit concurrent organization CRUD/seeder/test work unless it has become clean and the UX slice explicitly owns it.
- Use RED → GREEN → REFACTOR for every behavior or executable contract.
- Keep Blade presentational, Livewire orchestration-only, reads in query services and writes in Actions.
- Use semantic tokens and existing Flux controls; add no dependencies.
- Each slice must leave the application releasable and carry fresh focused evidence.

## Slice 1 — design contract and foundation

**Owned surfaces:** root design contract, `.impeccable/design.json`, shared stylesheet, UI Blade components, product mark and design-system tests.

1. Add failing tests for required light/dark semantic roles, 44/56px target tokens, flat card shadow, absence of colored side-stripe print rules, the product-specific logo, new component APIs and DESIGN.md/sidecar parseability.
2. Extend CSS-first tokens without renaming existing stable roles.
3. Implement pure presentation components: context-aware `x-ui.page-header`, `x-ui.priority-row`, `x-ui.workspace-split` and `x-ui.state-panel`.
4. Migrate shared primitives to semantic token utilities and preserve all accessibility contracts.
5. Run focused design-system, architecture, translation and production-build checks.

## Slice 2 — staff pilot

**Owned surfaces:** waiter dashboard/table detail, department dashboard shared view, kitchen/bar views, staff-focused tests and translations.

1. Write failing staff tests for one page heading, visible scope, priority-row semantics, durable keys, mobile detail links and desktop selection state.
2. Derive waiter desktop preview from the existing `BuildWaiterDashboardAction` payload with no new queries; validate nullable URL-backed selection against prepared visible sessions.
3. Render desktop selection controls and existing mobile detail links; clear selection when polling removes the resource without stealing focus.
4. Apply the same queue hierarchy, age/state presentation and 56px action targets to kitchen/bar while retaining isolated polling and existing Actions.
5. Apply context header and semantic sections to table detail without merging its existing child Livewire components.
6. Prove the waiter query budget does not increase.

## Slice 3 — guest journey

1. Write failing tests for semantic page structure, category navigation, cart/action visibility, empty/loading/offline states and 320px-safe markup.
2. Replace repeated nested cards with section/list hierarchy while preserving escaped content, allergen/dietary labels and localized fallback.
3. Keep table context visible and draft totals/actions reachable with one hand.
4. Preserve existing guest Actions, cache boundaries and query budgets.
5. Run guest menu, draft, status, allergen, cache/query and registration-to-paid-table suites.

## Slice 4 — management workspace

**Dependency:** begin only after concurrent `/organizations` CRUD work is committed/clean or its exact file ownership is explicitly available.

1. Write failing tests for Organization → Brand → Branch breadcrumbs/scope, pagination/search preservation, form labels, confirmations and responsive lists.
2. Apply context headers and semantic list/form components to organizations, brands, branches, staff, menu, onboarding and settings.
3. Keep simple status edits inline and retain separate forms for complex operations and destructive confirmation/reason contracts.
4. Keep Eloquent access in existing query services and preserve pagination/query budgets.
5. Run CRUD coverage, onboarding, authorization, seeded-language and organization query-budget suites.

## Slice 5 — perimeter and governance

1. Replace the Laravel mark across public/auth/sidebar contexts with the new product mark and correct decorative/standalone accessible-name behavior.
2. Apply the shared language to landing, authentication, appearance/security settings, superadmin, error states and QR print.
3. Keep superadmin evidence-first and dense; dangerous operations retain consequence, typed confirmation/reason, password confirmation and safe cancellation.
4. Remove the QR colored side stripe while preserving print dimensions, contrast, tokens and the audited generated SVG boundary.

## Documentation and acceptance

1. Update `docs/design-system.md`, `docs/frontend.md`, `docs/accessibility.md` and `docs/tailwind.md` to match shipped contracts.
2. Update compliance/testing/progress/changelog evidence only from observed results; do not add a requirement when behavior is unchanged.
3. Focused checks per slice, then run Pint, Larastan, sequential/parallel/coverage Pest, translation scan/audit, dependency audits and the production Vite build.
4. With the Herd-resolved URL, use disposable isolated Chrome contexts at 320, 390, 768, 1024 and 1440px. Check light/dark, EN/LT/RU, keyboard order, names, focus, zoom, reduced motion, forced colors, console/network errors and horizontal overflow.
5. Finish with a path-scoped diff review proving concurrent CRUD files were neither overwritten nor accidentally included.

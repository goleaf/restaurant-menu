<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tasks: Prompt 6 dish card completion

## Setup and foundation
- [x] T001 Reconcile clean main, current instructions, existing implementation and three real audits; record P6R in docs/IMPLEMENTATION_PLAN.md.
- [x] T002 Create spec/plan/research/data-model/contracts/quickstart in specs/003-dish-card and owned baseline archive/runner; inspect integration without upgrade.

## US1 — One retained dish context
- [x] T003 [US1] Root: RED legacy EN missing/blank/no-GET-write/save cases in tests/Feature/DishCardTest.php.
- [x] T004 [US1] Root: repair missing-original projection in app/Livewire/Forms/Menus/MenuItemForm.php using loaded translation presence, preserve server canonicalization.
- [x] T005 [P] [US1] UI/domain owners: reproduce and fix retained same-dish menu move in app/Livewire/Organizations/Brands/Branches/Menu/{Modifiers,Variants}.php; keep draft/version baselines and add focused Feature/browser tests.

## US2 — Safe independent confirmations
- [x] T006 [P] [US2] Domain: RED archived dish/menu and live POST target substitution in focused variant tests; reject in app/Actions/Menus and Variants.php before writes.
- [x] T007 [P] [US2] Domain: real process SQLite replay/concurrency for variants and clone/rebind using existing tests/Support harness; retain receipts/audit/order snapshots.
- [x] T008 [P] [US2] Media: rerun current media upload/identity/rollback/order/metadata suites, record actual evidence without speculative replacement.

## US3 — Honest preview and create-and-continue
- [x] T009 [P] [US3] Media: RED missing-vs-empty saved preview then repair app/Services/Menus/DishPreviewQuery.php; prove guest parity/no writes in DishPreviewParityTest.
- [x] T010 [US3] Root+UI: RED stale draft/child-save preview; implement independent prepared-preview invalidation in Dish.php and dish-preview.blade.php without auto-calculation or clearing draft.
- [x] T011 [US3] Root: verify existing creation/idempotency/unavailable result, guest configuration/accepted history, shared clone/copy and canonical entrances using current Dish/menu tests.

## Initial-load measurement follow-up
- [x] T017 [US1] Media owner after T009: RED initial Main hydration of100variants/180options in tests/Feature/DishPerformanceTest.php; defer full availability to explicit existing preview via CatalogData/DishQuery/item-editor, preserving gallery/context/direct states and authoritative reasons. Root owns EN/LT/RU keys. Baseline78queries/299models established from isolated b3c7f81 archive.

## Integration and acceptance
- [ ] T012 Root: localize changed UI/errors and correct applicable canonical docs, including docs/localization.md; preserve exact push-only notice in first-party Markdown.
- [ ] T013 Root+UI: actual browser journey/screenshots at320/390/768/1024/1440, keyboard/themes/zoom/reduced-motion/forced-colors; matched baseline queries/models/memory/HTML/payload/actions in existing performance harness.
- [ ] T014 Root: freeze reviewed source and run applicable full backend/parallel/coverage>=90%, complete browser inventory, analyse/Pint/architecture/JS/SCSS/translations/generated styles/build/budgets/caches/audits; distinguish runtime8.5 and8.6.
- [ ] T015 Independent non-author review, repair confirmed issues and rerun affected gates; Spec Kit analysis/convergence and docs/PROGRESS.md exact evidence.
- [ ] T016 Root: reviewed attributable Conventional Commit and ordinary configured-origin push; no remote verification or deployment.

## Dependencies and parallel execution
T001–T002 precede implementation. T003→T004 is the first existing-dish save slice. T005/T006/T008/T009 can run independently with exclusive file owners; T007 follows domain boundary. T010 depends on agreed root/UI API. T011–T013 integrate, T014 follows final review freeze, T015 closes findings and T016 follows all required passing gates or explicit unresolved external blocker reporting. Existing tests cover the full product specification; the new tasks repair audited gaps rather than rebuild working features.

## Phase 2: Convergence
- [ ] T019 [US1] Repair double-encoded persisted allergen/dietary lists at the MenuItem read boundary; reproduce the CatalogData TypeError, preserve editor selections and read-only persistence, and verify explicit saves retain canonical JSON arrays.
- [x] T018 [US3] Complete the real create-to-preview browser journey per FR-007/US3 (original finding: partial, HIGH; repaired): fix acknowledged creation navigation before redirect effects, preserve later input and the confirmed version/offline baseline, avoid syncing new default selector values into the old creation DOM, extract the existing bounded upload fixture adapter and record numeric request evidence. Retain independent save/history/authorization contracts.

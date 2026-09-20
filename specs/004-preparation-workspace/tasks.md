<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tasks: Prompt 10 preparation workspace

## Foundation
- [x] T001 Inspect current main, preserve incoming edits, read canonical context and verify Spec Kit in AGENTS.md/.specify.
- [x] T002 Record scoped specification/research/contracts and ownership in specs/004-preparation-workspace and docs/IMPLEMENTATION_PLAN.md.

## US1 — Canonical authorized entry
- [x] T003 [P] [US1] RED/GREEN separate family access and lifecycle safeguards in tests/Feature/PreparationAccessTest.php and app/Actions/Departments/ResolvePreparationAccessibleDepartmentIdsAction.php.
- [x] T004 [US1] RED/GREEN canonical route, exact invalid IDs, branch/account isolation and legacy entry wrappers in tests/Feature/PreparationWorkspaceTest.php, routes/web.php, app/Livewire/Departments/Dashboard.php and app/Services/Navigation.
- [x] T005 [US1] Add validated URL-backed scope/filter/ticket inputs via app/Livewire/Forms/Departments; reuse shared restaurant context and navigation.

## US2 — Full truth and bounded presentation
- [x] T006 [P] [US2] RED/GREEN >1000-row and multi-order/moved/terminal visit aggregates in app/Actions/Orders/SyncOrderStatusFromTicketItemsAction.php and tests/Feature/PreparationAggregateTest.php.
- [x] T007 [US2] RED/GREEN complete ticket counts, filtered rows, consistent counters, bounded selected details and immutable variants in app/Actions/Departments/BuildDepartmentDashboardAction.php and focused tests.
- [x] T008 [US2] Add Active/Ready/History views, explicit full-vs-partial identity and scoped units in resources/views/livewire/departments/dashboard.blade.php and resources/scss/components/_preparation.scss.
- [x] T009 [US2] Derive waiting/cooking/service timers from actual event timestamps; preserve600/900 thresholds and unknown legacy times in BuildDepartmentTicketDelayTimerAction.php and focused unit tests.

## US3 — Exact safe handoff
- [x] T010 [P] [US3] RED/GREEN shown version, terminal/served/cancelled/revoked conflicts and actual SQLite writers in UpdateDepartmentTicketItemStatusAction.php and dedicated Feature/concurrency tests.
- [x] T011 [US3] Persist pending/delivered metadata on the existing production event and explicitly retry database notifications in DeliverDepartmentTicketItemNotificationsAction.php; prove no duplicate transition/delivery.
- [x] T012 [US3] Review at most24 unique visible rows of one ticket using Form/locked review snapshot and per-row batch outcomes; test hidden selection, stale group member and all-portions semantics.
- [x] T013 [US3] Expose primary sequential actions and explicit permitted shortcuts from KitchenTicketItemStatus; keep serving/cancellation separate and errors alongside exact rows.

## US4 — Sustained operation and print
- [ ] T014 [US4] Freeze visible targets during selection/confirmation; offline/reconnect/session guard and explicit actual refresh, compact mode and focus in Departments Dashboard/Alpine plus Node/browser tests.
- [x] T015 [US4] Reauthorize print every request; snapshot variant/allergen/comment/cancellation/current-vs-original place and full scope in Departments/TicketPrint, print Blade/SCSS and KitchenTicketPrintTest.
- [ ] T016 [US4] Verify mixed-order waiter/kitchen/bar/overview/served journey, two tabs, response loss, notification failure and disabled work with isolated Browser fixtures and screenshots.

## Integration and acceptance
- [ ] T017 Localize every new service label/error in lang/en.json,lt.json,ru.json; verify actual messages, placeholders and scan/audit.
- [ ] T018 Record comparable SQL/hydration/memory/HTML/payload/network metrics; retain page bounds in focused performance tests and docs/performance.md.
- [ ] T019 Independent review + Spec Kit analyze/converge; fix confirmed gaps and preserve existing unrelated work.
- [ ] T020 Execute full discovered PHP/backend/parallel/coverage/browser, JS100%, SCSS, architecture, static, build, schema/seed/cache and dependency gates on owned resources; verify real stable CLI/web and isolated PHP8.6 separately.
- [ ] T021 Update canonical requirements/compliance/architecture/testing/progress/decisions and preserve one push-only block per first-party Markdown; inspect exact diff/secrets and source identity.
- [ ] T022 After required gates, commit only owned verified changes locally and ordinary push origin main; report observed push without remote follow-up.

Dependencies: T001–T002 before work; T003/T006/T010 may proceed independently. T004–T005 establish first complete slice before UI expansion. T007–T015 use agreed payload/mutation contracts. T016–T021 precede T022. Stage is not complete while any required task or gate is unverified.

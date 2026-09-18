<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tasks: Prompt 5 floor workspace

Authoritative status: docs/IMPLEMENTATION_PLAN.md P5R. Paths repository-relative. Behavioral changes require RED/GREEN; exact acceptance is separate from implementation.

## Foundation
- [x] T001 Checkpoint5e2987c, current instructions/skills/rules/runtime and baseline source in restaurant-p5-z2py0b1b.
- [x] T002 Analyze existing floor Actions/queries/UI and map confirmed gaps in specs/002-floor-workspace/research.md.
- [x] T003 Generate and analyze spec/plan/contracts/tasks against canonical requirements and constitution; root records P5R ownership in docs/IMPLEMENTATION_PLAN.md.

## US1 — One selected room and table
- [x] T004 [US1] Replace scaffold tests/Feature/FloorWorkspaceTest.php with actual zone URL→selected table→rename/stable QR scenarios and malformed/foreign URL cases; fix app/Livewire/Forms/Floor/FloorFilterForm.php.
- [x] T005 [US1] Connect created-area identity and existing onboarding links to exact selected context in ServicePoints/Index.php and RestaurantSetupQueryService.php; preserve old scoped routes.
- [x] T006 [US1] Preserve current selection/context and recover an empty last page after archive in ServicePoints/Index.php; verify in FloorWorkspaceTest.php.
- [x] T007 [US1] Add single-instance mobile zone/table/editor navigation, named unsaved dialog and corrupt-hierarchy presentation in floor Index Blade, _floor.scss and existing floor-workspace.js as needed; update tests/floor-workspace.test.mjs.

## US2 — Safe tree and physical properties
- [x] T008 [P] [US2] Add boundary/subtree/corrupt graph RED tests in FloorAreaSafetyTest.php; align AreaNode Actions and AreaNodeQueryService depth/read contract.
- [x] T009 [US2] Isolate parent pagination/search, pin chosen lifecycle context and localize type labels in AreaNodeQueryService.php/AreaEditor.php; test actual scoped selection.
- [x] T010 [P] [US2] Reject hidden existing-table area changes through PointEditor.php; keep dedicated move and truthful audit, local input and occupancy guards; add actual POST regressions in FloorServicePointSafetyTest.php.
- [x] T011 [US2] Run direct/merged service, archive/restore, parent race and independent SQLite concurrency suites; retain roles/history/QR and global WorkspaceActorGuard proof.

## US3 — Bulk result and bounded selection
- [x] T012 [P] [US3] Reproduce all-reserved zero-created event failure in BulkCreate.php; preserve previous selection and truthful created/skipped result; extend bulk operation tests.
- [x] T013 [US3] Verify1/200/201/reserved-code, changed-payload and response-loss receipts in BulkServicePointActionTest.php and FloorOperationTest.php.
- [x] T014 [US3] Make QR add-to-print preserve bounded selection in ServicePoints/Index.php; test mode/page/filter/foreign/tampered IDs and hidden targets in FloorWorkspaceTest.php.

## US4 — Durable QR and checked print
- [ ] T015 [P] [US4] Prove partial/corrupt image and alternate-host cases in FloorQrSafetyTest.php; repair QrCode Actions/services only after RED, retaining identity and post-commit recovery.
- [ ] T016 [US4] Prepare per-target ready/missing/nonactive display and permitted recovery in PrintPanel/QrPrintSnapshotQuery and print-panel Blade; retain immutable versioned snapshot contract.
- [ ] T017 [US4] Prove then fix Livewire-open print assets and print-only prepared labels through QR SCSS/PrintPanel Blade; no duplicate print catalogue.
- [ ] T018 [US4] Decode QR, rasterize actual multi-page PDF, validate quiet zone/white background/physical geometry, and measure100-label buffered download in existing QR tests and isolated artifacts.

## Integration and acceptance
- [x] T019 Remove only proven orphan area views and update tests/style-pipeline.test.mjs; retain safe legacy redirect adapters.
- [x] T020 Root updates EN/LT/RU, canonical requirement/architecture/security/data/interface docs and all first-party Markdown notices.
- [ ] T021 Execute real floor workflow browser matrix, offline/dirty/history/account/revocation scenarios and inspect screenshots in tests/Browser/FloorWorkspaceBrowserTest.php.
- [ ] T022 Record matched baseline/candidate SQL,hydration,memory,HTML,payload,PDF/time and task transitions in docs/performance.md.
- [ ] T023 Freeze source; run complete discovered backend/browser/coverage and all applicable static/format/JS/SCSS/translation/build/schema/cache gates; verify315+ worker artifacts and exact IDs rather than assuming counts.
- [ ] T024 Check actual stable CLI/web and available isolated PHP8.6 constraints/syntax; record unexecuted application/hardware/native-zoom gates honestly.
- [ ] T025 Independent non-author diff/security/contract review, Spec Kit convergence and repair/retest confirmed findings.
- [ ] T026 Root stages only verified attributable files, commits locally and ordinary pushes to existing origin; record observed delivery without remote verification or deployment.

## Dependencies
T001–T004 establish the first vertical slice. T008–T013 area/table work can proceed with exclusive owners; QR T015–T018 uses different files. Root owns shared Forms/routes/lang/schema/Index/UI integration. Browser/build runs are serialized. T019–T026 close every story; failed acceptance remains open. No task authorizes working database changes.

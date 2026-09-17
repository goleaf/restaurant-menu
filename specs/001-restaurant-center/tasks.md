<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tasks: Prompt 4 restaurant center

Authoritative status: docs/IMPLEMENTATION_PLAN.md P4S. This checklist is its executable scoped companion. Tests are required. Paths below are repository-relative.

## Foundation
- [x] T001 Checkpoint current branch/index/source and read canonical/scoped rules; baseline evidence in restaurant-p4-speckit-uhgxj6lc.
- [x] T002 Create and analyze spec.md, plan.md and tasks.md with the project constitution; reconcile docs/requirements.md before completion.
- [x] T003 Prove second-restaurant preservation/replay and current schema in tests/Feature/RestaurantCenterCreationTest.php, RestaurantSetupMenuSafetyTest.php, RestaurantSetupSchemaCompatibilityTest.php.

## US1 and US3 — Safe exact preparation
- [ ] T004 [US3] Add failing malformed menu/space selection tests in tests/Feature/RestaurantSetupSelectionTest.php; fix app/Livewire/Onboarding/RestaurantSetup.php.
- [ ] T005 [US1] Run existing actor/rollback/concurrency safety and repeat-safe creation suites; repair confirmed gaps within assigned Actions/Policies only.
- [ ] T006 [US3] Verify menu-first, old completed/partial/archived history, QR/image failure, no-op menu preservation and two-tab cases in existing preparation and onboarding tests.

## US2 — One authorized center
- [ ] T007 [P] [US2] Reproduce archived visibility and incompatible initial URL in tests/Feature/RestaurantCenterAccessStateTest.php.
- [ ] T008 [US2] Correct app/Services/Organizations/RestaurantCenterQuery.php, app/Livewire/Restaurants/Index.php and RestaurantCenterFilterForm.php; preserve existing list return context and scope.
- [ ] T009 [US2] Verify canonical editors, empty parents, legacy routes, explicit workspace and media/archive lifecycle through existing center/structure tests.

## US4 — Accessible Flux/SCSS composition
- [ ] T010 [P] [US4] Reproduce missing error association/native dialog names in tests/Feature/RestaurantCenterInterfaceTest.php.
- [ ] T011 [US4] Fix assigned restaurant/onboarding Blade views and resources/scss/components/_restaurant-center.scss; add actual image/placeholder with bounded prepared data.
- [ ] T012 [US4] Exercise tests/Browser/RestaurantCenterBrowserTest.php and existing full journey; inspect screenshots and required width/locale/theme/accessibility states, long duplicate names and keyboard paths.

## Integration and acceptance
- [ ] T013 Root: reconcile canonical requirements, architecture/data model/compliance and ledgers; translations EN/LT/RU and all first-party Markdown push-only notices.
- [ ] T014 Root: validate clean/upgrade schema and isolated stable/experimental runtime identity/platform without bypasses.
- [ ] T015 Root: run current full backend/browser/coverage, static/format/architecture/JS/SCSS/translation/build gates with inventory and exact failures.
- [ ] T016 Root: measure matched fixture user steps/repeated input/confirmations, SQL/memory/HTML/payload/network in docs/performance.md.
- [ ] T017 Non-author review real attributable diff, fix findings and repeat affected checks; root completes scoped local commit and ordinary push only when required gates pass.

## Dependencies and parallel execution
T001–T003 precede new behavior. Domain T004–T006, center T007–T009 and UI T010–T012 have distinct file owners and may proceed concurrently; root owns routes/migrations/translations/docs. UI coordinates prepared row fields with center. Browser/build are serialized. T013–T017 integrate all stories; failed gates remain incomplete. No automatic hooks or Git branch changes.

## Phase 6: Convergence — verified additional gaps

- [ ] T018 [US2] Prevent parent restoration from silently reactivating restaurants/prior non-owner staff access in app/Actions/Organizations/RestoreOrganizationAction.php and app/Actions/Brands/RestoreBrandAction.php; prove atomic consequences/history/no-op and rights in tests/Feature/RestaurantStructureRestoreSafetyTest.php.
- [ ] T019 [US3] Prove QR image failure after real database commit retains permanent identities and safely retries in tests/Feature/RestaurantPreparationConnectionsTest.php using owned isolated file SQLite.

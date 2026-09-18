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
- [x] T004 [US3] Add failing malformed menu/space selection tests in tests/Feature/RestaurantSetupSelectionTest.php; fix app/Livewire/Onboarding/RestaurantSetup.php.
- [x] T005 [US1] Run existing actor/rollback/concurrency safety and repeat-safe creation suites; repair confirmed gaps within assigned Actions/Policies only.
- [x] T006 [US3] Verify menu-first, old completed/partial/archived history, QR/image failure, no-op menu preservation and two-tab cases in existing preparation and onboarding tests.

## US2 — One authorized center
- [x] T007 [P] [US2] Reproduce archived visibility and incompatible initial URL in tests/Feature/RestaurantCenterAccessStateTest.php.
- [x] T008 [US2] Correct app/Services/Organizations/RestaurantCenterQuery.php, app/Livewire/Restaurants/Index.php and RestaurantCenterFilterForm.php; preserve existing list return context and scope.
- [x] T009 [US2] Verify canonical editors, empty parents, legacy routes, explicit workspace and media/archive lifecycle through existing center/structure tests.

## US4 — Accessible Flux/SCSS composition
- [x] T010 [P] [US4] Reproduce missing error association/native dialog names in tests/Feature/RestaurantCenterInterfaceTest.php.
- [x] T011 [US4] Fix assigned restaurant/onboarding Blade views and resources/scss/components/_restaurant-center.scss; add actual image/placeholder with bounded prepared data.
- [x] T012 [US4] Exercise tests/Browser/RestaurantCenterBrowserTest.php and existing full journey; inspect screenshots and required width/locale/theme/accessibility states, long duplicate names and keyboard paths.

## Integration and acceptance
- [x] T013 Root: reconcile canonical requirements, architecture/data model/compliance and ledgers; translations EN/LT/RU and all first-party Markdown push-only notices.
- [x] T014 Root: validate clean/upgrade schema and isolated stable/experimental runtime identity/platform without bypasses.
- [x] T015 Root: run current full backend/browser/coverage, static/format/architecture/JS/SCSS/translation/build gates with inventory and exact failures.
- [x] T016 Root: measure matched fixture user steps/repeated input/confirmations, SQL/memory/HTML/payload/network in docs/performance.md.
- [x] T017 Non-author review real attributable diff, fix findings and repeat affected checks; root completed scoped implementation commit `e472d6c` after required gates passed; ordinary `git push origin main` returned exit0 (`a3c8926..e472d6c main -> main`). No remote verification or deployment.

## Dependencies and parallel execution
T001–T003 precede new behavior. Domain T004–T006, center T007–T009 and UI T010–T012 have distinct file owners and may proceed concurrently; root owns routes/migrations/translations/docs. UI coordinates prepared row fields with center. Browser/build are serialized. T013–T017 integrate all stories; supported-runtime gates pass and T017 is closed from the actual successful implementation commit/push. PHP8.6 application constraints and unverified physical-device/native-zoom checks remain explicit limitations. No automatic hooks or Git branch changes.

## Phase 6: Convergence — verified additional gaps

- [x] T018 [US2] Prevent parent restoration from silently reactivating restaurants/prior non-owner staff access in app/Actions/Organizations/RestoreOrganizationAction.php and app/Actions/Brands/RestoreBrandAction.php; prove atomic consequences/history/no-op and rights in tests/Feature/RestaurantStructureRestoreSafetyTest.php.
- [x] T019 [US3] Prove QR image failure after real database commit retains permanent identities and safely retries in tests/Feature/RestaurantPreparationConnectionsTest.php using owned isolated file SQLite.
- [x] T020 [US2] Remove the three unreachable legacy structure list/editor PHP classes and their three Blade views. Migrate the 50 direct/helper-mediated test declarations in 14 files to the canonical Center, IdentityEditor, StructureCreate and setup contracts without dropping authorization, media, subscription or lifecycle cases. Assigned to center owner; root retains shared translations/routes.
- [x] T021 [US4] Remove obsolete onboarding/retired-list translation keys from EN/LT/RU; repair confirmed floor label keys and verify current form labels, empty business defaults and saved counts. Translation audit has zero findings; preserve current persisted icon values.
- [x] T022 [US3] Exercise cancellation of an in-flight shared floor form without changing a draft or replaying its pending operation; retain the JavaScript 100% line gate.

- [x] T023 [US2] Reproduce actual browser hierarchy search failure; clear parent search only on child navigation, retain it for the properties return URL, and preserve authorized scopes, sorting and parent pagination. Query/access/translation slice44/44139 passes; independent review clear.

- [x] T024 [US3] Preserve a saved starter menu name when the canonical editor lacks an English translation; verify no GET/cancel write and intact existing translations, schedule and publication in MenuTranslationManagementTest.
- [x] T025 [US3] Require explicit area/table Action actors and route bulk validation to actual Form fields without hiding receipt conflicts; preserve localized input and zero-write rejection cases.
- [x] T026 [US2] Repair numeric/null Livewire URL hydration for canonical floor links; move scoped QR reads to the existing query service and scalar presentation data to Blade; retain architecture guards.
- [x] T027 [US3] Add the missing creation-receipt organization FK index with a reversible additive migration and data-preserving schema round-trip evidence.

- [x] T028 [US4] Remove the unreferenced old onboarding focus provider and summary view, preserve active shared focus tests and CSV/recovery checks, prune only its seven unused locale keys, and reconcile the field-label manifest with the actual floor form.
- [x] T029 [US2] Give the canonical wizard exactly one current navigation destination without changing workspace selection or menu items; WorkspaceNavigationTest10/45 passes, browser DOM assertions remain in final acceptance.
- [x] T030 [US4] Add an explicit center asset entry/scenario guard and document independently reviewed category reserve redistribution under unchanged whole-graph/page ceilings, with unchanged font hashes and every lazy JavaScript chunk counted.

- [x] T031 [US1] Close the reproduced first-launch bypass from a false additional-business intent; share canonical organization creation eligibility across the wizard and standalone structure entry, preserve explicit permitted new-business creation and existing-organization branch rights, and reject revoked contexts without writes.
- [x] T032 Root: rehearse the twelve actual schema prerequisites on a private consistent copy, prove complete rollback and data preservation, then apply only that set locally under one IMMEDIATE transaction with backup and precommit checks; keep four unrelated migrations pending.
- [x] T033 [US2] Preserve the general Add restaurant entry for a global administrator who may create inside an eligible existing organization but may not create a new organization; prove unavailable-parent and revoked-context rejection, preserve bounded queries, and exercise the real browser creation without granting ownership. Scoped60/308 and browser11/715 pass; paired PHP6/114 and network6/78 preserve honest cost evidence.

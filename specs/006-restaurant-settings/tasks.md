<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tasks — Prompt 14

## Setup / foundations
- [x] T001 Inspect local branch, shared dirty changes, rules, canonical docs and installed runtime.
- [x] T002 Map parameters/writers/consumers and plan in specs/006-restaurant-settings/research.md.
- [x] T003 Root: additive migrations, Branch model and BranchSettingsChange factory/model.
- [x] T004 Settings agent: common transaction/replay contract in app/Actions/Branches/ExecuteBranchSettingsChangeAction.php.

## US1 — independently save profile (first vertical slice)
- [x] T005 [US1] Profile agent: failing independence/conflict/no-write tests in tests/Feature/BranchPublicProfileSectionsTest.php.
- [x] T006 [US1] Profile agent: profile Form, Rules, allowlisted Action and version/audit.
- [x] T007 [US1] Root: remove broad Settings::save, separate baseline/errors and readonly mount in app/Livewire/Organizations/Brands/Branches/Settings.php.

## US2 — safe settings groups
- [x] T008 [P] [US2] Settings agent: guest/settlement/locale/advanced Forms, scoped Rules/Action and regression tests.
- [x] T009 [US2] Settings agent: currency guard in all writers, defaults/first-create safety and independent-process conflicts.
- [x] T010 [US2] Settings agent: guest entry/invite acceptance enforcement; preserve legacy modes, mandatory confirmation and paid history.

## US3 — localized public content/media
- [x] T011 [P] [US3] Profile agent: pure shared presenter, guest readers and locale/fallback/contact tests.
- [x] T012 [US3] Profile agent: versioned actor-bound media replace/remove/replay and disk/rollback tests.
- [x] T013 [US3] Root: explicit saved/draft preview and separate Flux upload controls in settings Blade.

## US4 — bounded maintenance
- [x] T014 [P] [US4] Cleanup agent: recheck actual inactivity in CleanupInactiveTableSessionsAction.php and race regressions.
- [x] T015 [US4] Cleanup agent: readonly preview/confirmed receipt operation and permission/replay/bounded-report tests.
- [x] T016 [US4] Root: named confirmation dialog and truthful results in settings Blade.

## US5 — usable center
- [x] T017 [US5] Root: five sections, URL/search/focus, common dirty/offline/history guard and SCSS.
- [x] T018 [US5] Root: EN/LT/RU labels/help/validation and exact canonical links.
- [ ] T019 [US5] Root: Livewire/browser responsive/navigation/matched-fixture performance tests.

## Integration / acceptance
- [ ] T020 Root: migrate old tests/callers; independent review and fix regressions.
- [ ] T021 Root: canonical backend/parallel/coverage/browser/static/JS/SCSS/translation/build gates, isolated migration/idempotency and PHP8.6 platform evidence.
- [ ] T022 Root: reconcile canonical docs/compliance/prior-stage status and preserve push-only block in first-party Markdown.
- [ ] T023 Root: final diff/secrets/review; commit/push only if required gates pass.

Dependencies: T003→T004→T006/T008/T015; T005→T006→T007 first vertical; profile/other-group/cleanup work has distinct owners; integration follows all. No blanket completion from targeted tests.

Verification checkpoint: all 33 former settings scenarios remain represented. Independent reviews corrected cache publication races, stale cleanup result presentation and repeated unrelated validation messages. Final browser and aggregate results remain pending below their respective tasks; interrupted coverage is not a pass.

Resource checkpoint: sequential backend and initial parallel coverage exit 130 after explicit disk-pressure stops; no aggregate result or current PHP coverage is available. Final settings UI tests pass 20/138; browser settings 7/786, native history 1/39 and HTTP identity 1/4 pass. Fallback-history diagnosis remains open. Commit/push stay blocked by incomplete required verification.

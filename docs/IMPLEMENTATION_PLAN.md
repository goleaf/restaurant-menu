<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Restaurant Menu completion implementation plan


## 2026-09-15 — next product level (current run)

Baseline: local main `8dd6de4`, 46 modified files and two untracked rule files inherited at entry. Their content is preserved in an external temporary baseline snapshot and is not claimed as new work. PHP/Laravel/Livewire patch versions are checked locally; Herd maps this checkout to `ruflo.test`.

| Task | Status | User outcome | Owner | Dependencies | Acceptance |
| --- | --- | --- | --- | --- | --- |
| State, policy, tools and baseline | done | Safe continuation on existing main | Root | None | Current versions; actual Boost/DevTools/Playwright local calls; exact Markdown policy and idempotence |
| Focused menu workspace | done | One active section, clear restaurant context, history and draft protection | Design | Baseline | Allowlist/revoked-access tests; same-fixture query/HTML/snapshot comparison; responsive browser |
| Photo presentation | done | Per-photo focal point, card/detail preview and EN/LT/RU alt/caption | Media/root | Existing gallery | Identity/stale/rollback/promotion tests; non-destructive files; selected-only public metadata |
| Translation and guest continuity | done | Original beside translation; safe empty-field copy; restaurant-scoped guest language | Localization | Existing language panels | Copy/error focus tests; basket/admin/restaurant isolation browser regressions |
| Content quality and quick operations | done | Actionable missing-content filters and scoped bulk category/availability operations | Root/backend | Workspace | Exact selection, tenant and revoked-right tests; bounded reads |
| Local CSV exchange | done | Template, row errors, preview and explicit create/update with safe replay | Root/backend | Workspace | UTF-8, exact cents, malicious/foreign IDs, stale preview, atomic retry and safe export tests |
| Repeatable setup | done | Running setup again preserves APP_KEY | Root | Baseline | Isolated environment-file command tests; unchanged actual application key |
| Integrated restaurant/browser/performance proof | done | Usable complete authoring and service journey | Root | Implementation | Five widths/themes/keyboard/offline; real screenshots; fixed-fixture measurements |
| Independent implementation review | done | Regressions corrected before delivery | Independent reviewer | Implementation | Actual diff and requirement review, targeted reruns |
| Final local gates and documentation | done | Reproducible verified result | Root | Review | Composer/Pint/Larastan/backend/browser/parallel/coverage/translations/npm/build and isolated schema/seeds/caches |
| Local commit and ordinary push | in_progress | Reviewed changes delivered to existing origin/main | Root | Gates | User explicitly approved all 253 integrated paths, including 48 inherited paths; addressed staging and ordinary push now authorized; no other GitHub requests |

Current acceptance: all 15 browser cases pass in bounded, separate WebKit processes (940 assertions); the one-process suite stalled and is not recorded as passed. Full backend and coverage each pass 2,503 tests / 54,422 assertions; coverage is 93.8% against the unchanged 90% minimum. The affected final layout browser regression additionally passes 94 assertions. The entry status contains 40 staged and eight unstaged paths; the current index contains 41 staged paths, including an inherited CSS recovery hunk. The user explicitly approved including the 48 inherited paths with this implementation in one verified integration commit and performing ordinary git push origin main. Ownership is resolved; the reviewed 253-path diff and 1,097-file executable manifest match the approval snapshot.

Root exclusively owns shared JSON translations, manifests, lockfiles, migrations, factories, documentation and app.js. Catalogue operations owns Catalog/CatalogData/CatalogFilterForm and the bulk Action/Form/view/tests; performance owns CSV implementation and MenuOperationKind until handoff. Completed files return to root for integration. Design owns Index, its view, CatalogFilterForm, menu-workspace.js, app.css and MenuWorkspaceTest. Media owns explicitly assigned image Actions/trait/Form/view/JS/presentation helper and tests. Localization owns translation panels/JS and guest-locale source/tests. Browser runs, global formatting, migrations and full suites are serialized by root. No application-data reset or production seeding.

## 2026-09-15 — offline interaction and validation follow-up

Baseline: clean local `main` at `8dd6de4`. The completed product and recovery deliveries below remain the implementation baseline. This bounded pass verifies the remaining offline dialog/language and repeated catalogue validation scenarios, then corrects reproduced defects without changing the stack or schema.

| Task | Status | Owner | Dependencies | Areas and acceptance evidence |
| --- | --- | --- | --- | --- |
| State, policy and tools | done | Root | None | Clean main; idempotent Markdown policy synchronization made no changes; existing Boost and isolated Chrome/Herd navigation are available. |
| Reproduce remaining interaction gaps | done | Root/catalogue/media reviewers | State | Real browser and focused Feature regressions must fail before implementation; preserve earlier passing scenarios. |
| Catalogue validation recovery | done | Catalogue worker | Reproduction | Base-name conflicts are visible in the EN panel; repeated unchanged validation failures reopen the affected language without losing text. |
| Guest offline interactions | done | Root | Reproduction | Escape/close dismiss immediately without connectivity; focus is restored and reconnect cannot reopen a dismissed dialog; language controls cannot falsely change while offline. |
| Image batch recovery | done | Media worker | Reproduction | Actual multipart browser: 41 assertions; stable accumulated previews, serialized removal/offline guards, exact +2 images, ordering and promotion. Feature tests cover rollback, retry and committed receipt. |
| Independent final review | done | Independent reviewer | Implementation | Inspect actual final source/tests and correct confirmed findings. |
| Final local gates and documentation | in_progress | Root | Review | Run applicable local suites, coverage and quality checks on stable source; record fresh results separately from previous deliveries. |
| Commit and ordinary push | pending | Root | Gates | Exact owned staging, Conventional Commit on main and ordinary push; no additional GitHub request. |

Root owns guest views, browser tests, shared documentation/translations and all command execution. The catalogue worker exclusively owns the shared translation-fields view, translation JavaScript and its agreed Feature regression file. No concurrent runners, formatters, installations or migrations.

## 2026-09-15 — product recovery follow-up

Baseline: clean local `main` at `0450bac12ed22ac44fb390a267c154043106b09a`. The completed product delivery below remains in place. This bounded follow-up addresses concrete recovery gaps found in the current implementation; it does not restart the redesign or change the stack, schema or dependencies.

| Task | Status | Owner | Dependencies | Areas and acceptance evidence |
| --- | --- | --- | --- | --- |
| Local state and policy | done | Root | None | Clean main verified; idempotent policy synchronization changes no files; all 175 Markdown files and eight skill mirrors pass. |
| Guest recovery | done | Guest worker/root | State | Observed failing regressions, then 60 focused tests / 826 assertions pass. Active URL locale, retained configuration/attempt, explicit availability retry and vanished-trigger focus fallback are implemented; real guest recovery browser scenario passes. |
| Media rollback cleanup | done | Media worker | State | Observed cleanup exception and stale callback deleting an original image on the next commit. All 70 focused tests / 431 assertions pass, including real variant files, disk/logger failure and transaction independence. |
| Staff feedback and offline controls | done | Root | State | Observed stale-success RED, then 84 affected tests / 637 assertions pass. Offline/reconnect browser controls and semantic waiter details pass; compact kitchen timers retain 56-pixel actions. |
| Independent final review | done | Independent reviewer | Guest, media, staff | Reviewed actual implementation and tests; confirmed focus/offline findings were corrected. Final implementation and documentation review found no remaining blocker. |
| Final local gates and documentation | done | Root | Review | Backend 2,366, WebKit 11, parallel 2,366, coverage 93.8% and quality/isolated gates pass on unchanged source; testing.md records fresh evidence and the interrupted browser attempt. Final documentation checks precede commit. |
| Local commit and ordinary push | done | Root | Gates | Exactly 44 owned files committed on main as `efaba627b8715546a9602d8bbe8afaf34c025dca`. Ordinary `git push origin main` returned exit 0: `0450bac..efaba62 main -> main`. No other GitHub request or production deployment occurred. |

Root owns shared translations, views/components, documentation, manifests and all test execution. Workers own only explicitly allocated guest/media source and regression files. No concurrent test runners, dependency installs, migrations or project-wide formatters are permitted.

## 2026-09-14 — product delivery (completed)

Scope: the current user-authorized restaurant product improvement, starting from local `3e8178b401722f5a6e9bcb46a6b73ae4bce132da`. This section records the completed authorized delivery. Earlier audit sections below are historical records, including incomplete publication instructions; they do not authorize GitHub access. GitHub is used only for the final ordinary push, with no subsequent remote verification request. Local quality gates determine readiness.

| Stage | Status | Owner | Dependencies | Areas and acceptance evidence |
| --- | --- | --- | --- | --- |
| 1. State and GitHub restriction | done | Root | None | All 175 tracked/new Markdown policy blocks pass uniqueness/frontmatter checks; eight skill mirrors and 314 local links pass. Current main and workflow files are preserved. |
| 2. Capabilities and design tools | done | Root/design | 1 | Reuse current Laravel Boost, Chrome DevTools and Playwright with isolated profiles and Herd. Actual local navigation/inspection smoke tests observed; official package documentation and installed APIs checked. Equal-fixture measurements recorded for affected performance paths. |
| 3. Visual system and shared controls | done | Design | 2 | Shared semantic light/dark tokens, local Noto Sans and compact controls are implemented. Final WebKit tests and inspected screenshots cover owner/settings/guest/waiter/kitchen/bar at all five requested widths; keyboard and enlarged text checks pass. |
| 4. Complete image workflow | done | Media/root | 2 | Bounded decode, orientation/variants, previews and ordering plus image-identity checks and durable mutation receipts. Final media batch: 116 tests / 753 assertions, including lost-response/ABA replay, outer rollback, actual rendered-button arguments and cleanup resume after reload. |
| 5. Multilingual catalogue editor | done | Root | 2, 3 | Accessible shared EN/LT/RU panels, required-name indicators, error focus, draft preservation and explicit base-language mapping; focused item editing and guest preview. Independent translation persistence tests. |
| 6. Guest menu and locale | done | Root/design | 4, 5 | Lazy selected-item gallery/details work in browse-only mode; localized search/category/allergen filters and coherent locale preserve the basket and nested component. Final focused locale: 12 tests / 96 assertions; actual WebKit guest flow: 1 test / 43 assertions. Full browser integration remains stage 11. |
| 7. Restaurant workflows and useful operations | done | Root/design | 3, 5, 6 | Scoped search, availability, movement, duplication and bounded resumable deletion are implemented. The full browser suite includes the real onboarding-to-paid-table-closure journey; all 10 scenarios / 591 assertions pass. |
| 8. Rights, polling and recovery defects | done | Safety | 2 | Reproduce and fix ManageSettings closure authorization, snapshot-correct polling, and maintenance retention on restore+rollback failure using isolated SQLite. Focused safety 56 tests/441 assertions pass, including positive/negative/retry/race/real process-lock cases. Integration-wide gates remain stage 11. |
| 9. Measured performance | done | Root/safety/media | 4, 6, 7, 8 | Record fixed-fixture query/hydration/payload/image/polling comparisons; bound catalogue/dashboard reads and large delete work without unsupported runtime workers. Distinguish query savings from latency. |
| 10. Independent review | done | Independent reviewer | 3–9 | Independent final reviews confirmed image identity/replay/cleanup, guest locale state, revoked access, cache batching and escaped Blade data. Reproduced issues were fixed; no unresolved blocker. |
| 11. Final local validation and docs | done | Root | 10 | Backend 2,351, WebKit 10, parallel 2,351 and canonical coverage 93.8% pass with zero failures/skips. Composer/Pint/Larastan/translations/npm/build, isolated migrations/seeds/caches and final documentation inventories pass; testing.md records current evidence. |
| 12. Commit and push | done | Root | 11 | Addressed staging and local Conventional Commit `ef7b67afc231877c7c1abba4a6f8427306c3bc2e` completed. Ordinary `git push origin main` returned exit 0: `3e8178b..ef7b67a main -> main`. This documentation follow-up records the observed result; no extra GitHub request or production deployment occurred. |

Shared ownership: root exclusively edits JSON translations, manifests/lockfiles and Markdown. Media worker exclusively owns the new additive menu-operation migration/models/factories and resumable catalogue Actions. Media owns image-processing Actions/helpers/tests; safety owns waiter closure/change detector/polling and backup restore/tests; design owns CSS and agreed shared presentation components. Workers run scoped tests/Pint only. Global formatting, dependency installs, browser suite and final suites are coordinated by root. No application-database destructive tests, second server, runtime queue/cron dependency or production deployment.

Completion means implemented and observed behavior, not a document or mock-up. Each row moves to `done` only with its actual evidence. External limitations stay explicit while independent work continues.

## 2026-09-14 — eighth audit: area/table transport and bounded bulk creation

Baseline: clean published `51840d6`. Extend original-value validation to area and service-point editors, and move the existing 200-table allocation limit into the reusable bulk Action. No dependency, product or schema change is planned.

- [x] Reproduce malformed create/edit/bulk Livewire transport with valid controls, unchanged persistence, and existing tenant/permission regressions. Preserve raw editable values, trim strings only, and combine numeric/integer validation without changing Blade bindings.
- [x] Reproduce direct bulk Action range violations and repeated reads with bounded factory data. Enforce positive ascending ranges of at most 200 before allocation, retain transactional model events, reject cancelled required saves explicitly, and derive successful preview state from persisted results instead of repeating ownership/code reads.
- [x] Audit all models/factories/migrations, Action/Request/Form boundaries, Markdown links and repository skill mirrors. Update affected contracts and retain valid deployed migrations and correct definitions.
- [ ] Run targeted RED/GREEN tests, independent specification and quality review, Pint/Larastan, browser/parallel/sequential coverage suites, dependency audits/build/translations and isolated migration/seed/cache gates against stable source. Require zero failing/skipped tests and report statement coverage separately.
- [x] Historical eighth implementation is present in current local `3e8178b`; no root publication claim is made. Remote verification is prohibited under the current GitHub restriction.

Design: keep existing field ownership and focused Actions. Raw browser values remain available to shared rules; server-owned context and capabilities retain their existing types/authorization. The bulk Action guards allocation independently of Livewire, validates existing area ownership, preserves archived-code reservation and model events, and reports success only after every required write succeeds. Existing numeric range rules and localized field errors remain the presentation contract. A full Form rewrite or an upsert bypassing events would add unnecessary behavior changes. A bounded worker owns transport code/tests; the primary agent owns bulk invariants, integration and evidence. Reviewers inspect specification compliance before code quality.

## 2026-09-14 — seventh audit: menu transport types and dependent selections

Baseline: clean published `47badf0`. Review the remaining catalog, modifier and variant editors after the kitchen-department fix. Typed public form values may be coerced before shared rules run; dependent selections are also consumed by hooks, uniqueness rules and prepared reads. Validate this with real Livewire POSTs before editing implementation.

- [x] Reproduce malformed scalar, money, array and dependent-selector values for catalog/modifier/variant create and edit operations, including valid controls and permission restrictions.
- [x] Preserve original editable values until shared validation; guard dependent read selections and normalization without weakening tenant scopes, capability checks, money precision or accepted browser encodings. Reuse existing Actions, shared rule builders and the branch-menu component boundary.
- [x] Reconcile all model/factory/migration, Action/Request/Form, Markdown and skill inventories. Update affected current guidance and preserve correct model definitions, deployed migrations and data.
- [x] Run targeted regressions, formatting/static analysis, full browser/parallel/sequential coverage suites, dependency audits/build/translations and isolated schema/seed/cache gates against unchanged source. Final backend 2,093 and Browser 5 pass with zero failures/skips; canonical coverage is 93.8%, separately from the 100% pass rate.
- [x] Review exact owned changes, commit in English, push main normally and verify the remote SHA. Implementation `6a66abb675da1222ae56b64b17ee87bf6ac0e7ca` was pushed successfully; the remote ref returned the exact SHA.

Design: fix the existing validation boundary without changing Blade bindings or adding parallel field ownership. Editable values must remain untrusted through transport; server-owned identity/capability properties retain their locks/types. Selected read contexts may safely project malformed input to an empty selection, but the original submitted value must remain available for validation. Existing focused Actions continue to own persistence. Any required schema or form extraction must be justified by a reproduced defect rather than inventory counts alone.

## 2026-09-14 — sixth audit: current image state, transport validation and bounded access checks

Baseline: clean published `665dcb4`. Eight image retry regressions fail because callers can hold stale references after another save or outer rollback. Actual Livewire POST tests also reproduce malformed kitchen-department state being coerced or rejected before validation. A measured access check hydrates every branch assignment to answer one boolean question.

- [x] Reproduce all three findings with factory data, isolated storage and actual Livewire transport; reconcile all 49 models/factories and 88 migrations against the unchanged live schema.
- [x] Reload current image references inside the existing transaction through original parent scopes; persist only image changes, preserve unrelated caller state, and verify stale replacement/removal, rollback retry and rejected/deleted parents.
- [x] Validate kitchen-department transport values before normalization and preserve valid inputs, authorization and localized field errors.
- [x] Replace collection hydration in one-branch access decisions with bounded existence checks while retaining membership, subscription and assignment fallback rules.
- [x] Reconcile current Markdown and all eight skill/provider copies; document measured query costs and preserve valid historical migrations and evidence.
- [x] Run targeted and full browser/backend/parallel/coverage gates, formatting/static analysis, dependency audits/build/translations and isolated migration/seed/cache checks on stable source. Final backend 1,971/48,546 and Browser 5/433 pass without failures/skips; canonical application coverage is 93.9%, with all 222 Actions executed.
- [x] Review exact changes, commit in English, push main normally and verify the remote SHA. Implementation `395439a4d4e2db3be2575301b272129b0c4f9b8a` was pushed successfully; the remote ref returned the exact SHA.

Design: keep the existing image Actions and shared filesystem compensation boundary. Each domain Action reads a selected, current, original-parent-scoped record inside its transaction and synchronizes only persisted image/timestamp attributes to the caller. No generic image framework, new repository, queue or schema is needed. The access optimization retains the existing boolean interface; list queries remain separate. Independent workers own the access model/test and kitchen input implementation, while the primary agent owns image retries, integration and evidence.

## 2026-09-14 — fifth audit: media persistence failure contracts

Baseline: clean published `30cc939`. Reuse the completed article/application and full model/migration inventories, then inspect remaining image mutation callers. Installed Laravel 13 source confirms that `saveOrFail()` and `deleteOrFail()` retain model-event false returns; a persistence callback may also commit successfully before an after-commit callback throws.

- [x] Reproduce cancelled image saves/deletes and post-commit exceptions with factory data and isolated public storage.
- [x] Make the shared media transaction boundary and concrete image Actions distinguish rollback, rejected persistence and committed cleanup errors. Preserve tenant validation, existing events, bounded galleries and outer transactions.
- [x] Reconcile model/migration, Action/request, Markdown and eight-skill inventories; update affected contracts and provider copies without rewriting valid deployed migrations.
- [x] Run focused regressions, formatting/static analysis, complete browser/parallel/coverage suites, audits/build/translations and isolated migration/seed/cache gates. Final backend 1,898/48,303 and Browser 5/433 pass with zero failures/skips; canonical coverage is 93.9%, and all 222 Actions execute.
- [x] Review exact changes, commit in English, push main normally and verify the remote SHA. Implementation `cc48eb9bddba81f799a90e345d5557dce432b915` was pushed successfully; the remote ref returned the exact SHA.

Design: retain the existing shared image Actions and their void persistence callback contract. Register replacement compensation within an owned database transaction before invoking persistence, so only rollback removes a new file. Concrete callers must throw when a model event rejects a required write; gallery operations remain all-or-nothing. Merely testing dirty attributes cannot prove that a save succeeded, and a new queue/table is unnecessary for this synchronous contract. No product feature, dependency or schema change is planned.

## 2026-09-14 — fourth audit: bounded media cleanup and strict factory graphs

Baseline: clean published `9aaa839`. Recheck the previous audit's explicit limits and the complete model, migration, validation, Markdown and skill inventories. Isolated reproductions confirm unbounded parent-media hydration, cross-menu category cascade leakage with malformed references, non-terminating category discovery with a cycle, and strict-loading failures in two optional factory states.

- [x] Reproduce the findings using owned temporary SQLite data and files; inspect installed framework behavior and existing transaction/cascade contracts.
- [x] Stream parent media paths through a temporary file with one transaction cleanup registration instead of retaining every item ID/path. Preserve outer commit/rollback behavior, selected ID batches, model events and original-parent reloads. Bound category traversal to the original menu and terminate cyclic input; document any remaining hierarchy-memory limit. The integrated media/schema slice passes 51 tests/361 assertions, including wide-category filters, event vetoes, whole-menu cycles and malformed inverse references.
- [x] Complete missing variant/item/menu/branch relations before factory state construction, preserving already loaded objects and zero repeated relationship reads. Correct the additional department-readiness factory total through the existing active-line integer sum; focused factory/schema/dispatch tests pass 67/616 after observed RED cases.
- [x] Reconcile all 49 models/factories, 88 deployed migrations, Action/validation boundaries, first-party Markdown and eight project skills. Correct the unsafe unconditional lazy-batch mutation guidance in every provider copy; preserve valid schema/history and dependencies. Fresh schema totals remain 61 tables/633 columns/308 indexes/144 foreign keys, with no missing leading FK index or redundant nonunique prefix.
- [x] Run focused RED/GREEN regressions, formatting/static analysis, browser/parallel/sequential coverage suites, dependency audits, build/translations and isolated migration/seed/cache checks on stable source. Require zero failing/skipped tests; report statement coverage separately against the 90% gate. Final backend 1,866 passed, Browser 5 passed, zero failures/skips; application coverage 93.9%, with all 222 Actions executed. Full command evidence is in `testing.md`.
- [x] Review exact owned changes, commit in English, push normally to `main`, verify the remote SHA and record publication evidence. Implementation `da2f802580adad1ffb724525da6abca3ffccc647` was pushed successfully and the remote ref returned that exact SHA; `PROGRESS.md` records the observed result.

Design: a shared media cleanup Action encapsulates temporary-file lifetime and transaction callbacks for the two parent-deletion callers. An in-memory array retains the reproduced growth; a persistent cleanup table/worker adds an unnecessary deployment contract. The temporary file option keeps existing synchronous after-commit deletion and needs no schema change. Category ownership and traversal termination are correctness requirements independent of the media-memory optimization. The primary agent owns integration, evidence and canonical documentation; bounded workers own the media implementation, factory regressions and six skill-reference copies.

## 2026-09-14 — third audit: menu media and weekly schedules

Baseline: clean published `4e7be68`. Fresh Boost inspection confirms the local database now matches all 88 migrations. Review remaining call sites rather than repeating completed refactors. Confirmed defects are gallery/parent-delete filesystem work outside the outer transaction lifecycle, missing branch interval-overlap validation, and chronological availability lookup relying on user sort order.

- [x] Reproduce gallery rollback/post-commit cleanup and menu/item/category deletion failures using isolated data/files; inspect all related callers before changing shared behavior.
- [x] Make gallery and parent cleanup respect the outer transaction while retaining original files on rollback and committed replacements after cleanup errors. Preserve tenant scopes and bounded traversal.
- [x] Reproduce overlapping/overnight/week-boundary schedule input and out-of-order next-opening results. Implement reusable validation with localized field errors, a four-interval editor guard, DST occurrence handling and chronological availability without adding queries.
- [x] Recheck all model/migration, Action/request, query, Markdown and project-skill evidence; fix only confirmed gaps, preserve deployed migration history and existing data.
- [x] Run targeted regressions, formatting/static analysis, full browser/parallel/backend coverage gates, audits/build/translations and relevant schema/seed checks on stable source. Browser 5/433; backend 1,831/47,956 in parallel and canonical sequential coverage; zero failures/skips; application coverage 93.9%.
- [x] Review, commit with English Conventional Commit messages, push normally to main, verify the remote SHA and record actual publication evidence. Implementation `8b2ce02bd63f82a2237434090567ad590a0b6ab5` was pushed and the exact remote ref was observed; publication evidence is in `PROGRESS.md`.

The primary agent owns schedule validation and final integration. Bounded workers implemented verified menu media, factory-state and DST fixes after independent read-only audits. This work adds no deployment, external service or dependency requirement.

## 2026-09-14 — second audit: atomic branch configuration

Baseline: clean published `930059f`. Reuse the completed 49-model/88-migration audit and 1,738-test baseline, then investigate the remaining branch-settings operation. At that baseline, the single Save button performed independent settings, image, profile, closure and opening-hour writes. A later failure may retain earlier changes; wrapping the sequence alone is insufficient because generic media replacement deletes the old file before an enclosing transaction commits.

- [x] Reproduce partial settings persistence and image loss/leaks with focused failure/rollback tests. Confirm actual schema/indexes through the now-available Boost MCP without mutating application data.
- [x] Make shared image replacement/removal respect enclosing transaction commit/rollback while preserving immediate behavior outside transactions. Verify nested rollback and successful commit with owned temporary data/files.
- [x] Move substantial settings state/validation into a Livewire Form, prepared reads into the existing query service, and the complete authorized Save operation into one focused Action. Preserve localized bindings, tenant rejection, exact money and retry behavior.
- [x] Check meaningful query changes, all models/migrations and current documentation/skills; update only stale contracts and preserve valid code and historical migration files. Record a reusable rule for compound saves and filesystem compensation.
- [x] Run focused tests, formatting/static analysis, browser/build/localization, full parallel and sequential coverage gates on stable source. Require zero failing/skipped tests; report measured coverage separately.
- [x] Review and commit the verified changes with English Conventional Commit messages, push `main` normally and verify the remote SHA. `4b6fe0920aacbf403591a440767feed1798dd329` was pushed successfully and the remote returned that exact SHA; `PROGRESS.md` records the evidence.

The generic media lifecycle is an independent implementation task; root owns the settings Form/Action/UI integration and documentation. The schema audit found one pending local migration; it was applied after a checked private SQLite backup, with all row counts and integrity preserved. No production migration, seed, deployment or new dependency is required by the proposed repair.

## 2026-09-14 — repository-wide audit and publication

The expanded user request authorizes model/migration, Action/validation, performance/test and repository-local Markdown/skill audits, implementation of evidenced fixes, and a verified commit/push to the current `main` branch. English commit/release notes are required. Preserve existing data, applied migration history and the current authorized changes. Review every first-party area; update files whose contracts or evidence are stale rather than mechanically rewriting valid code or historical records.

- [x] Finish report-cache concurrency protection and its regression/query-cost evidence.
- [x] Audit all models, factories and migrations for integrity, indexes, relationships, safe rollback and meaningful executable coverage. All 88 migrations complete an empty isolated roundtrip; all 49 models have factories. No new migration is required.
- [x] Audit controller/Livewire authorization, validation and Action boundaries; inspect query and test gaps. Credential mutations now enforce feature flags and recent password confirmation; the factory snapshot resolver removes repeated source reads.
- [x] Audit all repository-local skills and first-party Markdown, repair stale current guidance and links, and distinguish historical results from fresh evidence. All eight skills were reviewed and all three tracked provider copies synchronized.
- [x] Run formatting/static analysis, dependency validation/audits, browser/build/localization, full sequential/parallel tests, canonical coverage and isolated schema/seed checks. Backend: 1,738 passed / 47,528 assertions, zero failures/skips in both full runs. Browser: 5/415. Application coverage: 93.8%; all 220 Actions execute (94.85% Action statement coverage). See `testing.md` for source digest and complete evidence.
- [x] Review the final diff, stage only verified authorized files, create English Conventional Commits and push without force; verify the remote commit identity. Commit `f2f09fd7fb51466d6783bfc3038b2ecbf656365a` was pushed to `origin/main`, and the remote ref returned that exact SHA. The publication evidence is recorded in `PROGRESS.md`.

Independent read-only audits cover model/schema, validation boundaries and skills/documentation while the primary work completes the cache correction. No production deployment or third-party service change is included.

## 2026-09-14 — concurrent report-cache invalidation

Scope: `perf-cache-001` / `sys-report-001`; preserve current report ages, authorization and prepared payloads. Current baseline is commit `0ce85ba`; the four existing documentation changes are retained.

Analysis: a cold or already-running deferred build can write an obsolete snapshot after invalidation. Concurrent registry appends can lose a key, so deleting indexed entries alone cannot guarantee freshness. Shared branch locks risk adding waits and coupling cache publication to SQLite source transactions. Use a per-report, per-branch random generation in snapshot keys; rotate it before physical invalidation. Correctness then does not depend on the registry or a lock lease. The bounded registry remains an opportunistic physical-cleanup mechanism. Obsolete records become unreadable immediately through the rotated generation; expiration also prevents cache reads, while bounded pruning on later report builds removes physically retained expired rows.

- [x] Reproduce cold publication, already-running refresh and lost-registry-entry invalidation with deterministic interleavings against the actual Laravel database cache and Actions. The corrected eight-case fixture also covers an expired refresh lease: all eight fail against the original Actions loaded from `0ce85ba` and pass with versioning.
- [x] Add a shared generation-signature module: batched reads, atomic `Cache::add` initialization, invalidation by deleting the generation before physical cleanup, and a one-day metadata TTL. Integrate both report Actions after access resolution, without request-local memoization.
- [x] Verify competing initialization, expiry/recreation, branch/scope/locale separation, pending refresh cancellation, preserved overflow bounds and query cost. A real two-process barrier proves competing initialization; deterministic interleavings cover publication races. Separate-connection commit/rollback and bounded cleanup regressions pass.
- [x] Run formatting, static analysis, targeted/full tests and coverage; update canonical evidence and review the final diff. Final 1,738-test backend runs and five browser scenarios pass without skips; publication follows the verified repository-wide plan above.

Source: [Laravel cache documentation](https://laravel.com/docs/13.x/cache#storing-items-in-the-cache), installed Laravel 13.26.1 `Cache/Repository.php::flexible` and database cache store. Boost MCP is unavailable in this session; official documentation and installed sources are the fallback.

## 2026-09-14 — export and report-cache audit

This is a scoped implementation/evidence plan for the requested analysis and immediate improvements. `requirements.md` remains authoritative; no new product feature, dependency or deployment is introduced. Preserve the existing article-review work on `main`.

| Priority | Finding | Decision |
|---|---|---|
| P1 | `StreamBranchCsvExportAction::putRow` passes untrusted text directly to `fputcsv`; formula-leading cells are not neutralized despite `sys-report-001`. | Implement a single CSV cell boundary shared by all four export types. |
| P1 | CSV relies on PHP's default backslash escape, which can break interoperable round trips for quoted/backslash-containing fields. | Use explicit `escape: ''`; verify parsed rows, not substring-only output. |
| P1 | Dashboard merges ViewOrders and ConfirmOrders into one key signature but derives its waiter link from ViewOrders alone. | Include the distinct waiter branch set in the cache signature and reuse it for link availability. |
| P2 | Both report registries retain 50 keys while silently dropping live older entries. | Delete displaced values and their flexible timestamps before discarding registry entries; keep the existing bound. |
| P2 | Registry get/put and an in-flight cache build can race with registration/invalidation. | Separate concurrency follow-up: compare shared branch locks with generation-scoped cache keys and prove with isolated concurrent/interleaved tests. Eviction alone does not resolve this race. |
| Release | The initial HEAD intentionally removed root lockfiles while the canonical dependency contract still required reproducibility. | Initially recorded as a gap. The expanded repository-wide request above now authorizes restoration; matching locked installs have been verified. |

### Task 1 — CSV output boundary

Files: `app/Actions/Exports/StreamBranchCsvExportAction.php`, `tests/Feature/DataExportsTest.php`, security/testing/compliance documentation.

- [x] Add HTTP export regressions for formula prefixes (`=`, `+`, `-`, `@`, full-width forms, leading control/whitespace), legitimate Unicode and exact numeric values. Parse with `fgetcsv(..., escape: '')`; include embedded comma, quotes, backslash and newline.
- [x] Observe RED in the focused export/dashboard regression run before implementation (20 failed / 5 passed / 153 assertions); the export cases exposed unsafe prefixes and a broken quoted/backslash round trip.
- [x] Centralize text-cell neutralization in `putRow`; prefix dangerous strings with an apostrophe and preserve numeric values. Explicitly disable proprietary CSV escaping. Keep streaming, columns, authorization and tenant filters unchanged. Document spreadsheet save/reopen limitations.
- [x] Rerun the complete export and dashboard files: 37 passed / 368 assertions; the final four-file regression run passes 63 / 853.

### Task 2 — waiter link cache scope

Files: `app/Actions/Dashboard/BuildRestaurantDashboardAction.php`, `tests/Feature/RestaurantDashboardTest.php`.

- [x] Reproduce both cache-warming orders for otherwise equivalent users with only ViewOrders versus only ConfirmOrders; assert different keys and correct links on cold and warm reads.
- [x] Add `'waiter' => $viewOrderBranchIds` to the access map, use it in `quickActions`, remove redundant waiter access resolution and invalidate the old key format with a version bump.
- [x] Run `PAO_DISABLE=1 php artisan test --compact tests/Feature/RestaurantDashboardTest.php tests/Feature/BasicAnalyticsTest.php tests/Feature/BranchCacheInvalidationTest.php`.

### Task 3 — bounded report registry eviction

Files: both report dashboard Actions and `tests/Feature/BasicAnalyticsTest.php`.

- [x] Exercise more than 50 live access variants through both Actions and show that an evicted registry entry retains its cache payload before the fix.
- [x] Partition normalized unique keys into retained/displaced sets; forget displaced `CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX` metadata before payloads and keep at most 50 registry keys. Do not claim this serializes concurrent registration or an already-running rebuild.
- [x] Verify overflow, retained snapshot reuse, branch invalidation and the existing deferred refresh/cancellation/locale cases.

### Task 4 — integrated verification and evidence

- [x] Run Pint, Larastan, the full Unit/Feature suite and canonical coverage; run browser scenarios because dashboard link availability changes.
- [x] Record measured query effects and explicitly identify unmeasured changes; update testing/security/cache/compliance documents and this checklist, and review the final diff. Keep implementation, tests and deployment evidence distinct.
- [x] Reconcile current missing-lockfile gaps in both compliance and traceability; strengthen `RequirementsTraceabilityTest` to verify all 51 statuses agree instead of requiring every applicable row to claim completion.
- [x] Correct the observed coverage-run Faker collision with deterministic names in the 52-branch fixture; rerun the two overflow cases and the full five-file focused batch.

Final evidence: 64 focused tests / 1,859 assertions; 1,678 Unit/Feature tests / 47,307 assertions and eight configured skips in both the four-process and canonical coverage runs; 93.5% application coverage; five WebKit browser scenarios / 415 assertions; Pint and Larastan pass. The first coverage attempt found a duplicate Faker branch name; the final fixture uses deterministic sequence names. Final 988-file source digest: `c9edb6d5dd9355f14aaae856a31ee1255da056241dc37775003890e6781d8dd5`. Independent agent review was unavailable because of the service usage limit; local diff review was completed. Details and limits are in `testing.md`.

The following candidate analysis records the pre-implementation concurrency follow-up. It is now resolved by the completed generation-based work above; it is retained to explain the decision and its acceptance conditions. That stage required an owned temporary SQLite database, bounded process timeouts and a test reproducing invalidation between snapshot construction and cache write. Its acceptance condition was that subsequent authorized reads cannot discover an invalidated snapshot even when registration or refresh overlaps the mutation.

The identified interleavings were: (1) two requests read the same branch registry, append different keys and overwrite one another; (2) a cold build reads source data, a mutation invalidates, then the build writes/registers the old snapshot; (3) a deferred callback passes Laravel's creation-timestamp guard, then invalidation occurs before its `putMany`. Installed `Illuminate/Cache/Repository.php::flexible` checks the timestamp before the callback, not after it. The later deterministic RED/GREEN cases now reproduce these failure modes; this does not claim arbitrary production schedules were exhaustively tested.

| Candidate for that separate stage | Benefit to verify | Cost / risk to measure |
|---|---|---|
| Shared branch locks around publication, registration and invalidation | Serialize all participating paths with one ordering contract. | Multiple-branch lock ordering, bounded wait/lease expiry, SQLite writes and request-tail latency; a refresh-only lock is insufficient. |
| Generation-scoped snapshot keys | Rotate a branch generation after mutation so an older in-flight write cannot be discovered by later reads. | Atomic first-generation creation, multi-branch generation reads, expiry behavior, obsolete-record cleanup and cancellation of pending callbacks. |

Acceptance fixtures must cover cold and stale builds, invalidation before/during publication, simultaneous registrations, two overlapping branch sets, EN/LT/RU, changed permissions, an expired lease, and recovery after an interrupted writer. Compare SQL counts plus cold/fresh/stale response time against the current Actions; retain the existing 60/300-second maximum ages and shared-hosting constraints. Select an implementation only after those reproductions and measurements.


## Completed follow-up — requirements traceability

- **P0 runtime gaps — none confirmed.** Reconciled every canonical requirement against the working route, Livewire/HTTP, policy, Action, Eloquent/SQLite and rendered-response path. The route/policy/tenant/security/schema checks and complete suites found no missing critical path requiring a runtime change.
- **P1 evidence contract — complete.** Added the 51-row [`REQUIREMENTS_TRACEABILITY.md`](REQUIREMENTS_TRACEABILITY.md) and an executable parity/path/status test, then synchronized stale inventory counts across the active documentation. Acceptance: the trace test passes 1 test/941 assertions; every referenced path exists; all 208 Actions have measured test execution.
- **P1 release verification — complete.** Sequential and parallel Pest, 93.5% Unit/Feature coverage, 94.8% aggregate Action coverage with zero uncovered Actions, Browser Pest, Pint, Larastan, dependency audits, production build, translation checks, caches and isolated fresh/repeated seeding pass. P2 remains limited to already documented physical-device/assistive-technology and production-release evidence.

## Completed follow-up — full localization audit

- **P0 catalogue integrity — complete.** Consolidated every application string into the flat semantic EN/LT/RU JSON catalogues, removed unused entries, completed domain/validation/pagination/accessibility states and expanded the scanner/auditor across PHP, Blade and JavaScript. Acceptance: 2,157 exact used keys per locale; no missing, extra, unused, empty, invalid, placeholder/plural or phrase-style issue.
- **P1 persistence and presentation — complete.** Locale preference now converges through authenticated user, web session, active guest and pending join-request identities. Dates/times, currency from integer cents, plurals and first-party pagination are locale aware; six relational menu-content translation families remain the sole guest-data strategy. Acceptance: focused localization tests, additive migration rollback/reapply, seed and isolated browser persistence checks pass.
- **P2 final quality gates — complete locally.** Pint, Larastan, dependency audits, production build, caches, sequential/parallel Pest, 93.5% coverage and Browser Pest pass. Physical screen-reader and non-Chromium evidence remain the already documented external boundary; no deployment or local application-data refresh occurred.

## Completed follow-up — canonical order lifecycle

- **P0 atomic order creation — complete.** Each draft item retains its guest owner and remains waiter-editable until the first confirmation. `ConfirmDraftOrderByWaiterAction` reloads and locks the draft, authorizes `confirm`, creates immutable order/item snapshots and dispatches the unique department ticket set before the transaction commits. Repeated and simultaneous confirmation converges on the same order/tickets. Acceptance: ownership/editing, immutable snapshot, replay, wrong-tenant and real two-process SQLite confirmation tests pass.
- **P1 centralized fulfilment — complete.** `OrderStatus` is the only aggregate state machine; `KitchenTicketItemStatus` is a subordinate forward-only production state. Kitchen/bar mutations reauthorize the exact department/ticket, lock the item, record actor-bearing history and derive aggregate progress; waiter service records the server actor and cannot regress a ready/served/cancelled item. Separate waiter, kitchen and bar class-based Livewire surfaces retain bounded visible polling without a worker or WebSocket service. Acceptance: allowed/forbidden transition, policy, tenant, history, polling, query and UI tests pass.
- **P2 settlement and closure — complete.** Bill request, offline payment and close Actions synchronize eligible canonical order states under the same SQLite transaction boundary. A draft with pending items or an order before the served/payment/cancelled closure set blocks table closure both in the prepared UI capability and on the server. Acceptance: bill, partial/full payment, close, repeat, cancellation and vertical guest-to-closed-table tests pass. No migration or dependency was needed because existing uniqueness, status/history and tenant keys already satisfy the flow.

## Completed follow-up — permanent QR and guest table flow

- **P0 identity and first entry — complete.** One active opaque QR and one deterministic hash-derived SVG survive table rename, renumber and same-restaurant area movement. A waiter opens the active session; the first server-side credential atomically creates the opener guest, while later guests enter the approval path. Closed guest identity never binds to a newly opened session. Acceptance: generation/regeneration/reissue/rename/move tests and real two-process first-entry SQLite proof pass.
- **P1 secure invitation and moderation — complete.** Guest invite plaintext is never stored; every link rotates a unique digest, creator/time and 30-minute expiry. Join requests are credential-idempotent, capped at 20 live rows and constrained to the scanned restaurant. Approval/rejection reload under transaction locks and repeat safely. Acceptance: expiry, rotation, replay, cross-restaurant, payload-tampering, double-approval concurrency and closed-state tests pass.
- **P2 operational evidence — complete locally.** Seed QR files use the same production path contract and repeated seeding yields exactly 19 stable files. Public GET and Livewire entry boundaries have independent hashed-key limits, EN/LT/RU messages are aligned, and the expanded related slice passes 168 tests with 2,206 assertions. Physical multi-device scanning remains covered by the existing external device/browser evidence boundary rather than being falsely claimed locally.

## Completed follow-up — complete menu module

The established menu graph remains `Menu → MenuCategory → MenuItem → MenuItemVariant` with existing modifier-group/option relationships and image gallery; no duplicate menu, add-on or allergen entity was introduced. Existing nutrition/allergen data remains the canonical dish allergen representation.

- **P0 integrity and localization — complete.** Added owner+locale translation rows for the previously untranslated menu, modifier-group and modifier-option entities, then used the same relation-table strategy across all six guest-visible entity families. Scoped validation requires `en`, `lt` and `ru`, rejects duplicates within the exact branch/parent, validates decimal prices and preserves canonical enum/status data. Acceptance: schema, translation, tenant-tampering, CRUD and seeder-idempotency tests pass; the safe forward migration upgrades existing SQLite data without loss and rolls back/reapplies in isolation.
- **P1 availability and guest correctness — complete.** Added indexed `hidden_until`, stable sort/name/ID ordering, locale-scoped eager guest loading and server-resolved localized draft snapshots. Add, update and send revalidate current menu/category/item/schedule/stock/modifier state; an unavailable item already in a cart stays understandable and removable but is not mutable or sendable. Acceptance: hidden-deadline, stale-draft, language-snapshot, schedule and query-budget tests pass with at most 15 cold and two warm guest-menu queries.
- **P2 presentation and fixtures — complete.** The class-based Livewire/Flux management UI edits all three locales, variants, modifiers, prices, availability, temporary hiding, images and sort order without database work in Blade. Factories and deterministic demo seed data cover complete translated graphs. Acceptance: component-size/static-analysis rules, EN/LT/RU scan/audit, production build and responsive organization browser journey pass.

## Completed follow-up — `/organizations` CRUD demo

The approved follow-up is defined by [`superpowers/specs/2026-08-23-organizations-full-crud-demo-design.md`](superpowers/specs/2026-08-23-organizations-full-crud-demo-design.md) and executed by [`superpowers/plans/2026-08-23-organizations-full-crud-demo.md`](superpowers/plans/2026-08-23-organizations-full-crud-demo.md). It extends the canonical factory-backed demo graph, seeds the actual local database only after isolated proof, and supplies an auditable 26-resource CRUD/lifecycle matrix for every management surface below `/organizations`.

The previously approved dish-gallery work is the completed subordinate plan [`superpowers/plans/2026-08-23-menu-item-image-gallery.md`](superpowers/plans/2026-08-23-menu-item-image-gallery.md). It is inventory row 20 of the master plan, not a competing track.

Execution status: the technical foundation, dish gallery and complete `/organizations` 26-resource CRUD acceptance matrix/browser journey are implemented and freshly verified. Only the explicitly bounded P2 operator/platform evidence remains outside this local completion run.

## Completed follow-up — restaurant hierarchy lifecycle

The product hierarchy remains `Organization → Brand → Branch → AreaNode → ServicePoint`; no duplicate company/restaurant/room/table models or migrations were introduced.

- **P0 correctness and isolation — complete.** Focused archive/restore Actions transactionally reload through the exact parent scope and authorize policies. Direct Livewire identifier substitution is fail-closed at all five levels. Organization, brand, branch, area and service-point archive operations reject active-order conflicts; service points retain the stricter active-session guard. A same-branch table move changes its area only and preserves permanent QR identity. Acceptance: positive/negative policy and mutation tests, archived factory states, schema and scoped-route checks pass.
- **P1 management UX and performance — complete.** Structure pages expose localized search, lifecycle/type/activity/status/QR filters, allowlisted sorting, pagination, archive/restore controls, confirmations, errors and empty states through class-based Livewire/Flux. Query services own selected, eager-loaded Eloquent reads; Actions own writes. Acceptance: the focused structural batch passes 125 tests/908 assertions, the 31-area/31-table budget proves one area-page query and at most six eager-loaded table-page queries, Larastan reports zero errors, and translation plus cache gates pass.
- **P2 physical deletion and speculative ordering schema — intentionally not added.** Ordinary users receive reversible soft-delete lifecycle controls because hard deletion would risk order, session, QR and audit history. Existing persisted area `sort_order` and service-point placement/display fields remain authoritative; company/brand/restaurant list sort is query-only, so no unused ordering columns or migrations were created.

## Goal

Bring the present checkout to a locally complete, reproducibly tested, release-ready state without discarding user work, changing the approved product scope, publishing a release, deploying production, or touching existing application data destructively.

## Scope and priority model

`docs/requirements.md` is the only product contract. This plan records executable closure work discovered by comparing all 51 requirements with the current code, schema, routes, tests, configuration, assets, and repository history. A task is complete only when its acceptance criteria and listed checks have fresh observed evidence in [`PROGRESS.md`](PROGRESS.md).

### P0 — correctness and repository invariants

#### P0.1 Complete and verify the deterministic demo graph

- **Dependencies:** committed demo factory/seeder integration at `d127940`; current additive follow-up in `DemoOperationalStateSeeder`.
- **Work:** preserve the factory-backed four-branch graph; prove per-branch staff/menu/QR/session/order/payment/history coverage; prove new/in-progress/ready bar work; keep production refusal and repeated seeding idempotent.
- **Acceptance:** exact maximum graph assertions pass; every first-party model still has a valid factory; a repeated isolated demo seed creates no duplicates; no existing database is refreshed or truncated.
- **Checks:** `DemoRestaurantSeederTest`, factory/architecture tests, Pint, Larastan, isolated fresh migration and two demo seed runs.

#### P0.2 Enforce named first-party routes

- **Dependencies:** Laravel 13 named-route contract and existing authenticated settings group.
- **Work:** name the existing `/settings` redirect without changing its URI, middleware, status, destination, or established profile/security route names.
- **Acceptance:** `settings.index` resolves and carries `web` plus `auth`; all existing route protection tests stay green.
- **Checks:** RED/GREEN `RouteProtectionAuditTest`, `route:list`, route cache.

#### P0.3 Reconcile canonical documentation with observed code

- **Dependencies:** P0.1 and P0.2 evidence.
- **Work:** update the requested completion documents, architecture inventory, requirement status note, documentation index, changelog/compliance evidence where behaviour changed, and concise permanent repository instructions.
- **Acceptance:** no document claims an unobserved gate; all 51 requirements retain stable IDs and matching compliance rows; completion ledgers do not become competing requirements or a second external backlog.
- **Checks:** link/path review, final diff review, requirement/compliance row parity.

### P1 — reproducible local release gates

#### P1.1 Backend and data gates

- **Dependencies:** all P0 code stable.
- **Work:** validate the Composer graph; audit dependencies; format; run Larastan; run targeted, sequential, parallel, and coverage suites; verify fresh SQLite migration and repeatable demo seeding in an isolated temporary storage/database root.
- **Acceptance:** zero test failures; application coverage at least 90%; no pending migration; no static-analysis or formatting defect; no mutation of the existing application database.
- **Checks:** `composer validate --strict`, `composer audit --locked`, `vendor/bin/pint --dirty --format agent`, `composer analyse`, `php artisan test --compact`, `php artisan test --compact --parallel`, `composer test:coverage`, migration/seeding commands against temporary paths.

#### P1.2 Frontend, localization, and cache gates

- **Dependencies:** P0 documentation and routes stable.
- **Work:** audit npm dependencies; build production assets; scan and audit EN/LT/RU keys; build config, route, event, and view caches; inspect cached routes.
- **Acceptance:** zero relevant dependency advisories; Vite production build succeeds; missing/legacy/placeholder translation issues remain zero; every cache command succeeds.
- **Checks:** `npm audit --audit-level=moderate`, `npm run build`, translation commands, Artisan cache commands followed by `optimize:clear`.

#### P1.3 Disposable-browser smoke and accessibility review

- **Dependencies:** successful build and a Herd-resolved application URL.
- **Work:** use isolated Chrome tooling against public/guest/health surfaces; inspect navigation, DOM, console, network, responsive widths, keyboard focus, and accessible names without using a personal browser profile.
- **Acceptance:** critical local pages return expected status, no fresh application/console error appears, no horizontal overflow at representative mobile and desktop widths, and primary controls remain keyboard reachable and named.
- **Checks:** Laravel Boost absolute URL and browser logs; Chrome DevTools/Playwright local navigation and inspection.

### P2 — external evidence and unapproved product expansion

#### P2.1 Publish and production verification

- **Dependencies:** all P0/P1 gates on one immutable release commit; maintainer-controlled release and production access.
- **Work:** GitHub issues #3, #4, and #5 cover exact-SHA publication/CI plus production health, logs, and error-alert verification.
- **Acceptance:** remote SHA matches the reviewed commit, CI is green, and production observability is verified without exposing secrets.
- **Current boundary:** publishing and production deployment are explicitly outside this run. Local issue #4 gates are executed against the working tree; external acceptance remains an operator action, not a hidden local TODO.

#### P2.2 Physical platform and assistive-technology evidence

- **Dependencies:** supported physical devices, Safari/Firefox environments, VoiceOver/NVDA or equivalent assistive technology.
- **Work:** GitHub issues #7 and #8.
- **Acceptance:** critical workflows and assistive-technology results are recorded on the specified real platforms.
- **Current boundary:** unavailable physical/external environments are documented in `known-limitations.md`; they do not justify weakening current automated or Chromium checks.

#### P2.3 Shared draft-item allocations

- **Dependencies:** an approved requirement defining ownership, allocation arithmetic, concurrency, authorization, migration, UX, accessibility, and history semantics.
- **Work:** GitHub issue #10 only after product approval.
- **Acceptance:** a new stable requirement ID and compliance row precede TDD implementation.
- **Current boundary:** not an active requirement and therefore intentionally not implemented speculatively.

## Completion sequence

1. Reconcile Git and concurrent changes before every edit boundary.
2. Finish P0 with targeted RED/GREEN tests and update `PROGRESS.md`.
3. Execute P1 backend/data, then frontend/localization/cache, then browser gates; fix any discovered defect before proceeding.
4. Perform a final requirements-to-code/compliance audit and diff review.
5. Report P2 external boundaries exactly; do not publish, deploy, rewrite history, or destroy data.

## Execution status

- **P0:** complete with targeted tests, route evidence, isolated migrations/seeding, static analysis, and documentation parity.
- **P1:** complete with dependency audits, sequential/parallel/coverage suites, translations, production build, caches, disposable Chrome navigation, mobile overflow/keyboard/accessibility checks, Lighthouse 100, and a clean self-contained `/up` response.
- **Completed product follow-up:** the dish gallery and `/organizations` 26-resource CRUD matrix are complete. The focused organization slice passes 179 tests/1,746 assertions, its browser journey passes 1 scenario/183 assertions, and the full browser suite passes 5 scenarios/376 assertions after the production build/cache cycle.
- **Completed security follow-up:** public registration is fully disabled and the hash-only, recipient/tenant/role-bound invitation lifecycle now covers creation, token-free review, atomic acceptance, replay, expiry, secure reissue, revoke, audit and exact role/tenant isolation. Fresh verification evidence is recorded in `PROGRESS.md` and `testing.md`.
- **P2:** external/operator evidence remains explicitly bounded as described above; no production deployment, release publication, physical-device claim, or unapproved shared-allocation feature was performed.

# Living implementation plan

Statuses: **pending**, **in progress**, **implemented**, **verified**, or **blocked by external dependency**. A pass becomes verified only after its listed command evidence succeeds.

## Pass 0 — repository protection and baseline

Status: **verified for capture; remediation remains in later passes**.

- [x] Record branch/HEAD/index/worktree/untracked state.
- [x] Read all first-party Markdown and governing project skill rules.
- [x] Inventory routes, modules, models, schema, factories, seeders, views, Livewire, tests, configuration, CI and runtime constraints.
- [x] Run safe baseline Composer/npm/Laravel/test/build/cache/translation checks.
- [x] Record exact failures and dependency advisories in `current-state-audit.md`.

Verification: `git status --short --branch`; inventory commands; baseline commands in the audit.

## Pass 1 — canonical requirements and documentation

Status: **in progress**.

Requirements: all, principally `test-feature-001`, `ops-deployment-001`.

- [x] Establish canonical reading order and durable root instructions.
- [x] Catalogue 42 stable, testable active requirements.
- [x] Document architecture, domain/data/security/auth/frontend/runtime boundaries.
- [x] Create first compliance matrix, feature applicability matrices and this plan.
- [ ] Reconcile every legacy Markdown path with a canonical document without losing history.

Verification: Markdown inventory/diff/link scan; final second pass after code.

## Pass 2 — dependency and framework baseline

Status: **in progress**.

Requirements: `sec-dependency-001`, `sec-session-001`, `livewire-001`, `tailwind-001`, `test-feature-001`, `ops-deployment-001`.

- Raise PHP constraint to `>=8.5.0 <8.6.0` and select latest stable compatible Laravel 13, Livewire 4, Flux Free 2, Fortify, Vite, Tailwind, Pest 4, Pint and Boost releases.
- Upgrade targeted lock-file graph, resolve Composer/npm advisories and remove obsolete platform-specific npm dependencies.
- Add latest compatible Larastan at a useful strict level without a broad baseline.
- Reconcile Laravel 13 bootstrap/config/CI with PHP 8.5 and one npm lock.
- Add class-component Livewire generation configuration; remove Flux Pro source/config assumptions.

Verification: Composer metadata/audit/why-not, npm audit/build, Artisan boot/cache/route listing, focused framework tests.

Rollback: Composer and npm manifests/locks form one atomic reviewable commit; revert that coherent commit if the selected graph cannot boot.

## Pass 3 — security and data-integrity foundations

Status: **pending**.

Requirements: `sys-tenant-001`, `sys-staff-001`, `sys-payment-001`, `sys-backup-001`, all `sec-*`, `data-*`.

- Add scoped nested bindings and a policy structure for protected resources/actions; test wrong parent/tenant/direct Livewire invocation.
- [x] Replace invitation plaintext/unusable links with digest-at-rest, expiring, revocable, atomic one-time acceptance; add rate limits/replay/concurrency tests. Verified by the focused Invitation/Staff/Token/Livewire suite (24 tests, 159 assertions), focused PHPStan (0 errors), fresh testing migration, route inspection, and translation audit (0 critical issues).
- [x] Serialize manual-payment balance checks before summary calculation with SQLite `IMMEDIATE` transactions, a bounded busy timeout, WAL/NORMAL pragmas, row locks on engines that support them, and deadlock retry; repeated stale submissions remain single-write. Verified by 10 payment tests / 98 assertions and focused PHPStan with 0 errors. Repository-wide duplicated float-based money helpers remain in Pass 4.
- [x] Replace live-file download with the native SQLite online-backup API, a mode-0600 private temporary snapshot, one-time reason-bound authorization, recent password confirmation, no-store headers, audit reason and delete-after-send cleanup. Verified by 49 backup/dangerous-action/route tests (243 assertions) and focused PHPStan with 0 errors.
- Make local image replacement persistence-first with failure compensation.
- Enable strict Eloquent behavior in local/test and fix all lazy/missing/discarded-attribute violations.
- Add forward-only constraints/indexes only where schema/query evidence requires them.

Verification: security/domain targeted tests, migration upgrade/fresh/rollback tests, SQLite query plans, full suite after the pass.

Rollback: new schema uses expand/backfill/verify/switch; no historical migration rewrite or destructive production rollback.

## Pass 4 — backend architecture and factories/seeders

Status: **pending**.

Requirements: `sys-*`, `perf-query-001`, `perf-cache-001`, `seed-model-001`, `seed-demo-001`.

- Move scattered authorization into policies and substantial validation into Form Requests/Livewire Forms/custom rules.
- Remove service-locator use from modified domain code; inject Actions/collaborators and split oversized use cases without generic repositories/services.
- Centralize minor-unit money formatting/calculation and locale-safe analytics/dashboard cache keys.
- Bound/paginate growing organization, brand, branch, staff, audit/export and operational lists; eager-load/select only needed relationships.
- Repair every factory default/relationship, add meaningful states/helpers and exhaustive persistence tests.
- Make fixed seeders idempotent and demo graph comprehensive, deterministic and production-safe.

Verification: affected Feature/Unit tests, Larastan, query budgets, factory/state sweep, fresh seed twice, production-safeguard test.

## Pass 5 — Livewire/Blade/frontend modernization

Status: **pending**.

Requirements: `livewire-001`, `blade-001`, `tailwind-001`, `i18n-001`, `ui-*`, relevant workflows.

- Convert both route SFCs to class components with separate views; prohibit SFC/Volt in architecture tests.
- Remove every `@php`, `@endphp`, ordinary PHP block and model/service/facade/container/business transform from first-party Blade.
- Reduce and type public component state, lock durable IDs, authorize direct actions, introduce Form Objects/computed/URL/loading/offline features where correct.
- Split independently updating expensive regions only after query/payload measurement.
- Build a CSS-first semantic token system, remove Flux Pro source, unsafe class construction and obsolete integration; reduce repeated `@apply` where components/tokens are clearer.
- Complete EN/LT/RU keys/placeholders/formatting and accessible responsive states.

Verification: architecture/Livewire/locale/design tests, production build size, isolated Chrome console/network/keyboard/focus/responsive/reduced-motion runs.

## Pass 6 — comprehensive verification and remediation

Status: **pending**.

Requirements: all.

- Make all relevant baseline failures pass; add regression, policy, race, route, migration, factory/seeder, cache/query, localization and security coverage.
- Run Pint and Larastan to the selected level with no broad suppression.
- Run full/parallel/coverage/fresh-migrate/fresh-seed/idempotency/build/audit/cache/browser gates.
- Inspect full diff for secrets, dead/debug code, generated artifacts and unrelated changes.

Verification: exact final command transcript and counts in the final report.

## Pass 7 — final synchronization and publication

Status: **pending**.

- Re-read every first-party Markdown file and align it with final code/evidence.
- Update requirement statuses, matrices, resolved audit findings, real limitations and dated changelog.
- Create coherent Conventional Commits, inspect staged scope, push `main` only after all required gates pass and report the observed result.

No isolated blocker pauses other safe passes. A task is never moved to verified because it is difficult or because a file exists.

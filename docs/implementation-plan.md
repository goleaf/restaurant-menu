# Modernization implementation plan

This living plan was executed on 2026-08-22. A pass is marked **verified** only because its implementation and listed gates succeeded on the final code tree. Requirement-level evidence is in [`compliance-matrix.md`](compliance-matrix.md).

## Pass 0 — protect and baseline

Status: **verified**.

- Captured clean `main` at `aa2c675`, index/worktree/untracked state and `origin/main`.
- Read all 56 first-party Markdown files and governing project instructions.
- Inventoried routes, 41 models/factories, schema, seeders, Actions, Livewire, Blade, configuration, tests, CI and shared-hosting constraints.
- Recorded the failing 655-test baseline and dependency/security findings in [`current-state-audit.md`](current-state-audit.md).

## Pass 1 — canonical requirements and documentation

Status: **verified**.

- Established the root `AGENTS.md` and [`index.md`](index.md) reading order.
- Normalized 48 stable requirements, architecture/domain/data/security/frontend/runtime documentation and three ADRs.
- Replaced duplicate historical instruction documents with concise canonical pointers while preserving the chronological `CHANGELOG.md`.
- Created this plan and the one-row-per-requirement compliance matrix.

## Pass 2 — dependency/framework baseline

Status: **verified**.

Requirements: `sec-dependency-001`, `sec-session-001`, `livewire-001`, `tailwind-001`, `test-feature-001`, `ops-deployment-001`.

- Locked PHP 8.5, Laravel 13, Livewire 4, Flux Free 2, Tailwind 4/Vite 8, Pest 4, Pint, Boost and Larastan to stable compatible releases.
- Removed all Composer/npm advisories and obsolete runtime assumptions; retained one npm lock.
- Modernized Herd development scripts so they do not start a second web server or mandatory queue worker.
- Verified Composer metadata/audits/prohibits, npm audit/build, application boot, route/cache builds and HTTP smoke.

Rollback: manifests and lock files are committed with the compatible code change; no prerelease package or production-only infrastructure was introduced.

## Pass 3 — security and data integrity

Status: **verified**.

Requirements: tenant/staff/payment/backup requirements, all `sec-*` and `data-*`.

- Added scoped nested bindings, aggregate policies and negative wrong-tenant/direct-action tests.
- Replaced plaintext invitation credentials with digest-at-rest, expiring/revocable atomic acceptance and throttling.
- Added public invitation entry, token-free login continuation, and atomic invited-user registration with recipient email binding.
- Disabled public Fortify registration so production account creation is invitation-only.
- Serialized SQLite manual-payment balance checks and made duplicate/race outcomes deterministic.
- Replaced live-database streaming with a private consistent online SQLite snapshot, recent-password and reason-bound authorization, no-store response and cleanup.
- Added persistence/file compensation, strict local/test Eloquent behavior, guarded mass assignment and exact-money contracts.
- Verified fresh migration, replay/race, upload lifecycle, backup cleanup and security regression suites.

Rollback: the only new migration is forward-compatible and reversible; no production data refresh, truncation or historical migration rewrite is used.

## Pass 4 — backend, factories and seeders

Status: **verified**.

Requirements: all `sys-*`, `perf-*`, `seed-*`.

- Replaced service-locator usage in application operations with explicit Livewire boot/Action dependencies.
- Prepared presentation arrays before Blade, bounded growing queries, eager-loaded relationships and locale-scoped cached payloads.
- Repaired all 41 factory defaults and meaningful states; there are no model exemptions.
- Added `DemoOperationalStateSeeder` for live, payment-requested, completed and audit workflows while preserving production refusal and idempotency.
- Verified factory/state sweeps and isolated 66-migration/default-seed/twice-demo-seed execution.

## Pass 5 — Livewire, Blade, Tailwind and localization

Status: **verified**.

Requirements: `livewire-001`, `blade-001`, `tailwind-001`, `i18n-001`, `ui-*`.

- Converted route single-file components and added the zero-state offline region as 42 ordinary class Livewire components with separate views; Volt is prohibited.
- Removed all Blade PHP blocks and direct model/service/facade/container/config/session/auth access; executable architecture tests preserve the boundary.
- Applied typed/locked/minimal public state and appropriate `Computed`, `Url`, `Isolate`, `Layout`, loading/offline/cloak/navigation features.
- Implemented CSS-first Tailwind sources and OKLCH semantic tokens; preserved Flux Free and added semantic localized password/modal accessibility overrides.
- Achieved EN/LT/RU parity: 2,039 semantic keys per locale, no missing/legacy/phrase keys.
- Browser-verified public/auth/dashboard/settings flows, locale persistence, modal names, keyboard focus, responsive overflow and zero console errors.

## Pass 6 — complete verification

Status: **verified**.

- Pint passed; Larastan level 8 reported 0 errors; 664 first-party PHP/Blade entry files passed syntax checks.
- Sequential and parallel Pest both passed 693 tests with 20,593 assertions; 9 skips are only disabled passkey/2FA feature gates.
- Numeric performance regressions cap audit history at 10 queries independent of page-row volume, guest menu at 15 cold / 2 warm and waiter dashboard at 40.
- Xdebug coverage passed at 90.4% before the UI slice; the final UI tree could not be freshly measured because the available PHP CLI/Herd proxy loads no coverage driver.
- Composer/npm audits, translation scan/audit, production build, fresh migration/seeding, cache builds and HTTP smoke passed.
- Chromium Lighthouse scored 100/100/100/100 on public, login and authenticated dashboard samples; no checked viewport overflowed.

## Pass 7 — synchronization and publication

Status: **verified after the observed final commit and push; publication evidence is reported in the handoff**.

- Re-read and synchronized all first-party Markdown after implementation.
- Updated the audit, matrices, exact version/test/asset evidence and only genuine environmental limitations.
- Inspected unstaged/staged/full diffs, secret/debug/generated-artifact patterns and preserved repository scope.
- Publication hashes and observed push result belong in the final report and changelog, not in requirement definitions.

## Pass 8 — UI/UX implementation addendum

Status: **implemented and verified locally**.

- Reworked public/guest/auth/dashboard hierarchy around role goals and one clear next action while preserving every domain route and mutation.
- Added shared metric strips, native progressive disclosure, localized skip navigation, Flux modal focus behavior, responsive touch targets, complete light/dark tokens and stable media dimensions.
- Added regression protection for layout anti-patterns, auth autofocus, image dimensions, modal semantics, dashboard hierarchy and invalid persisted Flux icon names.
- Verified responsive public, waiter and service-point surfaces at representative 360–1920 CSS px samples, mobile Lighthouse 100/100/100/100, zero final console issues and a no-throttle public trace of LCP 140 ms / TTFB 37 ms / CLS 0.
- Publication was not part of this UI request; the worktree remains intentionally uncommitted for review.

No implementation pass remains pending or partial. Fresh coverage collection remains the documented environment-only evidence limitation; future product work starts from new requirements rather than reopening this completed modernization plan.

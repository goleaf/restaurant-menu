# Current-state audit

Audit date: 2026-08-22. Branch at start: `main`, commit `aa2c675`, tracking `origin/main`. The working tree, index, and untracked set were clean before modernization. No existing user changes required preservation.

## Repository baseline

| Area | Observed baseline |
|---|---|
| Runtime | PHP 8.5.8, Laravel 13.13.0, Livewire 4.3.1, Flux UI Free 2.14.1, Tailwind 4.3.0, Vite 8.0.16, Pest 4.7.2 / PHPUnit 12.5.28. |
| Constraints | `composer.json` still allowed PHP `^8.3`, Laravel `^13.7`, and Livewire `^4.1`; CI tested PHP 8.3/8.4/8.5. |
| Storage | SQLite only; database cache/session/queue; local public/private disks. Boost schema inspection found 48 application/framework tables, no views, routines, or triggers. |
| Application size | 269 PHP files under `app`, 103 Actions, 39 Livewire PHP components, 41 first-party models, 41 factories, 65 migrations, 6 seeders, 100 Blade files, 118 PHP test files. |
| Tests | `php artisan test --compact`: 655 tests, 19,621 assertions, 567 passed, 64 failed, 15 errors, 9 skipped, 48.532 s. |
| Formatting | `composer lint:check`: passed. |
| Localization | 3 JSON catalogues, 2,002 keys per locale; audit found no missing/invalid/phrase-style key, and scan found 1,541 used plus 461 unused keys. |
| Frontend | `npm run build`: passed in 620 ms; CSS 282.03 kB / 36.41 kB gzip, application JS 0.00 kB. |
| Caches | Blade, configuration, and route cache builds passed, followed by `optimize:clear`. |
| PHP dependencies | `composer audit`: 17 advisories affecting Guzzle, PSR-7, and CommonMark, including high severity findings. |
| JavaScript dependencies | `npm audit`: 4 findings — 2 critical and 2 high — through `concurrently`, `shell-quote`, `postcss`, and `nanoid`. |

Baseline commands were non-destructive. PHPUnit uses SQLite `:memory:`. The existing application SQLite file was inspected read-only through Laravel Boost and `migrate:status` only.

## System model

The application is a tenant-aware restaurant SaaS with these bounded workflows:

- platform superadmin controls organizations, local subscriptions, safety checks, cleanup, and SQLite backup;
- organization owners/directors/admins manage brands, branches, staff, permissions, public profiles, schedules, areas, service points, menus, QR, and reports according to role and branch assignments;
- public QR tokens resolve the current physical service point without exposing internal route identifiers;
- guests enter or join table sessions through opaque cookie tokens, share a draft, express readiness, call a waiter, and request the bill;
- waiters review/edit/confirm drafts, explicitly dispatch confirmed orders, serve ready items, transfer/merge tables, and record authorized offline payment/closure operations;
- kitchen/bar departments receive department-split ticket snapshots and update item progress;
- order, status, payment, notification, and audit records retain operational history.

The detailed model is in `domain-model.md` and `data-model.md`.

## Confirmed defects and risks

| Finding | Evidence | Planned resolution |
|---|---|---|
| Baseline suite is not green. | 64 failures and 15 errors; clusters include tenant/role access, invalid factory graphs, locale-dependent UI assertions, cache invalidation, and ticket states. | TDD repair before declaring any requirement verified. |
| Dependency vulnerabilities. | Composer: 17 advisories; npm: 2 critical and 2 high. | Upgrade to latest stable compatible releases and rerun both audits. |
| Invalid factory/mass-assignment graphs. | `organization_users.organization_id`, `branches.organization_id`, and `area_node_waiters.organization_id` fail NOT NULL in tests. | Make relationship defaults explicit and test every factory/state. |
| No formal Policy layer. | `app/Policies` absent; access logic is spread across `User`, components, and Actions. | Add aggregate policies and route/action tests without weakening existing guards. |
| Invitation links are issued but acceptance is absent; plaintext bearer token is stored. | `Invitation::acceptUrl()`, invitation staff UI, no `/invite/{token}` route. | Add digest-at-rest, atomic single-use acceptance, expiry/revocation/rate limits, or stop issuing unusable links until the full flow exists. |
| Concurrent manual payment can overpay. | Remaining balance is computed in a transaction without a locking/idempotency guard. | Add a SQLite-compatible serialization/lock contract and concurrency/replay tests. |
| Active SQLite file download is not a guaranteed consistent snapshot under WAL/concurrent writes. | Backup controller streams the configured database file directly. | Produce a bounded consistent snapshot before download, with cleanup and tests. |
| File replacement is not coordinated with DB persistence. | New file is stored and prior path may be deleted before caller persistence succeeds. | Introduce commit/compensation semantics and lifecycle tests. |
| Blade boundary violations. | 40 files / 94 `@php` or `@endphp` occurrences, model methods in templates, and presentation payload construction. | Move preparation to classes/components and add architecture tests. |
| Two Livewire SFC/view-based components remain. | `resources/views/pages/guest/home.blade.php`, `resources/views/pages/restaurant/dashboard.blade.php`. | Convert to normal PHP component classes plus separate views. |
| Several unbounded administrative lists. | Organization, brand, branch, and staff computed collections use `get()` without pagination. | Add bounded pagination and filter-reset tests. |
| Money calculations use float in several actions/components. | Dashboard, analytics, guest totals, waiter detail, and `MoneyFormatter`. | Centralize minor-unit decimal-safe arithmetic and add edge-case tests. |
| Locale-sensitive data is cached without locale keying. | Dashboard/analytics caches include translated payloads but keys omit locale. | Cache neutral data or include normalized locale and test isolation. |
| Service locator use is widespread. | 66 `app()` calls across 32 first-party PHP files plus view-composer resolution. | Replace opportunistically with method/constructor injection and explicit collaborators. |
| Strict Eloquent and static analysis are absent. | No strict Eloquent configuration; no Larastan command/config. | Enable local/testing strictness, add Larastan, fix findings without broad ignores. |
| Historical migrations use current models/backfills. | Two deployed migrations couple to current Eloquent. | Do not rewrite deployed history; document forward-only baseline/squash strategy and test fresh migration. |

## Documentation inventory

| Path | Purpose / owner | Status and conflicts | Action |
|---|---|---|---|
| `AGENTS.md` | Repository rules | Previously generic Boost text and omitted product constraints. | Rewritten as canonical instructions. |
| `CLAUDE.md`, `GEMINI.md` | Agent entry points | Exact duplicates of old `AGENTS.md`. | Replaced with pointers. |
| `README.md` | Onboarding | 1,568 lines mixed current setup with prompt history and stale credentials. | Rewritten; history remains in `CHANGELOG.md`. |
| `CHANGELOG.md` | Historical record | Valuable and intentionally historical; some limitations conflict with later features. | Preserve history; prepend final modernization entry. |
| `docs/README.md` | Old document index | Listed planned documents that did not exist. | Replace with pointer to `docs/index.md`. |
| `docs/AI_CONTEXT.md` | Prompt-by-prompt agent memory | 4,076 lines duplicate changelog and contain stale next-prompt rules. | Replace with compact canonical context pointer. |
| `docs/CODEX_GUARDRAILS.md` | Product guardrails | Obsolete statements conflict with current item-status implementation. | Replace with pointer and non-negotiable invariants. |
| `docs/CURRENT_VERSION.md` | Version snapshot | Useful path but versions and completion claims drift. | Rewrite from final installed lock state. |
| `docs/DEMO_LOGIN.md` | Demo credentials | Current `@demo.test` contract is useful; older docs contradict it. | Keep as focused canonical supplement and verify seeder. |
| `docs/DEPLOY_SHARED_HOSTING.md` | Deployment | Useful but duplicated setup. | Point to and supplement `deployment.md`. |
| `docs/ERROR_HANDLING.md` | Error contract | Current enum/exception boundary remains useful. | Align with `security.md` and tests. |
| `docs/FACTORY_SEEDER_RULES.md`, `docs/SEED_ARCHITECTURE.md`, `docs/SEEDERS.md` | Factories/seeding | Three overlapping sources. | Consolidate into `seeding.md`; retain concise compatibility pointers. |
| `docs/FIELD_TRANSLATION_AUDIT.md` | Translation evidence | Useful dated evidence but not workflow authority. | Retain as evidence and link `localization.md`. |
| `docs/FUNCTIONAL_TEST_STRATEGY.md`, `docs/TEST_CHECKLIST.md` | Automated/manual tests | Duplicate and partly historical. | Consolidate rules in `testing.md`; keep checklist as executable manual/browser list. |
| `docs/MIGRATION_AUDIT.md`, `docs/SCHEMA_SNAPSHOT.md` | Schema evidence | Migration audit contains an obsolete proposed item-status redesign. | Replace with current `data-model.md` evidence and safe migration notes. |
| `docs/NEXT_STEPS.md` | Prompt queue | Explicitly said to wait and included stale queued product work. | Replace with pointer to active implementation plan. |
| `docs/SECURITY_RULES.md` | Security | Duplicates security prompt history. | Replace with focused invariants and point to `security.md`. |
| `docs/TRANSLATION_KEY_MAP.md`, `docs/TRANSLATION_STANDARD.md` | Localization namespace/workflow | Valuable details but split authority. | Consolidate authority in `localization.md`; keep namespace map as supplement. |

The generated/copy skill trees under `.agents`, `.claude`, and `.cursor` are tooling instructions rather than application requirements. `.agents` is the project skill source; `.claude` and `.cursor` mirror it and must not be treated as three independent requirement sets.

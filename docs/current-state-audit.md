# Current-state audit

Audit and modernization date: 2026-08-22. Initial branch: `main` at `aa2c675`, tracking `origin/main`. The index, worktree and untracked set were clean before this task; therefore every modernization diff is attributable and no pre-existing user change required merging or preservation.

## Factual baseline

| Area | Initial observation |
|---|---|
| Runtime | PHP 8.5.8; Laravel 13.13.0; Livewire 4.3.1; Flux UI Free 2.14.1; Tailwind 4.3.0; Vite 8.0.16; Pest 4.7.2 / PHPUnit 12.5.28. |
| Constraints | Composer still allowed PHP `^8.3`, Laravel `^13.7` and Livewire `^4.1`; CI included obsolete PHP targets. |
| Persistence | SQLite only; database cache/session/queue; local public/private disks; 48 tables, no views/routines/triggers at inspection time. |
| First-party size | 269 PHP files under `app`, 103 Actions, 39 Livewire classes plus two route SFCs, 41 models/factories, 65 migrations, 6 seeders, 100 Blade templates, 118 PHP test files. |
| Pest | 655 tests, 19,621 assertions: 567 passed, 64 failed, 15 errors, 9 skipped; 48.532 seconds. |
| Dependencies | Composer audit: 17 advisories. npm audit: 4 findings, including 2 critical and 2 high. |
| Localization | 2,002 keys per EN/LT/RU file; no missing/invalid keys; 1,541 used and 461 unused. |
| Frontend | Production build passed; CSS 282.03 kB / 36.41 kB gzip; application JS 0.00 kB. |
| Caches/format | Config, route and view cache builds and Pint formatting check passed. Larastan was absent. |

All destructive checks used testing SQLite only. The existing application database was inspected read-only through Laravel Boost and normal migration status commands.

## System model

Restaurant Menu is a tenant-aware restaurant SaaS. Superadmins own platform/subscription/safety/backup operations. Organization roles manage brands, branches, staff, permissions, profiles, schedules, floor areas, service points, menu/QR/reporting. Guests enter through opaque QR/cookie credentials, join a table, share drafts, request service and observe order status. Waiters validate/confirm drafts, explicitly dispatch immutable order snapshots and settle offline payments. Kitchen/bar teams progress department-scoped ticket items. Audit, notification, payment and status records retain operational history.

The authoritative detailed views are [`architecture.md`](architecture.md), [`domain-model.md`](domain-model.md), [`data-model.md`](data-model.md), [`authorization.md`](authorization.md) and [`security.md`](security.md).

## Findings and final resolution

| Initial finding | Final resolution and evidence |
|---|---|
| 64 failures and 15 errors | TDD fixes and regression coverage; the current sequential suite runs 878 tests with 869 passing, 8 intentional feature-gated skips, 1 tracked issue #10 `todo` and 23,166 assertions. The parallel gate also passes with process-unique SQLite restore sandboxes. |
| 17 Composer and 4 npm advisories | Stable targeted lock upgrades; both final audits report zero advisories. |
| Invalid factory/mass-assignment graphs | Explicit relationship defaults/guarded persistence; 43/43 factories and meaningful states persist. |
| No formal policy layer | Added aggregate Organization, Brand, Branch and Invitation policies, scoped bindings and negative tenant/action tests. |
| Unusable plaintext invitation bearer token | Digest-at-rest indexed lookup, expiry/revocation, atomic one-use consume, recipient/session binding and rate limiting. |
| Concurrent manual payment overpay risk | SQLite write serialization plus fresh balance read, bounded retry and duplicate/race regression tests. |
| Live SQLite file streamed directly | Native consistent online snapshot, mode-0600 private temporary file, recent password, confirmation/reason, no-store response and cleanup. |
| File replacement could diverge from DB | Persistence-first replacement with new-file compensation and lifecycle tests. |
| 94 Blade PHP directives/model/presentation calls | Removed; executable architecture scan blocks PHP blocks, models, Actions, Services, Illuminate/facades and container/auth/config/session access. |
| Two Livewire route SFCs | Converted to `Guest\\Home` and `Restaurant\\Dashboard` classes with separate views; SFC/Volt scan passes. |
| Unbounded lists/N+1 candidates | Bounded pagination/queries, selected/eager relations and executable budgets added: audit 10 constant, guest menu 15 cold / 2 warm, waiter dashboard at most 40. |
| Float and duplicated money conversions | Decimal/minor-unit-safe formatting and domain arithmetic centralized and tested. |
| Locale-sensitive cache keys | Cache ownership and invalidation now include the relevant branch/locale context; separation tests pass. |
| 66 `app()` service-locator calls in 32 PHP files | Application operations use constructor/Livewire `boot()` injection; architecture scan passes. |
| No strict Eloquent/static analysis | Strict local/test Eloquent enabled; configured Larastan level 5 analyses 502 files with 0 errors. |
| Historical migrations use model backfills | Historical files preserved; corrections are forward-only/reversible; all 73 migrations pass from zero. |
| Global Livewire offline root could serialize bearer invitation/reset URLs | Restricted `wire:offline` to authenticated bearer-free pages, added a snapshot-free guest/auth indicator and a token non-disclosure regression test; both paths passed isolated browser offline/online checks. |
| Generic equal-weight dashboard/card hierarchy and over-expanded waiter branches | Public, guest, restaurant, waiter and department surfaces now expose one primary path, compact related metrics and native progressive disclosure; responsive/browser regressions pass. |
| Persisted legacy area/service-point/menu-category icon names could address missing dynamic Flux components and return HTTP 500 | All three presentation boundaries enforce explicit supported-icon lists with type-safe fallback; demo values are corrected and regressions cover legacy records. |

## Final state

- PHP 8.5.8; Laravel 13.26.1; Livewire 4.4.1; Flux Free 2.17.0; Tailwind/plugin 4.3.3; Laravel Vite plugin 3.2.0; Vite 8.2.2; Pest 4.7.8 / PHPUnit 12.5.33; Larastan 3.10.0.
- 424 application PHP files, 52 concrete class Livewire components, 43 models/factories, 73 migrations, 7 seeders plus one translation support class, 123 Blade templates and 135 PHP test files.
- 878 sequential tests, 869 passed, 9 skipped, 23,166 assertions and 0 static-analysis errors. The fresh Unit/Feature coverage run used PHP 8.5.8 with Xdebug 3.5.0: 945 tests, 936 passed, 9 skipped including the tracked issue #10 `todo`, 23,265 assertions, 293.875 seconds and 91.9% application coverage.
- 2,178 semantic keys per locale; no missing, invalid, legacy or phrase-style keys.
- Production CSS 303.36 kB / 39.72 kB gzip; application JS is 4.56 kB / 1.73 kB gzip.

## First-party Markdown inventory

The current 57-file first-party Markdown set was reviewed during modernization and documentation consolidation. `Canonical` means the file defines current truth; `pointer` preserves an old path without duplicating requirements; `history` is intentionally chronological and may describe obsolete past states as dated history.

| Path | Purpose / owner | Status, conflict, history, requirement gap | Final action |
|---|---|---|---|
| `AGENTS.md` | repository instructions / maintainers | canonical, current, no unresolved conflict | rewritten and preserved |
| `README.md` | onboarding / maintainers | canonical overview, current | rewritten and synchronized |
| `SECURITY.md` | disclosure policy / security | canonical, current | rewritten and synchronized |
| `CHANGELOG.md` | chronology / maintainers | history; old limitations remain dated, not active | preserved; modernization entry prepended |
| `CLAUDE.md` | agent entry | duplicate of old rules | rewritten as pointer |
| `GEMINI.md` | agent entry | duplicate of old rules | rewritten as pointer |
| `PRODUCT.md` | user/product/design context | canonical design supplement; requirement behavior remains in `docs/requirements.md` | created and synchronized |
| `DESIGN.md` | visual system and anti-reference contract | canonical design supplement; implementation source remains `app.css` | created and synchronized |
| `docs/index.md` | documentation map / maintainers | canonical, current | created and preserved |
| `docs/requirements.md` | active requirements / product+engineering | canonical; 48 stable IDs, no unimplemented active row | created and synchronized |
| `docs/product-requirements.md` | product summary / product | canonical supplement | created and preserved |
| `docs/system-requirements.md` | system summary / engineering | pointer to catalogue | created and preserved |
| `docs/non-functional-requirements.md` | quality summary / engineering | pointer to catalogue | created and preserved |
| `docs/compliance-matrix.md` | traceability / engineering | canonical evidence; 47 verified, 1 feature-gated N/A | created and finalized |
| `ROADMAP.md` | current delivery sequence / product+engineering | only active roadmap; completed work stays in evidence and changelog | created from the retired implementation plan and prompt queue |
| `docs/current-state-audit.md` | baseline/resolution / engineering | canonical evidence | created and finalized |
| `docs/architecture.md` | boundaries/applicability / architecture | canonical, current | created and synchronized |
| `docs/domain-model.md` | roles/workflows/states / domain | canonical, current | created and preserved |
| `docs/data-model.md` | schema/value conventions / data | canonical, current | created and synchronized |
| `docs/security.md` | implemented controls / security | canonical, current | created and synchronized |
| `docs/authorization.md` | roles/policies / security | canonical, current | created and preserved |
| `docs/frontend.md` | Blade/Flux/browser boundary / frontend | canonical, current | created and preserved |
| `docs/livewire.md` | Livewire decisions / frontend | canonical feature matrix | created and finalized |
| `docs/tailwind.md` | Tailwind decisions / frontend | canonical feature matrix | created and finalized |
| `docs/design-system.md` | tokens/components / design | canonical, current | created and preserved |
| `docs/accessibility.md` | a11y contract/evidence / design | canonical, current with environmental limits | created and finalized |
| `docs/localization.md` | EN/LT/RU workflow / localization | canonical, current | created and finalized |
| `docs/testing.md` | quality commands / QA | canonical, commands executed | created and finalized |
| `docs/seeding.md` | factory/seeder matrix / data+QA | canonical, complete | created and finalized |
| `docs/performance.md` | budgets/measurements / engineering | canonical, final metrics | created and finalized |
| `docs/caching.md` | cache ownership / engineering | canonical, current | created and preserved |
| `docs/integrations.md` | external integration inventory / engineering | canonical; none external | created and preserved |
| `docs/deployment.md` | release contract / operations | canonical, current | created and synchronized |
| `docs/operations.md` | runtime/incident checks / operations | canonical, current | created and preserved |
| `docs/code-review.md` | review record / maintainers | canonical final review | created and finalized |
| `docs/known-limitations.md` | external/environmental limits / maintainers | canonical; no in-scope backlog hidden | created and finalized |
| `docs/decisions/0001-shared-hosting-runtime.md` | runtime ADR / architecture | canonical accepted decision | created and preserved |
| `docs/decisions/0002-class-livewire-and-presentation-blade.md` | UI ADR / architecture | canonical accepted decision | created and preserved |
| `docs/decisions/0003-one-time-token-storage.md` | token ADR / security | canonical accepted decision | created and synchronized |
| `docs/README.md` | legacy documentation entry | duplicated index | rewritten as pointer |
| `docs/AI_CONTEXT.md` | legacy prompt memory | historical duplication/stale queues | rewritten as pointer to canonical docs/history |
| `docs/CODEX_GUARDRAILS.md` | legacy guardrails | obsolete status statement | rewritten as current pointer/invariant |
| `docs/CURRENT_VERSION.md` | version snapshot | drift-prone | rewritten with exact final locks |
| `docs/DEMO_LOGIN.md` | demo credential supplement | current and intentionally focused | preserved and verified |
| `docs/DEPLOY_SHARED_HOSTING.md` | legacy hosting guide | duplicated deployment contract | rewritten as focused pointer |
| `docs/ERROR_HANDLING.md` | error supplement | current and focused | preserved and verified |
| `docs/FACTORY_SEEDER_RULES.md` | legacy seeding rules | duplicated two other files | rewritten as pointer |
| `docs/FIELD_TRANSLATION_AUDIT.md` | audit snapshot | old counts | synchronized with final command |
| `docs/FUNCTIONAL_TEST_STRATEGY.md` | legacy test strategy | duplicated testing contract | rewritten as pointer |
| `docs/MIGRATION_AUDIT.md` | legacy migration audit | old 65-migration count/proposal | rewritten with final evidence/pointer |
| `docs/SCHEMA_SNAPSHOT.md` | schema supplement | old 65-migration count | synchronized with final evidence/pointer |
| `docs/SECURITY_RULES.md` | legacy security rules | duplicated canonical controls | rewritten as pointer |
| `docs/SEEDERS.md` | legacy seeder reference | duplicated canonical seeding | rewritten as pointer |
| `docs/SEED_ARCHITECTURE.md` | legacy seeding architecture | duplicated canonical seeding | rewritten as pointer |
| `docs/TEST_CHECKLIST.md` | browser/manual checklist | useful supplement; executed representative subset | preserved and annotated with evidence |
| `docs/TRANSLATION_KEY_MAP.md` | namespace supplement | current | preserved |
| `docs/TRANSLATION_STANDARD.md` | legacy localization standard | useful allow-list boundary | preserved and verified empty |

Generated skill trees under `.agents`, `.claude` and `.cursor` are tool instructions, not first-party application requirements, and were excluded from this 57-file inventory as defined by the audit scope. The obsolete `docs/NEXT_STEPS.md` and completed `docs/implementation-plan.md` paths were removed during roadmap consolidation.

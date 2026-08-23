# Restaurant Menu completion progress

Updated 2026-08-23. This is an observed evidence ledger for [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md), not a source of requirements. Command results are recorded only after their exit status/output has been observed.

## Baseline and repository audit — complete

- Read repository instructions and the complete canonical/product/design/architecture/security/frontend/testing/seeding/deployment documentation set, then checked implementation rather than assuming documentation was current.
- Reconciled branch, HEAD, origin, status, staged/unstaged/untracked files, recent commits, and concurrent edits. The demo graph was committed concurrently as `d127940` and pushed to `origin/main`; its later additive working-tree follow-up is preserved.
- Confirmed the installed baseline: PHP 8.5, Laravel 13.26.1, Livewire 4.4.1, Flux 2.17, Tailwind 4.3.3, Vite 8.2.2, Pest 4.7.8, SQLite application/cache/session/queue defaults, and one npm lock file. No React, Vue, Inertia, Svelte, jQuery, Axios, Volt, Redis, S3, WebSocket, or Docker runtime dependency was found.
- Audited 71 registered routes, 73 applied migrations, database schema/index/foreign-key metadata, 43 models and factories, 16 policies, 175 Actions, 42 class-based Livewire components, 123 Blade templates, translations, seeders, CI workflows, and architecture/security tests.
- Compared all 49 canonical requirement rows with their compliance evidence. Forty-eight are implemented/verified; `sys-auth-002` remains explicitly not applicable because passkeys/2FA feature flags are disabled. GitHub issue #10 is not an approved active requirement.

## Initial gate evidence — complete

| Gate | Observed result |
|---|---|
| Target demo seeder suite before commit | 12 tests, 794 assertions, passed |
| Full parallel Pest baseline before final edits | 857 tests total, 848 passed, 9 feature-gated/skipped, 23,082 assertions, passed |
| Composer validation/audit | valid; zero advisories |
| npm audit | zero vulnerabilities at moderate threshold |
| Vite production build | passed |
| Translation scan/audit | 550 files scanned; 0 missing, 0 legacy, 0 parity/audit issues |
| Pint/Larastan after concurrent corrections | passed; 0 Larastan errors |

The first audit run found a formatting defect in `DemoOperationalStateSeeder` and an impossible nullable closure return in `OrderStatusLogFactory`. Both were corrected within the concurrently completed demo commit and were rechecked before continuing. Old test totals in documentation are historical evidence and will not be presented as final current totals.

## P0 progress

- **P0.1 demo graph — complete:** the factory-backed graph and bounded loop-count optimization are committed at local `ff8fac0`. Post-commit demo/factory/route verification passed with 67 tests and 1,264 assertions; Larastan reported 0 errors; Pint reported clean. All 73 migrations ran against `/tmp/restaurant-menu-p0.fGhb8K/audit.sqlite`, and two consecutive isolated `DemoRestaurantSeeder` runs completed successfully with storage isolated under the same temporary root.
- **P0.2 named routes — complete:** RED observed for missing `settings.index` (42 passed, 1 failed); route named without behavioural change; GREEN observed (43 tests, 169 assertions); the route inventory confirms `settings.index` carries `web` and `auth`; Pint passed.
- **P0.3 documentation — complete:** requested completion plan/progress/decisions, index, requirement status, architecture inventory, roadmap relationship, and permanent reading rule added. Requirement/compliance parity is 49/49, every new cross-document target exists, and `git diff --check` passes.

## P1 progress

- Backend/data final gates: Composer validation/audit, targeted P0 suites, Larastan, Pint, fresh isolated migration and repeated demo seed pass. The first sequential full run correctly exposed a cross-process collision with a concurrently running coverage process: backup/restore tests shared fixed SQLite and local-backup paths. After adding PID/random database and local-filesystem isolation, two simultaneous focused restore suites both passed 4 tests/24 assertions. On the final tree, sequential and parallel Pest each passed 1,071 total/1,062 passed/9 skipped/23,668 assertions; canonical coverage passed 1,067 total/1,058 passed/9 skipped/23,555 assertions at 93.3% in 303.495 seconds. The coverage script now disables Composer's 300-second process timeout while retaining Pest failures and the 90% floor.
- Frontend/localization/cache final gates: npm audit, Vite build, EN/LT/RU scan/audit and config/route/event/view cache builds pass; generated caches were cleared afterward.
- Disposable-browser smoke/accessibility review: isolated Chrome verified `/`, staff-login navigation, `/login`, and `/guest` at desktop and 390×844 mobile. Public/login/guest requests returned 200, accessibility trees were meaningful, keyboard focus began with the skip link and reached named controls, mobile horizontal overflow was zero, and `/guest` Lighthouse scored 100 in accessibility, best practices, SEO and agentic browsing. The first `/up` check exposed Laravel's external Bunny/jsDelivr assets plus a missing favicon; a RED/GREEN observability test and `RequireJsonHealthCheckResponse` fixed the root cause. Recheck observed only `GET /up` 200 with `{"status":"up"}` and zero console messages or asset requests.

## P2 boundaries

- Issues #3/#4/#5 require immutable release publication or production/operator access; this run performs the local issue #4 evidence only and does not deploy.
- Issues #7/#8 require external physical platforms/assistive technology; limitations remain explicit.
- Issue #10 requires an approved product contract and is intentionally excluded from implementation until then.

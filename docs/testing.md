# Testing and quality gates

Pest 4 is the sole primary PHP test style. Feature tests cover Laravel/Livewire integration and security boundaries; unit tests cover pure rules and translation contracts. Browser automation is reserved for DOM, focus, responsive, navigation and console behavior that PHP tests cannot prove.

## Development loop

1. Add or update a failing focused test.
2. Run `php artisan test --compact <path-or-filter>`.
3. Implement the smallest correct change and rerun the target.
4. Run `vendor/bin/pint --dirty --format agent` after PHP edits.
5. Run `vendor/bin/phpstan analyse --memory-limit=1G` on the stabilized pass.
6. Run `npm run build` after Blade/CSS/JavaScript source changes.
7. Update the requirement matrix only after observed evidence.

Tests use isolated SQLite, fake local disks and faked external I/O. Never run `migrate:fresh` against the application database. Factories create valid records; no test or seeder requires public internet or real credentials.

If `php artisan optimize` was run under the local environment, run `php artisan optimize:clear` before the test suite so PHPUnit's isolated environment and in-memory database configuration are loaded instead of the local cached configuration.

`composer test:coverage` is the canonical coverage command. It runs the Unit and Feature suites against `app/` and fails below 90%. GitHub Actions provisions Xdebug and executes this gate after the full behavioral suite. The local Herd PHP 8.5 CLI loads Xdebug with `xdebug.mode=off`; the Composer script enables coverage only for this command through `XDEBUG_MODE=coverage`, so normal local and production requests do not pay coverage overhead.

## Current working-tree evidence (2026-08-24)

| Command | Observed result |
|---|---|
| focused onboarding suite | 192 passed; 2,410 assertions |
| focused onboarding browser journey | 1 passed; 178 assertions |
| onboarding-related integrity, QR and vertical-slice batch | 31 passed; 256 assertions |
| complete `/organizations` focused slice | 179 passed; 1,746 assertions |
| `tests/Browser/OrganizationsCrudJourneyTest.php` | 1 passed; 187 assertions |
| `composer test:browser` | 5 passed; 396 assertions |
| `composer ci:check` | Pint passed; Larastan 0 errors; 1,392 tests, 1,383 passed, 9 feature-gated/skipped, 28,973 assertions; 194.952 seconds |
| `php artisan test --compact tests/Unit tests/Feature` | 1,387 tests; 1,378 passed; 9 feature-gated/skipped; 28,577 assertions; 165.713 seconds |
| `php vendor/bin/pest --parallel --testsuite=Unit,Feature --compact` | 1,387 tests; 1,378 passed; 9 feature-gated/skipped; 28,577 assertions; 83.235 seconds |
| `composer test:coverage` | 1,387 Unit/Feature tests; 1,378 passed; 9 feature-gated/skipped; 28,577 assertions; 93.5% application coverage; 576.628 seconds |
| authorized SQLite backup/restore and rollback suite | 12 passed; 86 assertions; 3.03 seconds |
| isolated WAL/concurrent-reader demo recovery drill | 1 organization, 4 branches, 19 QR records/files, 6 orders and 5 payments restored; cache/session/token cleanup and safety snapshot passed; core restore 0.066 seconds |
| `vendor/bin/pint --dirty --format agent` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` | passed; 0 errors |
| `php artisan translations:scan --json` | 609 files; 1,729 semantic keys used; 2,339 JSON keys per locale; 0 missing/legacy/phrase keys |
| `php artisan translations:audit` | 7,017 semantic entries; 0 critical issues |
| `composer validate --strict` / `composer audit --locked --no-interaction` | valid package; zero advisories |
| `npm audit --audit-level=moderate` / `npm run build` | zero vulnerabilities; Vite 8.2.2 passed; CSS 302.63 kB / 39.97 kB gzip; JS 4.56 kB / 1.73 kB gzip |
| isolated migration plus repeated default seeds | all 81 migrations passed; seeds were idempotent; the five current forward migrations rolled back/reapplied in their verified scopes; existing local data was upgraded additively |
| config, route, event and view cache builds | passed; 71 total routes, 21 first-party and all named; followed by `optimize:clear` |

## Invite-only authorization evidence (2026-08-24)

- RED/GREEN regressions cover hash-only credential creation, token-free redirect, recipient binding, new-account registration, existing-account joining, atomic compare-and-set acceptance, replay, expiry, cancellation, reissue rotation, superadmin denial, corrupt tenant chains, generic localized states and both credential/client plus independent client throttling. The rotating-credential test observed RED at request 31 before the independent client budget was added. Mail is faked and asserted empty because no invitation delivery integration is configured.
- Policy and Action tests cover superadmin, owner, director, restaurant administrator, canonical `head_chef` chef and waiter boundaries; equal/higher-role escalation, self changes, cross-organization/branch/brand identifiers, Livewire payload tampering, scoped search, exports and direct Action calls are denied. New corrupt-scope RED cases prove inconsistent branch membership and invitation rows are neither rendered nor mutated.
- The focused invite/staff/tenant/export/query batch passes 137 tests/917 assertions. Final sequential and parallel Pest each pass 1,312 tests/1,303 passed/9 feature-gated with 28,070 assertions. Canonical coverage passes 1,307 Unit/Feature tests/1,298 passed/9 skipped with 27,679 assertions at 93.5%.
- Larastan reports zero errors. Composer validation/platform/audit, npm audit, Vite build, translation audit/scan, config/route/event/view caches and scoped Pint pass. Route-cache inspection confirms four invitation endpoints and no public `register` or `register.store` route. Pest Browser passes 5 scenarios/391 assertions.
- An isolated Chrome context followed an unknown 64-character bearer through 302 to `/invite/pending`; the expected localized 410 page contained no bearer, carried no-store/referrer/noindex controls, had a meaningful accessibility tree, loaded every asset with 200 and had zero horizontal overflow. Chrome's sole error entry is the intentional top-level 410 response, not a JavaScript or asset failure.

## Database integrity audit evidence (2026-08-24)

- TDD RED observed six original failures: missing leading FK indexes, redundant index prefixes, cross-tenant branch/brand acceptance, cross-branch area-parent acceptance, missing guest-opener FK behavior and retained plaintext invitation columns. A seventh RED identified the missing Fortify confirmation datetime cast.
- The focused database/tenant/factory/invitation/schema/query batch passes 175 tests with 1,652 assertions. `DatabaseIntegrityAuditTest` itself passes 10 tests with 24 assertions and automatically audits every table/model FK index, redundant prefix, `BelongsTo` relation, nullable `SET NULL` target, boolean/date cast, soft-delete trait, public uniqueness and hierarchy boundary.
- An isolated SQLite database completed all 80 migrations, `migrate:fresh --seed`, two additional default seed passes, rollback of the four audit migrations, re-migration and `foreign_key_check`/`integrity_check` with no errors.
- The four forward migrations were then applied to the existing local database. A pre-migration copy and post-migration database were compared across 57 non-migration tables using all common columns; there were zero row differences. Tenant consistency checks remained zero and the local database reports no FK/integrity violations.
- SQLite query plans changed from index/full scans to indexed searches for `area_nodes.parent_id`, `service_points.area_node_id`, `audit_logs.user_id` and `(branches.organization_id, branches.brand_id)`. Schema metadata reports no missing FK-leading index and no redundant non-unique index prefix.

The technical-foundation refresh also passed `ProjectCleanupConsistencyTest` and proved all Eloquent reads and persistence are delegated out of Livewire components. The focused onboarding suite currently passes 192 tests with 2,410 assertions. It covers remount from no checkpoint and every successful checkpoint, starter-menu creation without a completion marker, explicit completed revisit, stale/repeated requests and snapshots across every mutation, revoked membership/subscription/branch-assignment snapshots, missing-subscription fail-closed behavior, explicit permission-deny precedence, bare-owner and orphaned staff-role eligibility, direct future-step and capability invocation, malformed navigation and hostile mixed/boolean form payloads, backward editing without duplicate identities, exact maximum boundaries, entity/checkpoint rollback plus retry, full service-point and QR batch rollback plus retry, starter-menu graph rollback, write-once completion including hard-deleted graph reconstruction, the persisted expected table count, malformed type/position rejection before QR/menu writes, hard-deleted area identity reuse, absence of checkpoint-free legacy mutation Actions, disabled and hard-deleted QR recovery, hard-deleted starter-item recovery, real-remount soft-delete hydration, narrow restore and menu-availability policy checks, partial and completed disabled operational references, cross-tenant parent/pivot corruption rejected before foreign relation hydration or writes, stale menu-chain rollback, safe same-branch table replacement, position normalization, locked and absent public-ID tampering, public-QR identity minimization, exact decimal money with binary-float rejection, EN/LT/RU rendering/key parity and a bounded initial/completed mount query budget. An isolated SQLite drill additionally proved the expected-count migration reversible and backfilled three existing ordered links to `3` without changing foreign keys. The focused 178-assertion browser journey verifies EN/LT/RU, keyboard focus, first-invalid-field recovery, combined help/error association, back/edit behavior, refresh/resume, 320–1,440 CSS-pixel reflow and 200% root text scaling without JavaScript errors or horizontal overflow. A fresh isolated Chrome context confirmed the guest redirect from `/onboarding/restaurant`, all login assets at 200, zero 320-pixel horizontal overflow, a complete accessibility tree, zero console messages and Lighthouse accessibility/best-practices/SEO scores of 100.

## Organizations CRUD evidence (2026-08-24)

The complete 26-resource matrix passes 179 focused tests with 1,746 assertions. The dedicated Pest Browser journey creates, edits, archives and restores only its own disposable organization, traverses every nested organization/staff/permission/brand/branch/settings/area/service-point/QR/menu surface, and verifies responsive layout, accessible names, modal focus, 200% text scaling, network success and a clean JavaScript console with 187 assertions. The current full five-scenario browser suite passes with 396 assertions.

An isolated empty SQLite database completed all 76 migrations and two identical demo seed runs. Counts, IDs, deterministic paths and file hashes remained stable, including eight secondary dish-gallery records; a forced production attempt failed closed without changing counts. Only after that proof was the non-production local demo seeder run twice without refresh or truncation. A final isolated Chrome context loaded `/organizations` and the seeded menu/gallery through Herd after the production build: all observed resources succeeded, the account controls had localized accessible names, the viewport did not overflow and the console contained no new error or warning.

## Dish gallery evidence (2026-08-24)

The focused schema, menu Action, CRUD, gallery, local-media and component-size suites passed 72 tests with 524 assertions. They cover multi-file append, the combined eight-image limit, dangerous and oversized files, branch tampering, persistence compensation, primary promotion, individual and parent cleanup, modal closure, edit-only Livewire controls, stable accessible markup and a single eager-load query for every rendered gallery. `ModelFactoryAuditTest` plus `FactoryStatesTest` passed 12 tests with 304 assertions; the gallery migration also completed migrate, rollback and migrate on an isolated SQLite file. Translation audit reported 6,882 aligned EN/LT/RU entries with zero critical issues, the 590-file translation scan reported zero missing/legacy/phrase keys, and Vite 8.2.2 built the production assets successfully. An isolated real browser selected two files in one upload, promoted and removed images, restored the seeded primary, produced zero console errors/warnings after HTTPS URL correction, showed no horizontal overflow at 390 px, and measured LCP 1,297 ms with CLS 0.00.

The local coverage run used `/Users/andrejprus/Library/Application Support/Herd/bin/php85`, PHP 8.5.8 and Xdebug 3.5.0. `php -m` and `php --ri xdebug` confirmed the extension in that binary, and `XDEBUG_MODE=coverage php --ri xdebug` confirmed coverage mode for the canonical command. The 90% threshold and first-party source set were unchanged. The coverage Composer script disables only Composer's process timeout because the verified run exceeds 300 seconds; Pest still owns failures and its coverage threshold. SQLite backup/restore tests use per-process unique database and local-filesystem roots so simultaneous full and coverage processes cannot corrupt each other's databases, candidates or safety snapshots.

## Preserved browser evidence

Before the merge, Chrome DevTools used distinct disposable isolated contexts for the restaurant UI and every demo role. The checked flows covered all 12 one-click role logins, authenticated switch prevention, missing/restored account behavior, invitation registration boundaries, EN/LT/RU, keyboard submission and focus, light/dark rendering, and 320, 360, 768 and 1,440 CSS-pixel layouts without horizontal overflow. Chrome and Playwright reported no console warnings/errors or failed assets; demo Lighthouse accessibility scored 100 on mobile and desktop.

On 2026-08-23, an additional disposable Chrome pass covered the waiter dashboard and table detail at 1,280 and 390 CSS pixels. The checked pages had no horizontal overflow, no checked interactive target below 24 CSS pixels, visible focus through the keyboard order, no console warnings/errors, and only successful network responses after applying the pending forward migrations to the local demo database. The complete four-test Pest Browser suite also passed in Playwright WebKit 26.5 with 113 assertions. Playwright Firefox 153 could not start on macOS 27 because of the [confirmed upstream sandbox failure](https://github.com/microsoft/playwright/issues/42082); a minimal browser launch reproduced the runtime failure before application navigation. Physical assistive technology/device, actual Safari/Firefox, and non-headless 200% zoom remain environmental limitations.

## Automated architecture boundaries

`ProjectCleanupConsistencyTest` and related repository tests prohibit route SFC/Volt, Blade PHP blocks, Blade model/Action/Service/Illuminate/facade/container/auth/config/session access, application service locators, hardcoded Livewire page titles/action errors, debug stubs, forbidden runtime packages and legacy translation keys. XSS, token, route, tenant, file, money, cache and query tests enforce the corresponding requirement families. Invitation tests also prove bearer tokens never appear in rendered Livewire snapshots.

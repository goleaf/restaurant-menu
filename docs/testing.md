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

## Current merged-tree evidence (2026-08-23)

| Command | Observed result |
|---|---|
| focused demo, invitation, seeder, route, design and vertical-flow Pest suite | 117 passed; 1,439 assertions |
| invitation registration → paid table closure browser E2E | 1 passed; 82 assertions |
| `vendor/bin/pest tests/Browser --browser chrome --compact` / `--browser safari` | 4 passed and 113 assertions in each of Chromium and Playwright WebKit 26.5 |
| `php artisan test --compact` | 1,078 tests; 1,069 passed; 8 feature-gated skips and 1 tracked `todo` for issue #10; 23,707 assertions; 88.850 seconds |
| `php artisan test --compact --parallel` | same counts and assertions; 28.245 seconds |
| `composer test:coverage` | 1,074 Unit/Feature tests; 1,065 passed; 9 skipped, including the single tracked `todo` for issue #10; 23,594 assertions; 93.3% application coverage; 300.075 seconds |
| authorized SQLite backup/restore and rollback suite | 12 passed; 86 assertions; 3.03 seconds |
| isolated WAL/concurrent-reader demo recovery drill | 1 organization, 4 branches, 19 QR records/files, 6 orders and 5 payments restored; cache/session/token cleanup and safety snapshot passed; core restore 0.066 seconds |
| `vendor/bin/pint --dirty --format agent` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` | passed; 0 errors |
| `php artisan translations:scan --json` | 570 files; 1,614 semantic keys used; 2,178 JSON keys per locale; 0 missing/legacy/phrase keys |
| `php artisan translations:audit` | 6,534 semantic entries; 0 critical issues |
| `composer validate --strict` / `composer audit --locked --no-interaction` | valid package; zero advisories |
| `npm audit --audit-level=moderate` / `npm run build` | zero vulnerabilities; Vite 8.2.2 passed; CSS 303.36 kB / 39.72 kB gzip; JS 4.56 kB / 1.73 kB gzip |
| isolated migration plus two `DemoRestaurantSeeder` runs | all 73 migrations passed; seeds completed in 4.131 and 7.089 seconds; 12 catalogue users and 19 QR SVGs retained |
| config, route and view cache builds | passed; 71 routes; followed by `optimize:clear` |

The technical-foundation refresh also passed `ProjectCleanupConsistencyTest` plus the onboarding wizard suite (18 tests, 78 assertions), proved all Eloquent reads and persistence are delegated out of Livewire components, and exercised the onboarding `Livewire\\Form` state through both Feature and Browser test selectors. A fresh isolated Chrome context then loaded `/` and `/login` with all assets returning 200, no console errors or warnings, no 390 CSS-pixel horizontal overflow, and Lighthouse 100 in accessibility, best practices, SEO and agentic browsing on both the audited public and login surfaces.

The local coverage run used `/Users/andrejprus/Library/Application Support/Herd/bin/php85`, PHP 8.5.8 and Xdebug 3.5.0. `php -m` and `php --ri xdebug` confirmed the extension in that binary, and `XDEBUG_MODE=coverage php --ri xdebug` confirmed coverage mode for the canonical command. The 90% threshold and first-party source set were unchanged. The coverage Composer script disables only Composer's process timeout because the verified run exceeds 300 seconds; Pest still owns failures and its coverage threshold. SQLite backup/restore tests use per-process unique database and local-filesystem roots so simultaneous full and coverage processes cannot corrupt each other's databases, candidates or safety snapshots.

## Preserved browser evidence

Before the merge, Chrome DevTools used distinct disposable isolated contexts for the restaurant UI and every demo role. The checked flows covered all 12 one-click role logins, authenticated switch prevention, missing/restored account behavior, invitation registration boundaries, EN/LT/RU, keyboard submission and focus, light/dark rendering, and 320, 360, 768 and 1,440 CSS-pixel layouts without horizontal overflow. Chrome and Playwright reported no console warnings/errors or failed assets; demo Lighthouse accessibility scored 100 on mobile and desktop.

On 2026-08-23, an additional disposable Chrome pass covered the waiter dashboard and table detail at 1,280 and 390 CSS pixels. The checked pages had no horizontal overflow, no checked interactive target below 24 CSS pixels, visible focus through the keyboard order, no console warnings/errors, and only successful network responses after applying the pending forward migrations to the local demo database. The complete four-test Pest Browser suite also passed in Playwright WebKit 26.5 with 113 assertions. Playwright Firefox 153 could not start on macOS 27 because of the [confirmed upstream sandbox failure](https://github.com/microsoft/playwright/issues/42082); a minimal browser launch reproduced the runtime failure before application navigation. Physical assistive technology/device, actual Safari/Firefox, and non-headless 200% zoom remain environmental limitations.

## Automated architecture boundaries

`ProjectCleanupConsistencyTest` and related repository tests prohibit route SFC/Volt, Blade PHP blocks, Blade model/Action/Service/Illuminate/facade/container/auth/config/session access, application service locators, hardcoded Livewire page titles/action errors, debug stubs, forbidden runtime packages and legacy translation keys. XSS, token, route, tenant, file, money, cache and query tests enforce the corresponding requirement families. Invitation tests also prove bearer tokens never appear in rendered Livewire snapshots.

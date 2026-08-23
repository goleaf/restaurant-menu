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

`composer test:coverage` is the canonical coverage command. It runs the Unit and Feature suites against `app/` and fails below 90%. GitHub Actions provisions Xdebug and executes this gate after the full behavioral suite; local runs require a PHP 8.5 Xdebug or PCOV driver.

## Current merged-tree evidence (2026-08-23)

| Command | Observed result |
|---|---|
| focused demo, invitation, seeder, route, design and vertical-flow Pest suite | 117 passed; 1,439 assertions |
| invitation registration → paid table closure browser E2E | 1 passed; 82 assertions |
| `php artisan test --compact` | 854 tests; 845 passed; 9 feature-gated skips; 22,990 assertions; 85.913 seconds |
| `php artisan test --compact --parallel` | same counts and assertions; 28.048 seconds |
| SQLite restore tests after in-memory maintenance isolation | 4 passed; 24 assertions |
| `vendor/bin/pint --dirty --format agent` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` | passed; 0 errors |
| `php artisan translations:scan --json` | 550 files; 1,614 semantic keys used; 2,178 JSON keys per locale; 0 missing/legacy/phrase keys |
| `php artisan translations:audit` | 6,534 semantic entries; 0 critical issues |
| `composer validate --strict` / `composer audit --locked --no-interaction` | valid package; zero advisories |
| `npm audit --audit-level=moderate` / `npm run build` | zero vulnerabilities; Vite 8.2.2 passed; CSS 303.36 kB / 39.72 kB gzip; JS 4.56 kB / 1.73 kB gzip |
| isolated migration plus two `DemoRestaurantSeeder` runs | all 73 migrations passed; seeds completed in 4.131 and 7.089 seconds; 12 catalogue users and 19 QR SVGs retained |
| config, route and view cache builds | passed; 71 routes; followed by `optimize:clear` |

Fresh local coverage remains an explicit environment-dependent gate. The last historical measurement was 90.4%; it is not evidence for the merged tree.

## Preserved browser evidence

Before the merge, Chrome DevTools used distinct disposable isolated contexts for the restaurant UI and every demo role. The checked flows covered all 12 one-click role logins, authenticated switch prevention, missing/restored account behavior, invitation registration boundaries, EN/LT/RU, keyboard submission and focus, light/dark rendering, and 320, 360, 768 and 1,440 CSS-pixel layouts without horizontal overflow. Chrome and Playwright reported no console warnings/errors or failed assets; demo Lighthouse accessibility scored 100 on mobile and desktop. Physical assistive technology/device, non-headless 200% zoom and non-Chromium evidence remain environmental limitations.

## Automated architecture boundaries

`ProjectCleanupConsistencyTest` and related repository tests prohibit route SFC/Volt, Blade PHP blocks, Blade model/Action/Service/Illuminate/facade/container/auth/config/session access, application service locators, hardcoded Livewire page titles/action errors, debug stubs, forbidden runtime packages and legacy translation keys. XSS, token, route, tenant, file, money, cache and query tests enforce the corresponding requirement families. Invitation tests also prove bearer tokens never appear in rendered Livewire snapshots.

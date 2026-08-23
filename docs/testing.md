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

## Final commands and observed results (2026-08-23)

| Command | Result |
|---|---|
| `vendor/bin/pint --format agent` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` | configured Larastan level 5; 503 files; 0 errors |
| PHP syntax loop over first-party PHP/Blade entry files | 757 files, 0 errors |
| `php artisan test --compact tests/Feature/DemoRestaurantSeederTest.php tests/Feature/SeededLanguageSwitcherTest.php tests/Feature/QrAdminDisplayTest.php` | 20 passed; 837 assertions |
| `php artisan test --compact` | 758 passed; 8 skipped; 1 todo; 21,349 assertions; 65.17 s |
| `php artisan test --compact --parallel` | same counts; 17.99 s; 8 processes |
| `composer test:coverage` | 90% minimum is enforced in CI with Xdebug; local collection remains blocked because the current PHP 8.5 CLI/Herd proxy loads no coverage driver; last locally verified result was 90.4% and is historical only |
| isolated `php artisan migrate --force --no-interaction` | all 70 migrations passed on a new temporary SQLite database |
| two isolated `DemoRestaurantSeeder` runs | passed; 3.983 s then 6.992 s; 19 SVG files and their SHA-256 hashes remained stable; exact graph counts and stable order/payment IDs also pass in Pest |
| `php artisan translations:scan --json` | 413 files; 1,495 semantic used; 0 missing/legacy/phrase keys |
| `php artisan translations:audit` | 6,117 semantic entries; 0 critical issues |
| `composer validate --strict` / `composer audit --no-interaction` | valid / zero advisories |
| `npm audit --audit-level=moderate` / `npm run build` | zero advisories / passed |
| config, route and view cache builds | passed; 64 routes; followed by `optimize:clear` |

The eight skips are intentional feature gates for disabled authentication capabilities. The one existing todo is the explicitly marked draft-order allocation case tracked by [GitHub issue #10](https://github.com/goleaf/restaurant-menu/issues/10); neither hides a failure in the seeded workflow.

## Automated architecture boundaries

`ProjectCleanupConsistencyTest` and related repository tests prohibit route SFC/Volt, Blade PHP blocks, Blade model/Action/Service/Illuminate/facade/container/auth/config/session access, application service locators, hardcoded Livewire page titles/action errors, debug stubs, forbidden runtime packages and legacy translation keys. XSS, token, route, tenant, file, money, cache and query tests enforce the corresponding requirement families. Invitation tests also prove bearer tokens never appear in rendered Livewire snapshots.

Browser verification used disposable isolated contexts `restaurant-menu-ui-ux-20260822` and `restaurant-menu-ui-auth-20260822`; no personal profile, cookies or storage were used. Final UI evidence covers public/login/restaurant/waiter/service-point/menu landmarks and hierarchy, skip-link keyboard focus, native disclosure, modal focus/restoration, light/dark contrast, 360–1920 CSS px samples, touch targets, Lighthouse, performance and console inspection.

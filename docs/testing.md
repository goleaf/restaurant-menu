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

## Final commands and observed results (2026-08-22)

| Command | Result |
|---|---|
| `vendor/bin/pint --format agent` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` | Larastan level 8, 0 errors |
| PHP syntax loop over first-party PHP/Blade entry files | 665 files, 0 errors |
| `php artisan test --compact tests/Feature/MenuCrudTest.php tests/Feature/DemoRestaurantSeederTest.php tests/Feature/DesignSystemTest.php tests/Feature/WaiterDashboardTest.php` | 29 passed; 806 assertions |
| `php artisan test --compact` | 694 tests; 685 passed; 9 skipped; 20,597 assertions; 62.488 s |
| `php artisan test --compact --parallel` | same counts; 20.227 s |
| `php artisan test --compact --coverage --min=90` and Herd coverage proxy | blocked: no Xdebug/PCOV driver loaded; last pre-UI verified result was 90.4% and is historical only |
| isolated `php artisan migrate:fresh --seed --force --no-interaction` | all 66 migrations and default seed passed |
| two isolated `DemoRestaurantSeeder` runs | passed; 3.62 s then 7.06 s |
| `php artisan translations:scan --json` | 413 files; 1,495 semantic used; 0 missing/legacy/phrase keys |
| `php artisan translations:audit` | 6,117 semantic entries; 0 critical issues |
| `composer validate --strict` / `composer audit --no-interaction` | valid / zero advisories |
| `npm audit --audit-level=moderate` / `npm run build` | zero advisories / passed |
| config, route and view cache builds | passed; 64 routes; followed by `optimize:clear` |

The nine skips are intentional feature gates for passkey/2FA behavior because `config('fortify.features')` currently enables only registration and password reset. They do not hide an enabled workflow failure.

## Automated architecture boundaries

`ProjectCleanupConsistencyTest` and related repository tests prohibit route SFC/Volt, Blade PHP blocks, Blade model/Action/Service/Illuminate/facade/container/auth/config/session access, application service locators, hardcoded Livewire page titles/action errors, debug stubs, forbidden runtime packages and legacy translation keys. XSS, token, route, tenant, file, money, cache and query tests enforce the corresponding requirement families. Invitation tests also prove bearer tokens never appear in rendered Livewire snapshots.

Browser verification used disposable isolated contexts `restaurant-menu-ui-ux-20260822` and `restaurant-menu-ui-auth-20260822`; no personal profile, cookies or storage were used. Final UI evidence covers public/login/restaurant/waiter/service-point/menu landmarks and hierarchy, skip-link keyboard focus, native disclosure, modal focus/restoration, light/dark contrast, 360–1920 CSS px samples, touch targets, Lighthouse, performance and console inspection.

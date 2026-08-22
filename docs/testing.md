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

## Final commands and observed results (2026-08-22)

| Command | Result |
|---|---|
| `vendor/bin/pint --format agent` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` | Larastan level 8, 0 errors |
| PHP syntax loop over `app bootstrap config database routes tests` | 558 files, 0 errors |
| `php artisan test --compact tests/Feature/SqlitePerformanceGuardrailsTest.php tests/Feature/GuestMenuDisplayTest.php tests/Feature/WaiterReviewFunctionalTest.php` | 26 passed; 737 assertions; 1.757 s |
| `php artisan test --compact` | 686 tests; 677 passed; 9 skipped; 20,469 assertions; 60.143 s |
| `php artisan test --parallel --compact` | same counts; 16.929 s |
| `php -d 'zend_extension=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-85-arm64.so' -d xdebug.mode=coverage ./vendor/bin/pest --compact --coverage` | same counts; 90.4% total; 213.619 s |
| isolated `php artisan migrate:fresh --force --no-interaction` | 66 migrations; 0.52 s |
| isolated `php artisan db:seed --force --no-interaction` | passed; 0.22 s |
| two isolated `DemoRestaurantSeeder` runs | passed; 3.75 s then 6.83 s |
| `php artisan translations:scan --json` | 412 files; 1,495 semantic used; 0 missing/legacy/phrase keys |
| `php artisan translations:audit` | 6,078 semantic entries; 0 critical issues |
| `composer validate --strict` / `composer audit --locked` | valid / zero advisories |
| `npm audit --audit-level=low` / `npm run build` | zero advisories / passed |
| config, route and view cache builds | passed; 64 routes; followed by `optimize:clear` |

The nine skips are intentional feature gates for passkey/2FA behavior because `config('fortify.features')` currently enables only registration and password reset. They do not hide an enabled workflow failure.

## Automated architecture boundaries

`ProjectCleanupConsistencyTest` and related repository tests prohibit route SFC/Volt, Blade PHP blocks, Blade model/Action/Service/Illuminate/facade/container/auth/config/session access, application service locators, hardcoded Livewire page titles/action errors, debug stubs, forbidden runtime packages and legacy translation keys. XSS, token, route, tenant, file, money, cache and query tests enforce the corresponding requirement families. Invitation tests also prove bearer tokens never appear in rendered Livewire snapshots.

Browser verification uses the disposable isolated Chrome context `restaurant-menu-modernization-20260822`; no personal profile, cookies or storage are used. The final evidence covers public/authenticated landmarks, registration/logout/login/account deletion, password confirmation, EN→RU locale persistence, localized password/modal controls, keyboard focus, representative viewport overflow, Lighthouse and console/network inspection.

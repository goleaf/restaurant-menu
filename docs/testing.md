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

## Pre-demo historical baseline (2026-08-22)

The table below predates the demo-login slice. It remains historical evidence only; current targeted demo evidence follows separately, while the stable-tree full-suite, cache and browser refresh remains pending Task 8.

| Command | Result |
|---|---|
| `vendor/bin/pint --format agent` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` | Larastan level 8, 0 errors |
| PHP syntax loop over first-party PHP/Blade entry files | 664 files, 0 errors |
| `php artisan test --compact tests/Feature/DesignSystemTest.php tests/Feature/DemoRestaurantSeederTest.php tests/Feature/ServicePointCrudTest.php tests/Feature/AreaNodeCrudTest.php` | 34 passed; 794 assertions |
| `php artisan test --compact` | pre-demo baseline: 693 tests; 684 passed; 9 skipped; 20,593 assertions; 61.058 s |
| `php artisan test --compact --parallel` | pre-demo baseline: same counts; 17.406 s |
| `php artisan test --compact --coverage --min=90` and Herd coverage proxy | blocked: no Xdebug/PCOV driver loaded; last pre-UI verified result was 90.4% and is historical only |
| isolated `php artisan migrate:fresh --seed --force --no-interaction` | all 66 migrations and default seed passed |
| two isolated `DemoRestaurantSeeder` runs | passed; 3.61 s then 6.67 s |
| `php artisan translations:scan --json` | 413 files; 1,495 semantic used; 0 missing/legacy/phrase keys |
| `php artisan translations:audit` | 6,117 semantic entries; 0 critical issues |
| `composer validate --strict` / `composer audit --locked` | valid / zero advisories |
| `npm audit --audit-level=low` / `npm run build` | zero advisories / passed |
| config, route and view cache builds | pre-demo baseline passed with 64 routes and was followed by `optimize:clear`; current read-only route inventory is 66, while cache/HTTP smoke refresh remains pending Task 8 |

The gated skips are intentional passkey/2FA feature boundaries because `config('fortify.features')` currently enables only password reset. Public registration is intentionally disabled and covered by negative route tests; invited account creation is covered by invitation acceptance tests.

## Demo-login targeted evidence

The current demo-login slice has separate targeted evidence and does not replace the historical full-suite results above:

| Check | Observed result |
|---|---|
| `php artisan test --compact tests/Feature/DemoLoginTest.php tests/Feature/RouteProtectionAuditTest.php` | 77 tests passed; 358 assertions |
| demo page query budget | exactly 2 Eloquent queries with two catalog users (one matching and one role-mismatched); all 12 roles remain in canonical order |
| disabled and production boundary | 21 repeated GET probes remain 404 in each state; guarded requests do not consume the demo throttle |
| CSRF and throttle regressions | demo POST without a token and logout POST without a token return 419; request 21 after 20 successful demo GETs returns 429 |
| `php artisan translations:scan --json` | 421 files; 1,505 semantic keys used; 0 missing, legacy or phrase keys |
| `php artisan translations:audit` | 6,168 semantic entries; 0 critical issues |

Final browser acceptance is still pending the final Task 8 gate. It must exercise every available role through selection, login and destination; EN/LT/RU content; keyboard operation and visible focus; 320 px mobile layout without horizontal overflow; and browser console/network inspection in disposable isolated profiles. These browser checks are acceptance criteria, not yet observed evidence for this slice.

## Automated architecture boundaries

`ProjectCleanupConsistencyTest` and related repository tests prohibit route SFC/Volt, Blade PHP blocks, Blade model/Action/Service/Illuminate/facade/container/auth/config/session access, application service locators, hardcoded Livewire page titles/action errors, debug stubs, forbidden runtime packages and legacy translation keys. XSS, token, route, tenant, file, money, cache and query tests enforce the corresponding requirement families. Invitation tests also prove bearer tokens never appear in rendered Livewire snapshots.

Browser verification used disposable isolated contexts `restaurant-menu-ui-ux-20260822` and `restaurant-menu-ui-auth-20260822`; no personal profile, cookies or storage were used. Final UI evidence covers public/login/restaurant/waiter/service-point landmarks and hierarchy, skip-link keyboard focus, native disclosure, modal focus/restoration, light/dark contrast, 360–1920 CSS px samples, touch targets, Lighthouse, performance and console inspection.

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

The table below predates the demo-login slice and remains historical evidence only. The current stable-tree evidence follows separately.

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
| config, route and view cache builds | pre-demo baseline passed with 64 routes and was followed by `optimize:clear` |

The gated skips are intentional passkey/2FA feature boundaries because `config('fortify.features')` currently enables only password reset. Public registration is intentionally disabled and covered by negative route tests; invited account creation is covered by invitation acceptance tests.

## Current stable-tree and demo-login evidence (2026-08-23)

All commands below were observed again on the final feature tree after the demo-login and shared favicon fixes:

| Check | Observed result |
|---|---|
| `composer validate --strict` / `composer audit --locked --no-interaction` | valid package; zero advisories |
| `composer prohibits php 8.6 --locked` | expected exit 1: the project constraint and three locked dependencies explicitly prohibit PHP 8.6 |
| `vendor/bin/pint --parallel --test` / `vendor/bin/phpstan analyse --memory-limit=1G` | passed / Larastan level 8, 0 errors |
| `php artisan test --compact tests/Unit/DemoAccountCatalogTest.php tests/Feature/DemoLoginTest.php tests/Feature/DemoRestaurantSeederTest.php tests/Feature/RouteProtectionAuditTest.php tests/Feature/DesignSystemTest.php` | 94 tests passed; 1,019 assertions |
| `php artisan test --compact` | 737 tests; 728 passed; 9 skipped; 21,006 assertions; 62.483 s |
| `php artisan test --compact --parallel` | same counts and assertions; 17.822 s |
| demo page query budget | exactly 2 Eloquent queries with two catalog users (one matching and one role-mismatched); all 12 roles remain in canonical order |
| disabled and production boundary | 21 repeated GET probes remain 404 in each state; guarded requests do not consume the demo throttle |
| CSRF and throttle regressions | demo POST without a token and logout POST without a token return 419; request 21 after 20 successful demo GETs returns 429 |
| `php artisan translations:scan --json` | 421 files; 1,505 semantic keys used; 0 missing, legacy or phrase keys |
| `php artisan translations:audit` | 6,168 semantic entries; 0 critical issues |
| `npm audit --audit-level=low` / `npm run build` | zero vulnerabilities; Vite 8.2.2 build passes with CSS 297.20 kB / 39.04 kB gzip and application JS 0.00 kB |
| config, route and view cache builds | pass; 66 routes total; exactly two demo routes with guard → CSRF → throttle order |
| isolated SQLite acceptance | all 66 migrations plus default seed pass; consecutive demo seeds take 3.582 s and 6.660 s and leave 12 catalogue users; validated temporary file removed |
| real Herd guard probes | local disabled GET/POST return 404; production with `DEMO_LOGIN_ENABLED=true` also returns 404; final environment restored to local/false |

Chrome DevTools used a distinct disposable isolated context for every role. All 12 visible buttons submitted to `/dashboard`, the account control named the expected demo user, and authenticated navigation back to `/demo-login` remained on `/dashboard`. A bounded local fixture check removed only the fictitious Cook user; Chrome exposed the unavailable status, hint and disabled control, guest dashboard access still redirected to login, and `DemoRestaurantSeeder` immediately restored all 12 identities. The automated action/HTTP tests provide the matching direct-POST rejection and session-ID regeneration assertions without exposing cookie or token values.

Fresh EN/LT/RU contexts verified translated title, warning, availability state and role button text with no raw placeholder. Chrome captured 320×640, 360×640, 768×1024 and 1440×900 screenshots in its disposable tool temp; each viewport had `scrollWidth == clientWidth`, 12 usable actions and 44 px minimum control height. Keyboard order was skip link → logo → first role action, focus remained visible, and Enter completed login. Light and dark Chromium rendering passed. The available headless interface did not change zoom for its 200% keyboard shortcut and exposes no reduced-motion or forced-colors emulator; the corresponding CSS media queries and focused design tests pass, but non-headless 200% zoom, physical assistive technology/device and non-Chromium checks remain environmental limitations.

Chrome and Playwright both completed real Herd navigation and accessibility snapshots with 0 console warnings/errors and no failed assets; the SVG favicon and Apple touch icon returned 200 and no legacy ICO request occurred. Mobile and desktop Lighthouse scored accessibility 100 and agentic browsing 100. Best practices 78 and SEO 60 reflect only the local HTTP/HTTPS-redirect checks and the intentional `noindex` demo response. The Chrome performance trace recorded LCP 100 ms, TTFB 29 ms and CLS 0.00.

## Automated architecture boundaries

`ProjectCleanupConsistencyTest` and related repository tests prohibit route SFC/Volt, Blade PHP blocks, Blade model/Action/Service/Illuminate/facade/container/auth/config/session access, application service locators, hardcoded Livewire page titles/action errors, debug stubs, forbidden runtime packages and legacy translation keys. XSS, token, route, tenant, file, money, cache and query tests enforce the corresponding requirement families. Invitation tests also prove bearer tokens never appear in rendered Livewire snapshots.

Browser verification used disposable isolated contexts `restaurant-menu-ui-ux-20260822` and `restaurant-menu-ui-auth-20260822`; no personal profile, cookies or storage were used. Final UI evidence covers public/login/restaurant/waiter/service-point landmarks and hierarchy, skip-link keyboard focus, native disclosure, modal focus/restoration, light/dark contrast, 360–1920 CSS px samples, touch targets, Lighthouse, performance and console inspection.

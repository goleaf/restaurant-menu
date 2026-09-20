<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

## Prompt 5 current-source runtime verification — 2026-09-20

Supported CLI checks use actual PHP8.5.10 and Composer2.10.3, with strict manifest, installed/locked platform checks and Composer audit passing. Node24.21.0 and existing owned npm12.0.2 pass the declared engines. Installed Laravel13.31.0, Livewire4.4.1, FluxFree2.17.0 and local FluxPro0.1.1 remain unchanged; Pro's upstream version is unknown. Official Packagist metadata currently offers13.32.0/4.4.5/2.20.0 in those respective major lines; this does not prove compatibility with the local Pro adaptation or authorize another stack upgrade. Current official npm versions remain Tailwind4.3.3/Vite8.3.0/sass-embedded1.104.1. No lock or dependency is changed.

The actual installed PHP8.6.0beta2 passes all1367 first-party non-Blade PHP syntax checks. Both real Composer platform gates fail; application/browser/coverage acceptance on8.6 is unperformed, with no requirement bypass. The historical owned Beta3 executable is absent. Supported production remains `>=8.5.0 <8.6.0`.

A fresh runtime-only request to the configured Herd85 FastCGI socket reports8.5.8/fpm-fcgi/500M; its owned probe was removed without Laravel bootstrap or database access. Browser functional tests use their own isolated responder, recorded in the final browser evidence. Boost returns stale `https://ruflo.test` (TLS name mismatch and HTTP Herd404), while configured `https://restaurant-menu.test/build/manifest.json` passes both isolated Chrome DevTools and Playwright MCP navigation/read smoke tests with11 entries on Chrome153.0.0.0. Only a static-page favicon404 is observed. No runtime, site mapping, application URL or working data was changed.

## Prompt 6 current runtime verification — 2026-09-18

Fresh stable PHP8.5.10 / Composer2.10.3 capability, strict manifest and installed/locked platform checks pass. Canonical coverage uses Xdebug3.5.0 with explicit4GiB coordinator and512MiB isolated workers. Node24.21.0 and the preserved owned npm12.0.2 prefix are selected; no global/Herd selection or dependency lock changes. Complete browser HTTP runtime evidence is recorded separately in PROGRESS.md.

The actually available experimental binary is PHP8.6.0beta2. Capabilities/manifest pass, but installed and lock Composer platform validation fail on the real dependency requirements, including Nette Schema1.3.6; the Oniguruma deprecation is retained. The final immutable source passes1,367/1,367 non-Blade PHP syntax checks on Beta2. This is not application/coverage/browser compatibility. The [official prerelease page](https://www.php.net/pre-release-builds.php), rechecked18September, lists Beta3 as testing-only; no production promotion, ignored requirement or claim that Beta3 ran here is made.

## Prompt 5 current runtime verification — 2026-09-18

Fresh owned preflight confirms stable PHP8.5.10 CLI / Composer2.10.3: capabilities, strict manifest, installed and locked platform requirements pass. The actually available experimental executable is PHP8.6.0beta2; capabilities/manifest pass, both real platform checks fail on Nette Schema1.3.6 (PHP8.1–8.5), and Composer reports the Oniguruma deprecation. No dependency constraint or production/Herd selection is changed. The [official PHP site](https://www.php.net/) checked18September lists Beta3 as testing-only, with RC1 planned24September; that does not make Beta3 the installed binary.

Node24.21.0 is retained. Shell npm11.19.0 is not the accepted engine; builds explicitly invoke the preserved owned npm12.0.2 prefix from Prompt4. Installed application/package locks remain unchanged. The final production build passes all entry, category and page budgets.

The final immutable candidate `4209f986ec7a8ca0d4c3af7a027939a19b5406e11d987b2fd050e8a66a52752a` passes1,359/1,359 first-party PHP syntax checks on the actual8.6.0beta2 binary. This is syntax evidence only. Backend application/coverage checks use8.5.10/Xdebug3.5.0. The complete100-case browser acceptance responder reports8.5.8/cli; a separate runtime-only request to the configured Herd85 socket reports8.5.8/fpm-fcgi,500M. The temporary probe is removed without application bootstrap or database access. Complete acceptance is recorded separately in PROGRESS.md; physical hardware and native OS zoom have not been tested.

# Current version baseline

Current execution stage is the September20 Prompt 5 convergence on existing `main`, starting at clean `ad1cac6`. Earlier Prompt4/5 delivery and the separate unfinished September18 Prompt6 acceptance are preserved. Dependencies and locks remain unchanged: Laravel 13.31.0, Livewire 4.4.1, Flux Free 2.17.0 and local Flux Pro 0.1.1 (unknown upstream version). Current acceptance is owned by PROGRESS.md; historical accepted snapshots do not certify this refresh.

## Prompt 4 continuation runtime evidence — 2026-09-18

The selected verification CLI is PHP **8.5.10** with Composer **2.10.3**; the browser runner actually uses Herd CLI PHP **8.5.8**. A separate runtime-only probe of the configured Herd FPM socket reports **8.5.8 / fpm-fcgi**, memory_limit 500M. This probe did not bootstrap the application or touch its database. Backend tests use an explicit 512M limit and isolated data/cache/storage. Node is **24.21.0** and npm **12.0.2** is selected from an owned prefix, without changing global tools.

The currently available experimental binary is **8.6.0beta2**, not the historical temporary Beta3 executable, which is no longer present. PHP.net still identifies Beta3 as a testing release, with RC1 planned for 24 September. Both real installed/locked Composer platform checks reject the available 8.6 runtime; no application/browser/coverage pass on 8.6 or production promotion is claimed. Requirements are not ignored or relaxed.

A fresh isolated Composer installation with networking, scripts and plugins disabled extracts 178 cached archives and installs the normal local Pro path mirror: all **179** package names, versions and source/dist references match the unchanged lock, with no extra packages or downloads. Strict PSR/ambiguity autoload, strict validation and installed/locked stable platform checks pass. The separate official Packagist audit reports no advisories, abandoned packages or filters. Evidence: `/tmp/restaurant-p4-offline-BGF5BD/report.json`; runtime logs and the FPM probe are in `restaurant-p4-speckit-uhgxj6lc`. This is dependency/runtime evidence, separate from application acceptance.

## Preserved Prompt 4 runtime recheck — 2026-09-17

Fresh `restaurant-p4-refresh-b12nr5t1/runtime-preflight-RmLoqm` evidence confirms CLI PHP 8.5.10 and 8.6.0beta3. Stable manifest, installed and lock platform checks pass. Experimental capabilities/manifest checks pass, but both real platform checks fail. Input hashes remain unchanged. No PHP 8.6 application/browser pass or production promotion is inferred. Browser HTTP runtime and final aggregate results are recorded separately in PROGRESS.md.

## Preserved Prompt 3 runtime recheck — 2026-09-17

Fresh isolated probes confirm stable PHP 8.5.10 and experimental PHP 8.6.0beta3 with Composer 2.10.3. Both manifest validations pass; only 8.5 passes the real installed and lock platform requirements. PHP 8.6 remains rejected by the root range, Nette Schema 1.3.6, Nette Utils 4.1.5, Sabberworm PHP CSS Parser 9.4.0 and ParaTest 7.20.0. Required extensions are present. Composer's MB_ONIGURUMA_VERSION deprecation is recorded, not hidden. No application/browser execution on 8.6 is claimed and neither requirement bypass nor production promotion is used. The [official PHP archive](https://www.php.net/archive/2026.php), checked 17 September, still identifies Beta 3 as a test release unsuitable for production.

Evidence: `restaurant-p3-refresh-s_upxwy8/runtime-preflight-YGLHpO/summary.json` and command logs. Manifest, lock and installed metadata hashes remain unchanged.

## Current Prompt 1 refresh — 2026-09-17

This section supersedes earlier dated runtime observations below. The current checkout starts at `98cc5af` with 352 pre-existing changed paths; earlier prompt results are historical evidence for their recorded snapshots, not acceptance of this combined worktree.

Production support remains PHP `>=8.5.0 <8.6.0`, stable Composer dependencies, SQLite and the existing shared-hosting deployment. The selected verification CLI is actual PHP **8.5.10**, the latest stable 8.5 release published on 27 August according to the [official PHP release metadata](https://www.php.net/releases/index.php?json&version=8.5), checked 17 September. Herd CLI separately reports 8.5.8. An owned CLI-only **8.6.0beta3** is built from the official prerelease archive; neither Herd nor production is reconfigured. Laravel 13's [support policy](https://laravel.com/framework/docs/13.x/releases#support-policy) still lists PHP 8.3–8.5. Native/syntax evidence on Beta 3 is distinct from application, HTTP, coverage and performance acceptance, which remain blocked by real Composer requirements.

Declared constraints, both locks and installed metadata were inspected separately. All 179 installed PHP package versions and dist references match the Composer lock. Fresh official publisher metadata from `repo.packagist.org/p2/{vendor}/{package}.json`, checked 17 September at 13:20 UTC, gives:

| Technology | Installed / lock and selected target | Published stable | Reason for difference |
| --- | --- | --- | --- |
| Laravel | 13.31.0 | 13.32.0 | No matching permitted local archive; published dist is GitHub-only |
| Fortify | 1.39.0 | 1.39.0 | Current |
| Livewire | 4.4.1 | 4.4.5 | Missing permitted archive |
| Flux Free | 2.17.0 | 2.20.0 | Missing archive and exact donor contract of the local Pro adaptation |
| Local Flux Pro | 0.1.1, upstream unknown | Unknown | Preserve proprietary source inventory, patches and locally assigned version |
| Laravel MCP | 1.0.0 | 1.0.0 | Current; no permission changes |
| Boost | 2.9.0 | 2.9.1, published 17 September | Missing permitted archive |
| Carbon | 3.13.2 | 3.14.0 | Missing permitted archive |
| Larastan / Pint | 3.11.0 / 1.31.1 | 3.12.1 / 1.32.1 | Missing permitted archives |
| Pest | 4.7.8 | 5.2.1; latest 4.x is 4.7.8 | Major plugin/PHPUnit migration; no complete permitted plugin archive set |
| Pest Laravel / Browser | 4.1.0 / 4.3.1 | 5.0.1 / 5.0.1 | Same major migration boundary |
| PHPUnit | 12.5.33 | 13.3.4; latest 12.x is 12.5.35 | Pest 4.7.8 excludes versions above 12.5.33 |
| ParaTest | 7.20.0 | 7.24.1 | Newer releases require PHPUnit 13 |
| Nette Schema / Utils | 1.3.6 / 4.1.5 | Same | Latest stable PHP constraints still exclude 8.6 |
| Sabberworm CSS parser | 9.4.0 | 9.4.0 | Latest stable PHP constraint still excludes 8.6 |

The existing PHP lock is retained. A cached newer ParaTest alone cannot form a compatible Pest 4 graph. No GitHub requests, platform emulation, ignored requirements, beta dependency graph or vendor patch is used. Cache CRC/reference/hash checks prove local integrity, not a publisher signature. Exact source/cache evidence is linked from PROGRESS.md.

Frontend metadata was rechecked against the official npm registry and [Node release index](https://nodejs.org/dist/index.json) on 17 September. Actual Node **24.21.0 LTS** is selected over Current 26.9.0. The shell still exposes npm 11.19.0; verification explicitly selects integrity-verified **npm 12.0.2** from an owned prefix, satisfying the existing engines without a global installation.

| Technology | Installed / lock / selected target | Latest stable on check date |
| --- | --- | --- |
| Vite / Laravel Vite plugin | 8.3.0 / 3.2.0 | Same |
| Tailwind / Tailwind Vite plugin | 4.3.3 / 4.3.3 | Same |
| Sass Embedded | 1.104.1 | Same |
| Stylelint / SCSS plugin / postcss-scss | 17.15.0 / 7.3.0 / 4.0.9 | Same |
| ESLint / @eslint/js / promise plugin / globals | 10.10.0 / 10.0.1 / 7.3.0 / 17.12.0 | Same |
| Playwright | 1.63.0 | Same; WebKit revision 2359 available |
| SimpleWebAuthn browser / Noto Sans / concurrently | 14.0.0 / 5.3.0 / 10.0.5 | Same |
| Vite's nested Rolldown | **1.2.8 → 1.2.9** | 1.2.9 |
| Local Pro's root Rolldown | **1.2.5**, intentionally retained | 1.2.9; exact reproducible Pro compiler is a separate contract |

The npm resolver updates only 17 nested Vite lock entries: Rolldown, its 15 platform bindings and `@oxc-project/types` 0.149.0 → 0.150.0. Both compiler versions produce byte-identical outputs for the same source. Clean registry-only installation and the shared offline installation each install 255 packages with lifecycle scripts disabled. A subsequent offline lock-only resolver run restores the repository's top-level `restaurant-menu` name after the temporary candidate directory label; package entries are unchanged. The product's current architecture, coverage and asset-budget failures remain failures; compiler equivalence does not waive those gates. The original package manifests, root Pro compiler and application dependency contracts stay unchanged.

## Prompt 6 runtime observation — 2026-09-17

Dish checks select the actual PHP 8.5.10 CLI and isolated browser HTTP responder. A fresh runtime-only request to the configured Herd85 socket returns PHP 8.5.8 / fpm-fcgi with memory_limit 500M; its temporary script was deleted without bootstrapping the application or database. Herd/production selection and dependency locks are unchanged.

The installed experimental binary is PHP 8.6.0beta2. Both complete and non-development `composer check-platform-reqs --lock` fail the Nette Schema PHP 8.1–8.5 requirement. Composer also emits the PHP 8.6 Oniguruma deprecation. PHP.net lists Beta 3 on 10 September and RC1 planned for 24 September: this does not make Beta 3 installed here. The final candidate passes 1,223 first-party non-Blade PHP syntax checks, plus the final two changed browser files, on this actual Beta 2 binary. No experimental application acceptance or production-runtime promotion is claimed.

Canonical supported-runtime coverage passes all 3,989 backend cases / 76,310 assertions at 93.5% application line coverage, with the 90% threshold unchanged and no failures, errors or skips. Complete browser inventories also pass: candidate 61/61 cases / 4,535 assertions and shared 75/75 / 5,595, with the actual PHP 8.5.10 HTTP runtime case included. The distinct Herd FPM version remains 8.5.8.

## Prompt 7 runtime observation — 2026-09-17

Availability application tests use the actual isolated PHP **8.5.10** CLI and HTTP responder. The independently probed configured Herd FastCGI process reports **8.5.8 / fpm-fcgi**; the runtime-only probe was removed and did not bootstrap the application or open its database. No site PHP selection changed.

Supported-runtime acceptance passes candidate 3,884 backend / 51 browser cases and 93.8% PHP coverage; shared integration passes 4,032 backend / 65 browser cases. Final JavaScript coverage passes candidate 211/211 and shared 214/214 cases at 100% lines.

The available experimental binary reports **8.6.0beta2**, not Beta 3. PHP.net lists Beta 3 (10 September) and RC1 planned for 24 September; release availability is distinct from an installed runtime. Actual Composer platform validation still rejects Nette Schema 1.3.6's PHP 8.1–8.5 requirement. The final shared snapshot passed 1,229 first-party syntax checks on that actual Beta 2 binary; no PHP 8.6 application/browser/coverage acceptance or production promotion is claimed. Laravel 13.31.0, Livewire 4.4.1, Flux Free 2.17.0, local Pro 0.1.1 with unknown upstream version and the existing lock files remain unchanged.

## Team prompt 8 runtime verification — 2026-09-17

The installed dependency graph is unchanged: Laravel13.31.0, Livewire4.4.1, Flux Free2.17.0 and local Pro0.1.1 (unknown upstream release), Vite8.3.0. Stable test selection is explicit Homebrew PHP8.5.10; focused tests also run on Herd CLI8.5.8. Node24.21.0 and standalone npm12.0.2 satisfy the existing engines.

Current Herd configuration selects the85 socket for restaurant-menu.test. A direct FastCGI request to that live socket, executing only an owned temporary runtime probe, returns PHP8.5.8, SAPI fpm-fcgi and php85-fpm. The probe is removed afterward; no application/database bootstrap, persistent route or PHP selection change occurs. This identifies the configured FPM process separately from CLI and from the isolated browser responder.

Available experimental PHP is8.6.0beta2. The fresh isolated verify-migration preflight aRnEHK fails Composer platform-lock on Nette Schema1.3.6's PHP8.1–8.5 requirement. Historical Beta3 artifact paths are not available in this run and are not reused as current proof. Production constraints remain >=8.5.0 <8.6.0. No ignored platform requirements, dependency patch or8.6 application acceptance is claimed.

Final stable acceptance records candidate 3,755 backend tests, 46 browser cases and 94.1% PHP coverage; shared integration 3,903 backend / 60 browser cases. The browser responder is PHP 8.5.10, distinct from the measured Herd FPM 8.5.8. All final changed-browser PHP files also pass the actual 8.6.0beta2 syntax follow-up; the application platform blocker remains unchanged.

## Controller migration Prompt 2 refresh — 2026-09-17 (acceptance incomplete)

Current Prompt 2 verification is incomplete: backend 4,341/4,387 pass; browser 64/78 pass with seven failures/seven timeouts; Xdebug coverage exits 124 after one hour without a current percentage. Full results and post-freeze corrections are in PROGRESS.md.

Baseline `8191bb5` already contains the previous controller migration. This refresh changes no dependency manifest, lock or installed graph. The canonical verifier confirms installed dependency integrity and actual stable PHP8.5.10 platform requirements; Composer/npm audits report no advisories. Laravel13.31.0, Livewire4.4.1, Fortify1.39.0, Flux Free2.17.0/local Pro0.1.1 (unknown upstream), Node24.21.0 and explicit npm12.0.2 remain selected. The working Herd/production runtime is not changed.

Current combined-source acceptance is incomplete: Larastan has five floor/QR errors, architecture24/25, JavaScript239/240 at99.83% included line coverage, five asset budgets and the translation audit fail. Successful historical figures below do not certify this source. PHP90% and JS100% thresholds remain unchanged. Current full backend, coverage and browser execution evidence is recorded in PROGRESS.md.

Actual owned PHP8.6.0beta3 at `/private/tmp/restaurant-platform-p1-20260917-vi_yfei6/runtime/bin/php` again fails real installed and locked Composer requirements (ParaTest and Nette Schema respectively). The root range stays `>=8.5.0 <8.6.0`; no bypass is used. Twenty changed non-Blade PHP files pass actual Beta3 syntax checks, with the final browser selector follow-up checked separately. PHP8.6 application, HTTP, coverage and performance remain unperformed. Boost returns `Invalid JSON output`; installed source and official documentation remain the API evidence.

## Workspace prompt 3 baseline — 2026-09-16

Prompt 3 starts at `9b6a71a1516737129a59c2a159d335e6b5d2a2d0`; installed dependencies and both lock files are unchanged. Stable implementation checks use explicit PHP 8.5.10, Node 24.21.0, npm 12.0.2 and the matching isolated Playwright/WebKit binaries. The current run's evidence is recorded in PROGRESS.md; the older platform counts below do not certify the workspace changes.

PHP 8.6.0beta3 remains an available isolated native runtime, but the actual Composer requirements still reject application execution. This stage neither widens the root range nor ignores requirements. Current changed-file syntax validation passes 52 PHP classes/routes/tests (Blade excluded) on the actual Beta3 binary; Composer check-platform-reqs --lock still exits 1. Syntax is not application/browser/coverage acceptance. Prompt 2 controller/auth migration also remains separate unfinished work.

## Current continuation at ce187c8 — checked 2026-09-16

This continuation starts from a clean `main` after the earlier dependency updates were committed. The installed versions in the tables below are reverified against both lock files (179 Composer /255 npm package locations); the current continuation makes no further dependency or lock changes. Packagist/npm metadata still supports the listed latest/selected differences and GitHub-only missing-archive limitations. PHP support remains `>=8.5.0 <8.6.0`, stable minimum stability and no platform emulation.

The previous temporary prefix no longer exists. Current stable verification selects `/opt/homebrew/opt/php/bin/php` **8.5.10**. The running Node is the pre-existing Herd NVM **24.21.0 LTS**; its default npm **11.19.0** violates engines. A hash-verified npm **12.0.2** package is prepared under `/private/tmp/restaurant-platform-p1-root-100b_i_e/npm/`, selected explicitly by the coordinator. Matching Playwright1.63 WebKit2359 is installed in that prefix and actual launch/render smoke passes. One FFmpeg CDN TLS failure fell back to Microsoft's official CDN without relaxing TLS checks. Xdebug3.5.0 from the existing Herd application loads successfully only in the stable coverage child.

The existing `restaurant-menu.test` Herd selection and `herd php` already resolve to **8.6.0beta2**; this differs from the earlier recorded selection and was not changed. The supported test environment is the explicit8.5.10 snapshot, not a claim about the working site's PHP. Local Boost now uses `php85` **8.5.8** and a real stdio initialize/get-absolute-url request succeeds.

Owned experimental **8.6.0beta3** was rebuilt from the official archive with the SHA-256 below at `/private/tmp/restaurant-platform-prompt1-php86-e4hg0fvg/runtime/bin/php`. Native extension/codec probes pass47 checks on8.5.10 and Beta3, and Beta3 syntax checks pass1121 first-party PHP files. Real Composer platform checks fail on Beta3: root, Nette Schema/Utils, Sabberworm and ParaTest constraints remain. The tool also emits an Oniguruma-constant deprecation from Composer2.10.3; it is recorded, not suppressed. No application, browser, coverage or performance acceptance on8.6 is claimed.

Beta3 UPGRADING adds form-feed to default trim(). The monetary parser now supplies the PHP8.5 whitespace set explicitly, preserving the established rejection of form-feed/Unicode whitespace. A native26-check probe reproduced8 failures before the fix on Beta3 and passes all26 afterward on both versions. The stable Feature regression passes18 tests/102 assertions. No8.6-only syntax, vendor patch, ignored platform requirement or production requirement expansion is introduced.

Clean lock installation is newly verified in `/private/tmp/restaurant-p1-clean-YjeTbU`: Composer179 packages from178 CRC/path-checked cached ZIPs plus the local Pro package, no scripts/plugins and OS-enforced network denial; npm12 installs255 packages with scripts disabled. Exact dependency metadata matches the working installation. Production build, budgets, generated styles, Pro provenance,95 migrations up/reset/reapply, double seed and cache builds pass. Two existing PSR-4 notices concern test-only stream-filter helpers; no application warning is hidden. Final stable acceptance is recorded in PROGRESS.md: 22 aggregate steps, 3,550 backend tests, PHP coverage 94.0%, JS 196 with 100% lines, and the separately integrated browser follow-up 42 / 3,063. All run on the selected actual runtimes above. PHP86 application compatibility remains blocked.

## Earlier execution version selection — checked 2026-09-16; historical runtime locations

The package version tables below remain applicable and were rechecked in the continuation above. Runtime paths, site selection and initial worktree observations in this earlier execution are historical; the current continuation section supersedes them.

Current execution began on local `main` at `225544923fde6a5a04c28ddcf706be340e8d00f0`, after the prompt's reference `73f783d`. The initial platform helper/tests and plan/progress changes were pre-existing and are not attributed to this execution. The shared index also changed concurrently; final delivery must preserve ownership.

Production support: stable PHP 8.5; selected test runtime Homebrew **8.5.10**, existing Herd site selection **8.5.8**. The global Herd CLI alias already pointed to **8.6.0beta2** before this stage; it was not changed and lacks JPEG/WebP in GD. Owned experimental **8.6.0beta3** was compiled from the official archive, SHA-256 `e8daf9546c4d4244dad961b5412734db57823c2e967df3fcfbb819a62d520ea6`; the standalone extension/codec probes pass. Its application/platform gate remains blocked. [PHP archive](https://www.php.net/archive/2026.php), [prerelease hashes](https://www.php.net/pre-release-builds.php), [stable release](https://www.php.net/releases/index.php?json&version=8.5.10), [Laravel support](https://laravel.com/framework/docs/13.x/releases) were checked on the date above. Laravel 13 officially lists PHP 8.3–8.5; the PHP 8.6 stable date of 19 November is a schedule, not production authorization.

Composer manifest/lock/installed baseline agreed (179 packages); the root remains `>=8.5.0 <8.6.0`, stable minimum stability and no `config.platform`. The latest stable Nette Schema 1.3.6, Nette Utils 4.1.5 and Sabberworm 9.4.0 still exclude PHP 8.6; installed ParaTest 7.20.0 and latest 7.24.1 do too. No ignored requirements, vendor patches or prerelease dependency graph are used.

The following Composer table distinguishes the initial installed/locked graph from selected installed targets. Metadata came directly from `https://repo.packagist.org/p2/{package}.json` on 2026-09-16. Newer packages whose only archive source is prohibited GitHub remain unavailable. Selected updates used matching existing cache ZIP revisions, CRC/path checks and recorded local SHA-256; upstream dist hashes are empty, so this is cache provenance, not publisher-signature verification. Installation had both Composer offline mode and an OS network-denial profile; lifecycle scripts/plugins were disabled. Full-suite acceptance is recorded separately in PROGRESS.md.

| Package | Baseline installed = lock | Latest stable | Selected installed target | Reason for target |
| --- | --- | --- | --- | --- |
| dompdf/dompdf | 3.1.6 | 3.1.6 | 3.1.6 | current stable |
| laravel/fortify | 1.38.0 | 1.39.0 | 1.39.0 | verified cached archive |
| laravel/framework | 13.26.1 | 13.32.0 | 13.31.0 | newest cached archive; 13.32 GitHub-only uncached |
| laravel/mcp | 1.0.0 | 1.0.0 | 1.0.0 | current stable; existing baseline work |
| laravel/tinker | 3.0.2 | 3.0.2 | 3.0.2 | current stable |
| livewire/flux | 2.17.0 | 2.20.0 | 2.17.0 | newer GitHub-only uncached; local Pro pins 2.17.0 |
| livewire/flux-pro | 0.1.1 local | upstream unknown | 0.1.1 local | keep provenance and proprietary contract |
| livewire/livewire | 4.4.1 | 4.4.5 | 4.4.1 | newer GitHub-only uncached |
| fakerphp/faker | 1.24.1 | 1.24.1 | 1.24.1 | current stable |
| larastan/larastan | 3.10.0 | 3.12.1 | 3.11.0 | newest cached archive; 3.12.1 uncached |
| laravel/boost | 2.9.0 | 2.9.0 | 2.9.0 | current stable; existing baseline work |
| laravel/pail | 1.2.7 | 1.2.7 | 1.2.7 | current stable |
| laravel/pao | 1.1.4 | 1.1.5 | 1.1.5 | verified cached archive |
| laravel/pint | 1.30.5 | 1.32.1 | 1.31.1 | newest cached archive; 1.32.1 uncached |
| mockery/mockery | 1.6.15 | 1.6.15 | 1.6.15 | current stable |
| nunomaduro/collision | 8.9.5 | 8.9.5 | 8.9.5 | current stable |
| pestphp/pest | 4.7.8 | 5.2.0 | 4.7.8 | latest 4.x; 5.x requires coordinated PHPUnit13/plugin5 upgrade, browser plugin5 archive unavailable |
| pestphp/pest-plugin-browser | 4.3.1 | 5.0.1 | 4.3.1 | current 4.x graph; plugin5 archive unavailable |
| pestphp/pest-plugin-laravel | 4.1.0 | 5.0.1 | 4.1.0 | current Pest4 graph |
| phpunit/phpunit | 12.5.33 | 13.3.4; 12.x 12.5.35 | 12.5.33 | latest compatible patch uncached; 13.x requires Pest5 |
| phpstan/phpstan | 2.2.9 | 2.2.14 | 2.2.13 | newest cached archive |
| brianium/paratest | 7.20.0 | 7.24.1 | 7.20.0 | cached latest requires PHPUnit13 and still excludes PHP8.6 |
| sabberworm/php-css-parser | 9.4.0 | 9.4.0 | 9.4.0 | current stable still excludes PHP8.6 |
| nette/schema | 1.3.6 | 1.3.6 | 1.3.6 | current stable still excludes PHP8.6 |
| nette/utils | 4.1.5 | 4.1.5 | 4.1.5 | current stable still excludes PHP8.6 |


Frontend registry metadata checked 2026-09-16 at `https://registry.npmjs.org/{package}/latest` and [Node releases](https://nodejs.org/dist/index.json). The table's installed column is the initial observation; targets are now installed unless a reason below says otherwise. Node **24.21.0 LTS** and npm **12.0.2** run from owned temporary prefixes; system installations are unchanged. Node Current 26.8.2 is not the chosen LTS line. Node archive SHA-256 `6239d4cf92d864487ec8cd3615038f7b67e7f58b77b21cd2f09ea9fbd68065fe` matches official SHASUMS256.txt. Engines are limited to tested Node 24.21+/npm 12.0.2+ major lines.

| Technology | Baseline manifest | Baseline lock | Baseline installed | Registry latest | Selected target |
| --- | --- | --- | --- | --- | --- |
| Node | >=22.12.0 | n/a | 24.19.0 | 26.8.2 Current; 24.21.0 LTS | 24.21.0 LTS, isolated binary prepared |
| npm | >=10 | n/a | 11.17.0 | 12.0.2 | 12.0.2 after toolchain tests; 11.19.0 bundled with prepared Node is available but is not latest |
| @simplewebauthn/browser | 13.3.0 | 13.3.0 | 13.3.0 | 14.0.0 | 14.0.0 after adapter and authentication regressions |
| @eslint/js | 10.0.1 | 10.0.1 | 9.39.5 | 10.0.1 | Repair installed mismatch using lock |
| @fontsource-variable/noto-sans | 5.3.0 | 5.3.0 | 5.3.0 | 5.3.0 | retain |
| @tailwindcss/vite | ^4.3.3 | 4.3.3 | 4.3.3 | 4.3.3 | retain |
| concurrently | ^10.0.5 | 10.0.5 | 10.0.5 | 10.0.5 | retain |
| eslint | 10.10.0 | 10.10.0 | 9.39.5 | 10.10.0 | Repair installed mismatch using lock |
| eslint-plugin-promise | 7.3.0 | 7.3.0 | 7.3.0 | 7.3.0 | retain; peer includes ESLint10 |
| globals | 17.12.0 | 17.12.0 | 17.12.0 | 17.12.0 | retain |
| laravel-vite-plugin | ^3.2.0 | 3.2.0 | 3.2.0 | 3.2.0 | retain; peer Vite^8 |
| playwright | 1.62.1 | 1.62.1 | 1.62.1 | 1.63.0 | 1.63.0 with matching isolated browser binaries |
| postcss-scss | 4.0.9 | 4.0.9 | 4.0.9 | 4.0.9 | retain |
| sass-embedded | 1.104.1 | 1.104.1 | 1.104.1 | 1.104.1 | retain |
| stylelint | 17.15.0 | 17.15.0 | 17.15.0 | 17.15.0 | retain |
| stylelint-scss | 7.3.0 | 7.3.0 | 7.3.0 | 7.3.0 | retain |
| tailwindcss | ^4.3.3 | 4.3.3 | 4.3.3 | 4.3.3 | retain |
| vite | ^8.2.2 | 8.2.2 | 8.2.2 | 8.3.0 | 8.3.0; preserve Pro minifier below |

ESLint 10.10.0 and @eslint/js 10.0.1 are now physically installed, repairing the old node_modules mismatch. Vite 8.3.0 requires Rolldown ~1.2.6 and currently resolves its own 1.2.8; **Rolldown 1.2.5 is now an explicit development dependency** because the maintained Pro generator requires those exact bytes. This is an intentional compatibility pin, not a claim that 1.2.5 is newest. Playwright 1.63.0 has an owned matching WebKit 26.6/revision 2359 browser installation from the official Microsoft CDN. Package installation alone was not treated as browser readiness.

[Flux's official blog](https://fluxui.dev/blog) advertises upstream Pro 2.20.0 on this date. Local Pro stays **0.1.1**, upstream version unknown; its 140-file/10-patch provenance, proprietary license and exact Free 2.17.0 pair remain unchanged. Livewire 4.4.5 and Free 2.20.0 archives are unavailable under the source restrictions. No account/credential inspection or license workaround was performed.

## Current component-system patch — 2026-09-16

Flux Free 2.17.0 / Livewire 4.4.1 remain installed. Local Pro is now 0.1.1, a project-local release with unknown upstream version: four template translation patches on the previously verified runtime. Composer updated only this local path record; proprietary license and original 139-file inventory remain unchanged. Runtime compatibility and final evidence are tracked in the current implementation ledger. Earlier installation checkpoints below are historical.

## Flux Pro preparation — 2026-09-15

Installed packages are Flux Free 2.17.0, local Flux Pro 0.1.0 and Livewire 4.4.1. Pro is an explicitly identified local adaptation, with unknown upstream version, installed offline from `packages/livewire/flux-pro/` through a physical Composer mirror. The original 139-file inventory remains recorded; two foundation patches correct local metadata/aliases and the provider namespace. All 178 previously locked package records remain unchanged. Composer validation, platform requirements, installation/integrity tests, 18-family server rendering and five asset endpoints pass. JavaScript and workflow compatibility remain under implementation; consult `IMPLEMENTATION_PLAN.md`.

## Product dependency changes — 2026-09-14

Existing PHP and JavaScript package majors and versions remain locked. The only new npm package is `@fontsource-variable/noto-sans` 5.3.0, used for local Latin/Latin-ext/Cyrillic fonts. All npm lock resolutions use registry.npmjs.org. Composer now declares EXIF, GD, PDO SQLite and SQLite3 as required platform extensions for the implemented media/recovery contracts.

The offline `composer update --lock` attempt could not resolve uncached metadata for existing locked versions and made no dependency change. The installed Composer Locker generated the manifest content hash and updated only the lock's platform requirements; package arrays are unchanged. Strict Composer validation and an offline install dry run both passed. Final platform/audit/install/build evidence is recorded in testing.md; no GitHub retrieval or dependency major migration is involved.

Reverified on 2026-09-14: PHP 8.5.8; Laravel 13.26.1; Livewire 4.4.1; Flux UI Free 2.17.0; Tailwind CSS and `@tailwindcss/vite` 4.3.3; Laravel Vite plugin 3.2.0; Vite 8.2.2; Fortify 1.38.0; Pest 4.7.8 / PHPUnit 12.5.33; Pint 1.30.5; Larastan 3.10.0; Boost 2.5.5.

Composer requires PHP `>=8.5.0 <8.6.0`, Laravel `^13.0` and Livewire `^4.4`. Package locks are authoritative; stable Pest 4 remains intentional even though Pest 5 is a newer incompatible major test-style migration.

The repository-wide repair restored the prior lock files. All 178 Composer versions match the installed graph. A clean disposable install succeeded for Composer and npm with lifecycle scripts disabled; the workspace npm tree was aligned with the lock using `npm ci --ignore-scripts`, followed by a successful Vite production build. No dependency constraint or major version was changed.

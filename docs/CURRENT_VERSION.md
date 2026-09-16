<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Current version baseline

## Platform selection — checked 2026-09-16; acceptance in progress

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

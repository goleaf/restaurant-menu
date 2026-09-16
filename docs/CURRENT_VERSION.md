<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Current version baseline

## Flux Pro preparation — 2026-09-15

Installed packages are Flux Free 2.17.0, local Flux Pro 0.1.0 and Livewire 4.4.1. Pro is an explicitly identified local adaptation, with unknown upstream version, installed offline from `packages/livewire/flux-pro/` through a physical Composer mirror. The original 139-file inventory remains recorded; two foundation patches correct local metadata/aliases and the provider namespace. All 178 previously locked package records remain unchanged. Composer validation, platform requirements, installation/integrity tests, 18-family server rendering and five asset endpoints pass. JavaScript and workflow compatibility remain under implementation; consult `IMPLEMENTATION_PLAN.md`.

## Product dependency changes — 2026-09-14

Existing PHP and JavaScript package majors and versions remain locked. The only new npm package is `@fontsource-variable/noto-sans` 5.3.0, used for local Latin/Latin-ext/Cyrillic fonts. All npm lock resolutions use registry.npmjs.org. Composer now declares EXIF, GD, PDO SQLite and SQLite3 as required platform extensions for the implemented media/recovery contracts.

The offline `composer update --lock` attempt could not resolve uncached metadata for existing locked versions and made no dependency change. The installed Composer Locker generated the manifest content hash and updated only the lock's platform requirements; package arrays are unchanged. Strict Composer validation and an offline install dry run both passed. Final platform/audit/install/build evidence is recorded in testing.md; no GitHub retrieval or dependency major migration is involved.

Reverified on 2026-09-14: PHP 8.5.8; Laravel 13.26.1; Livewire 4.4.1; Flux UI Free 2.17.0; Tailwind CSS and `@tailwindcss/vite` 4.3.3; Laravel Vite plugin 3.2.0; Vite 8.2.2; Fortify 1.38.0; Pest 4.7.8 / PHPUnit 12.5.33; Pint 1.30.5; Larastan 3.10.0; Boost 2.5.5.

Composer requires PHP `>=8.5.0 <8.6.0`, Laravel `^13.0` and Livewire `^4.4`. Package locks are authoritative; stable Pest 4 remains intentional even though Pest 5 is a newer incompatible major test-style migration.

The repository-wide repair restored the prior lock files. All 178 Composer versions match the installed graph. A clean disposable install succeeded for Composer and npm with lifecycle scripts disabled; the workspace npm tree was aligned with the lock using `npm ci --ignore-scripts`, followed by a successful Vite production build. No dependency constraint or major version was changed.

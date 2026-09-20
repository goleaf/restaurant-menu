<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Implementation Plan: Personal display formats

**Branch**: `main` preserved | **Date**: 2026-09-20 | **Spec**: [spec.md](spec.md)

## Summary
Add an independent format form to Profile; persist three allowlisted preferences. Extend shared date/money presentation with an explicit immutable DisplayPreferences value object and localized number formatting. Guest consumers explicitly use language defaults. Cached staff reports include a format fingerprint and capture preferences for deferred refresh.

## Technical Context
PHP8.5; installed Laravel13.31, Livewire4.4.1, Flux2.17/localPro0.1.1; SQLite; Pest4.7.8. No dependencies or runtime changes. Add nullable user columns (null means locale), no indexes: preferences are never query filters. No new routes, query services or repository layer. Existing authenticated user hydration supplies values with no per-format query. Supported deployment is Herd/shared hosting.

## Constitution Check
Canonical IDs and scope linked in spec; existing plan owns status. Class-based Livewire child + Form + focused Action; Eloquent writes only authenticated user; no business logic in Blade. Additive reversible migration and isolated SQLite tests preserve data. EN/LT/RU, previews, loading/offline/keyboard/reflow included. RED/GREEN, scoped Pint/PHPStan, translation audits and real isolated browser required; aggregate limitations recorded. Git branch/index and concurrent preparation edits preserved; no hooks/extensions exist and no GitHub operations. The shared feature selector remains untouched; scripts use explicit SPECIFY_FEATURE_DIRECTORY. Post-design check: pass, no exception required.

## Project Structure
- app/Support/{DisplayPreferences,LocalizedDateFormatter,LocalizedNumberFormatter,MoneyFormatter}.php
- app/Livewire/Settings/DisplayFormats.php and app/Livewire/Forms/DisplayFormatsForm.php
- app/Actions/Users/UpdateUserDisplayFormatsAction.php
- app/Models/User.php; database/migrations/*_add_display_formats_to_users_table.php
- resources/views/livewire/settings/{profile,display-formats}.blade.php
- Existing analytics/dashboard, report period/query and guest presentation call sites
- tests/Feature/Settings/DisplayFormatsTest.php and focused formatter/cache/guest regressions
- lang/{en,lt,ru}.json; existing canonical docs

## Implementation Strategy
Tests first, then schema/value object and form, then shared formatting, guest/cache boundaries and targeted bypasses. Do not relabel canonical input/CSV/API values. Verify independently from concurrent preparation work; do not claim repository-wide acceptance from scoped evidence.

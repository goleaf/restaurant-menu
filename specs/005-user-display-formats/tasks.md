<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tasks: Personal display formats

## Setup and foundations
- [x] T001 Inspect current settings, schema, formatters, caches and canonical contracts; record findings in specs/005-user-display-formats/research.md.
- [x] T002 Define scoped stories, data and UI contract in specs/005-user-display-formats/{spec,plan,data-model}.md and contracts/profile.md.
- [x] T003 Write failing persistence, transport, formatter and cache tests in tests/Feature/Settings/DisplayFormatsTest.php.

## US1 — Personal date/time (P1)
- [x] T004 [US1] Add nullable varchar20 date_format/time_format/number_format columns in database/migrations; implement allowlisted defaults in app/Support/DisplayPreferences.php and app/Models/User.php.
- [x] T005 [US1] Implement mixed-until-validation state and three-field-only save in app/Livewire/Forms/DisplayFormatsForm.php and app/Actions/Users/UpdateUserDisplayFormatsAction.php.
- [x] T006 [US1] Add independent accessible preview/save/reset form in app/Livewire/Settings/DisplayFormats.php and resources/views/livewire/settings/display-formats.blade.php; mount in existing profile.
- [x] T007 [US1] Extend app/Support/LocalizedDateFormatter.php and targeted report/menu/service-point date labels while preserving machine values.

## US2 — Number and money separators (P1)
- [x] T008 [US2] Extend app/Support/MoneyFormatter.php and add app/Support/LocalizedNumberFormatter.php; verify exact cents and selected separators.
- [x] T009 [US2] Isolate public guest formatting and captured preference-aware report cache/fallback keys in existing Actions and presenters.
- [x] T010 [US2] Add meaningful guest/cache isolation and numeric-presentation regressions to tests/Feature/Settings/DisplayFormatsTest.php.

## Polish and verification
- [x] T011 Add every used new label in lang/en.json, lang/lt.json and lang/ru.json; update existing canonical docs and implementation ledger.
- [ ] T012 Run scoped Pest/Pint/PHPStan, migration rollback/reapply in isolated SQLite, translation audits, asset build and isolated browser settings checks; record exact evidence in docs/PROGRESS.md.

Dependencies: T001–T003 precede implementation; T004–T006 establish settings, T007/T008 build formatters, T009 depends on both before browser acceptance. T010 validates cross-user/public boundaries. T011/T012 finish the bounded delivery. Independent read-only research may run alongside setup; implementation is owned by the primary agent in this shared dirty tree. No competing backlog, branch, feature-selector overwrite, automatic staging or commit.

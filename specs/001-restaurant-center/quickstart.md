<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Validation guide

Use tests/Support/platform-verification.mjs resolvePhpRuntime and createVerificationEnvironment with explicit supported PHP and Composer. It isolates SQLite memory, APP_KEY, storage, compiled views, caches, mail and sessions. Never run fixtures against the working DB.

First run RestaurantCenterCreationTest, RestaurantSetupMenuSafetyTest, RestaurantSetupSchemaCompatibilityTest. Then selection/access-state/interface regressions, existing center/preparation/workflow/concurrency suites and legacy wizard consumers. Browser runs use tests/browser.php disposable environment; capture actual PHP HTTP identity and inspect saved screenshots.

Acceptance then uses composer analyse, scoped Pint and frozen-source formatting, full backend discovery/execution, canonical test:coverage >=90%, browser suite, npm test:js:coverage, architecture, lint:js, lint:styles, styles:check, translation audit/scan, production build and unchanged budgets. Run actual experimental PHP platform checks without ignores; failed prerequisites block application tests honestly.

Record exact commands/logs/source hashes, matched fixture SQL/memory/HTML/Livewire/network and interaction counts in docs/PROGRESS.md and docs/performance.md. No deployment or remote verification.

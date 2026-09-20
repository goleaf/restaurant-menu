<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Validation guide

Use supported php85; tests use phpunit.xml SQLite :memory:. Do not refresh the working database.

1. Run php85 artisan test --compact tests/Feature/Settings/DisplayFormatsTest.php and existing Settings/ProfileUpdateTest.php, LocalizationTest.php and MoneyFormatterTest.php (resolve actual path).
2. Exercise two authenticated users with different formats and identical language/branch cache scope; refresh stale reports and confirm isolation. Warm guest menu as an authenticated user and confirm language defaults for anonymous reuse.
3. Run scoped Pint/PHPStan and translations:scan / translations:audit; record unrelated failures.
4. Apply only the new additive migration to local Herd after isolated tests; use isolated Chrome profile, sign in via existing local demo flow, open Profile, choose dotted date +24h+space/comma, inspect preview/save/reload. Restore the demo account's original values after checking EN/LT/RU at320/1440px.
5. Build existing assets and check console/keyboard/no horizontal overflow. No development server, commit, push or deployment.

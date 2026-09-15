<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Translation audit evidence

The final 2026-08-24 `php artisan translations:audit` inspected `en.json`, `lt.json`, and `ru.json`: 2,157 semantic keys per locale, 6,471 total entries, zero missing/extra/unused/empty/invalid/legacy/phrase-style/placeholder/plural issues and zero critical issues. `translations:scan --json` scanned 635 first-party files: all 2,157 semantic keys are used and none are missing, extra, unused or legacy.

Counts are evidence from that execution, not a permanent guarantee. The canonical workflow and required parity checks are in [`localization.md`](localization.md); rerun both commands after every user-facing text change.

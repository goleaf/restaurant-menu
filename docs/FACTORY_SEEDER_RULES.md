<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Factory and seeder rules

The canonical rules, model coverage and commands are in [`seeding.md`](seeding.md). This compatibility file no longer defines a second standard.

Defaults create valid minimal records; meaningful graphs and workflow states are explicit; fixed seeders are idempotent; demo seeding refuses production; no seeder truncates unrestricted data or contacts the internet.

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# System requirements view

The canonical catalogue is [`requirements.md`](requirements.md). The system baseline is PHP 8.5, Laravel 13, class-based Livewire 4, Flux UI Free 2, Tailwind 4 through Vite 8, SQLite, local disks, database cache/session/queue, Pest 4, Pint, and Larastan.

The controlling technical requirements are `sec-session-001`, `sec-authz-001`, `sec-input-001`, `sec-output-001`, `sec-upload-001`, `sec-dependency-001`, `data-schema-001`, `data-integrity-001`, `data-money-001`, `perf-query-001`, `perf-cache-001`, `livewire-001`, `blade-001`, `tailwind-001`, `i18n-001`, `test-feature-001`, `test-architecture-001`, and `ops-deployment-001`.

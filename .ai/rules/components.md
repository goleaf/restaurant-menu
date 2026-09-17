---
paths:
  - 'resources/js/alpine/components/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Components

## Release completed navigation requests before response effects
For Livewire 4 message interceptors, release a completed request at onSync, after the returned snapshot is applied and before redirect effects run; onEffect is too late for server navigation. Keep the guard for other pending requests, an idempotent onFinish fallback and listener cleanup. Never auto-replay a blocked navigation or mutation.

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Non-functional requirements view

The canonical, testable non-functional requirements are the security, data, performance, frontend, localization, quality and operations rows in [`requirements.md`](requirements.md). Their current implementation and command evidence is in [`compliance-matrix.md`](compliance-matrix.md).

The project quality bar is not a subjective “modern” label: all applicable full-suite, static-analysis, dependency-audit, build, migration, seed, localization, browser, responsive, accessibility and cache/query checks must pass with factual evidence.

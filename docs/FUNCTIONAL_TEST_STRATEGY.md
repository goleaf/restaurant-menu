<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Functional test strategy

The canonical automated strategy and commands are in [`testing.md`](testing.md). Requirement coverage is mapped in [`compliance-matrix.md`](compliance-matrix.md), and browser/manual workflows are in [`TEST_CHECKLIST.md`](TEST_CHECKLIST.md).

Historical prompt-by-prompt execution notes remain in the root [`CHANGELOG.md`](../CHANGELOG.md); they are not substitutes for a current passing full suite.

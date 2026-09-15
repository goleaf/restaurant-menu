<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Implementation guardrails

Canonical mandatory rules are in the root [`AGENTS.md`](../AGENTS.md). Architecture boundaries are in [`architecture.md`](architecture.md), security controls in [`security.md`](security.md), and executable repository prohibitions in [`testing.md`](testing.md).

This path remains for compatibility. In particular, old statements that kitchen ticket items have no status are obsolete: item-level `new`, `in_progress`, and `ready` states are current domain behavior and are documented in [`domain-model.md`](domain-model.md).

---
paths:
  - 'lang/*.json'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Lang

## Keep EN LT RU catalogues exact and fully used
All three flat JSON catalogues must contain the same semantic dot keys with non-empty locale-specific values and placeholder/plural parity. Do not keep unused keys or use English as a missing LT/RU fallback. Run translations:scan and translations:audit after catalogue or call-site changes.

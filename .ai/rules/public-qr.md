---
paths:
  - 'app/Livewire/PublicQr/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Public Qr

## Reauthorize isolated guest polling
Every guest polling action must resolve the current QR-scoped cookie or session credential and active guest again before querying. If access is revoked or the QR does not belong to the session service point or an active merged link, clear all serialized guest state immediately.

## Preserve a configured dish across availability conflicts
A failed save of an already configured dish must keep the comment, modifier selection and idempotency attempt, including when the item vanishes from the current menu payload. Render one recoverable dialog with an explicit recheck action, and revalidate ordering before adding. URL locale restoration may persist only for an active guest, matching event-based locale changes.

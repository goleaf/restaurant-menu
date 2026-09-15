---
paths:
  - 'resources/views/livewire/public-qr/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Livewire Public Qr

## Keep guest dish dismissal local with a native dialog
The guest dish uses one native dialog showModal/close lifecycle driven by local Alpine visibility. Do not combine it with x-trap: installed Alpine schedules trap activation after 15 ms without cancelling that callback on a fast close, which can steal focus after dismissal. Keep the native open attribute through morphs with wire:ignore.self, use a stable accessible title ID, restore focus after close, and tie background scroll locking to the actual open dialog so removal also releases it.

---
paths:
  - 'resources/js/menu-*.js'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Js

## Keep menu editor lifecycle compatible with installed Livewire
For the installed Livewire 4.4.1 lifecycle, use global Livewire.interceptMessage with explicit component/action filtering and dispose its subscription. Component-scoped message unsubscription calls a missing WeakBag.delete method in this version. Use the available onRender callback, and keep novalidate in the Blade markup of translated forms: a JS-only attribute disappears during morphing and prevents repeated server validation.

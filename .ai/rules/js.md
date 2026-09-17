---
paths:
  - 'resources/js/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Js

## Keep menu editor lifecycle compatible with installed Livewire
For the installed Livewire 4.4.1 lifecycle, use global Livewire.interceptMessage with explicit component/action filtering and dispose its subscription. Component-scoped message unsubscription calls a missing WeakBag.delete method in this version. Use the available onRender callback, and keep novalidate in the Blade markup of translated forms: a JS-only attribute disappears during morphing and prevents repeated server validation.

## Register Alpine factories before the one bundled Livewire start
resources/js/app.js imports Livewire's own ESM Alpine, registers all named factories/bindings, then starts Livewire exactly once. Critical roots stay inert until this registered runtime initializes. Layouts place @livewireScriptConfig before @fluxScripts; no automatic second runtime or per-screen registration fallback. Component init/destroy owns timers/listeners/uploads/blob URLs/audio; preserve global interceptMessage unsubscribe for installed Livewire 4.4.1. The only first-party fetch allowlist is resources/js/integrations/passkeys.js: same-origin abortable options/credential protocol around pinned @simplewebauthn/browser. Keep CSRF/credentials, reject redirects, check the current operation after each await, and never claim success after cancelling a possibly completed POST. Application CRUD stays in Livewire.

## Keep Alpine resource owners independent of directive context
Capture the owning root element or its immutable configuration in init(); Alpine $el can resolve to a child element when a method is invoked from its directive, including after await. Test child-triggered handlers. When focusing an x-show panel, wait for the reveal animation frame after $nextTick; own and cancel that frame on replacement or destroy, and guard stale callbacks.

---
paths:
  - 'resources/views/components/ui/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Ui

## Keep product composition above Flux, not generic control clones
Use installed Flux Free directly for buttons, fields, callouts, cards and skeletons. Keep Ui components only for domain semantics or repeated product composition; the architecture test rejects removed generic wrappers. Use callout-contrast to preserve readable heading/text colors through Flux callout CSS variables. Persistent modal hosts and their initial focus controls must exist before a server modal-show event; conditionally render only their editable content. Flux 2.17 progress needs a step-based wire:key when value changes through a Livewire morph.

## Label the native Flux 2.17 dialog from its persistent heading
Flux 2.17 forwards aria-label/aria-labelledby on flux:modal to ui-modal rather than its dialog. In product modal compositions, a persistent heading with a stable id uses x-init and native closest('dialog') to set aria-labelledby. This lets translated/morphed heading text remain the accessible name without a published modal override. Browser tests must assert the actual dialog name and focus restoration; reevaluate when Flux changes its attribute forwarding.

## Associate Flux errors and use semantic error contrast
Installed Flux 2.17 does not automatically associate every error/help node with its field. Changed forms use explicit description:id/error:id plus aria-describedby (or full field composition). For normal-size error text use text-danger! via class or the documented error:class attribute: upstream light red-500 measured only 3.81:1 on white. WorkspaceComponentsTest checks rendered contrast >=4.5. Avoid a global vendor selector override.

## Keep domain breadcrumb names literal
Breadcrumb labels are literal by default because organization, brand and branch names are untrusted user text, not translation keys. Static keys must explicitly set translate=true. Keep regression cases named navigation.organizations and validation; passing arbitrary names through __() can change their text or return a translation array.

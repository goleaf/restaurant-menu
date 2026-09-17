---
paths:
  - 'app/Livewire/Workspace/RestaurantSwitcher.php'
  - 'resources/views/livewire/workspace/restaurant-switcher.blade.php'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Workspace Switcher

## Keep restaurant selection separate from confirmed workspace context
Use the existing Flux listbox with a separate server-search slot; option labels must never overwrite search input. Keep the confirmed restaurant unchanged until authorized navigation arrives, preserve bounded search/pagination and explicit error associations, and keep cancel/focus restoration local while selection is disabled offline.

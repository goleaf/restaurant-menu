<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Translation key map

The active namespaces are `ui`, `auth`, `navigation`, `organizations`, `brands`, `branches`, `areas`, `service_points`, `qr`, `guest`, `menu`, `waiter`, `departments`, `orders`, `payments`, `reports`, `staff`, `permissions`, `statuses`, `fields`, `validation`, `errors`, `notifications`, `activity`, and `superadmin`.

Use stable dot-separated semantic keys; keep confirmation keys under `ui.confirmations`; use `statuses` for shared state labels and a domain namespace when meaning differs. Never derive a key from English copy. The canonical workflow and formatting rules are [`localization.md`](localization.md); the translation files and audit command are executable truth.

<!-- translation-key-namespaces:start -->
- `ui.*`
- `ui.confirmations.*`
- `auth.*`
- `navigation.*`
- `organizations.*`
- `brands.*`
- `branches.*`
- `areas.*`
- `service_points.*`
- `qr.*`
- `guest.*`
- `menu.*`
- `waiter.*`
- `departments.*`
- `orders.*`
- `payments.*`
- `reports.*`
- `staff.*`
- `permissions.*`
- `statuses.*`
- `fields.*`
- `validation.*`
- `errors.*`
- `notifications.*`
- `activity.*`
- `superadmin.*`
<!-- translation-key-namespaces:end -->

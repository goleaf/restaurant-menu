<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Preparation interface contract

`restaurant.preparation.dashboard` directly routes the canonical class-based component. Existing kitchen/bar endpoints are thin entry-family wrappers. Query: branch from shared context, department absent for first permitted default / exact positive ID / explicit all, filter from existing enum plus History, ticket exact positive ID, bounded pages, compact display. Explicit invalid/forbidden identity never falls back.

Current authorized IDs are separately resolved for kitchen and bar before union and branch scoping. No public state grants authority. Queue projection: bounded tickets/items, full_item_count, matching_item_count, portion_count, is_partial, saved department identity, current/original serving place and exact row status/version. Selected ticket bypasses row status display filter to show its full bounded contents, not authorization.

Mutations receive item,targetStatus,expectedStatus,expectedUpdatedAt,ticketId; group confirmation uses locked server-prepared snapshot of at most24 explicitly selected visible rows. Poll reauthorizes and replaces only a nonfrozen visible queue. Successful server refresh time is explicit; offline/reconnect never replays writes.

Print reauthorizes every request and uses full ticket saved content. Full print scope is labelled. Components do not call controllers or query models. Forms validate untrusted selection, policy authorizes, Actions transact. Parent owns shared enums/routes/translations/navigation/docs; workers have exclusive backend boundaries.

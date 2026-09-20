<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Personal display formats data model

Add nullable varchar columns users.date_format (20), time_format (20), number_format (20). Null means language default; existing rows need no data rewrite. User is the only persistence entity; use existing primary key for update. No query index or relationship required.

Allowed date values: locale, d.m.Y, d/m/Y, m/d/Y, Y-m-d. Time: locale, 24h, 12h. Number: locale, comma_dot, dot_comma, space_comma, space_dot. Save locale as null. Form state is mixed until required/string/allowlist validation. Only three validated fields reach the Action; user identity always comes from authentication. Read-only DisplayPreferences normalizes unknown stored values without rewriting them. Action reloads only its owned fields and never saves an unrelated dirty name/email/password.

Migration down removes only these columns; rollback/reapply verified on isolated SQLite, never on working application data.

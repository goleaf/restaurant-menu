<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Existing data and invariants

AreaNode retains branch,parent,type,name,icon,sort,is_active,soft deletion and structure_version. ServicePoint retains text display identity, optional unique internal code, nullable area, type,capacity,physical settings,status,is_active and structure_version. QrCode retains active/disabled/revoked state,short code,permanent public token and local image. Session occupancy includes direct and linked nonterminal sessions; order/audit history is immutable.

FloorOperation remains the bounded actor/branch/kind/request-key/payload-bound receipt for existing create/bulk/move/QR commands. PreparedFloorPrintStore retains actor/branch-bound expiring immutable labels and fingerprints; it is not a business entity or global selection. No schema migration is currently justified. All fixtures and optional schema probes use disposable databases; working database is explicitly outside this prompt's authorization.

The baseline64-node area read limit,200 bulk-create maximum,100 selected-operation maximum and4MB print snapshot bound remain unless measured evidence requires a separately documented safe adjustment. No role/subscription/QR token backfill.

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Data model — existing preparation records

No new order entity. KitchenTicket belongs to one order, branch, visit, original service point and immutable routed department snapshot. KitchenTicketItem preserves quantity (all portions change together), saved item/modifier/comment/allergen content and the linked immutable OrderItem variant.

New/Accepted/InProgress/Ready/Cancelled remain persisted item states; Ready plus served_at is served history. No new persisted tab status. Full Eloquent relation aggregates distinguish ticket, department and order results without limits. Historical event times come from OrderStatusLog, never guessed from later updated_at.

Shown row version is exact status plus raw updated_at with served_at null, scoped to explicit ticket; monotonic transitions prevent ABA. Status-log event identity records pending/delivered database notification metadata and supports explicit retry. Batch input: at most24 unique visible rows, one ticket, each id/status/updated_at. Outcome is per row; there is no false all-or-nothing promise.

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Data model scope

Canonical schema: [docs/data-model.md](../../docs/data-model.md). This working feature does not create a second schema catalogue.

RestaurantOnboarding uses explicit actor and restaurant identity, globally unique creation key with actor-bound replay and payload hash, setup_version, existing area/table/menu relationships, expected table count and historical completed_at. Multiple private attempts per user are permitted. First/additional/continue are different commands. Existing migrations and compatibility tests must preserve completed/partial/archived rows and permanent QR links.

StructureCreationReceipt is an existing staged additive table for actor-scoped standalone organization/brand creation. Its unique actor/request key protects replay; resource IDs come from authorized results, not arbitrary browser model names. Fresh installation and upgrade are validated only in disposable databases.

No new activity flags, cross-organization transfer, implicit restoration or cascading domain deletion. Existing BranchReadinessService supplies current readiness; completion remains historical.

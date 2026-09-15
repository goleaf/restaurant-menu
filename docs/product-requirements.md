<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Product requirements view

The canonical requirements are in [`requirements.md`](requirements.md). This view groups the product workflows without redefining them.

- Platform and tenancy: `sys-tenant-001`, `sys-role-001`, `sys-subscription-001`, `sys-superadmin-001`, `sys-backup-001`.
- Restaurant setup: `sys-staff-001`, `sys-branch-001`, `sys-branch-002`, `sys-area-001`, `sys-service-point-001`, `sys-qr-001`.
- Guest experience: `sys-guest-001`, `sys-guest-002`, `sys-menu-002`, `sys-menu-003`, `sys-draft-001`, `sys-waiter-call-001`.
- Restaurant operations: `sys-waiter-001`, `sys-order-001`, `sys-department-001`, `sys-order-002`, `sys-payment-001`.
- Governance and insights: `sys-report-001`, security/data/performance requirements in the canonical catalogue.

The product deliberately excludes online acquiring, delivery logistics, a public restaurant directory, AI translation, a separate SPA, and mandatory infrastructure services. Those capabilities may be introduced only by new canonical requirements and an accepted architecture decision.

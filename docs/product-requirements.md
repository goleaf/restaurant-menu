# Product requirements view

The canonical requirements are in [`requirements.md`](requirements.md). This view groups the product workflows without redefining them.

- Platform and tenancy: `sys-tenant-001`, `sys-role-001`, `sys-subscription-001`, `sys-superadmin-001`, `sys-backup-001`.
- Restaurant setup: `sys-staff-001`, `sys-branch-001`, `sys-branch-002`, `sys-area-001`, `sys-service-point-001`, `sys-qr-001`.
- Guest experience: `sys-guest-001`, `sys-guest-002`, `sys-menu-002`, `sys-menu-003`, `sys-draft-001`, `sys-waiter-call-001`.
- Restaurant operations: `sys-waiter-001`, `sys-order-001`, `sys-department-001`, `sys-order-002`, `sys-payment-001`.
- Governance and insights: `sys-report-001`, security/data/performance requirements in the canonical catalogue.

The product deliberately excludes online acquiring, delivery logistics, a public restaurant directory, AI translation, a separate SPA, and mandatory infrastructure services. Those capabilities may be introduced only by new canonical requirements and an accepted architecture decision.

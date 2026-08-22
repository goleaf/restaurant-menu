# Authorization model

## Roles

The closed role set is: superadmin, owner, director, restaurant administrator, shift manager, waiter, head chef, cook, bartender, cashier, accountant and marketer. Roles supply default permission bundles; `PermissionUserOverride` can allow or deny a specific permission for a user. A deny override wins for that user. Organization membership must be active, and branch assignment must include the target branch where the role is branch-scoped.

System role labels and permission labels are localization keys, not database-rendered English text.

## Decision order

1. Require an authenticated, active account for staff surfaces.
2. Apply the superadmin rule only to explicitly system-wide capabilities.
3. Resolve active organization membership and reject another tenant.
4. Resolve branch assignment when the resource is branch-owned.
5. Apply resource ownership/state rules.
6. Resolve effective permission from explicit override and role defaults.
7. Deny by default.

Route middleware protects page entry, but every Livewire mutation/controller action repeats resource authorization. `#[Locked]` prevents accidental client mutation of an identifier and is never authorization.

## Protected capability groups

| Capability | Permission / rule | Typical actors |
|---|---|---|
| Organization/branch administration | manage branches/settings/zones/service points | owner, director, configured administrators |
| Staff/role management | manage staff/permissions | owner/director and explicitly privileged administrators |
| Menu | manage menu/change prices/availability | configured management and menu staff |
| Table and order flow | manage sessions/view/confirm/edit/cancel/send/serve orders | waiter/shift/department roles according to assignment |
| Kitchen/bar | view department orders/mark ready | users assigned to the department/branch |
| Settlement | view/manage/correct payments/close sessions | cashier, waiter or manager according to explicit permission |
| Reporting/export/audit | view reports/export/view history/view audit | owner/director/accountant/authorized staff |
| Subscription | manage subscription | organization owner/director policy |
| System operations | superadmin dashboard/backup/suspension | superadmin only |

## Required test matrix

Every policy method and Livewire mutation covers: allowed owner; denied non-owner; allowed privileged role; denied role; wrong organization; wrong branch; absent membership/assignment; suspended/removed user; invalid/deleted resource; invalid state. Route model binding is also tested with a valid parent and a child belonging to a different parent.

The implementation must use model/resource Policies as the canonical decision surface. Existing permission helper calls may support policy decisions during migration, but scattered component-only checks are not a complete authorization design.

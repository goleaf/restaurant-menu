# Authorization model

## Roles

The closed role set is: superadmin, owner, director, restaurant administrator, shift manager, waiter, head chef, cook, bartender, cashier, accountant and marketer. The product-level “chef” role is the canonical `head_chef` system role; `cook` remains a separate subordinate kitchen role. Roles supply default permission bundles; `PermissionUserOverride` can allow or deny a specific permission for a user. A deny override wins for that user. Organization membership must be active, and branch assignment must include the target branch where the role is branch-scoped.

Staff administration follows the seeded `sort_order` hierarchy. Superadmin may manage any non-superadmin staff role. A tenant actor must hold the relevant `manage_staff` or `manage_permissions` capability and may invite, add, reassign, deactivate, revoke or reissue only a strictly lower role. Self role changes, self permission escalation, superadmin invitations and equal-or-higher targets fail closed in both Livewire and the invoked Action/Policy boundary.

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

Invitation management is tenant-scoped by organization and, when present, the exact brand/branch chain. Lists, searches and identifier lookups apply the same scope before hydrating email, creator or acceptance audit data. Only a matching invited email may view or accept a pending invitation. Reissue rotates the bearer because only its digest is stored; cancellation clears both credential digests. Existing and newly created recipients enter the same atomic acceptance Action.

Restaurant onboarding is user-owned until it creates the first organization. Only a new authenticated user without an existing tenant membership and without a non-owner system identity may create a checkpoint; existing waiter, kitchen, bar, suspended or other tenant staff cannot use onboarding to provision an owner context. Its checkpoint policy requires the authenticated checkpoint owner and rechecks active membership, active subscription and the exact active branch assignment on every Livewire hydration when assignments exist; a suspended/removed assignment fails closed, and explicit permission overrides take precedence over role defaults. Each subsequent step also authorizes the corresponding domain resource/capability (organization/brand/branch update or create, zones, service points, QR, menu, price and availability changes). The policy exposes a narrow `restoreCheckpointResource` operation for a soft-deleted model only when its ID and complete parent chain still match that checkpoint and ordinary subscription access is still active; this does not broaden the ordinary resource restore policies. The read service scopes relationships before hydration, and Actions reload the checkpoint with `user_id` and verify organization/brand/branch plus table-area ownership before any mutation, so a member of another tenant, a stale snapshot or a corrupted foreign link cannot inject or observe a checkpoint or domain identifier.

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

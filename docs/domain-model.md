# Domain model

## Ownership hierarchy

An `Organization` owns `Brand` records; a brand owns `Branch` records. A branch owns its operational settings, opening hours, area tree, service points, menu, staff assignment, table sessions, orders, departments, payments and audit trail. Every access path must preserve this hierarchy and reject a child identifier from another parent.

Users join organizations through `OrganizationUser`, which carries a role and membership status. `BranchUser` narrows branch access. System roles grant permissions; per-user overrides can explicitly allow or deny a permission. `Superadmin` is a system-wide role and is not inferred from a URL or client state.

## Principal workflows

### Restaurant setup

An authenticated user creates or joins an organization, configures brand and branch data, creates areas/service points, assigns staff, publishes a localized menu, and issues a QR code. Only authorized actors may cross each state boundary.

### Guest QR and table session

An active QR identifies one branch-owned service point. Scanning can open or join a table session according to branch policy. The guest has a session-scoped identity, not organization-wide access. Join requests and guest-created sessions use explicit states and may require waiter approval.

### Draft to fulfilment

A guest edits a draft containing available menu items/modifiers. Sending locks the submitted state for waiter review. A waiter may edit/reject or atomically convert it into an order with immutable item/price/department snapshots. Confirmation creates department tickets. Ticket items move through `new -> in_progress -> ready`; order progression, serving and cancellation remain authorized and audited.

### Payment and closure

Manual payments belong to a table session and optionally a guest. Amounts, service charge and tips are stored in minor units and cannot make paid value exceed the eligible balance. Corrections and closure are explicit privileged operations. A table may close only after the configured settlement rule is satisfied or a separately authorized override is audited.

## State sets

| Aggregate | States |
|---|---|
| Organization membership | invited, active, suspended, removed |
| Invitation | pending, accepted, expired, cancelled, rejected |
| Menu | draft, active, archived |
| QR | active, disabled, revoked |
| Table session | pending, active, waiting waiter confirmation, payment requested, paid, closed, cancelled |
| Table guest | pending approval, active, rejected, left, removed |
| Draft order | draft, sent to waiter, waiter review, rejected, converted to order |
| Order | confirmed, sent to departments, in progress, ready, served, payment requested, paid, closed, cancelled |
| Ticket item | new, in progress, ready; serving is tracked independently |
| Waiter call | pending, handled |
| Subscription | active/inactive plus payment status pending/paid/overdue/failed |

Only Actions may perform multi-record transitions. Transition validation is repeated at persistence time inside a transaction; a disabled UI control is never an invariant.

## Security boundaries

- Organization and branch ownership scope every staff/resource operation.
- Table-session guest tokens grant only the minimum table-session capability and must not expose staff data.
- Invitation credentials are single-purpose, expiring, one-time values stored as digests.
- QR tokens are public locators, but disabled/revoked and cross-branch combinations fail closed.
- Backup, role, permission, payment correction, QR reissue, suspension and destructive operations require explicit privileged authorization and audit records.

The physical schema is described in [`data-model.md`](data-model.md); executable state behavior is mapped in [`requirements.md`](requirements.md).

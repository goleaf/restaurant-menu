# Domain model

## Ownership hierarchy

An `Organization` owns `Brand` records; a brand owns `Branch` records. A branch owns its operational settings, opening hours, area tree, service points, menu, staff assignment, table sessions, orders, departments, payments and audit trail. Every access path must preserve this hierarchy and reject a child identifier from another parent.

Users join organizations through `OrganizationUser`, which carries a role and membership status. `BranchUser` narrows branch access. System roles grant permissions; per-user overrides can explicitly allow or deny a permission. `Superadmin` is a system-wide role and is not inferred from a URL or client state.

## Principal workflows

### Restaurant setup

An authenticated user creates or joins an organization, configures brand and branch data, creates areas/service points, assigns staff, publishes a localized menu, and issues a QR code. Only authorized actors may cross each state boundary.

### Guest QR and table session

An active QR identifies one branch-owned service point. Scanning can open or join a table session according to branch policy. The guest has a session-scoped identity, not organization-wide access. The permanent QR survives table rename, number change, area move and every historical session; only an explicit QR reissue changes its public credential.

The executable lifecycle is:

| Product stage | Persisted state and permitted next step |
|---|---|
| Table free | `ServicePointStatus::Free` and no non-terminal session guard. Authorized staff may open a new session; a first QR scan may instead create one `Pending` guest-created session when branch policy allows it. |
| Waiter opened table | One `Active` waiter-opened session owns the service-point guard. Repeated open returns that same session. |
| First guest joined | The first credential claims a waiter-opened session only when it has no current or historical guest. The guest becomes `Active`; the session remains `Active`. |
| Additional guest waiting | A serialized scan creates one expiring `Pending` join request. It does not create an active participant or a second session. |
| Guest approved or rejected | An active participant atomically changes the request to `Approved` and creates one active guest, or changes it to `Rejected` without creating a guest. Replays converge and expired requests fail closed. |
| Order forming | The session is `Pending` or `Active`; each active guest may mutate only their own `Draft` items. |
| First order awaiting waiter confirmation | Sending a non-empty draft moves it to `SentToWaiter` and the session to `WaitingWaiterConfirmation`. New joins, invites and guest order mutations stop, while an existing guest may retain read-only access, call the waiter or leave. |
| Order sent | Waiter confirmation atomically creates the canonical order/tickets and returns the session to `Active`; waiter rejection returns only the draft through its explicit state graph. |
| Awaiting service completion | The session remains `Active` while `OrderStatus` and ticket-item states own fulfilment. A bill request is allowed only after all drafts are submitted and every non-cancelled order is served, then moves the session to `PaymentRequested`; recording the complete offline payment moves it to `Paid`. |
| Session closed | Authorized closure rejects unfinished drafts/orders, moves eligible orders to `Closed`, moves the session to `Closed`, ends guests, expires pending joins, handles waiter calls, clears temporary invite credentials and frees all linked service points. Order, guest and audit history remain. |
| Table available again | A later staff open creates a new session ID behind the same permanent QR. Closed/cancelled sessions are history only and can never be restored by cookie, server session, old URL or stale Livewire payload. |

Role capabilities are checked again by the called Action; this table describes the baseline role grant inside an assigned restaurant:

| Stage | Guest | Waiter | Director | Restaurant administrator |
|---|---|---|---|---|
| Free / waiter-opened with no guest | Scan and enter a name; no staff capability | Open once; repeated open restores the same current session | Same as waiter | Same as waiter |
| Pending or active participation | Own draft only; create guest invite; approve/reject pending joins; call waiter; request bill only when workflow-complete; leave | View session; remove active guests; edit/reject/confirm submitted drafts; serve authorized items; close only when workflow-complete | Same session/order capabilities plus view and record/correct offline payments | Same table-session and payment capabilities as director |
| Waiting waiter confirmation | View own/table summary, call waiter or leave; cannot join, invite or mutate an order | Edit, reject or atomically confirm the submitted draft; cannot force an impossible session transition | Same as waiter | Same as waiter |
| Fulfilment / payment requested / paid | View permitted current-session data, call waiter or leave; no order mutation after payment request; a bill-request replay is idempotent | Fulfil authorized waiter work and close only after the closure guard passes; baseline waiter cannot record payment | Record/correct offline payment and close after the guard passes | Same as director |
| Closed / cancelled | No restoration, view or mutation through the guest credential | Tenant-scoped read-only history; may open a distinct new session when the table is free | Same as waiter | Same as waiter |

Per-user permission overrides may narrow or extend a baseline staff role, but branch membership, table-session policy and authoritative row state still apply. A rejected, removed or departed guest has no further guest capability; no role may enter another tenant by substituting an identifier.

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

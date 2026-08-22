# Data model

## Database contract

SQLite is the supported local, test and production database. The schema is migration-owned and currently consists of 66 migrations with no view, trigger or routine dependency and no first-party raw SQL query strings. Foreign keys, unique constraints and query-driven indexes are required; Eloquent is the only first-party query layer.

## Entity groups

| Group | Tables / models | Important integrity rules |
|---|---|---|
| Identity | users, passkeys, sessions, password reset tokens | email unique; sensitive auth fields hidden; locale constrained by application enum |
| Access | roles, permissions, permission_role, role_user, permission_user_overrides | role/permission keys unique; membership/resource authorization still required |
| Tenancy | organizations, organization_users, brands, branches, branch_users | parent foreign keys and organization membership unique combinations |
| Branch setup | branch_settings, branch_opening_hours, area_nodes, area_node_waiters, service_points, qr_codes | service points/QR/assignments stay within the branch hierarchy |
| Menu | menus, categories/translations, items/translations, modifier groups/options, schedules | localized records unique per owner+locale; published relationships remain valid |
| Guest/session | table_sessions, session_service_points, guests, join_requests, waiter_calls | guarded active/pending service-point uniqueness; session ownership enforced |
| Ordering | draft_orders/items, orders/items, order_status_logs | money uses fixed-precision decimal snapshots; immutable values preserve historical meaning |
| Fulfilment | kitchen_departments, kitchen_tickets/items | branch/department/order consistency; item status transitions are closed enums |
| Settlement/governance | manual_payments, audit_logs, organization_subscriptions, notifications | non-negative money; replay-safe operations; immutable audit facts |
| Runtime | cache, cache_locks, jobs, job_batches, failed_jobs | infrastructure records contain no cross-tenant business cache leakage |

## Value conventions

- Money: fixed-precision decimal strings/columns at persistence boundaries and integer minor units for arithmetic where an operation requires it; binary float never crosses a domain boundary. Display formatting is locale/currency aware and never feeds persistence.
- Time: database timestamps represent an unambiguous instant; branch/user locale formats presentation.
- State: backed enum values persisted as canonical lowercase snake-case strings.
- Localization: branch/menu presentation supports `en`, `lt`, and `ru`; translation tables use owner+locale uniqueness.
- Files: relative paths on a configured disk; generated UUID-based filenames; original names are metadata at most.
- Soft deletion: important business entities preserve history; queries must intentionally include or exclude deleted records.

## Migration policy

Historical migrations are immutable because deployed installations may replay them. Corrections use forward-only migrations. Risky changes follow expand/backfill/verify/switch/contract, with resumable bounded backfills and a documented rollback. No seeder or migration truncates unrestricted data; `migrate:fresh` is test-only.

The existing migration chain uses application models in several historical data backfills. This is a maintenance risk: those files are not rewritten after deployment. New data migrations must use stable schema-level operations or dedicated versioned data logic that cannot drift with current model scopes/events.

## Query/index strategy

All production collections are bounded or paginated. Lists select necessary columns and eager-load presented relationships. Aggregates use `withCount`, `withExists` or database-side totals; no relationship count/query is executed in Blade or loops. Composite index order follows actual equality filters before ordering/range columns. Important queries are verified using SQLite query plans and stable query-count tests.

## Factory and seed coverage

Every one of the 41 first-party Eloquent models has a factory. The final state/exemption inventory and idempotent seeding contract live in [`seeding.md`](seeding.md). Factory defaults must satisfy every non-null foreign key and must not implicitly create unexpectedly large graphs.

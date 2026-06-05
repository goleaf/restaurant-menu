# Schema Snapshot

Last verified: 2026-06-05
Source: Laravel Boost `database_schema`, `php artisan migrate:status`, migrations,
models, and focused schema tests.
Runtime: Laravel 13.13.0, PHP 8.5, SQLite.

This snapshot records the current database architecture. It is descriptive, not
permission to add product behavior automatically.

## Required Table Contract

| Required concept | Current table | Result | Architecture notes |
| --- | --- | --- | --- |
| Users | `users` | Present | PK `id`, unique `email`, timestamps, Fortify 2FA columns, persisted `locale`. |
| Organizations | `organizations` | Present | PK `id`, FK `owner_user_id`, unique owner/name, timestamps, soft deletes, local `logo_path`. |
| Brands | `brands` | Present | PK `id`, FK `organization_id`, unique organization/name, timestamps, soft deletes, local `logo_path`. |
| Branches | `branches` | Present | PK `id`, FKs `organization_id`, `brand_id`, branch/profile/status indexes, timestamps, soft deletes, local logo/cover paths. |
| Branch settings | `branch_settings` | Present | PK `id`, unique FK `branch_id`, timestamps, service/inactivity/payment setting columns. |
| Roles | `roles` | Present | PK `id`, unique `code` and `sort_order`, timestamps. |
| Permissions | `permissions` | Present | PK `id`, unique `code` and `sort_order`, timestamps. |
| Role permissions | `permission_role` | Present as canonical pivot | PK `id`, FKs `role_id`, `permission_id`, unique role/permission, enabled index, timestamps. No duplicate `role_permissions` table. |
| User permission overrides | `permission_user_overrides` | Present as canonical pivot | PK `id`, FKs `user_id`, `permission_id`, unique user/permission, enabled index, timestamps. No duplicate `user_permission_overrides` table. |
| Organization users | `organization_users` | Present | PK `id`, FKs organization/user/role/inviter, unique organization/user, status indexes, timestamps. |
| Branch users | `branch_users` | Present | PK `id`, FKs organization/branch/user/role/assigner, unique branch/user, status indexes, timestamps. |
| Invitations | `invitations` | Present | PK `id`, FKs organization/brand/branch/role/inviter, unique token/code, email/phone/status/expiry indexes, timestamps. |
| Area nodes | `area_nodes` | Present | PK `id`, FK `branch_id`, self FK `parent_id`, branch/type/activity indexes, timestamps, soft deletes. |
| Service points | `service_points` | Present | PK `id`, FKs `branch_id`, `area_node_id`, branch/status/type/name indexes, timestamps, soft deletes. |
| QR codes | `qr_codes` | Present | PK `id`, FKs service point/active service point/users, unique `public_token`, unique `short_code`, status/revocation indexes, timestamps. |
| Table sessions | `table_sessions` | Present | PK `id`, FKs branch/service points/users/guest invite creator, active/pending service point guards, status/source indexes, timestamps. |
| Table session guests | `table_session_guests` | Present | PK `id`, FK `table_session_id`, unique `guest_token`, status/ready indexes, timestamps. |
| Join requests | `table_session_join_requests` | Present | PK `id`, FK `table_session_id`, guest/user approver/rejector FKs, unique guest token, status/expiry indexes, timestamps. |
| Menus | `menus` | Present | PK `id`, FK `branch_id`, branch/status/sort indexes, timestamps, soft deletes. |
| Menu categories | `menu_categories` | Present | PK `id`, FK `menu_id`, self FK `parent_id`, menu/parent/activity indexes, timestamps, soft deletes. |
| Category translations | `menu_category_translations` | Present | PK `id`, FK `menu_category_id`, unique category/language, language lookup index, timestamps. |
| Menu items | `menu_items` | Present | PK `id`, FKs `menu_id`, `category_id`, `kitchen_department_id`, availability/sort indexes, timestamps, soft deletes. |
| Item translations | `menu_item_translations` | Present | PK `id`, FK `menu_item_id`, unique item/language, language lookup index, timestamps. |
| Menu item variants | Not present as separate table | Gap by requested table list | Current variant-like choices are `modifier_groups`, `modifier_options`, and `menu_item_modifier_groups`. Dedicated size/variant pricing needs a future design prompt. |
| Modifier groups | `modifier_groups` | Present | PK `id`, FK `branch_id`, branch/name/sort indexes, timestamps. |
| Modifier options | `modifier_options` | Present | PK `id`, FK `modifier_group_id`, availability/sort indexes, timestamps, `price_delta` money field. |
| Departments | `kitchen_departments` | Present as canonical table | PK `id`, FK `branch_id`, branch/type/activity indexes, timestamps. No duplicate generic `departments` table. |
| Draft orders | `draft_orders` | Present | PK `id`, FKs table session/guest/users, status lifecycle indexes, timestamps. |
| Draft order items | `draft_order_items` | Present | PK `id`, FKs draft/guest/menu item, item/order indexes, timestamps, money snapshot columns. |
| Orders | `orders` | Present | PK `id`, FKs branch/service point/session/draft/user, unique draft, branch/status/session indexes, timestamps, money total/currency. |
| Order items | `order_items` | Present | PK `id`, FKs order/guest/menu item/kitchen department, kitchen and guest indexes, timestamps, immutable menu/money snapshots. |
| Order department readiness | Not present | Critical architecture gap | Current readiness is inferred from `kitchen_tickets` and `kitchen_ticket_items.status`; moving to a coarse order/department readiness table needs a focused migration/refactor. |
| Payments | `manual_payments` | Present as manual ledger | PK `id`, FKs branch/service point/session/guest/user, branch/session/guest/payment indexes, timestamps, subtotal/service/tips/amount/currency snapshots. No generic online `payments` table. |
| Payment corrections | Not present | Not implemented yet | Future correction history should be added only with a manual payment correction feature. |
| Activity logs | `audit_logs` | Present | PK `id`, nullable organization/branch/user/guest FKs, entity/action indexes, created timestamp. |
| Notifications | `notifications` | Present | String PK `id`, polymorphic notifiable indexes, timestamps. |
| Media | Path columns plus local storage | Gap by requested table list | Current media is local filesystem paths on organizations, brands, branches, and menu items. No duplicate `media` table exists yet. |

## Supporting Tables

The system also includes Laravel/shared-hosting support tables and operational
tables: `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches`, `failed_jobs`, `passkeys`, `role_user`,
`organization_subscriptions`, `branch_opening_hours`,
`menu_availability_schedules`, `menu_item_modifier_groups`,
`kitchen_tickets`, `kitchen_ticket_items`, `order_status_logs`,
`table_session_service_points`, `area_node_waiters`, and `waiter_calls`.

## Cross-Cutting Checks

- Primary keys: all application tables have a primary key; Laravel notification
  IDs use string UUID-style primary keys.
- Foreign keys: core ownership, staff, QR, session, menu, order, ticket,
  payment, notification, and audit relationships are constrained where the
  relationship is concrete. Polymorphic notifications intentionally do not have
  FKs.
- Indexes: branch-scoped, status-scoped, token lookup, translation lookup,
  session lifecycle, and reporting/date access paths have explicit indexes.
- Timestamps: all domain tables have timestamps except `audit_logs`, which has
  immutable `created_at` only.
- Soft deletes: enabled on durable master data that should not disappear during
  normal operations: organizations, brands, branches, area nodes, service
  points, menus, menu categories, and menu items.
- Status fields: status-bearing Eloquent models cast to enums for table
  sessions, guests, join requests, QR codes, service points, menus, draft
  orders, orders, kitchen tickets, kitchen ticket items, invitations,
  organization users, branch users, waiter calls, and subscriptions.
- Money fields: current money columns are SQLite `numeric` decimals with
  currency snapshots on orders and manual payments. Manual payment snapshots
  split subtotal, service charge, tips, and collected amount.
- Translatable menu content: menu category and item names/descriptions are in
  dedicated translation tables keyed by language. Base `name`/`description`
  columns remain canonical fallback/admin values.
- Duplicate concept tables: no duplicate generic `departments`, `payments`,
  `role_permissions`, or `user_permission_overrides` tables exist; canonical
  current tables are documented above.
- Item-level operational statuses: `kitchen_ticket_items.status` exists and is
  the current kitchen/bar workflow implementation. This violates the desired
  future architecture of department-level readiness and is tracked as a
  critical migration candidate, not silently changed in this verification pass.

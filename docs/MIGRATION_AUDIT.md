# Migration Audit

Last verified: 2026-06-05
Source: Laravel Boost `database_schema`, `php artisan migrate:status`, migration
files, models, and focused schema tests.
Runtime: Laravel 13.13.0, PHP 8.5, SQLite.

## Result

All migrations are applied on the current SQLite database. No migration was
added during this audit because the missing pieces below require explicit
product/architecture confirmation, not unused placeholder tables.

## Findings

| Severity | Finding | Current state | Required follow-up |
| --- | --- | --- | --- |
| Critical | Department readiness is item-level today. | `kitchen_ticket_items.status` stores `new`, `in_progress`, and `ready`; `kitchen_tickets` split orders by department, but there is no `order_department_readiness` table. | Add a focused migration and refactor that creates order/department readiness rows, moves readiness transitions out of item rows, and updates kitchen/bar, waiter, notifications, audit, and tests. |
| Medium | Dedicated menu item variants are not a table. | Variant-like choices use `modifier_groups`, `modifier_options`, and `menu_item_modifier_groups`. | Add `menu_item_variants` only when the product needs separate size/SKU/variant pricing beyond modifiers. |
| Medium | Media has no registry table. | Uploads are local files referenced by path columns such as `logo_path`, `cover_image_path`, and `menu_items.image`. | Add a local `media` registry only when replacement history, ownership metadata, cleanup queues, or media ZIP backup needs database metadata. |
| Deferred | Payment corrections are not implemented. | `manual_payments` is append-only for recorded payments; no `manual_payment_corrections` or `payment_corrections` table exists. | Implement only with a future payment correction prompt that records actor, reason, old/new snapshots, and audit log entries. |
| Accepted | Required names differ from Laravel/current conventions. | `permission_role`, `permission_user_overrides`, `kitchen_departments`, and `manual_payments` are canonical. | Do not create duplicate alias tables for `role_permissions`, `user_permission_overrides`, `departments`, or `payments`. |

## SQLite Compatibility Notes

- Keep future migrations one concern per file and reversible.
- Use `foreignId()->constrained()` or explicit `constrained('table')` where the
  table name is non-standard.
- Avoid partial indexes and engine-specific DDL unless guarded; the current app
  targets SQLite/shared hosting first.
- Prefer additive migrations for production-like data. Do not rewrite existing
  migration history.

## No Random Table Rule

The audit found real gaps, but adding empty `media`, `menu_item_variants`, or
`order_department_readiness` tables without updating actions/models/UI/tests
would create duplicate concepts and false confidence. Each future table needs a
small prompt with behavior, data migration, tests, and rollback notes.

## Verification Commands

Run these before and after future schema work:

```bash
php artisan migrate:status --no-interaction
php artisan test --compact tests/Feature/MenuSchemaTest.php tests/Feature/OrderSchemaTest.php tests/Feature/DraftOrderSchemaTest.php tests/Feature/TableSessionSchemaTest.php tests/Feature/ServicePointSchemaTest.php tests/Feature/QrCodeSchemaTest.php tests/Feature/AreaNodeSchemaTest.php tests/Feature/ManualPaymentTest.php tests/Feature/KitchenDepartmentTest.php tests/Feature/KitchenTicketDispatchTest.php tests/Feature/LocalMediaStorageTest.php
```

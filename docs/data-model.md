<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Data model

## Branch report data semantics — 2026-09-15

Application timestamp writers use `now()` with the configured UTC application timezone. `BranchReportPeriod` converts branch-local calendar boundaries into UTC comparisons; it does not change timestamp storage, add date columns or migrate historical values. Report windows are half-open (`>=` local start converted to UTC, `<` the next local midnight converted to UTC), including 23/25-hour DST days. `today`, `yesterday`, `last7` and custom ranges of at most 31 inclusive calendar days resolve separately for every selected authorized branch.

| Report measure | Persistent source and inclusion rule |
| --- | --- |
| Confirmed order count and amount | `orders.confirmed_at` falls in the branch window; `status != cancelled`. All other canonical order statuses remain eligible, including unpaid, paid and closed orders. Amount is `total_price_cents`, grouped by recorded `currency`. Legacy empty order currency retains its EUR fallback. |
| Recorded payments | `manual_payments.paid_at` falls in the branch window; sum recorded `amount_cents` and count rows by currency independently of order confirmation dates or current order/session status. There is no `recorded_at` or payment-status field in this report contract. |
| Popular items | Active (`cancelled_at IS NULL`) OrderItems belonging to the qualifying order subquery. Exact `item_name` / `item_name_snapshot` pairs aggregate in SQLite; historical fallback and `mb_strtolower()` merge Unicode names before the stable quantity-ranked top five is selected. |
| Closed sessions / cancelled orders | Respectively `table_sessions.status = closed` with `ended_at` in the window, and `orders.status = cancelled` with `updated_at` in the window. Active-session counts remain current and do not use the historical date window. |

Order amounts are not receipts. Payments can cover different confirmation dates and include their recorded service/tip amounts. The immutable payment model has no refund/reversal workflow; reporting preserves the sign of any stored amount without inventing such an operation. Money from different currencies is never added together: counts may combine, amounts stay per currency, order averages are rounded per currency, and combined order/item money is null when currencies differ. A payment-only period can contain payment totals with no confirmed orders; it is not a zero-payment period.

Migration `2026_09_15_121714_add_report_name_index_to_order_items_table.php` adds `order_items_report_names_idx` on `(item_name, item_name_snapshot)` for the correlated name-aggregate lookups. Its rollback drops only that index. It adds no table, rewrites no historical snapshot and changes no permanent QR identity. Local schema presence does not imply the migration has run on a deployed database.

## Additive photo presentation — 2026-09-15

Migration `2026_09_15_094602_add_presentation_to_menu_images` adds nullable JSON `menu_items.image_presentation` and `menu_item_images.presentation`. Values hold focal_x/focal_y (0–100), EN/LT/RU alt/caption and an internal revision used in stale-write detection. Public projection exposes only selected-language alt/caption and a normalized object position. Existing files are unchanged. Null is the legacy center/default-alt presentation. No index is added because these columns are never filter predicates. Existing in-progress duplication receipts interpret a missing presentation snapshot as null; later metadata changes still invalidate the copy. Both factories provide opt-in withPresentation states.

## Current product schema — 2026-09-14

The 2026-09-15 interaction follow-up rechecks all 52 model/factory pairs and 89 migration definitions without changing them. Upload failure recovery reads the existing completed operation receipt; no parallel ledger, new field or rewritten deployed migration is needed. Fresh isolated migration/seed evidence is recorded separately in testing.md when complete.

The 2026-09-14 source snapshot had 89 migrations, 52 first-party Eloquent models and 52 factories. Isolated schema/factory verification applied all 89 migrations and observed 63 tables. `MenuOperation` stores user/branch-scoped UUID receipts, operation kind, bounded continuation phase, cursors, source-change state and pending media paths. `MenuOperationCategory` stores the unique operation/category traversal frontier. Unique request and active-scope keys protect replay and overlapping operations; branch/completion, target and frontier indexes support actual continuation queries. `DatabaseSessionRecord` maps the existing configured sessions table for complete restore invalidation and adds no table. Earlier dated audit inventories below describe their original source states.

Only `2026_09_14_160946_create_menu_operations_tables` is new. Historical migrations remain unchanged. Image upload, removal and promotion use the same receipt ledger. A rendered image identity detects stale confirmations; an actor/branch/item/action-bound UUID prevents response-loss and ABA replays from changing another image. Removal retains pending file paths until cleanup succeeds, and the existing continuation controls resume it after reload. Copy work remains soft-deleted until complete; failed copies keep an internal unpublished graph and release the operation scope after bounded media cleanup. Do not discard unfinished ledgers or roll back their tables while cleanup remains.

## Eighth audit persistence review — 2026-09-14

Read-only Boost inspection retains 61 tables / 633 columns / 308 indexes / 144 foreign keys, with no missing leading foreign-key index. All 49 Eloquent models have factories; all 88 migrations retain up/down methods. Area/table input validation and bulk allocation guards require no schema changes. Existing branch/internal-code uniqueness reserves archived codes; per-row model events, all-or-nothing writes and outer rollback behavior are preserved.

## Seventh audit persistence review — 2026-09-14

Fresh read-only Boost inspection confirms 61 tables / 633 columns / 308 indexes / 144 foreign keys and no missing leading foreign-key index. All 49 model definitions retain matching factories; all 88 migration files provide up/down and are recorded in the application migration ledger through batch 9. The full owned SQLite migrate/reset/migrate roundtrip and two default seed runs pass. Menu transport hardening changes the validation boundary only: decimal strings become exact integer cents after validation, stored flags and limits keep their existing types, and deployed migrations, permanent identities, snapshots and application data are preserved.

## Sixth audit persistence review — 2026-09-14

The schema remains 61 tables / 633 columns / 308 indexes / 144 foreign keys across 88 migrations. All 49 models have factories. A current selected entity reload supplies authoritative image ownership and paths before persistence; the caller's unrelated dirty attributes do not become part of an image-only save. Boolean branch-access checks require no additional column or index. Transport validation changes do not alter stored kitchen-department types or ranges. Existing migration history, snapshots, constraints and application data are preserved.

## Database contract

SQLite is the supported local, test and production database. The schema is migration-owned and currently consists of 91 migrations with no view, trigger or routine dependency and no first-party raw SQL query strings. Foreign keys, unique constraints and query-driven indexes are required; Eloquent is the only first-party query layer. `DatabaseCacheEntry` maps the existing framework cache table for bounded expiration cleanup; it adds no application table or migration.

## Restaurant hierarchy

Product language maps to the established persistence language without introducing duplicate models or tables:

| Product concept | Model / table | Ownership and field purpose | Nullability, identity and lifecycle |
|---|---|---|---|
| Company | `Organization` / `organizations` | `owner_user_id` identifies the accountable user; `name` and `logo_path` are company identity | owner and name are required and unique together; logo is optional; soft deleted |
| Brand | `Brand` / `brands` | `organization_id` places the brand inside one company; `name` and `logo_path` are brand identity | organization and name are required and unique together; `(organization_id, id)` supports the composite restaurant FK; soft deleted |
| Restaurant | `Branch` / `branches` | `organization_id` is the tenant scope, `brand_id` is the parent brand, and profile/address/timezone/currency/availability fields describe one venue | company, brand, operational address and locale settings are required; public profile/contact/temporary-closure details are optional; `(organization_id, brand_id)` must reference the same brand tenant; name is unique within a brand; soft deleted |
| Zone or room | `AreaNode` / `area_nodes` | `branch_id` owns the node; nullable `parent_id` forms the ordered area tree; `type`, `name`, `icon`, active state and metadata describe the physical grouping | `parent_id` is null only for roots; cross-restaurant parents and cycles are rejected by Actions; soft deleted |
| Table or other service point | `ServicePoint` / `service_points` | `branch_id` owns the point; nullable `area_node_id` places it in a zone; type, display identity, capacity, coordinates, state and metadata support table/bar/room/pickup workflows | area is optional for unplaced/pickup points; `internal_code` is optional but unique within a restaurant when present; public QR identity lives in `qr_codes`; soft deleted |

Every hierarchy model exposes both parent and child Eloquent relationships. Tenant-scoped read services select only presentation columns, paginate growing lists and eager-load every rendered service-point relationship. The branch-to-brand tenant agreement is also a composite database FK. Area-parent and service-point-area agreement remains Action-enforced because SQLite composite `SET NULL` would also clear the required `branch_id`; focused negative tests cover both boundaries, including direct Livewire identifier substitution.

Structure lifecycle is intentionally reversible. Organization, brand, branch, area and service-point archive operations use soft deletion after a scoped, authorized transactional reload. Any active order attached to the selected hierarchy scope blocks the archive; service points additionally reject active direct or linked table sessions. Restoring a service point does not silently reactivate it or its disabled permanent QR. Moving a service point between areas changes only `area_node_id`, so its row identity, `internal_code` and `qr_codes.public_token` remain unchanged.

## Entity groups

| Group | Tables / models | Important integrity rules |
|---|---|---|
| Identity | users, passkeys, sessions, password reset tokens | email unique; sensitive auth fields hidden; locale constrained by application enum |
| Access | roles, permissions, permission_role, role_user, permission_user_overrides | role/permission keys unique; membership/resource authorization still required |
| Tenancy | organizations, organization_users, brands, branches, branch_users | parent foreign keys and organization membership unique combinations |
| Branch setup | branch_settings, branch_opening_hours, area_nodes, area_node_waiters, service_points, qr_codes | service points/QR/assignments stay within the branch hierarchy |
| Onboarding | restaurant_onboardings, restaurant_onboarding_service_points | one checkpoint per user; entity FKs are unique; expected table count plus ordered service-point links detect missing/corrupt sets; links are unique by checkpoint+position and by service point; `completed_at` is the explicit terminal state |
| Menu | menus/translations, categories/translations, items/translations/images, variants/translations, modifier groups/translations, modifier options/translations, schedules | every localized record is unique per owner+locale; `hidden_until` is an indexed optional item deadline; image paths and item+sort order are unique; required image saves/creates/deletes abort the owning Action transaction if a model event rejects persistence; image rows cascade on hard delete while application Actions stream file cleanup for soft-deleted parents; category discovery and observer cascades enforce original-menu ownership and terminate cycles |
| Guest/session | table_sessions, table_session_service_points, guests, join_requests, waiter_calls | guarded active/pending service-point uniqueness; session ownership enforced; guest opener is a nullable FK used as the serialized first-guest claim; guest invite digest is unique and carries an explicit expiry; join credentials are unique and live pending requests are application-bounded; guest and pending-request locale is a supported enum value copied through approval |
| Ordering | draft_orders/items, orders/items, order_status_logs | money uses integer-cent snapshots; immutable values preserve guest ownership and historical meaning; `orders.status` is the one canonical aggregate lifecycle; nullable draft-item command keys are unique within one draft |
| Fulfilment | kitchen_departments, kitchen_tickets/items | branch/department/order consistency; subordinate item status transitions are centralized closed enums and cannot regress; actor/timestamp history is retained |
| Settlement/governance | manual_payments, audit_logs, organization_subscriptions, notifications | non-negative money; replay-safe operations; immutable audit facts |
| Runtime | cache, cache_locks, jobs, job_batches, failed_jobs | infrastructure records contain no cross-tenant business cache leakage |

## Value conventions

- Money: validated decimal input is converted to integer cents for persistence and arithmetic; percentage rates use integer basis points. Binary float never crosses a domain boundary. Display formatting is locale/currency aware and never feeds persistence.
- Time: application timestamps are written in UTC; branch timezone defines report calendar boundaries and branch/user locale formats presentation.
- State: backed enum values persisted as canonical lowercase snake-case strings.
- Order lifecycle: `confirmed_by_waiter → sent_to_kitchen_bar → in_progress → ready → served → payment_requested → paid → closed` is forward-only, with guarded shortcuts for all-ready, direct offline settlement and eligible manual close; cancellation is terminal. The transient confirmed state exists for atomic construction and legacy repair but normal waiter confirmation commits only after department tickets and the sent state exist. Ticket-item `new → accepted → in_progress → ready` is subordinate, supports terminal cancellation and guarded forward shortcuts, and cannot overwrite or regress the canonical order state. Department `completed` is a read state derived from `ready` plus non-null `served_at`, not another persisted status.
- Onboarding progress: current step is reconstructed from non-deleted scoped relationships, the persisted expected table count, contiguous ordered table-only service-point positions and active permanent QR completeness; identity links, the minimal expected-count invariant and a write-once explicit completion timestamp are persisted, so browser state cannot advance or re-time the workflow. The count detects a hard-deleted trailing pivot that relational links alone cannot distinguish from a smaller valid set. Soft-deleted links and same-branch survivors of a hard-deleted area remain available for authorized recovery, while retries and later operational disable/archive flags do not erase completed setup history.
- Localization: interface preference persists on `users.locale`, active guests on `table_session_guests.locale`, and pending entrants on `table_session_join_requests.locale`; all default to `en` for new records and are validated through `SupportedLocale`. Guest menu presentation supports `en`, `lt`, and `ru`; menu, category, item, variant, modifier-group and modifier-option translation tables all use owner+locale uniqueness. Management writes require all three names; legacy base columns remain a read fallback during safe forward adoption.
- Files: relative paths on a configured disk; generated UUID-based filenames; original names are metadata at most.
- Soft deletion: important business entities preserve history; active and archived management lists are explicit, and there is no ordinary hard-delete UI for restaurant-structure identity.
- Staff invitation credentials: only SHA-256 digests persist. The obsolete nullable plaintext columns were removed after a migration preflight proved that they contained no values.
- Guest invite credentials: `table_sessions.guest_invite_token_hash` stores the unique SHA-256 digest and `guest_invite_expires_at` stores the 30-minute deadline. The forward migrations hash any legacy guest invite, clear it and then remove the obsolete plaintext column after a no-values preflight. Rotation replaces the digest, so the previous URL becomes unusable immediately.
- Historical snapshots: fields such as `original_menu_item_id`, department/type/name snapshots and polymorphic audit entity IDs intentionally do not point at mutable live rows. `order_items.allergens_snapshot` is captured at waiter confirmation and copied into `kitchen_ticket_items.allergens_snapshot` at dispatch, so later menu edits cannot silently change a production safety warning; pre-existing rows upgrade to an empty JSON list without data loss.
- Draft-item retries: `draft_order_items.idempotency_key` is a nullable normalized UUID. The unique `(draft_order_id, idempotency_key)` index lets legacy/no-key rows coexist while making one browser command converge on one item inside its draft; the key is hidden from model serialization and has no meaning outside that command boundary.
- Framework-owned fields: cache/job/session/passkey transport columns are consumed by Laravel/Fortify even when first-party application code does not reference their names directly. `audit_logs` intentionally has only `created_at` because rows are immutable.

## Migration policy

Historical migrations are immutable because deployed installations may replay them. Corrections use forward-only migrations. Risky changes follow expand/backfill/verify/switch/contract, with resumable bounded backfills and a documented rollback. No seeder or migration truncates unrestricted data; `migrate:fresh` is test-only.

The existing migration chain uses application models in several historical data backfills. This is a maintenance risk: those files are not rewritten after deployment. New data migrations must use stable schema-level operations or dedicated versioned data logic that cannot drift with current model scopes/events.

## Query/index strategy

All production collections are bounded or paginated. Lists select necessary columns and eager-load presented relationships. Aggregates use `withCount`, `withExists` or database-side totals; no relationship count/query is executed in Blade or loops. Composite index order follows actual equality filters before ordering/range columns. Important queries are verified using SQLite query plans and stable query-count tests.

The automated schema audit verifies that every FK-column sequence has a matching leading index and that the complete current inventory has no identical or redundant non-unique prefix pair. The database-audit migration added the previously missing FK indexes plus the branch tenant composite and removed eight indexes already covered by stronger sequences; later menu translation owners, `hidden_until` and the draft-scoped idempotency constraint add only their required FK/unique/filter indexes. Public QR/short codes, guest tokens, staff/guest invitation digests and tenant-local natural identities retain database uniqueness. Guest invite expiry needs no second index because every acceptance lookup first resolves the unique digest and checks one row.

## Factory and seed coverage

Every one of the 52 first-party Eloquent models has a factory. The final state/exemption inventory and idempotent seeding contract live in [`seeding.md`](seeding.md). Factory defaults must satisfy every non-null foreign key and must not implicitly create unexpectedly large graphs. `MenuItemImage` is opt-in from its parent graph, stores one generated relative path and an integer order, and never changes the legacy `menu_items.image` primary path during migration. Menu translation factories remain opt-in states so ordinary parent factories stay small while tests and demo seeders can require complete locale graphs explicitly.

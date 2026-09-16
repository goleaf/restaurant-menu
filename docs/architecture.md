<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Architecture

## Current SCSS / Alpine boundary — 2026-09-16

The accepted migration supersedes earlier native-CSS-only and page-script decisions for first-party code. `resources/css/app.css` is only the Tailwind/installed Flux bridge. `resources/scss/app.scss` owns product tokens, light/dark variables, fonts, base accessibility and semantic compositions; `qr-print.scss` is a print-only entry. `pdf-qr.scss`, `pdf-report.scss` and `emergency.scss` compile into fixed committed `resources/views/generated/styles/*.blade.php` artifacts, so emergency/PDF rendering never needs a Vite manifest or runtime Node. `resources/build/styles.js` generates token aliases and concrete breakpoints automatically in both Vite build and dev/HMR; `npm run styles:check` detects drift. Do not edit generated CSS.

Cascade order remains Tailwind `theme, base, components, utilities`. Sass emits into existing layers and preserves narrowly scoped unlayered accessibility fixes; there is no new reset. Runtime `--rm-*` values have one canonical Sass map; generated `@theme inline static` aliases resolve the current Flux `.dark` appearance without another theme store. QR physical geometry stays 76×104 mm, 48 mm code plus quiet zone and 8 mm A4 margins. Emergency actions now meet the product's 44 px minimum.

`resources/js/app.js` imports the installed Livewire ESM runtime and its Alpine instance, registers named component factories/bindings, then calls `Livewire.start()` once. Compatibility palette/radius values used by semantic compositions are retained in the same map; static aliases prevent Tailwind pruning variables used only by the separate SCSS output. Every layout supplies `@livewireScriptConfig` before retained `@fluxScripts`. No independent Alpine package or `Alpine.start()` exists. Registration is cheap and synchronous; the WebAuthn SDK loads only when a ceremony starts. Local panel/focus/clipboard/preview/timer/audio state belongs to Alpine; forms, filters, uploads and mutations belong to class-based Livewire and existing Actions. The obsolete twelve root page/behavior modules are removed.

Transport allowlist: Fortify authentication; the single first-party `fetch` boundary in `resources/js/integrations/passkeys.js` for WebAuthn options/credential HTTP protocol; invitation credential/identity transitions; demo identity entry; authorized CSV/PDF/media/SQLite downloads; exclusive-barrier SQLite restore POST. Restore must obtain its lock before authentication/session reads and therefore cannot be moved behind the ordinary Livewire update transport. Static landing/error/PDF/email and navigation/redirect responses remain SSR. `LivewireInteractionBoundaryTest` pins all 30 class-based page routes, 17 HTTP exceptions and 15 CSRF-protected native POST forms; existing negative/replay/tenant tests exercise those boundaries. There were no first-party fetch/XHR/Axios requests at migration baseline, so none are claimed removed. The sole added passkey adapter replaces `@laravel/passkeys` with its installed lower-level `@simplewebauthn/browser` SDK pinned to 13.3.0: cryptographic browser operations stay in the SDK, while owned GET/POST requests enforce same-origin URLs, credentials, CSRF and redirect rejection. Every await checks the current owner; destroy/cancel aborts its request and ceremony. A cancelled POST has an unknown server outcome and never produces a local success claim. `alpine-passkeys-adapter.test.mjs` tests the real SDK after a cancelled delayed GET and controlled cancel/error/success transport boundaries. Application CRUD continues through Livewire.

Quality entrypoint: `npm run verify:migration` executes manifest validation, architecture rules, Stylelint, ESLint, Pint, Larastan, production build, complete PHP discovery/execution, JS coverage, PHP coverage, isolated browser coordinator, translations and budgets with real exit codes. This command defines verification; its presence does not imply it has passed. Exact current results and open gates belong in `PROGRESS.md` and `IMPLEMENTATION_PLAN.md`; older dated counts below remain historical.


## Internal Flux Pro distribution — 2026-09-15

`packages/livewire/flux-pro/` is the maintained local distribution boundary. Adjacent `flux-pro.provenance.json` and `flux-pro.sha256` retain the original source inventory and explicit local patches. Root Composer installs the truthful local release `0.1.1` through a path repository with `symlink: false`; ordinary discovery loads a physical vendor mirror. The upstream version remains unknown. Installed Free 2.17.0 supplies the tested PHP compatibility target; browser compatibility is a separate acceptance gate.

Keep first-party adapters in `app/` and `resources/`; the package's vendor Blade idioms do not relax first-party architecture rules. Domain reads remain scoped read services/models, mutations remain authorized Actions and Forms, and widgets receive prepared values. Free and Pro templates/PHP/compiled assets must belong to one accepted compatible pair. Integrity checks can run without the original root directory; application independence must additionally be proved before its deletion. See `ui-flux-pro-001` and the [execution ledger](IMPLEMENTATION_PLAN.md).

## Team completion boundaries — 2026-09-15

The staff query service prepares current-page summaries, selected-editor data and invitation aggregate counts. The existing scoped Actions remain the mutation boundary; the UI does not provision accounts. `ResolveInvitationRecipientRoleAction` supplies preserved effective recipient roles to the existing acceptance screen, while `ResolveInvitationDestinationAction` resolves the authorized branch or department within the invitation organization. `ProtectInvitationResponses` and the exception response hook apply invitation cache/referrer/indexing headers, including error responses. The shared static staff editor owns only responsive dialog/focus behavior; Livewire retains validation, authorization and persistence.

## Team workspace refinement — 2026-09-15

Organization Staff Index owns the shared class-based workspace contract; the branch component supplies the additional scoped route context. StaffQueryService prepares only the selected paginated list and bounded membership summaries. Invitation, member and area forms load one requested editor. Common Blade renders prepared rows; Alpine owns clipboard feedback, draft navigation confirmation and focus only. The existing invitation and membership Actions remain the mutation boundaries.

Waiter area selection preserves exact-area semantics: children are selected separately; empty selection means all areas in this branch for the existing My areas filter. One deferred draft is previewed, then saved atomically against an assignment fingerprint. Conflict retains the local draft; explicit refresh/review is required before applying again. Coverage describes active access assignments, not presence or shift scheduling.


## Branch control and reporting boundaries — 2026-09-15

`BuildRestaurantDashboardAction` resolves current capabilities and active branch visibility before preparing either report or operational data. A selected branch narrows every access set; an invalid or unauthorized identifier fails validation. An all-branches report queries only branches with current `ViewReports` permission. Operational cards and readiness are rebuilt from current state independently of the selected historical report period; Blade receives prepared labels, amounts, links and availability flags. The prepared dashboard payload stays in server-prepared view data and is not serialized as a public Livewire property.

`App\Support\Reports\BranchReportPeriod` owns `today`, `yesterday`, `last7` and inclusive custom dates of at most 31 calendar days. It resolves each branch's calendar dates in that branch's timezone, converts local start and next-midnight boundaries to UTC, and applies `>= start` / `< end`. An all-branches selection can therefore cover different local dates at the same instant, and DST days need not last 24 hours. Its fingerprint includes each branch's dates, timezone and currency.

`App\Services\Reports\BranchReportQuery` is shared by the restaurant dashboard and BasicAnalytics. The caller supplies already authorized Branch models; the query rejects a period whose branch IDs differ from that set. Eloquent scopes own confirmed-order and recorded-payment selection. Currency aggregates and exact stored-name aggregates replace complete order/item collections, while PHP preserves `historicalItemName()` fallback and `mb_strtolower()` grouping. No first-party SQL strings, dependency or worker are introduced. Financial definitions and the supporting index are described in [data model](data-model.md); measurement limits are in [performance](performance.md).

Only the raw restaurant report snapshot uses the 45/60-second stale-while-revalidate cache. Presentation and current operational state are prepared separately. A failed synchronous report read can show the last successful report for the same permission/branch/period/locale context with its original generation time and an explicit stale state; without a snapshot it shows unavailable data. This fallback is distinct from an empty report and from the normal freshness interval. Cache lifetimes and invalidation are defined in [caching](caching.md).

## Focused content operations — 2026-09-15

The menu Index owns a fixed, authorized URL section allowlist and mounts exactly one child. URL-backed catalogue filters remain in CatalogFilterForm. CatalogData prepares only paginated list presentation and loads gallery metadata for the open editor. Bulk and CSV mutations have focused Actions and use the existing menu operation receipt ledger, fresh actor/tenant checks and transaction boundaries. Synchronous import/bulk receipts cannot enter resumable deletion traversal. CSV uses the existing translation Actions and explicit integer-cent money conversion; no new HTTP/AJAX upload stack or runtime worker is introduced.

## Transient interaction and upload receipt boundaries — 2026-09-15

Alpine owns only the guest dialog's visibility, focus and current gallery frame. `GuestMenu` retains the existing validated configuration; a successful scoped `openItem` dispatches a self event, while same-item reopen preserves its comment/modifiers/request UUID. Add authorization and transaction boundaries are unchanged.

`ManagesItemImages` translates a permanent storage RuntimeException into existing localized field feedback. It consults the existing actor/branch/request/kind/target-scoped completed receipt only on that failure path: absent completion preserves the batch; proven completion clears it as success. `AddMenuItemImagesAction` continues to own storage compensation and the transaction. No new model, migration, queue or dependency is introduced.

## Recovery boundaries — 2026-09-15

`GuestMenu` preserves its existing configuration state and idempotency key through availability conflicts. Its explicit refresh action reuses the current scoped menu/gallery reads; it does not create another basket, authorization cache or language mechanism. Blade exposes the prepared recovery state and returns focus through local Alpine behavior.

`DeleteRolledBackLocalImageAction` is the narrow non-throwing compensation boundary used by `ReplaceLocalImageAction` and `AddMenuItemImagesAction`. Both deletion and warning logging are guarded so Laravel can finish clearing transaction callbacks. It never writes a cleanup receipt into the transaction being rolled back. Ordinary file deletion and after-commit cleanup continue to report failure, preserving the existing resumable operation ledger. A disk-refused rollback file can remain unreferenced; see [deployment](deployment.md) for the operational limit.

## Product operation boundaries — 2026-09-14

Catalogue rendering and bounded search live in the existing `CatalogData` read service. A 24-item page hydrates at most 25 item models; focused editing loads the selected item independently of the page. Livewire form objects validate original transport values, shared Blade locale panels present EN/LT/RU, and Actions own translation, image and operation transactions. A content fingerprint rejects stale text/price edits without discarding the user's form. Required Eloquent writes reject event vetoes inside the same transaction.

Persisted menu-operation receipts replace long UI deletion/duplication requests. Start/continue Actions authorize the actor and original branch on every call, advance bounded work and retain pending file cleanup. The Livewire operation trait exposes progress/retry and preserves unrelated open editors. The media helper derives display/thumbnail metadata from generated paths without storage reads; selected guest galleries are fetched only when opened.

Photo mutations also use that ledger: the prepared button carries a hash of the displayed image and an operation UUID. The Action reauthorizes, checks any receipt before looking up a possibly removed gallery row, and locks the current image before a new mutation. Lost responses replay the receipt; failed physical cleanup resumes through the existing operation controls. Image continuations explicitly bypass menu/category deletion traversal.

Waiter detail fingerprints are computed from the exact prepared section snapshot in the same SQLite transaction, including same-second edits and changes to older rows. Fresh permission checks remain outside any indefinite cache. HTTP restore coordination begins before session/auth database access and lasts through session writes; a persistent unsafe marker prevents reopening an uncertain database after restore plus rollback failure.

Current model/migration inventory is in data-model.md. Older counts below are historical observations, not limits or claims about this source.

## Eighth audit boundaries — 2026-09-14

Area and service-point editable values retain original transport types until shared validation; accepted values alone enter the existing focused Actions. The bulk Action owns the positive ascending 200-row allocation invariant independently of the component. Required saves remain eventful and transactional; rejected saves throw. Successful response preparation uses the initial reserved-code set and actual saved model codes without repeating area/code reads. Existing flat Blade bindings, authorization and model/Form/Request boundaries remain in place.

## Seventh audit boundaries — 2026-09-14

Catalog, Modifiers and Variants preserve original editable transport types until shared validation succeeds. BranchMenuComponent trims only strings and projects scalar selections for dependent read-service calls and uniqueness scopes without overwriting the public input. Integer rules include numeric validation; malformed names bail before uniqueness queries. Existing flat Blade bindings, scoped CatalogData reads, capability refresh and focused persistence Actions remain in place. This correction does not introduce a second form state, claim that all existing menu validation has moved into Form objects, or change the schema.

## Sixth audit boundaries — 2026-09-14

Organization/brand/branch image Actions select current active image state inside the transaction through original parent predicates. They persist on that current record, reuse shared rollback/outer-commit file handling, and synchronize only image/timestamp state to the caller. No unrelated dirty model field is saved or cleared. Stale replacement/removal, same-instance retry after rollback and moved/archived records are covered by EntityImageRetryTest.

KitchenDepartments preserves original editable transport values until validation, then passes normalized data to existing Actions. Array/null/boolean tampering produces field errors rather than pre-validation exceptions or coerced persistence. User branch access uses a boolean query boundary separately from the full assignment-list interface. These changes retain the model/Action/Form inventory and introduce no schema, route, Blade or dependency change.

## System shape

Restaurant Menu is a single Laravel application deployed as server-rendered HTML. Laravel routes and Fortify provide HTTP/authentication boundaries; class-based Livewire components own interactive page state; Blade and installed Flux Free/local Pro components render presentation; Actions coordinate domain writes; Eloquent models own persistence and entity-local behavior. SQLite, local files, and database-backed cache, sessions, and queues are the supported shared-hosting baseline.

```text
HTTP route / Livewire action
        -> authentication and authorization
        -> validated request or Livewire form state
        -> focused Eloquent read service or one application Action
        -> Eloquent query shape or transaction and domain invariants
        -> optional audit/notification/file side effects
        -> prepared view data / redirect / download
```

Blade is a terminal presentation boundary: it may render escaped prepared values and localized text, but it may not query models, resolve services, authorize mutations, calculate money, or construct business/SEO payloads. JavaScript is limited to local DOM behavior that does not make business decisions; Livewire owns persisted mutations.

Shared monetary form rules include `App\Support\Validation\DecimalMoney`, which delegates parsing and overflow checks to `MoneyFormatter` and translates failures into field errors. Existing required, decimal precision and per-field range rules remain in `RestaurantValidationRules`; the custom rule adds no database reads or value coercion.

Restaurant report and BasicAnalytics payloads contain localized money/date values, so both retain locale-scoped database cache keys. The restaurant cache stores raw report snapshots, while BasicAnalytics retains its 240/300-second snapshot contract. Current access is resolved before every lookup; branch generations fence obsolete normal snapshots, and bounded branch registries support physical cleanup. See the branch-reporting boundary above and [caching](caching.md) for normal refresh, explicit failure fallback and cleanup behavior.

One branch-settings Save is one transaction owned by `SaveBranchConfigurationAction`: reload and authorize the branch, scope its settings row, then persist settings, profile, closure, hours and images together. `BranchSettingsForm` owns validation and normalization; `BranchSettingsQueryService` owns selected reads. Local media replacement preserves the old file until the outer SQLite commit and removes newly stored files on rollback. Post-commit cleanup errors cannot undo the database commit and must not remove its replacement file.

Weekly hours use `NonOverlappingOpeningHours` in the Form rules and persistence Action, with bounded structural validation. The pure rule handles recurring Sunday/Monday overlap and permits touching intervals without database reads. The editor enforces four intervals in both its mutation and prepared Add-control visibility. Opening status orders intervals by wall-clock opening time and chooses the earliest actual normalized start for the next opening. Overnight dates are determined from stored times; when daylight-saving normalization collapses an occurrence, that occurrence is skipped rather than extended by a day.

Gallery uploads register rollback compensation per stored path. Menu, category and item deletion reload the original parent-scoped root and remove current owned files after the outer commit. Menu/category Actions stream selected 200-record media batches into `DeleteLocalMediaFilesAfterCommitAction`; that shared Action owns the temporary path file and a single cleanup callback per parent operation. Category discovery tracks visited IDs, keeps query filters at 200 IDs, and scopes both discovery and observer child/item deletion to the original menu. Fallback category candidates recheck scoped active existence so cascaded rows do not fire duplicate deletion events; remaining menu-owned items are handled even when their category reference is malformed. A model-event veto aborts the owning transaction. Category tracking still grows with the number of categories; media paths and item IDs are no longer retained as full collections. Exceptions after commit do not compensate already committed uploads. Shared logo/cover replacement and removal also own their persistence transaction; required save/delete results and bounded gallery creation results are checked before success. Replacement rollback cleanup is registered before persistence, so observer after-commit exceptions preserve the committed file.

## Verified implementation inventory

The refreshed 2026-09-14 inventory observes 49 first-party Eloquent models with 49 factories, 222 classes in the focused Action namespace, 61 Livewire PHP files, three Livewire Form objects, 18 policies, 88 forward migrations, and 133 Blade templates. The route inventory contains only Blade/Livewire/Fortify/Flux endpoints; no first-party SPA, JSON API, Volt component, or non-SQLite application database is present. Counts are audit evidence rather than architectural limits; executable architecture and model-factory tests remain authoritative when the code changes.

## Domain modules

| Module | Responsibilities | Principal code |
|---|---|---|
| Identity and access | invite-only Fortify account entry, digest-only invitation lifecycle, organization membership, strict role hierarchy, permissions and overrides | `Actions/Fortify`, `Actions/Invitations`, `InvitationForm`, `RolePolicy`, `Models/User`, role/permission models |
| Restaurant structure | organizations, brands, branches, settings, areas, service points and waiter assignment | organization/branch/area Actions and Livewire components |
| Restaurant onboarding | persistent user-owned setup checkpoint, verified step derivation and transactional creation/update of the initial restaurant graph | `RestaurantOnboarding`, onboarding Actions, `RestaurantSetupQueryService`, `Livewire/Onboarding` |
| Menu | menus, categories, localized menu items, ordered image galleries, modifiers, schedules and availability | `Actions/Menus`, menu models, `CatalogData`, branch menu components |
| Guest table flow | QR entry, table sessions, guests, join requests and draft carts | `Actions/TableSessions`, `Actions/DraftOrders`, `Livewire/PublicQr` |
| Fulfilment | order confirmation, kitchen/bar tickets, item progress, service and waiter calls | order/kitchen/bar/waiter Actions and Livewire components |
| Settlement | manual cash/terminal/other payments, corrections, table closure | `Actions/Payments`, payment models and waiter screens |
| Governance | audit log, exports, subscriptions, superadmin backup and dashboard | audit/export/subscription/backup/system Actions |

## Boundaries and dependencies

- Routes declare middleware, names, bindings and constraints only.
- Controllers and Livewire components validate and authorize, then invoke Actions or focused read services. They do not own Eloquent query construction or reusable domain rules.
- Each Action represents one use case and owns the smallest necessary transaction. External or filesystem work must not leave persistence inconsistent.
- Focused domain read services prepare bounded, selected and eager-loaded Eloquent data for a component. They are not repositories and do not hide write operations.
- Models define typed casts, relationships, scopes and cohesive entity behavior. They do not make external network calls.
- Policies are the canonical resource-authorization boundary; permission resolution is a supporting capability, not a replacement for resource ownership checks.
- Invitation routes remove bearer credentials from the URL before rendering. A focused resolver maps credentials to non-disclosing states; create/reissue/cancel/accept Actions own authorization, rotation, revocation, atomic consumption, membership writes and audit. Existing-user acceptance is an atomic pending-to-accepted compare-and-set. New-user registration also locks the invitation and recipient identity; an exact retry may reuse the winning account only when its stored password verifies the repeated submission, while ambiguous pre-existing-account attempts fail generically. Administrator lists are prepared by an exact tenant-scoped read service.
- Presentation data that is non-trivial or reused crosses into Blade as typed data or explicit arrays, never raw service objects.
- Public Livewire state is untrusted, minimal, typed and intentionally serializable. Durable identifiers may be locked, but every mutation still authorizes the resolved resource.
- Restaurant onboarding stores identity links, the expected initial table count and a write-once `completed_at`, not a mutable current-step flag or redundant ID arrays. The read service derives the first valid incomplete step from tenant-scoped parent links, the expected count, a contiguous ordered table-only service-point set and active permanent QR identities. The small expected-count invariant distinguishes a legitimately complete set from a hard-deleted final pivot and lets retry reconstruct only the missing table. Soft-deleted checkpoint references are loaded only to hydrate a scoped recovery form and never count as completed; same-branch survivors whose area was hard-deleted are reused by the replacement area step. Retries and operational disable/archive flags do not rewrite an already explicit completion. Each save Action reuses the user-owned checkpoint, re-resolves the chain and updates the domain object plus checkpoint in one retryable SQLite transaction.
- Product terminology `company → brand → restaurant → zone/room → table` maps to `Organization → Brand → Branch → AreaNode → ServicePoint`. The physical schema enforces organization/brand agreement for branches and parent existence with FKs; Actions enforce same-branch area-tree and service-point placement where nullable unlink semantics prevent a safe composite SQLite FK.
- Structure management is a Livewire read/write split: URL-backed search, lifecycle/type/activity filters, allowlisted sorts and independent paginators feed selected tenant-scoped query services; create/update/archive/restore/status/move mutations invoke focused Actions. Archive Actions lock and re-resolve the resource inside its parent scope, authorize through policies, reject active-order conflicts and soft-delete. Restore Actions repeat the same scope and policy checks; service-point restore deliberately does not re-enable operational or QR access.
- QR/table entry is a credential-driven state machine rather than an ID-driven route. `GenerateQrCodeForServicePointAction` owns one active opaque identity and delegates a deterministic hash-derived local SVG path; name/number/area edits never call reissue. Generation and reissue commit the credential identity before materializing the deterministic file, so a retry repairs a missing file; replaying the same retired QR returns its already active replacement instead of rotating a second time. `TransitionTableSessionStatusAction` reloads and locks the authoritative row before accepting the explicit enum transition, so a stale model cannot reopen a terminal workflow. `CreateGuestPendingTableSessionAction` serializes the first waiter-opened guest only when no guest has ever occupied that session through the existing `opened_by_guest_id` claim; an abandoned session with guest history cannot be claimed by a stranger and must be explicitly closed/reopened. A QR-scoped cookie and server-session credential independently restore the permitted current guest without exposing the bearer in Livewire state; stale identities are deleted and closed sessions never restore. A table transfer records a bounded origin history: only an already known credential from a proven previous permanent QR may follow the session to its new current table, while ordinary entry, same-branch substitution and inactive merge links remain excluded. Later guests receive bounded, notification-idempotent join requests; invite links expose one freshly rotated bearer while only its SHA-256 digest and 30-minute expiry persist, and the bearer is revalidated under the session lock before insertion. Approval/rejection reload both request and moderator under one transaction, authorize before any expiry/status mutation and are safe to repeat; the process-concurrency test exercises the same SQLite `IMMEDIATE` mode used in deployment. Closure finalizes only temporary credentials, pending joins, calls and active guest access, while order/session history remains available through a bounded tenant-authorized staff history query.
- Order fulfilment uses `OrderStatus` as its sole aggregate state machine. The waiter may edit the shared draft before confirmation, but `ConfirmDraftOrderByWaiterAction` then locks the draft and atomically creates immutable guest-owned order snapshots plus the unique kitchen/bar ticket set. `KitchenTicketItemStatus` is subordinate production detail (`new → accepted → in_progress → ready`, with guarded forward shortcuts for legacy/rapid work and terminal cancellation); waiter service derives `completed` from `ready + served_at` without creating another stored order status. Department mutations authorize the exact ticket family, lock and compare-and-set the item, reject regressions, record one actor/history entry and derive only forward aggregate transitions. `BuildDepartmentDashboardAction` scopes before hydration, explicitly selects/eager-loads immutable modifiers/comments/allergens, paginates 24 tickets and sorts active work oldest-first and terminal history newest-first. The isolated region uses three-second `wire:poll.visible`, announces meaningful fingerprints instead of elapsed-second churn and requires no queue worker, scheduler or WebSocket service.
- Localization has one UI catalogue boundary and one guest-content boundary. Flat semantic JSON keys own all application/interface text, while six owner+locale translation-table families own persisted menu content; the approaches do not overlap. Middleware resolves valid explicit, authenticated and session preferences, focused Actions persist user/guest choices, formatters prepare localized date/time/money labels before Blade, and the translation audit rejects key drift, empty/unused values, placeholder/plural mismatches and phrase-style calls across PHP, Blade and JavaScript.

## Business invariant ownership

Business rules are enforced at the deepest reusable mutation boundary, not copied into Livewire, controllers, Blade or observers. Components may prepare capability flags for usability, but the Action reloads the authoritative row, authorizes the resource and rechecks the invariant inside its transaction. Observers retain cache/file lifecycle side effects only and are not state-transition authorities.

| Invariant | Canonical implementation | Persistence backstop | Regression evidence |
|---|---|---|---|
| A user may act only inside an authorized tenant | resource policies, tenant-scoped query services and resolver Actions; mutation Actions authorize the reloaded resource | organization/branch membership and parent FKs | policy, access-control, IDOR, waiter-open-table and department tests |
| A table belongs to one restaurant and at most one zone/room in that restaurant | `EnsureAreaNodeBelongsToBranchAction` is shared by single create, update and bulk create | `service_points.branch_id` and nullable `area_node_id` FKs; tenant-local uniqueness | service-point CRUD, onboarding and database-integrity tests |
| One permanent active QR identity belongs to one table | generate/reissue Actions are the only credential lifecycle boundary; rename/number/move never call reissue; a retired-QR replay returns the active replacement and repairs its deterministic file | unique active `qr_codes.active_service_point_id` and unique opaque public token | QR generation, reissue replay/process concurrency, admin display, move and schema tests |
| A closed/cancelled session accepts no new action; paid also locks order mutation until explicit close | `TableSessionStatus::isTerminal`, `allowsGuestParticipation` and `locksOrderChanges` are used by guest, waiter, invite, bill and close Actions | guarded active/pending service-point columns and session status enum cast | table close, payment, guest flow and lifecycle state-machine tests |
| A session transition uses current database state and a previous session is never silently reused | `TransitionTableSessionStatusAction`, open/close Actions and current-session scopes reload under the smallest transaction; repeat open converges, while close then open creates a new ID behind the permanent QR | active/pending service-point unique guards, terminal timestamps and immutable historical foreign keys | stale-object, repeat-open, abandoned-session, close/reopen, history and concurrency tests |
| Temporary table access is finalized without deleting service history | `LeaveTableSessionAction`, `RemoveTableSessionGuestAction` and `FinalizeTableSessionTemporaryStateAction` record explicit guest state, expire pending joins, handle calls and clear invite digests | guest/join/call status rows and order/audit foreign keys are retained | leave/remove, no-approver expiry, close cleanup, old-token denial and historical Livewire tests |
| A guest may change only their own draft rows | `EnsureGuestOwnsEditableDraftItemAction` checks item owner, draft/session identity, active guest, active table and guest-editable draft | item/guest/draft FKs preserve ownership links | draft functional and paid/foreign-guest negative tests |
| Waiter edits stop at the permitted draft transition | `EnsureWaiterCanEditDraftOrderAction`, `DraftOrderStatus::isWaiterEditable` and `MoveDraftOrderToWaiterReviewAction` | one draft status plus unique order link | waiter editing/review and state-machine tests |
| First confirmation dispatches once; sent work never silently returns to draft | `ConfirmDraftOrderByWaiterAction`, `DraftOrderStatus::canTransitionTo`, `OrderStatus::canTransitionTo` and centralized order transition Actions | unique `orders.draft_order_id`, unique department ticket identity and append-only status history | lifecycle, forbidden-transition and process-concurrency tests |
| Kitchen and bar see and mutate only their production family | `KitchenDepartmentType::kitchenProductionTypes` / `barProductionTypes` feed dashboard, mutation resolver and print Actions; the mutation uses an atomic old-status predicate | ticket department/type FKs, ticket-item unique order-item identity and ticket policy | kitchen/bar screen, process-concurrency, wrapper Action and ticket-print isolation tests |
| Quantities and totals remain valid | immutable `OrderItemQuantity`, centralized line-price calculation, integer minor-unit arithmetic and snapshot validation | quantity/money checks and non-null integer/decimal columns | quantity, money, draft editing, confirmation and payment tests |
| A retried request does not create a duplicate | `IdempotencyKey` plus `CreateDraftOrderItemIdempotentlyAction`; exact update/delete/send, join moderation, invitation acceptance/registration, QR lifecycle, order confirmation/status and table close replays return the persisted result after authorization and state checks | unique draft attempt, membership, QR, active session, order, ticket and ticket-item identities | guest/waiter Livewire replay, double-click/network retry, lifecycle and real process-concurrency tests |

## Runtime model

No worker, cron, Redis, WebSocket, S3, Docker or SSH-only runtime capability is required for a core workflow. Database queues may be used only when the deployment explicitly supplies a worker; otherwise long work must be resumable and bounded through web requests. The application is multi-organization: organization, branch and table-session identifiers are security boundaries, not UI filters.

## PHP 8.5 applicability

| Feature | Decision | Location / rationale | Evidence |
|---|---|---|---|
| Strict types | Applicable to every new and materially modified first-party PHP file | Prevent coercion ambiguity at application boundaries | Pint, Larastan and affected tests |
| Enums and `match` | Used | Closed domain states already use backed enums; complete mappings avoid magic strings | Enum/unit and workflow tests |
| Readonly data/value objects | Applicable when immutable multi-layer data is introduced | Avoided for one-line framework calls; use only for a real boundary | Larastan and unit tests |
| URI extension | Not currently applicable | The application does not fetch or normalize user-controlled remote URLs | Security review |
| Clone-with / pipe operator | Not currently applicable | No immutable transformation pipeline becomes clearer with these constructs | Code review |
| `#[NoDiscard]` | Candidate only for internal critical results | Framework/application APIs currently consume results directly; no safe omission defect identified | Static analysis |
| `#[Override]` | Applicable to modified overrides where supported and useful | Communicates framework contract without changing behavior | PHP syntax and tests |
| Partitioned cookies | Not applicable | No cross-site embedded workflow | Session configuration review |
| Persistent cURL sharing | Not applicable | No external HTTP integration | Integration inventory |

## Laravel 13 feature applicability

| Framework feature | Use case and decision | Files | Evidence |
|---|---|---|---|
| Modern bootstrap middleware/exceptions | Used; retain project-specific web/auth behavior | `bootstrap/app.php` | boot/cache/HTTP tests |
| Scoped implicit bindings | Used for nested organization resources | `routes/web.php` | cross-tenant route tests |
| Eloquent lazy-loading prevention | Enabled outside production, including local, testing and staging; violations throw | `AppServiceProvider::configureDefaults` | `EloquentLazyLoadingTest` checks environment selection, rejected unloaded relations and two-query eager loading |
| Controller/authorization attributes | Evaluate per endpoint; policies remain primary | controllers/policies | feature tests |
| API/JSON:API resources | Not applicable; no public API contract | none | route inventory |
| `Cache::touch` | Not applicable without a cache entry whose lifetime must be extended | none | caching review |
| Queue attributes/routing | Not applicable to required shared-hosting flows | none | operations review |
| AI/vector/realtime features | Not applicable to product requirements | none | requirements catalogue |
| Image manipulation additions | Not needed: logos and dish galleries retain validated JPG/PNG/WebP source content without server transforms | media/menu Actions | upload, gallery and rollback tests |

Important long-lived architecture choices are recorded in [`decisions/`](decisions/); completion-audit choices are summarized in [`DECISIONS.md`](DECISIONS.md). The compact requirement status is in [`compliance-matrix.md`](compliance-matrix.md), and the concrete route-to-database relationship is in [`REQUIREMENTS_TRACEABILITY.md`](REQUIREMENTS_TRACEABILITY.md).

## Local login directory — 2026-09-16

Fortify prepares `/login` through `BuildLocalLoginDirectoryAction`. It returns null before querying unless both resolved and configured environments are local, demo mode is enabled and the host is allowlisted. Otherwise it selects 25 users per page and eagerly loads roles, company memberships, ownership, role grants and scoped overrides; only prepared arrays reach the anonymous `auth.local-user-directory` Blade component. The password column checks the canonical demo email, exact role and configured password against the current hash. GET does not seed or change users. Role defaults and individual exceptions are labelled separately; resource policies remain authoritative.

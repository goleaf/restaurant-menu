# Architecture

## System shape

Restaurant Menu is a single Laravel application deployed as server-rendered HTML. Laravel routes and Fortify provide HTTP/authentication boundaries; class-based Livewire components own interactive page state; Blade and Flux UI Free render presentation; Actions coordinate domain writes; Eloquent models own persistence and entity-local behavior. SQLite, local files, and database-backed cache, sessions, and queues are the supported shared-hosting baseline.

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

## Verified implementation inventory

The refreshed 2026-08-24 inventory observes 45 first-party Eloquent models with 45 factories, 194 classes in the focused Action namespace, 55 class-based Livewire components (52 concrete and 3 abstract), two Livewire Form objects, 18 policies, 80 forward migrations, and 129 Blade templates. The route inventory contains only Blade/Livewire/Fortify/Flux endpoints; no first-party SPA, JSON API, Volt component, or non-SQLite application database is present. Counts are audit evidence rather than architectural limits; executable architecture and model-factory tests remain authoritative when the code changes.

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
- Invitation routes remove bearer credentials from the URL before rendering. A focused resolver maps credentials to non-disclosing states; create/reissue/cancel/accept Actions own authorization, rotation, revocation, atomic consumption, membership writes and audit. Administrator lists are prepared by an exact tenant-scoped read service.
- Presentation data that is non-trivial or reused crosses into Blade as typed data or explicit arrays, never raw service objects.
- Public Livewire state is untrusted, minimal, typed and intentionally serializable. Durable identifiers may be locked, but every mutation still authorizes the resolved resource.
- Restaurant onboarding stores identity links, the expected initial table count and a write-once `completed_at`, not a mutable current-step flag or redundant ID arrays. The read service derives the first valid incomplete step from tenant-scoped parent links, the expected count, a contiguous ordered table-only service-point set and active permanent QR identities. The small expected-count invariant distinguishes a legitimately complete set from a hard-deleted final pivot and lets retry reconstruct only the missing table. Soft-deleted checkpoint references are loaded only to hydrate a scoped recovery form and never count as completed; same-branch survivors whose area was hard-deleted are reused by the replacement area step. Retries and operational disable/archive flags do not rewrite an already explicit completion. Each save Action reuses the user-owned checkpoint, re-resolves the chain and updates the domain object plus checkpoint in one retryable SQLite transaction.
- Product terminology `company → brand → restaurant → zone/room → table` maps to `Organization → Brand → Branch → AreaNode → ServicePoint`. The physical schema enforces organization/brand agreement for branches and parent existence with FKs; Actions enforce same-branch area-tree and service-point placement where nullable unlink semantics prevent a safe composite SQLite FK.
- Structure management is a Livewire read/write split: URL-backed search, lifecycle/type/activity filters, allowlisted sorts and independent paginators feed selected tenant-scoped query services; create/update/archive/restore/status/move mutations invoke focused Actions. Archive Actions lock and re-resolve the resource inside its parent scope, authorize through policies, reject active-order conflicts and soft-delete. Restore Actions repeat the same scope and policy checks; service-point restore deliberately does not re-enable operational or QR access.

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
| Strict Eloquent behavior | Used in local and test environments | `AppServiceProvider` | full suite without lazy-loading violations |
| Controller/authorization attributes | Evaluate per endpoint; policies remain primary | controllers/policies | feature tests |
| API/JSON:API resources | Not applicable; no public API contract | none | route inventory |
| `Cache::touch` | Not applicable without a cache entry whose lifetime must be extended | none | caching review |
| Queue attributes/routing | Not applicable to required shared-hosting flows | none | operations review |
| AI/vector/realtime features | Not applicable to product requirements | none | requirements catalogue |
| Image manipulation additions | Not needed: logos and dish galleries retain validated JPG/PNG/WebP source content without server transforms | media/menu Actions | upload, gallery and rollback tests |

Important long-lived architecture choices are recorded in [`decisions/`](decisions/); completion-audit choices are summarized in [`DECISIONS.md`](DECISIONS.md). The requirement-to-code relationship is authoritative in [`compliance-matrix.md`](compliance-matrix.md).

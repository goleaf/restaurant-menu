# Architecture

## System shape

Restaurant Menu is a single Laravel application deployed as server-rendered HTML. Laravel routes and Fortify provide HTTP/authentication boundaries; class-based Livewire components own interactive page state; Blade and Flux UI Free render presentation; Actions coordinate domain writes; Eloquent models own persistence and entity-local behavior. SQLite, local files, and database-backed cache, sessions, and queues are the supported shared-hosting baseline.

```text
HTTP route / Livewire action
        -> authentication and authorization
        -> validated request or Livewire form state
        -> one application Action
        -> Eloquent transaction and domain invariants
        -> audit/notification/file side effects
        -> prepared view data / redirect / download
```

Blade is a terminal presentation boundary: it may render escaped prepared values and localized text, but it may not query models, resolve services, authorize mutations, calculate money, or construct business/SEO payloads. JavaScript is limited to local DOM behavior that does not make business decisions; Livewire owns persisted mutations.

## Domain modules

| Module | Responsibilities | Principal code |
|---|---|---|
| Identity and access | Fortify login, passkeys, 2FA, organization membership, roles, permissions and overrides | `Actions/Fortify`, `Actions/Invitations`, `Models/User`, role/permission models |
| Restaurant structure | organizations, brands, branches, settings, areas, service points and waiter assignment | organization/branch/area Actions and Livewire components |
| Menu | menus, categories, localized menu items, modifiers, schedules and availability | `Actions/Menus`, menu models, branch menu component |
| Guest table flow | QR entry, table sessions, guests, join requests and draft carts | `Actions/TableSessions`, `Actions/DraftOrders`, `Livewire/PublicQr` |
| Fulfilment | order confirmation, kitchen/bar tickets, item progress, service and waiter calls | order/kitchen/bar/waiter Actions and Livewire components |
| Settlement | manual cash/terminal/other payments, corrections, table closure | `Actions/Payments`, payment models and waiter screens |
| Governance | audit log, exports, subscriptions, superadmin backup and dashboard | audit/export/subscription/backup/system Actions |

## Boundaries and dependencies

- Routes declare middleware, names, bindings and constraints only.
- Controllers and Livewire components validate and authorize, then invoke Actions. They do not own reusable domain rules.
- Each Action represents one use case and owns the smallest necessary transaction. External or filesystem work must not leave persistence inconsistent.
- Models define typed casts, relationships, scopes and cohesive entity behavior. They do not make external network calls.
- Policies are the canonical resource-authorization boundary; permission resolution is a supporting capability, not a replacement for resource ownership checks.
- Presentation data that is non-trivial or reused crosses into Blade as typed data or explicit arrays, never raw service objects.
- Public Livewire state is untrusted, minimal, typed and intentionally serializable. Durable identifiers may be locked, but every mutation still authorizes the resolved resource.

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
| Image manipulation additions | Not needed for current MIME-validated local logo storage | media Actions | upload tests |

Important choices are recorded in [`decisions/`](decisions/). The requirement-to-code relationship is authoritative in [`compliance-matrix.md`](compliance-matrix.md).

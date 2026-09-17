<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Restaurant Menu Constitution

## Core Principles

### I. Canonical Requirements and Bounded Scope

[`AGENTS.md`](../../AGENTS.md), applicable `.ai/rules`, and canonical documentation MUST govern
Spec Kit work. [`docs/requirements.md`](../../docs/requirements.md) remains the sole active
requirement catalogue. Feature specifications MUST link existing requirement IDs and describe
bounded changes; they MUST NOT become a competing product catalogue. Accepted requirement
changes belong in that catalogue. Authoritative task ownership and status remain in
[`docs/IMPLEMENTATION_PLAN.md`](../../docs/IMPLEMENTATION_PLAN.md); measured evidence belongs in
[`docs/PROGRESS.md`](../../docs/PROGRESS.md) and
[`docs/compliance-matrix.md`](../../docs/compliance-matrix.md).
Historical reports and generated checklists MUST NOT be treated as current acceptance.

### II. Server-Owned Application Boundaries

New application pages, forms, filters and mutations MUST use class-based Livewire and separate
Blade views. Components validate and authorize, then invoke focused Actions or bounded read
services. Actions own transactions; Models own relationships, casts and scopes; Policies own
resource authorization; Forms and Rules own substantial validation. Blade MUST contain only
prepared presentation data. Eloquent is the only application query layer: no raw SQL, queries
or aggregates in loops, lazy-loaded rendered relationships, or unbounded growing lists.
Reuse established boundaries in [`docs/architecture.md`](../../docs/architecture.md).

### III. Tenant Safety and Durable Data Integrity

Every protected mutation MUST reauthorize current actor, organization, restaurant and resource
state on the server. Public Livewire state and action arguments are untrusted; locked IDs are
not authorization. Preserve Fortify, CSRF, session, origin and rate-limit controls. Secrets and
credentials MUST NOT enter specifications, logs, snapshots or client presentation. Transactions,
constraints and meaningful retry/concurrency tests MUST protect repeated multi-record operations.
Preserve permanent QR identity, historical order snapshots and existing data. Money MUST use
exact decimal strings or integer minor units. SQLite migrations MUST be additive and reversible;
destructive refresh is allowed only on an owned isolated test database. Follow
[`docs/domain-model.md`](../../docs/domain-model.md) and [`docs/data-model.md`](../../docs/data-model.md).

### IV. Localized and Accessible Restaurant Workflows

All user-facing text MUST use the existing EN/LT/RU JSON system with placeholder parity.
Interfaces MUST support keyboard, touch, focus and translated responsive behavior, including
loading, empty, validation, error, offline, pending and success states as applicable. Reuse
accepted Flux and Blade components. First-party styling belongs in `resources/scss`;
Tailwind/Flux retain the separate CSS-first bridge. Alpine owns ephemeral DOM state only;
Livewire owns validated persistent state. Interface work MUST first read `PRODUCT.md`,
`DESIGN.md`, and the frontend/accessibility documents listed in `docs/index.md`.

### V. Evidence Before Completion

Behavior changes MUST begin with a meaningful failing Pest test and follow red-green-refactor.
Authorization needs positive and negative cases; retries, races and lifecycle bugs need matching
regression evidence. Use factories and isolated SQLite fixtures. Apply the repository's relevant
formatting, static-analysis, translation, build and browser gates. `composer analyse` is the
canonical static-analysis command; `composer test:coverage` MUST retain at least 90% application
coverage. Full acceptance MUST use fresh results for the exact source being accepted. Record
failures, skipped checks and external blockers explicitly. Installation, implementation,
verification, commit, push and deployment are distinct states.

## Repository Boundaries

- Supported production: PHP `>=8.5.0 <8.6.0`, Laravel 13, Fortify 1, Livewire 4,
  Blade SSR, accepted Flux Free/locally adapted Pro, Tailwind 4, Dart Sass and Vite 8.
  Check installed versions before using APIs; PHP 8.6 remains experimental until accepted.
- SQLite and local filesystem storage MUST remain deployable on shared hosting. No required
  Redis, WebSockets, S3, Docker, online payment provider or continuously running worker.
- Herd serves the application. Use supported `php85` when needed and resolve URLs through
  Boost; never start a replacement development server or switch the global runtime.
- Preserve the current branch and unrelated staged/unstaged work. Spec Kit feature identifiers
  name local specification directories; they do not authorize new Git branches or worktrees.
- GitHub remains push-only to the existing origin under `AGENTS.md`. Do not invoke
  `$speckit-taskstoissues`, GitHub APIs/MCP/gh, remote lookups, fetch/pull, PR/release automation,
  hooks, or `.github/workflows` changes. No Git extension or hook is enabled by this adoption.
- Templates, optional extensions and workflow suggestions MUST NOT override these boundaries,
  change application dependencies, or authorize additional product work.

## Spec Kit Workflow

1. Read `AGENTS.md`, `docs/index.md`, canonical requirements, relevant architecture/topic
   documents and scoped rules before planning. Reconcile the existing local plan and dirty
   worktree; initialization does not authorize executing all open roadmap work.
2. For a requested feature, use `$speckit-specify` to create a focused working specification
   under `specs/<feature-id>/`, mapped to canonical requirement IDs and explicit acceptance
   scenarios. Keep the current Git branch and use the toolkit's local feature selection.
3. Use `$speckit-clarify` for material unresolved requirements, then `$speckit-plan` and
   `$speckit-tasks`. The Constitution Check MUST cover all five principles and repository
   boundaries. Templates saying tests are optional do not relax this project's TDD requirement.
4. Link feature working artifacts from the existing implementation plan and maintain its
   authoritative status there. Reuse existing sufficient specs/plans. Update canonical
   documents when the implemented contract changes; do not duplicate the product backlog.
5. Use `$speckit-analyze`, `$speckit-implement` and `$speckit-converge` within authorized scope.
   Resolve conflicts against canonical documents before implementation. Template complexity
   justifications cannot waive hard rules; convergence cannot mark failed gates done.
6. Execute steps in the current agent session, respecting existing user authorization. The
   bundled workflow is available but is not automatically dispatched by initialization.

## Governance

This constitution adapts existing repository rules for Spec Kit; it does not supersede them or
introduce a second product specification. In a conflict, follow the current user instruction,
`AGENTS.md`, applicable scoped rules and canonical requirements in their existing authority order.
Read linked documents in their current form instead of relying on copied historical details.

Amendments MUST follow user-authorized changes, identify affected canonical documents and gates,
and preserve the sole requirement catalogue. Use semantic versioning: MAJOR for incompatible
principle changes, MINOR for new/materially expanded principles, PATCH for clarifications.
Every plan and completion review MUST assess constitutional compliance and report unresolved
violations; no automatic commit, push, publication or runtime change follows.

**Version**: 1.0.0 | **Ratified**: 2026-09-18 | **Last Amended**: 2026-09-18

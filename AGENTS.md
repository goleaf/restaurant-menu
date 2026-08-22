# Repository operating rules

## Purpose

This repository is a shared-hosting restaurant operations application. It gives restaurant organizations a tenant-safe administration surface, permanent QR entry, guest table sessions and shared drafts, waiter review, kitchen/bar fulfilment, manual offline settlement, reporting, and local operations tooling.

## Mandatory reading order

Before changing code, read:

1. `AGENTS.md`.
2. `docs/index.md`.
3. `docs/requirements.md` and `docs/compliance-matrix.md`.
4. `docs/architecture.md`, `docs/domain-model.md`, and `docs/data-model.md`.
5. The topic document for the affected area, then relevant tests and implementation.
6. `docs/implementation-plan.md` when modernization work remains open.

For interface work, read root `PRODUCT.md` and `DESIGN.md` before `docs/frontend.md`, `docs/design-system.md`, `docs/accessibility.md`, and `docs/tailwind.md`. They define product/design context without overriding `docs/requirements.md`.

`docs/requirements.md` is the canonical active requirement catalogue. `CHANGELOG.md` is historical and must not override current requirements.

## Technology baseline

- PHP `>=8.5.0 <8.6.0`.
- Laravel 13.x and Fortify 1.x.
- Livewire 4.x using class-based PHP components and separate Blade views.
- Flux UI Free 2.x where an official Flux component is already the best fit.
- Blade SSR; no React, Vue, Inertia, separate SPA, jQuery, or Volt.
- Tailwind CSS 4.x through `@tailwindcss/vite`, CSS-first configuration, and Vite 8.x.
- SQLite is the only application database. Database cache, sessions, and queues are the deployable defaults.
- Pest 4 and PHPUnit 12; Laravel Pint; Larastan when configured by the repository.
- Local filesystem disks only. No Redis, WebSockets, S3, Docker, online payment provider, or paid runtime service is required.

Use the existing npm lock file. Do not introduce another JavaScript package manager or lock file.

## Architectural boundaries

- Routes declare named endpoints, middleware, constraints, and bindings only.
- Controllers and full-page Livewire components authorize, accept validated input, invoke a focused Action, and return a response.
- Actions represent one application operation and own transaction boundaries.
- Models own persistence relationships, casts, cohesive entity behaviour, and reusable query scopes. Use Eloquent only.
- Policies own model/resource authorization. Broad capabilities may use gates. Every protected Livewire mutation must authorize on the server.
- Form Requests and Livewire form objects own substantial validation. Treat every public Livewire property and action argument as untrusted.
- Blade receives prepared presentation data and contains presentation conditions only.
- Do not add repositories, interfaces, managers, helpers, or services unless they establish a real boundary or remove substantial duplication.

## Database and Eloquent rules

- Never write raw SQL strings in first-party code. Never use `DB::select`, `DB::statement`, `DB::raw`, `selectRaw`, `whereRaw`, or equivalents.
- `DB::transaction` is allowed in Actions and seeders; queries remain Eloquent.
- Never query in Blade or inside render loops. Never aggregate in loops.
- Never use unbounded `Model::all()`. Paginate growing lists; use `lazyById()` or `chunkById()` for batches.
- Eager-load every rendered relationship and use `withCount`, `withExists`, or database-side aggregates when appropriate.
- Models use intentional fillable/hidden attributes and modern `casts()` definitions. Money is never stored or calculated as float.
- Preserve SQLite compatibility, foreign keys, unique constraints, indexes, permanent QR identity, historical order snapshots, and existing data.
- Migrations are additive and reversible. Never edit a migration that may have run in production. Never use `migrate:fresh` outside an isolated test database.
- Strict Eloquent behaviour is enabled in local/testing; fix violations instead of globally suppressing them.

## Livewire rules

- Class-based components only, one PHP class and one Blade view. Do not use Volt, single-file components, or PHP blocks in component templates.
- Keep public state minimal, typed where Livewire supports it, and safe to serialize. Do not expose secrets, collections, builders, or service objects.
- Use `#[Locked]` for immutable browser identifiers but still authorize every action.
- Use `#[Computed]`, `#[Url]`, isolation, loading targets, polling, and other Livewire 4 features only where their semantics improve the current workflow.
- Use stable durable `wire:key` values. Use deferred input binding unless immediate server reaction is required.
- Poll only bounded, independently useful regions and keep shared-hosting operation viable without a long-running process.

## Blade and frontend rules

- No `@php`, `@endphp`, ordinary `<?php` blocks, direct models, queries, facades, actions, services, or container resolution in first-party Blade.
- No business calculations, authorization decisions, collection pipelines, SEO construction, or lazy-loaded relationships in Blade.
- Escape user-controlled output. Raw output is allowed only at an explicit tested sanitization boundary such as the audited generated QR SVG.
- All user-facing text uses the existing JSON translation system and must be added to `en`, `lt`, and `ru` with placeholder parity.
- Reuse Flux or Blade components for presentation. Do not create Livewire components for static reuse.
- Alpine is for local ephemeral DOM state only. Livewire owns validated, authorized, persistent mutations.

## Tailwind and design-system rules

- Keep CSS-first Tailwind configuration in `resources/css/app.css` using `@import`, `@theme`, `@source`, variants, and small intentional utilities.
- Use design tokens for repeated colors, spacing, typography, focus, radius, shadow, motion, and z-index values.
- Do not construct dynamic Tailwind class fragments. Every utility must be statically discoverable or explicitly sourced.
- Mobile-first layouts must avoid horizontal overflow and remain usable with translated text, keyboard, touch, 200% zoom, reduced motion, and forced colors.
- Use semantic HTML and visible focus. Icon-only controls need accessible names; status cannot rely on color alone.

## Authentication, authorization, and security

- Fortify owns authentication. Preserve login, registration, password reset, password confirmation, passkeys, 2FA, recovery codes, session regeneration, and rate limiting as configured and tested.
- Do not weaken CSRF, origin, session-cookie, password, or rate-limit controls.
- Scope organization and branch data before returning it. Client-hidden controls and `#[Locked]` are never authorization.
- Public QR URLs contain only high-entropy public tokens. Guest tokens, invite tokens, internal IDs, secrets, and credentials are not rendered, exported, or logged.
- Files use configured local disks, generated names, content-aware validation, ownership checks, and lifecycle cleanup.
- Never log secrets, full tokens, credentials, session identifiers, or unnecessary personal data.
- Production errors are localized and generic; unexpected exceptions are reported with safe structured context.

## Testing and seeding

- Use TDD for behaviour changes: write or update a failing Pest test, implement the smallest correct change, then refactor.
- Every changed behaviour needs a targeted Feature, Unit, or Livewire test. Critical authorization needs positive and negative cases.
- Every first-party Eloquent model has a valid factory or an explicit documented exemption. Factory defaults create valid records; graph-heavy data is opt-in.
- Fixed seeders are idempotent. Demo data is deterministic, fictitious, and blocked in production. Seeders never truncate unrestricted databases.
- Testing uses SQLite `:memory:` unless a documented test requires an isolated temporary file.

## Documentation and quality gates

- Update the requirement, compliance, architecture, testing, seeding, security, deployment, and changelog documents whenever their implemented contract changes.
- Do not create competing requirement documents. Historical prompt records belong in `CHANGELOG.md`.
- Before completion run, as applicable: Composer validation/audit, dependency inspection, Pint, Larastan, targeted and full Pest suites, parallel tests, coverage, translation audit/scan, fresh migrations, seed/idempotency checks, npm audit, production build, route/config/view caches, browser console checks, responsive/a11y checks, and a final diff review.
- Never claim a gate passed without observing the command result. Record blockers and skipped checks exactly.

## Runtime constraints

- The primary deployment target is conventional shared hosting with Laravel Herd for local development.
- The application must work without Redis, WebSockets, S3, Docker, Supervisor, or a continuously running queue worker.
- The optional scheduler may run inactivity cleanup; equivalent bounded web/Artisan controls must remain safe and idempotent.
- Do not start a development server: Herd serves the project. Resolve URLs with Laravel Boost before sharing or browser testing.

## Git workflow

- Work on the current branch unless repository instructions require another branch. Do not create unnecessary branches.
- Before edits and commits inspect branch, status, staged/unstaged diff, untracked files, and recent commits.
- Preserve unrelated work. Never reset, clean, restore, stash, broadly stage, rewrite history, or force-push.
- Commit only coherent verified changes with Conventional Commit messages. Push only after required checks pass and report the observed result.

## Definition of done

Work is done only when the applicable requirement is implemented, authorized, validated, localized, accessible, factory/seed compatible, covered by meaningful passing tests, formatted, statically analysed, built, browser-checked when user-facing, documented, and represented accurately in the compliance matrix. Future agents must read the current canonical documentation before changing code.

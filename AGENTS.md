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
6. Root `ROADMAP.md` when scheduled work remains open.

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
- `composer analyse` is the canonical Larastan gate; `composer test:coverage` is the canonical Xdebug/PCOV gate and must remain at or above 90% application coverage.
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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>

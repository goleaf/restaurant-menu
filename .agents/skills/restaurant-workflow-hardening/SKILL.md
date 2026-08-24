---
name: restaurant-workflow-hardening
description: Audit and harden multi-step Laravel/Livewire workflows in this restaurant-menu repository when persistence, retries, tenant authorization, or end-to-end UI quality must be verified. Use for workflows such as restaurant onboarding, not for an isolated copy or styling edit.
metadata:
  short-description: Harden restaurant workflows safely
---

# Restaurant Workflow Hardening

Coordinate cross-layer workflow changes without replacing the repository's Laravel, Livewire, Pest, Flux, or Tailwind skills. Load those skills for their detailed framework patterns; this skill supplies the repository-specific order, invariants, and evidence standard.

## Establish the current contract

Before planning or editing:

1. Inspect the current branch, recent commits, staged and unstaged diffs, and untracked files. Treat the checkout as shared and preserve unrelated work; never reset, clean, restore, stash, broadly stage, or rewrite it.
2. Read `AGENTS.md`, then follow `docs/index.md`. `docs/requirements.md` is the only active requirement catalogue; use `docs/compliance-matrix.md` as its evidence map. Read architecture/data-model and the affected topic documents. `CHANGELOG.md`, plans, progress ledgers, and `ROADMAP.md` cannot redefine behavior.
3. Read `.ai/rules/index.md`, every path-matched rule, and search `.ai/rules` for the domain and concerns being changed. Repository rules override generic skill examples.
4. Load `laravel-best-practices`, `livewire-development`, and `pest-testing`; add `fluxui-development` and `tailwindcss-development` for interface work. Do not copy their API guidance into this skill.
5. Confirm installed PHP packages with `composer show --direct` and JavaScript versions in `package.json`. Use Laravel Boost `search-docs` with package filters before relying on version-specific Laravel, Livewire, Flux, Tailwind, or Pest behavior. Use Boost schema inspection before schema changes, `get-absolute-url` before browser QA, and recent `browser-logs` after it. Herd already serves the project; do not start a server.

Map the requirement through route, component/Form, read service, Action, Policy/Gate, models/schema, Blade, translations, factories, and tests before choosing a change. Prefer the established boundary over a new abstraction.

## Preserve the application boundaries

- Routes declare named endpoints, middleware, bindings, and constraints only.
- Class-based Livewire components with separate Blade views authorize, validate/co-ordinate state, consume prepared read services, and invoke Actions. They never construct Eloquent queries or persist models.
- Substantial multi-field validation belongs in a Livewire Form and may reuse the repository's rule builders.
- Actions own one application operation and its transaction. Models own relationships, casts, entity behavior, and reusable scopes. Policies/Gates own authorization.
- Blade is presentation-only: no PHP blocks, models, queries, services, authorization, money calculations, or business transformations.

## Harden the workflow state

Treat every public property, action argument, URL value, and stale Livewire snapshot as hostile.

- Keep public state minimal. Apply `#[Locked]` to server-owned immutable identifiers when useful, but re-resolve every resource inside the authenticated tenant scope and authorize every mutation.
- Scope parent ownership before hydrating names, counts, URLs, menu data, QR data, or identifiers. Hidden controls and locked state never establish access.
- Reconstruct progress from deterministic, scoped relational state when safe. Otherwise persist the smallest checkpoint and explicit terminal state needed to remove ambiguity. Do not store redundant ID arrays or a client-controlled current step.
- A completed workflow must remain completed. Back navigation may edit the same graph, never create a parallel graph.
- For every mutation, prove the outcomes for: repeat execution, concurrent duplicate execution, failure halfway, and retry after failure. Use database uniqueness/integrity constraints plus the Action transaction rather than frontend flags alone.
- Multi-record writes must be atomic or have an explicit compensating boundary for filesystem effects. Retry must converge on one valid tenant graph.
- Use Eloquent only. Preserve SQLite foreign keys, uniqueness, index order, and lock/transaction behavior. Migrations are additive, reversible where safe, compatible with existing data, and tested on an isolated SQLite database; never rewrite deployed migrations or run `migrate:fresh` against the application database.
- Keep money as decimal strings or integer minor units, never binary floats.

## Drive changes with adversarial Pest tests

Write or update a focused failing test before each behavioral fix, then implement the smallest coherent correction. Use factories and existing states instead of hand-built database graphs.

For a multi-step workflow, cover the applicable boundaries directly:

- mount/remount with no state, after every successful step, after completion, and after deleted/inactive/corrupt references;
- direct invocation of future actions, prerequisite bypass, property tampering, wrong-parent IDs, wrong tenant, revoked membership/assignment/subscription, and explicit permission denial;
- the same Action twice, stale component retry, duplicate requests, transaction rollback, and safe recovery;
- validation types and boundaries, including enums, timezone, currency, counts, identifiers, arrays, files, and exact money;
- update-versus-duplicate behavior, query budgets where regression is plausible, and absence of secret/internal-ID disclosure.

Do not weaken an existing assertion to accommodate a defect. UI visibility assertions supplement rather than replace direct server authorization tests.

## Keep presentation internationally usable

- All user-visible text and accessible names use the existing JSON keys with exact `en`/`lt`/`ru` key and placeholder parity. Persisted restaurant names remain domain data. Run both translation commands after changes.
- Prefer Flux UI Free or existing Blade components. Tailwind remains CSS-first in `resources/css/app.css`; utilities must be statically discoverable and repeated values use existing design tokens.
- Verify semantic headings/progress, labels plus associated descriptions/errors, keyboard focus, non-color status, action-specific loading/offline states, duplicate-submit disabling, touch targets, reduced motion, forced colors, 200% zoom, long translations, and 320px reflow without horizontal overflow.
- Use browser automation only with a disposable profile. Exercise validation, navigation, completion/resume, responsive widths, console/network errors, and the accessibility tree when the changed behavior is browser-facing.

## Verify and report observed evidence

Run the narrowest Pest file/filter during TDD. Before completing a workflow hardening change, run the applicable repository gates and record their actual outputs:

```bash
php artisan test --compact <focused-test-path-or-filter>
vendor/bin/pint --dirty --format agent
composer analyse
php artisan translations:scan --json
php artisan translations:audit
npm run build
```

Use `composer ci:check`, `composer test:browser`, and `composer test:coverage` when the change breadth or canonical requirement requires those full gates. Validate migrations/factories/seed idempotency when touched, and run dependency audits for dependency or release work. Clear locally built Laravel caches before tests when necessary.

Finally inspect the scoped and combined diff and perform a focused security review. Search for direct Eloquent in Livewire, queries/business logic in Blade, raw SQL, unauthorized public IDs, cross-tenant reads, partial writes, float money, hardcoded visible strings, translation drift, dynamic Tailwind fragments, accidental TODO/debug code, missing factories/indexes, and tests that only hide UI. Re-run every affected gate after a fix.

Never report a command as passing unless its result was observed in the current checkout. Distinguish focused from full evidence, state genuine environmental blockers exactly, and finish with the current Git status without claiming ownership of unrelated changes.

---
name: restaurant-workflow-hardening
description: Audit and harden multi-step Laravel/Livewire workflows in this restaurant-menu repository when persistence, retries, tenant authorization, or end-to-end UI quality must be verified. Use for workflows such as restaurant onboarding, not for an isolated copy or styling edit.
metadata:
  short-description: Harden restaurant workflows safely
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> **GitHub restriction — current user instruction.** GitHub is allowed only as the remote destination of an ordinary `git push`; create commits locally with `git commit`. Do not use GitHub for any other read or write: no API, MCP, plugin, `gh`, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses or remote checks. Do not open GitHub links, change repository settings/integrations, or create, edit or delete `.github/workflows/*`. Do not run fetch/pull/ls-remote or make an extra GitHub request to verify a push. Use local history and local quality gates; report the actual push command result. Historical GitHub references below are archival evidence and grant no authorization. Keep this single marked block in every tracked or newly created project Markdown file, after YAML frontmatter when present. Do not modify external skills or generated dependencies.
<!-- END GITHUB_PUSH_ONLY -->

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
- For an already-authorized local application-database forward migration, freshly verify the effective environment, exact SQLite path and pending migrations, create a consistent private backup with integrity and isolated restoration proof, apply only reviewed migrations, then verify intended schema changes and row-count preservation as described in [deployment](../../../docs/deployment.md) and [operations](../../../docs/operations.md).
- Keep money as decimal strings or integer minor units, never binary floats.

## Compound saves and local media

- Treat one branch-settings Save submission as one operation across settings, public profile, uploads, temporary closure and opening hours. Validate the whole payload in `BranchSettingsForm`, then invoke `SaveBranchConfigurationAction` once; it reloads and authorizes the branch/settings before composing the focused Actions inside one transaction. Separate child-Action transactions do not make a sequential component save atomic. Independent controls may retain their own operation when that matches the UI contract.
- Reuse `ReplaceLocalImageAction` and `RemoveLocalImageAction`. A persistence callback returning, or a nested transaction committing, does not mean the enclosing transaction committed. Preserve the old file until the owning connection's `afterCommit`; replacement cleanup on persistence failure or `afterRollBack` removes only newly stored files. The shared Actions own their persistence transaction even for standalone callers; replacement rollback compensation is registered before invoking persistence, and old-file cleanup runs after the outer commit. Follow the [Eloquent required-write guidance](../laravel-best-practices/rules/eloquent.md) for cancelled model writes and unsaved creation results.
- For entity logo/cover replacement or removal, reload the active row within its original parent scope and resolve the current file path inside the owning transaction. Reusing a stale model, including after outer rollback, must use the persisted path for cleanup. Synchronize only the changed image attribute and timestamp back to the caller, preserving unrelated unsaved state.
- Rollback compensation must not throw, including when warning logging fails. Reuse `DeleteRolledBackLocalImageAction` for newly stored files so Laravel can discard transaction callbacks. Prove all variants are attempted, the original exception survives, and a subsequent transaction cannot run stale old-file deletion. See `tests/Feature/MediaRollbackCleanupFailureTest.php`; ordinary deletion and after-commit cleanup must retain explicit failure/retry semantics. A disk-refused rollback file may remain unreferenced and is not a durable cleanup receipt.
- An exception during old-file cleanup after commit cannot roll back the persisted path. Preserve the referenced replacement and report the cleanup failure; do not catch the entire operation as a persistence failure and delete the new file.
- Inspect `tests/Feature/BranchSettingsTest.php`, `tests/Feature/LocalImageTransactionTest.php`, `tests/Feature/MediaPersistenceFailureTest.php` and `tests/Feature/EntityImageRetryTest.php` when changing these boundaries. Prove late-step failure restores every earlier database field and old file, nested commit followed by parent rollback removes new files, inner rollback followed by parent commit preserves surviving files, and post-commit cleanup failure keeps the committed replacement. Passing child-Action happy paths alone does not prove the compound save.

## Polling, restore and resumable operations

- For waiter polling, follow [the exact snapshot rule](../../../.ai/rules/app.md#fingerprint-the-exact-authorized-polling-snapshot): test same-second edits, older-record edits, delete/add replacements and changes between payload reads. Compare query and hydration costs for the requested section.
- For SQLite restore, follow [the request barrier rule](../../../.ai/rules/app.md#keep-the-restore-barrier-outside-the-restored-database): prove in-flight session writes drain, old credentials cannot be recreated, and restore plus rollback failure keeps access blocked. Use isolated temporary databases and coordinate CLI writers separately.
- For resumable catalogue work, follow [the owned receipt rule](../../../.ai/rules/menu.md#resume-bounded-catalogue-operations-from-owned-receipts): exercise lost responses, completed replay, revoked access, bounded continuation and cleanup retry. Prove completion preserves another open draft and validates selections within the chosen branch and menu.
- For catalogue image removal or promotion, follow [the image receipt rule](../../../.ai/rules/menus.md#bind-image-mutations-to-rendered-identity-and-a-durable-receipt): bind the rendered path identity and request UUID to the actor and operation, check the receipt before resolving a potentially removed gallery row, and retry cleanup through the existing ledger. Prove stale confirmations, lost responses, ABA replacement, outer rollback and cleanup continuation that never enters catalogue deletion.

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

- Preserve configured guest input and its idempotency key across availability conflicts. Retry must re-resolve current scope; closing a panel whose card disappeared returns focus to a stable heading. URL locale persistence requires an active restored guest, matching locale events. Exercise offline controls in a browser and restore the online state before assertions can abort the test.
- All user-visible text and accessible names use the existing JSON keys with exact `en`/`lt`/`ru` key and placeholder parity. Persisted restaurant names remain domain data. Run both translation commands after changes.
- Prefer Flux UI Free or existing Blade components. Tailwind remains CSS-first in `resources/css/app.css`; utilities must be statically discoverable and repeated values use existing design tokens.
- Verify semantic headings/progress, labels plus associated descriptions/errors, keyboard focus, non-color status, action-specific loading/offline states, duplicate-submit disabling, touch targets, reduced motion, forced colors, 200% zoom, long translations, and 320px reflow without horizontal overflow.
- Use browser automation only with a disposable profile. Exercise validation, navigation, completion/resume, responsive widths, console/network errors, and the accessibility tree when the changed behavior is browser-facing.

## Verify and report observed evidence

Run the narrowest Pest file/filter during TDD. Before completing a workflow hardening change, run the applicable repository gates and record their actual outputs:

```bash
php artisan test --compact <focused-test-path-or-filter>
vendor/bin/pint <owned-php-paths> --format agent
composer analyse
php artisan translations:scan --json
php artisan translations:audit
npm run build
```

For an independently owned parallel task, pass explicit owned PHP paths to Pint without `--dirty`; combining paths with `--dirty` can still format another contributor's dirty files. The coordinating agent runs the repository-required `vendor/bin/pint --dirty --format agent` only after workers and shared edits are stable, then repeats affected checks if formatting changes code.

Use `composer ci:check`, `composer test:browser`, and `composer test:coverage` when the change breadth or canonical requirement requires those full gates. Validate migrations/factories/seed idempotency when touched, and run dependency audits for dependency or release work.

Before testing, inspect active test/browser/cache-build processes and confirm the effective testing database and cache paths. Use an owned temporary path for `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, `APP_EVENTS_CACHE` and `VIEW_COMPILED_PATH` when shared compiled artifacts could interfere, with SQLite `:memory:`, empty `DB_URL`, and array cache/session stores for ordinary tests. File-backed or database-cache tests must own their temporary database/store and cleanup. Do not run `optimize:clear`, `cache:clear` or clear another process's compiled caches to get a green run; coordinate shared operations with their owner and keep the application database untouched.

Finally inspect the scoped and combined diff and perform a focused security review. Search for direct Eloquent in Livewire, queries/business logic in Blade, raw SQL, unauthorized public IDs, cross-tenant reads, partial writes, float money, hardcoded visible strings, translation drift, dynamic Tailwind fragments, accidental TODO/debug code, missing factories/indexes, and tests that only hide UI. Re-run every affected gate after any relevant source/configuration change, including another contributor's edits.

Record the command, exit result and digest of its relevant source/configuration before and after each final gate. A passing result proves only that unchanged tested snapshot; a shared checkout path or an old full-suite report is not enough. If files change during a run, reconcile the changes and repeat the affected checks before claiming the final state verified. Distinguish focused from full evidence, state genuine environmental blockers exactly, and finish with the current Git status without claiming ownership of unrelated changes.

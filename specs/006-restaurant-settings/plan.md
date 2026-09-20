<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Implementation plan — Prompt 14

## Technical context
PHP8.5; installed Laravel13.31, Livewire4.4.1, Flux Free2.17/local Pro0.1.1, SQLite IMMEDIATE/WAL, Eloquent, existing JSON catalogues, Sass/Vite8. No package changes. Main preserved with substantial prior staged/unstaged changes captured in owned temporary evidence directory.

## Constitution check
Canonical scope retained; focused Forms/Rules/Policies/Actions; per-group allowlists and reauthorization; additive schema and preserved data; EN/LT/RU accessible SSR; TDD plus observed checks. Spec identifier is not a new branch. No external GitHub lookups/hooks/deployment.

## Sequence and owners
1. Root: source/consumer map, specs, additive receipt+translation schema, models, shared translations and integration.
2. Profile agent: first vertical profile Form/Action/presenter + media integrity + guest readers/tests.
3. Settings agent: four bounded Forms, group fingerprint/write/receipt contract, defaults, currency guard across writers, real guest entry enforcement/tests.
4. Cleanup agent: current-activity recheck and read-only bounded preview/confirmed command/tests.
5. Root: one Settings page, Flux Pro navigation/upload/confirm, URL/search/focus/dirty bridge, SCSS, translations and meaningful Livewire/browser tests.
6. Independent review agent after implementation: review changed boundaries and adversarial test gaps. Root resolves integration and runs canonical gates in isolated databases/snapshot.

## Design decisions
Hashes cover exact owned persisted group fields rather than global timestamps. Transactional request receipts bind branch/actor/group/payload+expected version. Replays reauthorize. Reading missing settings uses unsaved effective model from canonical defaults; first explicit settings save uses unique branch constraint. Public profile text/contact writes never initialize financial settings. Monetary changes recheck dependencies inside write serialization. Profile JSON preserves absent legacy language; null clears explicit optional translation. Media separately committed and clearly labelled.

See research.md for source map, data-model.md for schema, contracts/settings.md for UI/API, tasks.md for execution.

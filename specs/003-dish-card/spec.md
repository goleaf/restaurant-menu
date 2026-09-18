<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Feature Specification: Prompt 6 unified dish card

**Date**: 2026-09-18. **Status**: implementation in progress. **Baseline**: clean main b3c7f81.
This working specification maps `sys-dish-001` in docs/requirements.md; it does not replace the canonical catalogue or the P6R status in docs/IMPLEMENTATION_PLAN.md.

## User Scenarios & Testing

### US1 — Edit one dish without losing its context (P1)
Open a dish once from the catalogue or an addressed section; edit the original and optional translations, save, and remain on that dish. Existing legacy base content appears when its original translation row is absent; an explicitly empty translated description stays empty. Opening, changing language and section write no business data. Moving within the restaurant to another allowed menu/category retains visited variant/modifier drafts and their original conflict versions. Return preserves bounded catalogue filters/page.

### US2 — Save independent resources safely (P1)
Photos, variants and modifier operations confirm separately inside the same card, retaining unsaved main input. Price and availability permissions, actor identity, restaurant ownership and active authoring context are checked again at write. Stale archive or target substitution must not mutate a different/archived dish. Concurrent replay creates one result. Shared changes disclose impact; disconnect preserves the group; independent copying substitutes only this dish's binding. Images retain stable identity, combined limit and safe retry/cleanup.

### US3 — See an honest preview and create-and-continue (P1)
Saved preview matches guest content for the chosen content language, including missing versus intentionally empty translations. Draft preview is explicitly labelled; changes to main or related configuration cannot make an old prepared price look current. Preview never creates guest/cart/order/publication. Explicit creation returns to the same canonical card, unavailable under existing rules, with response replay and no placeholder GET writes.

## Functional Requirements

FR-001 Preserve the existing four-section card, canonical entrances, URL state and separate interface/content languages (US1).
FR-002 Resolve missing original translation from legacy base values without treating intentional blank text as absent; explicit save remains canonical (US1, US3).
FR-003 Retain child input and immutable dish identity when its permitted menu/category changes; do not refresh conflict baselines to bypass a conflict (US1).
FR-004 Reject stale archived authoring and altered editor targets before any business/audit/receipt write; historical relations remain intact (US2).
FR-005 Preserve resource-specific versions/replays, absolute variant price plus modifier deltas, shared impact/copy rules, image lifecycle and immutable accepted orders (US2).
FR-006 Invalidate stale preview independently of transport dirty state; recompute only on explicit preparation (US3).
FR-007 Verify existing creation, media, permissions, shared groups, locale errors, offline/history and accessible responsive behavior with current-source tests; repair only reproduced relevant gaps (all).
FR-008 Measure identical-fixture queries/models/memory/HTML/payload and user actions; distinguish accepted runtime, coverage and blocked experimental execution (all).

## Key Entities
Dish with canonical English content and translations; independent image identity/presentation; variant aggregate; restaurant-shared modifier group/options; dish-to-group bindings; scoped command receipts; immutable accepted-order snapshots. No new business entity or migration is presumed.

## Success Criteria
SC-001 A user selects a dish once, manages all four sections, and returns to the original list without another dish selection.
SC-002 Every reproduced lifecycle/security regression has a failing baseline and passing repaired test; no unrelated draft or historical order changes.
SC-003 A prepared preview is either current for its declared input or visibly requires refresh, with no fabricated price.
SC-004 Current complete applicable backend/browser inventories run without hidden skips; application coverage remains at least 90%; source/build/runtime evidence is recorded separately.

## Assumptions and scope
The substantial implementation present in b3c7f81 is preserved and reverified, not credited solely from historical docs. Current availability, shared workspace, library and guest calculator remain authoritative. No platform upgrade, controller, SPA, worker, new global draft system, working-database change or deployment. User authorizes reversible decisions and local main work without further questions.

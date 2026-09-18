<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Implementation Plan: Prompt 6 current-source completion

**Branch**: existing main (Spec Kit feature identifier 003-dish-card). **Date**: 2026-09-18. Canonical owner/status: docs/IMPLEMENTATION_PLAN.md P6R.

## Technical Context
Use installed Laravel13, Livewire4 class components/Forms, FluxFree2/localPro0.1.1 unknown upstream, PHP8.5, SQLite IMMEDIATE, existing Actions/Policies/read services, SCSS and separate Tailwind bridge. Refresh exact versions before API use. Boost application-info/search-docs currently fail JSON parsing; installed source and opened official documentation are the fallback. No dependency changes.

## Constitution Check
PASS: sole requirement catalogue; server Actions/Policies, bounded reads and no Blade business logic; actor/tenant/identity protection and preserved history; EN/LT/RU and existing design/dirty guard; RED→GREEN and exact-source gates. Main stays unchanged; no hooks/extensions registered, GitHub only ordinary push. No working DB or runtime switch. Recheck at convergence.

## Research and confirmed scope
The canonical Dish and independent resource Actions already exist. Read-only audits identify missing original translation fallback, saved-preview missing-row collapse, stale child menu context, transport-only preview freshness and active-parent variant gaps. Each code change requires reproduction first. At initial audit, variant retarget and true configuration concurrency were bounded test candidates; T006/T007 subsequently reproduced the retarget defect and verified real concurrent replay. Existing media and clone contracts need current regression evidence rather than replacement.

## Ownership and sequence
Root owns Dish.php, all shared Forms, shared routes/translations/entrypoints, docs/Spec Kit and integration. Media reviewer owns DishPreviewQuery and a dedicated DishPreviewParityTest; after that task, ownership of CatalogData/DishQuery/item-editor/DishPerformanceTest transfers from root for the measured initial-load optimization. Root retains all translations. Domain owner owns variant Actions, DishConfigurationData, Variants.php and focused variant safety/concurrency tests. UI owner owns Modifiers.php, dish Blade/preview Blade and focused retained-context/preview browser tests; coordinate Variants changes through its owner. No simultaneous edits to a file, staging, builds or browser runs; independent tests use separate owned environments. Root implements first existing-dish main save/fallback slice, then integrates retained child context and truthful preview. Common JS/SCSS remains root until explicit transfer.

## Verification
Owned root restaurant-p6-we31du9x contains immutable clean baseline archive and runner. Stable PHP explicit; test DB/storage/cache isolated. Run focused baseline RED first; no mock of primary business operation. Domain regressions, process SQLite races, guest/order/media/copy contracts, browser one-card journey and responsive screenshots; matched baseline measurements. Final freeze once reviewed, then canonical analyse, complete backend/parallel/coverage, browser inventory, JS coverage, translations, SCSS/generated styles/build/budgets, applicable caches/schema/seeds/audits. PHP8.6 actual platform gate is separate. Conserve 3.8GiB free disk; avoid redundant dependency copies. Local reviewed scoped commit/push only after gates, no remote verification.

## Structure
Reuse app/Livewire/Organizations/Brands/Branches/Menu, app/Livewire/Forms/Menus, app/Services/Menus, app/Actions/Menus and existing modifier/media Actions; separate Blade/SCSS and tests. No new giant coordinator or alternative calculator.

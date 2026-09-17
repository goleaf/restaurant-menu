<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Implementation Plan: Restaurant center — Prompt 4

**Branch**: `main` | **Date**: 2026-09-18 | **Spec**: [spec.md](spec.md)
**Authoritative plan**: [docs/IMPLEMENTATION_PLAN.md](../../docs/IMPLEMENTATION_PLAN.md), P4S continuation.

## Summary
Verify and finish the existing center and four-group preparation through narrow regression-driven changes. Current source already implements explicit creation, private progress, drafts and canonical editors. Confirm gaps before edits; first prove second-restaurant preservation.

## Technical Context
PHP 8.5 supported; isolated PHP 8.6 prerelease only. Laravel13, class Livewire4, separate presentation Blade, local Flux Pro0.1.1 (unknown upstream), Free2.17, Tailwind4 CSS bridge plus token-based SCSS, Vite8. SQLite and local storage; Pest4, installed Playwright browser harness and canonical Composer/Node gates. Shared hosting, no new background runtime or dependency update. Bounded current-page queries and no readiness scan on each search key.

## Constitution Check
All five principles apply: canonical IDs/plan remain authoritative; Forms/Rules/Policies/Actions preserve server boundaries; current tenant/auth/SQLite/idempotency protections; EN/LT/RU/accessibility/SCSS; observed regression and full evidence. Working DB, index, dependencies and unrelated changes remain intact. No branches/hooks/GitHub calls or deployment. Both pre-design and post-design checks pass; verification gates remain tasks, not assumed passes.

## Phases and ownership
1. Root: checkpoint source/index, requirements alignment, this working spec and existing ledger. Domain agent: audit second creation and bounded regressions. Test first/second/replay/menu preservation/schema before changes.
2. Domain owner: RestaurantSetup.php and RestaurantSetupSelectionTest.php; reject malformed existing menu/space IDs before casts; inspect remaining domain callers and run safety/concurrency cases. Root owns any needed migrations.
3. Center owner: RestaurantCenterQuery.php, Restaurants/Index.php, RestaurantCenterFilterForm.php, RestaurantCenterAccessStateTest.php; policy-consistent archived visibility and initial URL parent normalization, preservation of list context.
4. UI owner: four restaurant/setup Blade views, _restaurant-center.scss, RestaurantCenterInterfaceTest and RestaurantCenterBrowserTest; named dialogs, error relationships/contrast, image/placeholder and actual browser matrix.
5. Root: routes, translations, existing wizard/journey integration tests, canonical docs, complete local gates and runtime checks, schema clean/upgrade, measured costs. Specialists inspect final diff independently of authors. Exact pending tasks are in tasks.md and authoritative ledger.

## Project Structure
Existing app/Actions/Onboarding, app/Livewire/{Restaurants,Onboarding,Forms}, app/Services/{Onboarding,Organizations}, app/Policies and existing schema retain ownership. Tests live under tests/Feature, tests/Browser and tests/Support. No application controller or parallel readiness/context abstraction.

## Verification and delivery
Small owned environments first. Freeze source for full backend/browser/coverage/build/static/translation evidence; record source hashes, discovery totals, runtime and exact blockers. Stable PHP coverage >=90%, JS line threshold100% unchanged. Review actual diff and attributable staged candidate. Commit/push only after required checks; existing failing unrelated gates stay explicit. No remote verification after ordinary push.

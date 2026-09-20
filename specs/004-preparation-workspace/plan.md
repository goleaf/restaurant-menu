<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Implementation Plan: Unified preparation workspace

**Branch**: main | **Date**:2026-09-20 | **Spec**:[spec.md](spec.md)

## Summary
Reuse shared Departments Dashboard/Actions, isolate authorization unions, correct full aggregates, preserve versioned transitions and durable existing-event notifications, then deliver one Flux/SCSS queue and print contract. Canonical status belongs to docs/IMPLEMENTATION_PLAN.md.

## Technical Context
PHP>=8.5<8.6; Laravel13.31, Livewire4.4.1, FluxFree2.17/localPro0.1.1, SQLite, independent Sass and Tailwind4. Shared hosting/Herd, no new services or runtime upgrade. Pest4/PHPUnit12, Node tests, real isolated browser suite. Queue24 tickets/page; detail100 rows/page; batch24 explicit rows/one ticket. Full business aggregates never share display limits.

## Constitution Check
Pass design checks: canonical requirements referenced; one class/view coordinator and focused Form/Action/query/policy boundaries; branch and actor reauthorization/CAS/replays preserved; translations/tokens/accessibility included; TDD and exact-source verification required. No raw SQL, ordinary controller, new permission grants, working database mutation, external GitHub request, hooks or workflow edits. PHP8.6 application support remains subject to actual platform gates. Post-design check: no hard-rule exceptions.

## Project Structure and ownership
- Root: app/Livewire/Departments, thin Kitchen/Bar entries, Forms/Departments, shared enums, Services/Navigation, routes, translations, docs and integration tests.
- access_context: access resolvers, KitchenTicketPolicy, department lifecycle guards and focused access tests; minimal management error presentation.
- queue_aggregates: BuildDepartmentDashboard/DelayTimer/Print, SyncOrderStatusFromTicketItems and new aggregate tests.
- transitions_notifications: status mutation, existing-event notification delivery, bounded batch Action and new mutation/concurrency tests.
- After backend boundaries settle, available agent owns preparation Blade/SCSS/JS and another performs independent browser/tests/review. One active owner per file; no worker commits.

## Execution
1. Prove canonical entry and separate kitchen/bar scope with failing/passing Livewire tests.
2. Integrate exact URL/form context, one visible queue, full aggregate/detail contract and navigation.
3. Integrate versioned single/batch actions, explicit partial outcomes and notification retries.
4. Add truthful event timers, snapshot print, lifecycle diagnostics, offline/compact/focus behavior.
5. Run targeted then aggregate stable gates on isolated data; browser mixed handoff and source-equivalent measurements; independent review; convergence and accurate docs.
6. Scoped local commit and ordinary origin push only after required gates; preserve incoming dirty work. No extra remote verification.

## Complexity Tracking
No new framework or order entity. Existing status log gains minimal delivery metadata because a committed production event otherwise loses its notification on retry. All other persistence contracts are reused.

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Implementation Plan: Prompt 5 floor workspace

**Git branch**: `main`. **Date**: 2026-09-18. **Spec**: [spec.md](spec.md).
Authoritative execution: `docs/IMPLEMENTATION_PLAN.md` P5R. Baseline5e2987c is clean; archive/index/diffs are in `restaurant-p5-z2py0b1b`. The existing partial floor implementation and accepted Prompt4 remain intact.

## Technical Context

PHP8.5.10 CLI, Laravel13.31.0, Livewire4.4.1 classes/separate Blade, FluxFree2.17.0/localPro0.1.1 (unknown upstream), SQLite IMMEDIATE transactions, SCSS/Tailwind4/Vite8.3, Dompdf3.1.6/Bacon3.1.1. Use current locks and Herd; no dependency upgrade. Existing Forms/Policies/Actions/read services and FloorOperation/prepared-print storage remain the architecture. Tests use only owned SQLite/storage/cache. PHP8.6 execution depends on real Composer constraints.

## Constitution Check

PASS: single canonical requirement catalogue/status ledger; server-owned writes and bounded reads; current tenant/actor authorization and stable identity; localized accessible Flux/SCSS composition; TDD plus exact-source acceptance. No Git branch/worktree/hooks/workflow/GitHub lookup. No working DB, APP_KEY, roles or permanent QR changes. No new controller, global state owner, worker or generic manager. Recheck these gates in analysis/convergence.

## Confirmed current gaps

1. FloorFilterForm rejects legitimate integer URL zone hydration; existing transport coverage omits this parameter.
2. Area write/read depth limits disagree and reparent does not account for subtree depth; parent search shares areasPage and stale selected lifecycle context can disappear. Created room lacks a parent URL event; invalid hierarchy/type-label presentation is incomplete.
3. PointEditor exposes mutable hidden area input despite a separate move contract. All-skipped bulk result dispatches an empty selection that parent rejects. Existing account guard and direct/merged service safety are present and must not be duplicated.
4. Print panel omits per-target missing-QR disclosure; QR print event replaces previous selection. Image readiness trusts file existence. Real alternate-Host, partial-write, print-only layout and Livewire stylesheet-load hypotheses require RED evidence before fixes.
5. Mobile has a long three-region stack; named unsaved dialog and post-archive pagination recovery need acceptance. Two legacy area Blade files appear orphan; remove only after reference proof.

## Ownership and implementation sequence

Root owns Index.php, FloorFilterForm and other shared Form/validation changes, index Blade, _floor.scss, floor-workspace.js/tests, shared routes/translations/migrations, onboarding link integration, docs and Spec Kit. Root first proves area URL→point→rename with stable identity, then integrates selection/mobile/return behavior.

Area specialist exclusively owns AreaNode Actions, AreaNodeQueryService, AreaEditor PHP/Blade, FloorAreaSafetyTest/AreaNodeCrudTest and focused new area tests. Table specialist owns ServicePoint Actions, PointEditor PHP/Blade, BulkCreate PHP/Blade, FloorServicePointSafetyTest/BulkServicePointActionTest and focused new domain tests; request shared Form changes from root. QR specialist owns QrCode Actions/services, QrPanel/PrintPanel PHP/Blade, prepared-print support, QR print SCSS and QR tests/new floor browser journey. Root coordinates any cross-owner API/translation change before edits. No agent stages or commits. Independent reviewers swap domains after implementation.

P5R.1 initial vertical slice → P5R.2 area/tree and P5R.3 table/bulk domain in parallel → P5R.4 QR/print and root responsive integration → P5R.5 legacy cleanup/localization → P5R.6 final real browser/documents/matched measurements/full gates → P5R.7 non-author review and delivery. Existing functionality is tested rather than recreated.

## Verification

Use existing owned-environment runner and explicit stable PHP; minimum failing Pest case precedes each change. Add real Livewire POST/account/revocation cases; retain process-based SQLite races. Browser/build executions are serialized. Measure baseline archive and candidate with identical fixtures/assets where possible and declare remaining asset differences. Decode/rasterize generated documents; inspect real screenshots. Final discovery must exactly match executed backend/browser inventories; all worker artifacts accounted for. Run canonical analyse, Pint, JS coverage, translations, SCSS/generated styles, build/budgets, caches and applicable schema/seed tests. Keep original diagnostics and supported/experimental/web runtime identities separate. Commit scoped verified files and ordinary push only; no remote verification/deployment.

## Structure

Reuse `app/Livewire/Organizations/Brands/Branches/ServicePoints/`, `app/Livewire/Forms/Floor/`, `app/Actions/{AreaNodes,ServicePoints,QrCodes}/`, `app/Services/{Branches,QrCodes}/`, `app/Support/Floor/`, matching Blade/SCSS, Feature/Browser suites. No competing roadmap or abstraction layer.

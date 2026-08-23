# Restaurant Menu completion decisions

These decisions scope the 2026-08-23 repository-completion run. Long-lived architecture decisions remain in [`decisions/`](decisions/); [`requirements.md`](requirements.md) remains the sole product contract.

## D-001 — Preserve the shared main worktree

Work continues on `main` because the user prohibited new branches. Existing and concurrent changes are preserved and reconciled before edits; no reset, clean, restore, stash, history rewrite, broad stage, force-push, destructive database refresh, or production deployment is permitted.

## D-002 — Treat the 49-row catalogue as active scope

Code and executable tests were checked against the canonical catalogue and compliance matrix. GitHub issue #10 describes a possible shared-allocation feature but has no approved requirement contract, so implementing schema and behaviour now would be speculative and unsafe. It stays P2 until normal change control adds a stable requirement.

## D-003 — Separate local readiness from external release evidence

All reversible local release gates are part of P1. Publication, exact remote release SHA, production health/log/alert checks, physical devices, non-local browsers, and physical assistive technology require external access or actions explicitly excluded from this run. They remain visible P2 acceptance work rather than being falsely marked complete.

## D-004 — Keep completion documents subordinate

The user explicitly requested `IMPLEMENTATION_PLAN.md`, `PROGRESS.md`, and `DECISIONS.md`, despite the prior roadmap rule against duplicate implementation plans. These files are bounded execution/evidence ledgers, not product roadmaps or requirement sources; `ROADMAP.md` remains the GitHub issue index.

## D-005 — Adopt the concurrent factory-backed demo graph

The expanded four-branch demo graph matches `seed-demo-001`, removes database-creating side effects from default factory definitions, and adds deterministic bar/order/payment/history coverage. It is treated as the P0 baseline after targeted, idempotency, static-analysis, and full-suite verification, not merely because it was committed.

## D-006 — Make the settings redirect name additive

The only unnamed first-party application route was `/settings`. Adding `settings.index` satisfies the named-route invariant while retaining the existing redirect destination, auth middleware, URI, and established `profile.edit`, `appearance.edit`, and `security.edit` contracts.

## D-007 — Do not perform an unnecessary dependency migration

The installed dependencies match the declared target stack and audits are clean. Pest 5 is a major-version opportunity, not a current requirement; upgrading it during closure would expand scope and invalidate a stable Pest 4 baseline without a product or security benefit.

## D-008 — Serve the readiness endpoint as JSON for every client

Laravel 13's built-in health route preserves the required `DiagnosingHealth` event and generic 200/500 handling, but its HTML branch loads fonts and Tailwind from public CDNs and requests a favicon. A path-scoped request middleware forces only `/up` through Laravel's built-in JSON branch, retaining framework health semantics while making readiness deterministic, offline-safe, detail-free, and free of browser console/network noise.

## D-009 — Make release tests safe under concurrency and long coverage runs

Independent full and coverage processes may run in the shared checkout. SQLite restore tests therefore allocate unique database and local-filesystem roots per process/test instead of sharing candidates or safety snapshots. The canonical coverage script disables Composer's generic process timeout because the verified suite exceeds 300 seconds; Pest remains responsible for test failures and enforcing the unchanged 90% application floor.

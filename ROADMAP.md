<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Roadmap

Historical external roadmap snapshot from 2026-09-14; its links and statuses are retained as archives and must not be opened or reverified. Current work and acceptance are tracked locally in docs/IMPLEMENTATION_PLAN.md and docs/PROGRESS.md. It schedules work but does not redefine behaviour: [`docs/requirements.md`](docs/requirements.md) remains authoritative and [`docs/compliance-matrix.md`](docs/compliance-matrix.md) records implementation evidence. The bounded repository-completion run requested on 2026-08-23 is tracked separately in [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) and [`docs/PROGRESS.md`](docs/PROGRESS.md).

## Current state

The accepted Flux Pro extension (`ui-flux-pro-001`) is active after the modernization baseline below. Its local package is installed and its complete original source inventory is preserved. Current P0–P18 status is maintained in [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md). JavaScript and workflow acceptance precede the clean-release gates and removal of the original root source.

The production modernization programme is implemented. Runtime, security, data integrity, Livewire/Blade boundaries, localization, factories, seeders, tests, static analysis, build, observability, and SQLite backup/restore have repository-level verification. Completed milestones are summarized in [`CHANGELOG.md`](CHANGELOG.md); baseline and resolution evidence remain in [`docs/current-state-audit.md`](docs/current-state-audit.md).

## Open work

The table below is historical only. Current acceptance comes from the user-authorized scope, docs/requirements.md and the local implementation plan; no GitHub issue or remote-check access is required or permitted.

| Priority | Issue |
|---|---|
| Now | [#3 — Publish the verified modernization release](https://github.com/goleaf/restaurant-menu/issues/3) |
| Now | [#4 — Run all production release gates on the exact release commit](https://github.com/goleaf/restaurant-menu/issues/4) |
| Now | [#5 — Configure and verify production health, logs, and error alerts](https://github.com/goleaf/restaurant-menu/issues/5) |
| Next | [#7 — Validate critical workflows on physical devices and Safari/Firefox](https://github.com/goleaf/restaurant-menu/issues/7) |
| Next | [#8 — Complete physical screen-reader and assistive-technology review](https://github.com/goleaf/restaurant-menu/issues/8) |
| Later | [#10 — Define and implement shared draft-item allocations between guests](https://github.com/goleaf/restaurant-menu/issues/10) |

The external evidence gaps are summarized in [`docs/known-limitations.md`](docs/known-limitations.md). Issue #10 requires an approved requirement contract before implementation.

## Roadmap hygiene

- Preserve historical links without opening or updating them. Record completed local outcomes in `CHANGELOG.md` and `docs/PROGRESS.md`.
- Do not create competing product roadmaps, prompt queues, or duplicate issue backlogs. `docs/IMPLEMENTATION_PLAN.md` is a temporary evidence-backed completion plan, not a second source of product requirements.
- Keep current acceptance criteria, ownership, dependencies and status in the existing local plan. Do not use GitHub for tracking or verification.
- Never use roadmap text to weaken active requirements, security controls, or tests.

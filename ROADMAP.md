# Roadmap

Updated 2026-08-23. This is the repository's only active roadmap. It schedules work but does not redefine behaviour: [`docs/requirements.md`](docs/requirements.md) remains authoritative and [`docs/compliance-matrix.md`](docs/compliance-matrix.md) records implementation evidence.

## Current state

The production modernization programme is implemented. Runtime, security, data integrity, Livewire/Blade boundaries, localization, factories, seeders, tests, static analysis, build, observability, and SQLite backup/restore have repository-level verification. Completed milestones are summarized in [`CHANGELOG.md`](CHANGELOG.md); baseline and resolution evidence remain in [`docs/current-state-audit.md`](docs/current-state-audit.md).

## Open work

GitHub Issues is the only source for acceptance criteria and status. This table is a priority index, not a duplicate backlog.

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

- Keep only links to open GitHub issues here; move shipped outcomes to `CHANGELOG.md` and close the issue with evidence.
- Do not recreate `NEXT_STEPS`, implementation-plan, prompt queues, or parallel roadmaps.
- Keep acceptance criteria, discussion, ownership, and status in GitHub Issues.
- Never use roadmap text to weaken active requirements, security controls, or tests.

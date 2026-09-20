<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Feature Specification: Personal display formats

**Feature Branch**: `main` (preserved shared branch)
**Created**: 2026-09-20
**Status**: Approved scope from user request; implementation in progress
**Input**: Add personal settings for formats such as `24.08.2026 15:00`, analyze other useful user settings and begin implementation.

This working specification refines `i18n-001`, `sys-auth-001`, `livewire-001`, `blade-001`, `ui-accessibility-001` and `ui-responsive-001` in [canonical requirements](../../docs/requirements.md). [The implementation plan](../../docs/IMPLEMENTATION_PLAN.md) owns status.

## User Scenarios & Testing

### User Story 1 — Choose dates and times (Priority: P1)

An authenticated user chooses a familiar date format and 12/24-hour time independently of interface language, sees an example, and saves these choices in Profile.

**Independent test**: Save dotted day-first dates and 24-hour time, reload, and see `24.08.2026 15:00` for the example in each supported language.

1. Existing users start with the current language-based formats.
2. Choosing an option changes the example without saving the account or another open profile draft.
3. Saving survives a new session; resetting the form to language defaults requires explicit Save.
4. Invalid or tampered values fail validation and cannot change another account.

### User Story 2 — Choose number separators (Priority: P1)

A user chooses among language default, `1,234.56`, `1.234,56`, `1 234,56` and `1 234.56`. A number and EUR amount example show the result; the actual restaurant currency remains authoritative.

**Independent test**: Save a separator preset and verify positive, negative and zero money displays and ordinary numbers, with unchanged exact stored amounts.

1. Numbers and sums use the selected separators; currency symbols remain localized.
2. Two users of the same language and restaurant can use different formats, including cached panels.
3. Guest menus continue to follow guest language even when opened by a signed-in administrator.

### Edge cases

Null dates, midnight/noon, seconds, timezone boundaries, invalid stored preferences, malformed Livewire payloads, signed amounts, stale cache refresh, offline save and locale changes must have deterministic behavior. Relative phrases remain language-based. Machine timestamps, browser-native input values, exported CSV data and restaurant-local schedules remain canonical.

## Requirements

- **FR-001**: Expose date, time and number format settings with live examples and explicit Save in the existing Profile page.
- **FR-002**: Allow only finite supported presets; no arbitrary formatting expressions.
- **FR-003**: Store choices per authenticated account, independent of name/email, theme and restaurant settings.
- **FR-004**: Apply preferences through shared human-facing formatters and isolate preference-bearing cached output.
- **FR-005**: Preserve guest presentation, currency, timezone, storage, arithmetic, exports and authorization.
- **FR-006**: Localize every new label, validation attribute and confirmation in EN/LT/RU; support keyboard, 320px layouts and offline states.
- **FR-007**: Record the broader settings inventory with concrete existing behavior and priorities; do not add speculative notification or timezone controls without their domain contracts.

### Key entities

- User: three independent display-format choices; default means follow the active interface language.
- Display examples: illustrative fixed date/time, number and EUR amount; never saved as business data.

## Success Criteria

- **SC-001**: All three languages can display the requested `24.08.2026 15:00` example after selection.
- **SC-002**: Choices survive reload/login and affect only their owner; invalid input writes nothing.
- **SC-003**: Shared cached panels and guest menus never expose a different user's presentation choices.
- **SC-004**: Profile format controls remain usable at 320px and by keyboard, with no browser errors.

## Assumptions

The request concerns presentation, not changing restaurant currency, units or operating timezone. The first delivery covers date, time, number and monetary presentation and an analysis of further personal settings. Existing native date/time input conventions and machine-readable exports are intentionally preserved. No dependency upgrade, GitHub operation, commit or deployment is requested.

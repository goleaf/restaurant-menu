<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Prompt 14 — Restaurant settings center

Current branch: main. Canonical authority: docs/requirements.md; execution ledger: docs/IMPLEMENTATION_PLAN.md. Requirements mapped: sys-branch-001, sys-settings-001, sys-tenant-001, data-integrity-001, livewire-001, i18n-001, ui-flux-pro-001, ui-accessibility-001, ui-responsive-001.

## User stories and acceptance

### US1 (P1): Save only the intended profile
An authorized restaurant manager edits guest text/contacts independently from financial and service settings. Two stale pages saving different groups preserve both changes; same-group stale writes fail visibly. GET/search/preview never create settings. All mutations reauthorize. Form errors and drafts of other groups survive.

### US2 (P1): Understand operational and settlement choices
Five sections: Public profile; Guests and orders; Settlement; Language and local time; Advanced. Each has Save/Cancel, dirty/success/offline states. Supported behavior is distinguished from legacy descriptive modes. Waiter confirmation is invariant. Currency is branches.currency; compatibility field stays synchronized only during an authorized currency operation. Existing monetary data/unfinished visits block currency changes. New data after preview is rechecked atomically. Service-charge basis points/tips preserve payment history.

### US3 (P1): Publish truthful localized presentation
EN/LT/RU independent name/description translations, legacy values retained without inferred locale; selected→default→legacy→internal-name fallback. Shared safe presenter for real guest/preview; no sessions/drafts created by preview. HTTP(S)-only links. Separate logo/cover operations retain fallback provenance, version/replay/file-compensation safety.

### US4 (P1): Review bounded maintenance
Configuration saves never run cleanup. Read-only preview shows cancellations/warnings/skips and limit. Confirm binds actor, restaurant, settings and explicit candidates; current activity, orders, drafts, status and policy are rechecked. Replays never duplicate transitions/audit.

### US5 (P2): Navigate without losing work
Search task labels/help, URL allowlisted section/language/group only, keyboard focus, existing dirty/history guard, one responsive editor, canonical structural/availability/team/QR links. No draft text/reason/files in URLs. Offline never schedules reconnect replay.

## Edge cases
Missing settings, malformed legacy enums/JSON, changed ownership/access/account, archived parents, double submit/lost response, same/different group concurrency, new financial data, new guest activity, disk veto/rollback, optional translation deletion versus absence, inherited logo, 320px/200% zoom.

## Success criteria
Zero unrelated persisted columns per group; zero business writes on read paths; real file-SQLite independent-process races; no historical payment/visit transition on save. Measure query/HTML/payload/memory/timing against matched baseline. Fresh focused/backend/parallel/coverage>=90/browser/architecture/JS/SCSS/translation/build gates; PHP8.5 supported and real PHP8.6 dependency gate reported separately. No required runtime services/dependency upgrade/working DB mutation/GitHub operation besides permitted ordinary push.

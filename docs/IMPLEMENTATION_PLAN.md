# Restaurant Menu completion implementation plan

## Goal

Bring the present checkout to a locally complete, reproducibly tested, release-ready state without discarding user work, changing the approved product scope, publishing a release, deploying production, or touching existing application data destructively.

## Scope and priority model

`docs/requirements.md` is the only product contract. This plan records executable closure work discovered by comparing all 49 requirements with the current code, schema, routes, tests, configuration, assets, and repository history. A task is complete only when its acceptance criteria and listed checks have fresh observed evidence in [`PROGRESS.md`](PROGRESS.md).

### P0 — correctness and repository invariants

#### P0.1 Complete and verify the deterministic demo graph

- **Dependencies:** committed demo factory/seeder integration at `d127940`; current additive follow-up in `DemoOperationalStateSeeder`.
- **Work:** preserve the factory-backed four-branch graph; prove per-branch staff/menu/QR/session/order/payment/history coverage; prove new/in-progress/ready bar work; keep production refusal and repeated seeding idempotent.
- **Acceptance:** exact maximum graph assertions pass; every first-party model still has a valid factory; a repeated isolated demo seed creates no duplicates; no existing database is refreshed or truncated.
- **Checks:** `DemoRestaurantSeederTest`, factory/architecture tests, Pint, Larastan, isolated fresh migration and two demo seed runs.

#### P0.2 Enforce named first-party routes

- **Dependencies:** Laravel 13 named-route contract and existing authenticated settings group.
- **Work:** name the existing `/settings` redirect without changing its URI, middleware, status, destination, or established profile/security route names.
- **Acceptance:** `settings.index` resolves and carries `web` plus `auth`; all existing route protection tests stay green.
- **Checks:** RED/GREEN `RouteProtectionAuditTest`, `route:list`, route cache.

#### P0.3 Reconcile canonical documentation with observed code

- **Dependencies:** P0.1 and P0.2 evidence.
- **Work:** update the requested completion documents, architecture inventory, requirement status note, documentation index, changelog/compliance evidence where behaviour changed, and concise permanent repository instructions.
- **Acceptance:** no document claims an unobserved gate; the 49 requirements retain stable IDs; completion ledgers do not become competing requirements or a second external backlog.
- **Checks:** link/path review, final diff review, requirement/compliance row parity.

### P1 — reproducible local release gates

#### P1.1 Backend and data gates

- **Dependencies:** all P0 code stable.
- **Work:** validate the Composer graph; audit dependencies; format; run Larastan; run targeted, sequential, parallel, and coverage suites; verify fresh SQLite migration and repeatable demo seeding in an isolated temporary storage/database root.
- **Acceptance:** zero test failures; application coverage at least 90%; no pending migration; no static-analysis or formatting defect; no mutation of the existing application database.
- **Checks:** `composer validate --strict`, `composer audit --locked`, `vendor/bin/pint --dirty --format agent`, `composer analyse`, `php artisan test --compact`, `php artisan test --compact --parallel`, `composer test:coverage`, migration/seeding commands against temporary paths.

#### P1.2 Frontend, localization, and cache gates

- **Dependencies:** P0 documentation and routes stable.
- **Work:** audit npm dependencies; build production assets; scan and audit EN/LT/RU keys; build config, route, event, and view caches; inspect cached routes.
- **Acceptance:** zero relevant dependency advisories; Vite production build succeeds; missing/legacy/placeholder translation issues remain zero; every cache command succeeds.
- **Checks:** `npm audit --audit-level=moderate`, `npm run build`, translation commands, Artisan cache commands followed by `optimize:clear`.

#### P1.3 Disposable-browser smoke and accessibility review

- **Dependencies:** successful build and a Herd-resolved application URL.
- **Work:** use isolated Chrome tooling against public/guest/health surfaces; inspect navigation, DOM, console, network, responsive widths, keyboard focus, and accessible names without using a personal browser profile.
- **Acceptance:** critical local pages return expected status, no fresh application/console error appears, no horizontal overflow at representative mobile and desktop widths, and primary controls remain keyboard reachable and named.
- **Checks:** Laravel Boost absolute URL and browser logs; Chrome DevTools/Playwright local navigation and inspection.

### P2 — external evidence and unapproved product expansion

#### P2.1 Publish and production verification

- **Dependencies:** all P0/P1 gates on one immutable release commit; maintainer-controlled release and production access.
- **Work:** GitHub issues #3, #4, and #5 cover exact-SHA publication/CI plus production health, logs, and error-alert verification.
- **Acceptance:** remote SHA matches the reviewed commit, CI is green, and production observability is verified without exposing secrets.
- **Current boundary:** publishing and production deployment are explicitly outside this run. Local issue #4 gates are executed against the working tree; external acceptance remains an operator action, not a hidden local TODO.

#### P2.2 Physical platform and assistive-technology evidence

- **Dependencies:** supported physical devices, Safari/Firefox environments, VoiceOver/NVDA or equivalent assistive technology.
- **Work:** GitHub issues #7 and #8.
- **Acceptance:** critical workflows and assistive-technology results are recorded on the specified real platforms.
- **Current boundary:** unavailable physical/external environments are documented in `known-limitations.md`; they do not justify weakening current automated or Chromium checks.

#### P2.3 Shared draft-item allocations

- **Dependencies:** an approved requirement defining ownership, allocation arithmetic, concurrency, authorization, migration, UX, accessibility, and history semantics.
- **Work:** GitHub issue #10 only after product approval.
- **Acceptance:** a new stable requirement ID and compliance row precede TDD implementation.
- **Current boundary:** not an active requirement and therefore intentionally not implemented speculatively.

## Completion sequence

1. Reconcile Git and concurrent changes before every edit boundary.
2. Finish P0 with targeted RED/GREEN tests and update `PROGRESS.md`.
3. Execute P1 backend/data, then frontend/localization/cache, then browser gates; fix any discovered defect before proceeding.
4. Perform a final requirements-to-code/compliance audit and diff review.
5. Report P2 external boundaries exactly; do not publish, deploy, rewrite history, or destroy data.

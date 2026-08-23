# Changelog

This file records shipped milestones, not active requirements or future work. See [`ROADMAP.md`](ROADMAP.md) for current priorities and [`docs/compliance-matrix.md`](docs/compliance-matrix.md) for verification evidence.

## 2026-08-23 — operational hardening

- Upgraded `/up` to verify the migrated SQLite schema, cache, private storage, and log storage; added request IDs, safe structured exception context, redacted daily logs, and deduplicated opt-in production error email.
- Added protected SQLite restoration with password confirmation, audited intent, schema validation, exclusive locking, maintenance mode, a private safety snapshot, rollback, and session invalidation.
- Established Larastan/PHPStan as the single static-analysis stack and added a CI-enforced 90% application coverage floor.
- Enabled opt-in Xdebug coverage on the local Herd PHP 8.5 CLI and refreshed the current-tree application result to 90.6% without changing the coverage floor or production runtime mode.
- Completed deterministic, production-guarded demo graphs across roles, restaurants, branches, areas, tables, localized menus, orders, payments, and print-safe QR images.
- Finished integer-cent money persistence, item-level order cancellation history, kitchen/bar readiness separation, main-module policies, duplicate-operation protection, guest throttles, complete menu translation editing, model factories, and idempotent seed coverage.
- Replaced public registration with recipient-bound invitation registration while preserving Fortify login, password reset, session regeneration, CSRF, and rate-limit controls.
- Added an explicitly enabled, non-production-only demo login for all 12 seeded roles, backed by a shared identity catalogue and protected by guest, CSRF, throttle, and production-deny middleware.

## 2026-08-22 — production-grade modernization

- Raised the baseline to PHP 8.5, Laravel 13, Livewire 4, Flux UI Free 2, Tailwind CSS 4, Vite 8, Pest 4, Pint, and Larastan; resolved Composer/npm advisories.
- Established canonical requirements, compliance evidence, architecture/data/security/operations documentation, and ADRs.
- Moved use cases into Actions, added policy and tenant boundaries, removed service-location and Blade business logic, and converted all Livewire surfaces to class/view pairs without Volt.
- Hardened invitation credentials, QR and guest tokens, manual-payment concurrency, file compensation, SQLite backup consistency, validation, logging, and production failure handling.
- Completed factories and deterministic seed scenarios for the full domain while preserving production refusal and repeat safety.
- Reworked guest, waiter, kitchen/bar, menu, settings, and administration UI around clear primary actions, progressive disclosure, accessible semantics, responsive layouts, and EN/LT/RU parity.
- Added executable architecture, query-budget, security, migration, seeding, localization, browser, static-analysis, and coverage gates.

## 2026-06-03 to 2026-06-05 — application foundation and vertical slices

- Built the Laravel/Livewire/SQLite shared-hosting foundation with Fortify authentication, fixed roles, permissions, organizations, brands, branches, settings, staff, and invitations.
- Added area trees, service points, permanent QR identity, guest sessions, invitations/join approval, shared drafts, waiter review/editing, immutable orders, kitchen/bar tickets, status history, and offline payments.
- Added waiter/bill requests, database notifications, service modes, opening hours, menu schedules and availability, restaurant profile, onboarding, reports, analytics, audit history, exports, subscriptions, backup download, and superadmin controls.
- Introduced localization, design-system and accessibility foundations, responsive guest/waiter/kitchen interfaces, SQLite/query guardrails, cache invalidation, soft deletes, security audits, dangerous confirmations, and print-friendly department tickets.
- Historical prompt-by-prompt detail remains available in Git history; current behaviour is defined only by the requirement catalogue and tests.

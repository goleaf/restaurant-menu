# Documentation Index

Use this file as the entry point for project documentation. It is meant to help
developers and coding agents find the right source before changing code or
product behavior.

## Reading Order

1. Start with [CURRENT_VERSION.md](CURRENT_VERSION.md) for the short current
   snapshot.
2. Read [AI_CONTEXT.md](AI_CONTEXT.md) before implementation work.
3. Read [SECURITY_RULES.md](SECURITY_RULES.md) before auth, guest, staff,
   token, export, backup, upload, or branch-access work.
4. Read [ERROR_HANDLING.md](ERROR_HANDLING.md) before changing exception
   handling, business errors, logs, or user-facing error copy.
5. Check [NEXT_STEPS.md](NEXT_STEPS.md) for queued prompts, guardrails, and
   known refactor debt.
6. Use the topic-specific documents below when the prompt touches that area.

## Documents

| File | Status | Purpose | Update When | Read By |
| --- | --- | --- | --- | --- |
| [AI_CONTEXT.md](AI_CONTEXT.md) | Current | Long-form working memory for coding agents: implemented scope, tables, routes, Livewire components, business rules, forbidden services, and prompt history. | After a completed feature, architecture pass, or documentation refresh changes the working context future agents need. | Codex, developers picking up a prompt, reviewers checking current constraints. |
| [PRODUCT_DECISIONS.md](PRODUCT_DECISIONS.md) | Planned | Durable product decisions, non-goals, tradeoffs, and scope boundaries that should outlive one prompt. | When a prompt changes product policy, rejects a feature, or makes a decision that future work must preserve. | Product owner, developers, Codex before feature planning. |
| [CODE_ORGANIZATION.md](CODE_ORGANIZATION.md) | Planned | Code layout rules: Actions, Livewire components, models, enums, observers, services, Blade views, tests, and docs ownership. | When architecture boundaries move, a new layer is introduced, or a repeated pattern becomes standard. | Developers, Codex before refactors, reviewers checking placement. |
| [DOMAIN_MODULES.md](DOMAIN_MODULES.md) | Planned | Domain module map for organizations, brands, branches, service points, QR, guests, orders, departments, payments, analytics, audit, and exports. | When a module is added, renamed, split, merged, or its ownership changes. | Developers, Codex, QA, onboarding contributors. |
| [TRANSLATION_RULES.md](TRANSLATION_RULES.md) | Planned | Localization rules for UI text, supported locales, guest language behavior, menu translations, and translation guardrails. | When supported locales, translation workflow, or UI text rules change. | Developers editing Blade/Livewire, Codex, translators. |
| [SECURITY_RULES.md](SECURITY_RULES.md) | Current | Security and access rules for branch isolation, permissions, guest/staff separation, QR tokens, guest tokens, invite tokens, Livewire public properties, validation, uploads, XSS, CSRF, money totals, audit logs, backups, exports, and shared hosting. | When permissions, middleware, guest access, tokens, backups, exports, uploads, or sensitive data handling changes. | Developers, Codex, reviewers, deployment maintainers. |
| [ERROR_HANDLING.md](ERROR_HANDLING.md) | Current | Shared strategy for validation, permission, branch, QR, guest, draft, order, payment, file-upload, and system errors. | When exception handling, business-rule errors, error pages, logs, or user-facing error copy changes. | Developers, Codex, reviewers, support. |
| [CORE_FLOW.md](CORE_FLOW.md) | Planned | Main operational flow from permanent QR scan through guest entry, shared draft, waiter confirmation, kitchen/bar work, payment, and table close. | When the guest, waiter, kitchen/bar, payment, or session lifecycle changes. | Developers, Codex, QA, support. |
| [CURRENT_VERSION.md](CURRENT_VERSION.md) | Current | Short project snapshot with domain map, permanent QR rule, guest/table flow, waiter/kitchen/bar flow, payment mode, hosting mode, and current limits. | After a meaningful baseline change that future agents should see in the first minute. | Everyone before starting work. |
| [DEPLOY_SHARED_HOSTING.md](DEPLOY_SHARED_HOSTING.md) | Current | Shared-hosting deployment notes for SQLite, database drivers, local storage, writable paths, cron, backups, and forbidden services. | When deployment steps, hosting constraints, required env values, or filesystem expectations change. | Developers deploying the app, Codex before infrastructure changes, hosting maintainers. |
| [SEED_ARCHITECTURE.md](SEED_ARCHITECTURE.md) | Current | Complete seed architecture plan covering reference, platform, demo organization, restaurant structure, staff, menu, and optional scenario layers. | When seed layer ownership, demo data scope, idempotency strategy, or reference data policy changes. | Developers, Codex before seeder/factory work, reviewers checking seed architecture. |
| [FACTORY_SEEDER_RULES.md](FACTORY_SEEDER_RULES.md) | Current | Factory and seeder rules: factories for demo data, fixed reference data exceptions, idempotency keys, dev-only scenarios, and forbidden training mode. | When factory defaults, seeder safety rules, demo data rules, or idempotency keys change. | Developers, Codex before factories/seeders, reviewers checking data setup. |
| [SEEDERS.md](SEEDERS.md) | Current | Operational guide for current seeders, seed commands, production/demo boundaries, required order, QR file rules, and verification. | When seeder classes, commands, production safety, or verification steps change. | Developers running seeds, Codex before seeder changes, deployment maintainers. |
| [TEST_CHECKLIST.md](TEST_CHECKLIST.md) | Current | Manual smoke checklist and focused regression commands for the main restaurant flow and recent prompts. | After adding or changing a user-facing flow, regression command, or manual verification path. | QA, developers, Codex before and after flow changes. |
| [NEXT_STEPS.md](NEXT_STEPS.md) | Current | Queue of future prompts, risky areas, guardrails, and remaining refactor tasks. It is not permission to implement automatically. | After completing a prompt, discovering refactor debt, or changing recommended next work. | Codex, product owner, developers planning the next prompt. |

## Missing Planned Documents

The `planned` files are intentionally listed here before they exist so the
documentation shape is clear. Create them when the relevant rules need to move
out of broad context files into focused, durable references.

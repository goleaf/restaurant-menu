<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Product

## Accepted Flux Pro direction — 2026-09-15

The Pro migration extends the existing restaurant workflows: searchable scoped choices, accessible language/section controls, branch-local dates, reliable image editing, permitted quick navigation, truthful history, report charts and kitchen boards. Rich description editing requires a separate safe content contract. These are accepted targets under `ui-flux-pro-001`; copying the package does not deliver them. Preserve the product's current recovery, permission and shared-draft behavior throughout the migration. The [implementation ledger](docs/IMPLEMENTATION_PLAN.md) distinguishes prepared source from working features.

## Recovery polish — 2026-09-15

A guest who has already chosen a dish must not lose the typed comment or options when staff changes availability. Keep the detail panel open, explain the conflict and provide a real retry once the dish is available again. Ordering remains subject to current server permissions. Kitchen/bar screens prioritize the next action and compact elapsed status; failed attempts replace stale success feedback. Offline controls communicate that work cannot currently be submitted.

## Delivered interaction direction

The catalogue is a searchable, paginated working list with one focused dish editor. Language tabs, photo previews and a selected-language preview keep authoring close to the guest result. Safe duplication creates a new unavailable dish for review; large deletion shows durable progress and retry. Unrelated drafts survive these operations.

Guests can inspect full descriptions and a selected-item gallery even when ordering is closed. Compact photo-led cards, category navigation, locale-aware search and explicit allergen exclusions help find food without treating missing safety data as a guarantee. Waiter and department screens prioritize the selected branch, pending work and the next authorized action. Existing shared-draft, preparation, service and manual settlement rules remain the product contract.

## Register

product

## Users

- Guests seated in a restaurant, usually using one hand on a phone in mixed lighting. They need to join the correct table, understand the menu, order together, call staff and follow payment or order progress without learning restaurant terminology.
- Waiters, kitchen and bar staff working under time pressure. They need immediate orientation, large reliable actions, unmistakable status and minimal navigation between a table, an order and its next valid action.
- Restaurant owners, managers and trusted specialists configuring branches, areas, service points, menus, staff, reports and governance. They need safe workflows, clear scope and confidence that changes affect the intended restaurant.
- Platform operators performing exceptional safety and recovery work. They need dense evidence, explicit confirmation and strong separation from everyday restaurant operations.

## Product Purpose

Restaurant Menu is a tenant-aware restaurant operations product and in-venue QR ordering experience. It connects permanent table identity, a calm guest ordering flow and fast staff fulfilment while preserving branch ownership, authorization and historical order truth. Success means that every role can identify the current state and the next valid action within seconds, on the device used in its real working environment.

Canonical behaviour remains in `docs/requirements.md`; this document supplies design context rather than a second requirement catalogue.

## Brand Personality

Calm, hospitable, assured. The interface should feel attentive without becoming decorative, and operational without becoming cold. Language is direct, human and specific in English, Lithuanian and Russian.

## Anti-references

- Generic SaaS dashboards made from identical floating cards, oversized metrics, purple gradients or decorative glass.
- Theme-restaurant ornament, stock food imagery or nostalgic visual motifs that compete with menu content and operational status.
- Dense black control rooms, neon state colors or tiny controls that trade legibility for a technical aesthetic.
- Mobile layouts that merely shrink desktop tables, rely on hover or hide the primary action among secondary controls.
- Motion, badges and color used as decoration instead of orientation or state feedback.

## Design Principles

1. **The next action is visible.** Each task surface establishes context, exposes one primary action and makes its result and next step explicit.
2. **Hospitality for guests, tempo for staff.** Guest surfaces are calm and explanatory; waiter, kitchen and bar surfaces favor glanceable state, generous targets and bounded density.
3. **Scope is never implicit.** Organization, branch, table and guest context remain visible wherever a wrong-scope action would be costly.
4. **Progressive disclosure protects focus.** Frequent actions stay close to the work; rare, advanced and destructive actions remain available without dominating it.
5. **Familiar controls earn trust.** Native semantics and Flux primitives are preferred; distinctive character comes from hierarchy, language and the warm brand accent rather than invented widgets.

## Accessibility & Inclusion

Critical guest and staff workflows target WCAG 2.2 AA. They must support keyboard, screen reader, touch, 200% zoom, 320 CSS-pixel reflow, reduced motion and forced-colors modes. Status never depends on color alone, controls have practical touch targets, and translated EN/LT/RU content may expand without clipping. Physical service conditions—low light, noise, interruptions, wet or occupied hands—inform contrast, target size and recovery behavior.

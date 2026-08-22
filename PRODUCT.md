# Product

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

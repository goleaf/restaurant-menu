# Documentation index

## Authority and reading order

`AGENTS.md` defines repository operating rules. This index routes readers to canonical documents. Active behaviour is defined by `requirements.md`; implementation evidence is tracked in `compliance-matrix.md`. Historical prompt records in `CHANGELOG.md` are evidence, not requirements.

1. [`requirements.md`](requirements.md) — canonical functional and non-functional requirements.
2. [`compliance-matrix.md`](compliance-matrix.md) — implementation, schema, localization, seeding, tests, commands, and status for every requirement.
3. Root [`PRODUCT.md`](../PRODUCT.md) and [`DESIGN.md`](../DESIGN.md) — product/design context and anti-reference contract for interface work; neither redefines active requirements.
4. [`architecture.md`](architecture.md), [`domain-model.md`](domain-model.md), [`data-model.md`](data-model.md) — system boundaries and persistent model.
5. [`security.md`](security.md), [`authorization.md`](authorization.md) — trust boundaries and access model.
6. [`frontend.md`](frontend.md), [`livewire.md`](livewire.md), [`tailwind.md`](tailwind.md), [`design-system.md`](design-system.md), [`accessibility.md`](accessibility.md) — interface contracts.
7. [`localization.md`](localization.md), [`testing.md`](testing.md), [`seeding.md`](seeding.md) — contribution workflows.
8. [`performance.md`](performance.md), [`caching.md`](caching.md), [`integrations.md`](integrations.md) — runtime behaviour.
9. [`deployment.md`](deployment.md), [`operations.md`](operations.md) — shared-hosting delivery and operation.
10. Root [`ROADMAP.md`](../ROADMAP.md) — the only active delivery roadmap; [`current-state-audit.md`](current-state-audit.md), [`code-review.md`](code-review.md), and [`known-limitations.md`](known-limitations.md) retain modernization evidence.
11. [`decisions/`](decisions/) — accepted architecture decisions.

## Requirement views

The following are curated views of the canonical catalogue and must not redefine requirements:

- [`product-requirements.md`](product-requirements.md) — user roles and workflows.
- [`system-requirements.md`](system-requirements.md) — platform and technical contracts.
- [`non-functional-requirements.md`](non-functional-requirements.md) — security, performance, accessibility, operations, and quality.

## Historical compatibility paths

Older documentation paths are retained as concise pointers or focused operational supplements. Their canonical replacement is recorded in [`current-state-audit.md`](current-state-audit.md). Useful implementation history remains in the root [`CHANGELOG.md`](../CHANGELOG.md).

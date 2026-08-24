# Documentation index

## Authority and reading order

`AGENTS.md` defines repository operating rules. This index routes readers to canonical documents. Active behaviour is defined by `requirements.md`; implementation evidence is tracked in `compliance-matrix.md`. Historical prompt records in `CHANGELOG.md` are evidence, not requirements.

1. [`requirements.md`](requirements.md) — canonical functional and non-functional requirements.
2. [`compliance-matrix.md`](compliance-matrix.md) — compact implementation and status evidence for every requirement.
3. [`REQUIREMENTS_TRACEABILITY.md`](REQUIREMENTS_TRACEABILITY.md) — concrete backend, UI, authorization, table, test, status and end-to-end proof for every canonical ID.
4. Root [`PRODUCT.md`](../PRODUCT.md) and [`DESIGN.md`](../DESIGN.md) — product/design context and anti-reference contract for interface work; neither redefines active requirements.
5. [`architecture.md`](architecture.md), [`domain-model.md`](domain-model.md), [`data-model.md`](data-model.md) — system boundaries and persistent model.
6. [`security.md`](security.md), [`authorization.md`](authorization.md) — trust boundaries and access model.
7. [`frontend.md`](frontend.md), [`livewire.md`](livewire.md), [`tailwind.md`](tailwind.md), [`design-system.md`](design-system.md), [`accessibility.md`](accessibility.md) — interface contracts.
8. [`localization.md`](localization.md), [`testing.md`](testing.md), [`seeding.md`](seeding.md) — contribution workflows.
9. [`performance.md`](performance.md), [`caching.md`](caching.md), [`integrations.md`](integrations.md) — runtime behaviour.
10. [`deployment.md`](deployment.md), [`operations.md`](operations.md) — shared-hosting delivery and operation.
11. [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md), [`PROGRESS.md`](PROGRESS.md), and [`DECISIONS.md`](DECISIONS.md) — the current repository-completion execution plan, observed gate evidence, and scoped audit decisions; none redefine product behaviour.
12. Root [`ROADMAP.md`](../ROADMAP.md) — the external delivery priority index; [`current-state-audit.md`](current-state-audit.md), [`code-review.md`](code-review.md), and [`known-limitations.md`](known-limitations.md) retain modernization evidence.
13. [`decisions/`](decisions/) — accepted architecture decisions.

## Requirement views

The following are curated views of the canonical catalogue and must not redefine requirements:

- [`product-requirements.md`](product-requirements.md) — user roles and workflows.
- [`system-requirements.md`](system-requirements.md) — platform and technical contracts.
- [`non-functional-requirements.md`](non-functional-requirements.md) — security, performance, accessibility, operations, and quality.

## Historical compatibility paths

Older documentation paths are retained as concise pointers or focused operational supplements. Their canonical replacement is recorded in [`current-state-audit.md`](current-state-audit.md). Useful implementation history remains in the root [`CHANGELOG.md`](../CHANGELOG.md).

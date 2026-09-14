---
paths:
  - 'app/Actions/**'
---

# Actions

## Resolve entity image paths from current scoped persistence
Organization, brand and branch image Actions reload a selected active record inside their transaction before reading the old image path. Retain original organization/brand ownership predicates; stale instances after another save or rollback must not drive cleanup. Save only the fresh image state and synchronize image/timestamp attributes back to the caller without persisting or clearing unrelated dirty fields. Reuse shared rollback/outer-commit media Actions.

## Enforce bulk allocation bounds inside the reusable Action
Component validation alone does not bound direct Action callers. Guard positive ascending range size before range/allocation with overflow-safe arithmetic; preserve transactional model events and throw on rejected required saves. Build successful results from known existing codes and actual saved model values instead of rerunning ownership and preview reads; retain archived-code reservations.

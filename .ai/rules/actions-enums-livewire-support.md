---
paths:
  - 'app/{Actions,Enums,Livewire,Support}/**/*.php'
---

# Actions Enums Livewire Support

## Route workflow mutations through invariant contracts
Use TableSessionStatus and DraftOrderStatus methods for session and draft mutability; do not reimplement status arrays in callers. Guest item edits go through EnsureGuestOwnsEditableDraftItemAction, waiter edits through EnsureWaiterCanEditDraftOrderAction and MoveDraftOrderToWaiterReviewAction, and quantities through OrderItemQuantity. Kitchen and bar screens must pass their exact KitchenDepartmentType family. Retried draft-item creation must carry a UUID idempotency key and rely on the draft-scoped unique constraint.

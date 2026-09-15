---
paths:
  - 'app/{Actions,Enums,Livewire,Support}/**/*.php'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Actions Enums Livewire Support

## Route workflow mutations through invariant contracts
Use TableSessionStatus and DraftOrderStatus methods for session and draft mutability; do not reimplement status arrays in callers. Guest item edits go through EnsureGuestOwnsEditableDraftItemAction, waiter edits through EnsureWaiterCanEditDraftOrderAction and MoveDraftOrderToWaiterReviewAction, and quantities through OrderItemQuantity. Kitchen and bar screens must pass their exact KitchenDepartmentType family. Retried draft-item creation must carry a UUID idempotency key and rely on the draft-scoped unique constraint.

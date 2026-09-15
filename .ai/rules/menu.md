---
paths:
  - 'app/{Actions/Menus,Models,Services/Menus,Livewire/Organizations/Brands/Branches/Menu}/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Menu

## Preserve the dish image gallery boundary
Keep the primary dish image in menu_items.image for backward compatibility and ordered secondary images in menu_item_images. Enforce a combined maximum of eight with branch-scoped reloads. Store through StoreLocalImageAction, compensate only newly written files on failure, and delete referenced files only after persistence succeeds.

## Media persistence includes the outer transaction
For shared local-image replacement/removal, successful persistence means the outer default SQLite transaction has committed, not merely that the persistence callback returned. Reuse shared media Actions: preserve old files until afterCommit and remove only replacement files on rollback. A cleanup exception after commit must never delete the new path already referenced by the database.

## Gallery and parent deletion follow outer commit
Gallery upload registers rollback cleanup immediately after each stored file and must not catch post-commit callback failures as if persistence rolled back. Menu/item/category deletion reloads the original parent-scoped root inside the transaction, collects current owned paths, and deletes files only after the outer default SQLite commit. Preserve observer events and their lazyById batches.

## Stream parent media cleanup and contain malformed cascades
Menu/category Actions stream selected 200-record media batches through DeleteLocalMediaFilesAfterCommitAction, which owns a temporary path file and one outer commit/rollback callback pair. Keep item-ID filters as Eloquent subqueries; category discovery uses a visited-ID set and <=200-ID query inputs. Category observer child/item queries stay in the original menu. Menu fallback categories recheck active scoped existence to avoid duplicate stale deletion events; remaining menu-owned items are also deleted even if their category reference is malformed. A false deleting-event result aborts the owning transaction, preserving all rows/files. Category tracking is still O(categories); post-commit cleanup is not a durable retry queue.

## Resume bounded catalogue operations from owned receipts
Catalogue operation UUID receipts are bound to actor, branch, operation kind and target; authorize every continuation and completed replay. Resume bounded batches and durable cleanup checkpoints without repeating completed mutations. Keep copies unavailable until publication, and compensate only operation-owned files/rows. Completion must preserve unrelated editor drafts and surviving selections; reconcile missing selections inside the chosen menu and branch rather than resetting every open editor.

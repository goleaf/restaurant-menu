---
paths:
  - 'app/{Actions/Menus,Models,Services/Menus,Livewire/Organizations/Brands/Branches/Menu}/**'
---

# Menu

## Preserve the dish image gallery boundary
Keep the primary dish image in menu_items.image for backward compatibility and ordered secondary images in menu_item_images. Enforce a combined maximum of eight with branch-scoped reloads. Store through StoreLocalImageAction, compensate only newly written files on failure, and delete referenced files only after persistence succeeds.

## Media persistence includes the outer transaction
For shared local-image replacement/removal, successful persistence means the outer default SQLite transaction has committed, not merely that the persistence callback returned. Reuse shared media Actions: preserve old files until afterCommit and remove only replacement files on rollback. A cleanup exception after commit must never delete the new path already referenced by the database.

## Gallery and parent deletion follow outer commit
Gallery upload registers rollback cleanup immediately after each stored file and must not catch post-commit callback failures as if persistence rolled back. Menu/item/category deletion reloads the original parent-scoped root inside the transaction, collects current owned paths, and deletes files only after the outer default SQLite commit. Preserve observer events and their lazyById batches.

---
paths:
  - 'app/{Actions/Menus,Models,Services/Menus,Livewire/Organizations/Brands/Branches/Menu}/**'
---

# Menu

## Preserve the dish image gallery boundary
Keep the primary dish image in menu_items.image for backward compatibility and ordered secondary images in menu_item_images. Enforce a combined maximum of eight with branch-scoped reloads. Store through StoreLocalImageAction, compensate only newly written files on failure, and delete referenced files only after persistence succeeds.

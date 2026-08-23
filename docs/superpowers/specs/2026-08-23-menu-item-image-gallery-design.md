# Menu item image gallery design

## Context

The branch menu catalogue currently stores one image path in `menu_items.image`. The existing upload control is shown beside a saved dish and replaces that path on every upload. Restaurant staff need to upload several images for one dish from the dish editing form without losing the current image or weakening tenant, authorization, storage, and cleanup guarantees.

This design extends the existing single-image contract rather than replacing it. The current `menu_items.image` value remains the primary image used by existing catalogue and guest-menu presentation. Additional images are stored as ordered gallery records.

## Scope

The change includes:

- multi-file upload inside the existing dish editing form;
- an ordered gallery of up to eight images per dish, counting the primary image;
- automatic primary-image selection when a dish has no image;
- choosing any saved gallery image as primary;
- removing one saved image at a time;
- automatic promotion of the first remaining image when the primary image is removed;
- preservation of existing single-image data and existing guest-menu primary-image behavior;
- EN, LT, and RU interface text, accessible controls, tests, and browser verification.

The change does not add a guest-facing carousel, drag-and-drop sorting, image cropping, remote storage, image processing, or a new JavaScript dependency.

## Considered approaches

### 1. Primary column plus normalized additional images — selected

Keep `menu_items.image` as the primary image and add a `menu_item_images` table for additional ordered paths. Existing views continue to use the primary path without a breaking migration. Gallery operations centralize the invariant that each stored path appears exactly once.

This is the lowest-risk additive design for deployed SQLite databases and preserves current menu rendering.

### 2. JSON array on `menu_items`

This needs fewer classes, but ordering, uniqueness, individual cleanup, constraints, and future metadata become harder to validate. It is not selected.

### 3. Replace `menu_items.image` with a fully normalized relation

This is structurally clean but requires immediate backfill and coordinated changes across every admin, guest, deletion, backup, seeder, and test path. It expands the migration risk beyond the requested editing workflow and is not selected.

## Data model

Add `menu_item_images` with:

- `id`;
- `menu_item_id` foreign key with cascade deletion;
- `path` as a unique local-disk relative path;
- `sort_order` as a non-negative integer;
- timestamps;
- a unique constraint on `menu_item_id` plus `sort_order`;
- an index on `menu_item_id` plus `sort_order` for ordered eager loading.

`MenuItem` gains an ordered `galleryImages()` relationship. `MenuItemImage` is fillable only for its owner, path, and order, uses an integer cast for order, and has a factory. The existing `image` attribute remains the primary path and requires no data backfill.

The total image count is `primary image + gallery image rows` and cannot exceed eight.

## Application operations

Focused Actions own gallery mutations:

1. **Add images** verifies that the dish belongs to the selected branch, checks the eight-image limit again on the server, stores each validated file under the existing organization/brand/branch/menu-item directory, assigns the first file to `menu_items.image` when no primary exists, and appends the rest in deterministic order.
2. **Promote an image** verifies ownership and swaps the selected gallery path with the current primary path inside one transaction. No file is copied or renamed.
3. **Remove an image** verifies ownership. Removing a secondary deletes its row and media file. Removing the primary promotes the first ordered secondary before deleting the old primary file; if no secondary remains, the primary becomes null.
4. **Delete menu item/category/menu** collects both primary and gallery paths before deleting records and removes every owned local file through the existing media cleanup boundary.

Database persistence completes before destructive file removal. Newly stored but uncommitted files are removed when persistence fails. Existing referenced files remain in place if the database mutation fails.

## Livewire editing form

The upload and gallery controls live inside the existing `editingItemId === item id` form, as requested. The non-editing dish row keeps only its compact primary thumbnail.

The edit form provides:

- a labelled file input with `multiple` and deferred Livewire binding;
- JPG, JPEG, PNG, and WebP guidance plus the existing per-file size limit;
- a localized “up to eight images” limit message;
- one upload action with precise loading state for that dish only;
- an ordered responsive preview grid of saved images;
- an explicit “Primary image” label on the cover;
- “Make primary” and confirmed “Remove” actions for each applicable image;
- stable `wire:key` values based on the dish and gallery record IDs;
- per-file validation messages associated with the upload field.

The first uploaded image becomes primary only when the dish has no primary image. Later uploads append and never silently replace an existing image.

## Validation, authorization, and storage security

- Every upload mutation requires the existing menu-management authorization and resolves the dish through the current branch-scoped query boundary.
- The Livewire property is untrusted. Validate the outer array, total count, and every file.
- Reuse the current content-aware JPG/JPEG/PNG/WebP validation, extension allowlist, maximum file size, generated UUID filename, configured public disk, and tenant-owned directory.
- Reject scriptable formats, dangerous original extensions, invalid MIME/content, oversized files, and attempts to exceed eight total images.
- Direct Action calls repeat critical ownership, file, and count validation.
- Do not expose absolute paths or original filenames in HTML, logs, or validation output.

## Query and presentation impact

The catalogue query eager-loads ordered gallery images with explicit selected columns. Gallery data is mapped to prepared arrays before Blade. No query, relationship lookup, authorization decision, storage call, or collection transformation is added to Blade loops.

Existing guest-menu and operational consumers continue to use `MenuItem::imageUrl()` and therefore display the selected primary image. A guest-facing multi-image viewer is deliberately outside this change.

## Accessibility and localization

- Every file input has a visible label and associated hint/error text.
- Each preview uses the dish name and image position for useful alternative text.
- Icon-assisted actions retain visible localized labels or explicit accessible names and practical touch targets.
- Primary state is communicated with text, not color alone.
- The gallery reflows at 320 CSS pixels, supports keyboard operation and 200% zoom, and remains usable with expanded EN/LT/RU strings.
- New keys are added with placeholder parity to `lang/en.json`, `lang/lt.json`, and `lang/ru.json`.

## Testing

TDD begins with failing Pest coverage for:

- schema constraints, relationship order, model factory, and eight-image limit;
- uploading several valid images in one Livewire action;
- preserving an existing primary image while appending new images;
- automatically creating a primary image for an empty dish;
- promoting a gallery image without copying files;
- removing secondary and primary images, including automatic promotion;
- authorization denial, wrong-branch tampering, invalid content, dangerous extensions, oversized files, and excess-count rejection;
- storage/database failure compensation;
- complete file cleanup when deleting a dish, category, or menu;
- prepared catalogue payloads and no N+1 regression;
- EN/LT/RU keys and accessible edit-form markup.

Targeted tests run before the full relevant menu/media suite. PHP files are formatted with Pint, Larastan is run for the changed application surface, and the production frontend build is checked.

Browser acceptance uses the Herd URL with a disposable isolated profile. It verifies editing a real dish, selecting multiple files, saved thumbnails, primary selection, individual removal, responsive layout, keyboard focus, Livewire request success, and an empty browser console.

## Acceptance criteria

- An authorized manager can select and upload several images from the dish editing form.
- A dish never has more than eight total images.
- Existing single images remain valid and visible after deployment.
- Uploading additional images never silently replaces the current primary image.
- One saved image is clearly primary, can be changed, and is still used by existing guest/catalogue cards.
- Individual removal and parent deletion leave no owned orphaned files.
- Unauthorized and cross-branch mutations fail without changing database rows or files.
- All changed tests, formatting, static analysis, localization checks, build, and browser acceptance pass with observed results.

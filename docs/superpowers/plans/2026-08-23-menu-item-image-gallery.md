# Menu Item Image Gallery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow an authorized menu manager to upload and manage up to eight images per dish from the existing dish editing form while preserving `menu_items.image` as the primary image used by all current guest and operational views.

**Architecture:** Keep the deployed primary-image column and add ordered `MenuItemImage` records for secondary images. Focused menu Actions validate branch ownership and own transactions plus filesystem compensation; `CatalogData` eager-loads and prepares the gallery; the class-based Livewire component authorizes and validates untrusted upload state; Blade renders prepared arrays only.

**Tech Stack:** PHP 8.5, Laravel 13, Eloquent/SQLite, Livewire 4, Blade SSR, Flux UI Free 2, Tailwind CSS 4, Pest 4, local public filesystem, EN/LT/RU JSON translations.

---

## Execution contract

- Work in `/Users/andrejprus/Herd/restaurant-menu` on the current branch; do not create another branch unless the user asks.
- Before editing, run `git status --short --branch`, `git diff --cached --name-status`, `git diff --name-status`, and `git log -5 --oneline`. The checkout currently contains unrelated staged, unstaged, renamed, and untracked work, including concurrent edits to `Catalog.php` and the staged move to `app/Services/Menus/CatalogData.php`.
- Preserve all existing work. Never reset, restore, stash, clean, broadly stage, rewrite, or force-push. Re-read current file contents before every patch and integrate with the in-progress `CatalogData` service move.
- Do not commit an overlapping file while it contains unattributable work. Commit steps below are conditional: stage exact owned paths only after the concurrent owner change is committed as an ancestor or after an attributable patch slice is proved safe. Otherwise leave the verified gallery work uncommitted and report that commit isolation is blocked.
- Before the first edit, re-read `AGENTS.md`, the mandatory repository and interface documents, `.ai/rules/index.md` plus every matching rule when `.ai/rules` exists, and run `grep -rin 'image\|upload\|menu' .ai/rules` when applicable.
- Use Laravel Boost `database-schema` before the migration and `search-docs` for Livewire multiple uploads, array validation, Eloquent transactions, and filesystem testing. Do not start a development server; Herd serves the site.
- Follow RED -> GREEN -> REFACTOR. Observe each listed failing targeted test before implementation and the passing result after it.

## File map

- Create `database/migrations/2026_08_23_223000_create_menu_item_images_table.php`: additive ordered secondary-image storage.
- Create `app/Models/MenuItemImage.php` and `database/factories/MenuItemImageFactory.php`: gallery record, ordered owner relation, valid factory.
- Modify `app/Models/MenuItem.php`: eight-image constant and ordered `galleryImages()` relation.
- Create `app/Actions/Menus/AddMenuItemImagesAction.php`: append several validated images with branch/count checks and rollback cleanup.
- Create `app/Actions/Menus/PromoteMenuItemImageAction.php`: atomically swap a secondary path with the primary path.
- Create `app/Actions/Menus/RemoveMenuItemGalleryImageAction.php`: remove one secondary record, then its file.
- Modify `app/Actions/Menus/RemoveMenuItemImageAction.php`: promote the first secondary when removing the primary.
- Modify `app/Actions/Menus/DeleteMenuItemAction.php`, `DeleteMenuCategoryAction.php`, and `DeleteMenuAction.php`: include secondary records and files in parent cleanup.
- Modify `app/Support/Validation/RestaurantValidationRules.php`: validate a bounded outer upload array and each file.
- Modify `app/Services/Menus/CatalogData.php`: branch-scoped image lookup, eager loading, and prepared gallery arrays.
- Modify `app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php`: editing-form upload/promote/remove actions and transient upload cleanup.
- Modify `resources/views/livewire/organizations/brands/branches/menu/catalog.blade.php`: accessible multi-file gallery inside the existing edit form; keep only the primary thumbnail outside edit mode.
- Modify `lang/en.json`, `lang/lt.json`, and `lang/ru.json`: aligned gallery copy and validation messages.
- Create `tests/Feature/MenuItemImageGalleryTest.php`; modify `tests/Feature/MenuSchemaTest.php`, `MenuCrudTest.php`, `LocalMediaStorageTest.php`, and `ModelFactoryAuditTest.php` only where the contract needs coverage.
- Update `docs/architecture.md`, `docs/data-model.md`, `docs/seeding.md`, `docs/security.md`, `docs/testing.md`, `docs/requirements.md`, `docs/compliance-matrix.md`, and `CHANGELOG.md` to record the delivered contract and fresh inventory counts.

### Task 0: Reconcile the shared checkout and establish the baseline

**Files:** Read-only inspection of all files listed above.

- [ ] **Step 1: Capture ownership and current deltas**

~~~bash
pwd
git status --short --branch
git diff --cached --name-status
git diff --name-status
git log -5 --oneline
git diff --cached -- app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php app/Services/Menus/CatalogData.php
git diff -- app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php app/Services/Menus/CatalogData.php resources/views/livewire/organizations/brands/branches/menu/catalog.blade.php
~~~

Expected: path and branch are correct; unrelated changes remain intact; the implementation records which pre-existing hunks overlap the gallery work.

- [ ] **Step 2: Confirm installed APIs and current schema**

~~~bash
composer show laravel/framework --direct
composer show livewire/livewire --direct
composer show livewire/flux --direct
php artisan about --only=environment
php artisan migrate:status
~~~

Use Boost `database-schema` for `menu_items` and `search-docs` with `['multiple file uploads', 'validate nested array files', 'database transactions', 'filesystem fake uploaded file']` scoped to the installed packages. Expected: Laravel 13, Livewire 4, Flux 2, SQLite, and no existing `menu_item_images` table.

- [ ] **Step 3: Run the untouched targeted baseline**

~~~bash
php artisan test --compact tests/Feature/MenuSchemaTest.php tests/Feature/MenuCrudTest.php tests/Feature/LocalMediaStorageTest.php tests/Feature/ModelFactoryAuditTest.php
~~~

Expected: pass. If it fails before gallery edits, record the exact pre-existing failure and do not conflate it with the feature.

### Task 1: Add the normalized image record and ordered relation

**Files:**

- Create: `database/migrations/2026_08_23_223000_create_menu_item_images_table.php`
- Create: `app/Models/MenuItemImage.php`
- Create: `database/factories/MenuItemImageFactory.php`
- Modify: `app/Models/MenuItem.php`
- Modify: `tests/Feature/MenuSchemaTest.php`
- Verify: `tests/Feature/ModelFactoryAuditTest.php`

- [ ] **Step 1: Write failing schema, constraint, relation-order, and factory assertions**

Add assertions that `menu_item_images` has `id`, `menu_item_id`, `path`, `sort_order`, timestamps, a cascading foreign key, a unique `path`, and a unique `(menu_item_id, sort_order)` pair. Add a relationship test that creates orders `20` and `10` and expects `galleryImages()->pluck('sort_order')->all()` to equal `[10, 20]`. Extend the factory audit expectation only if its explicit model inventory changes; the dynamic audit must discover and persist `MenuItemImage` automatically.

~~~bash
php artisan test --compact tests/Feature/MenuSchemaTest.php tests/Feature/ModelFactoryAuditTest.php
~~~

Expected RED: missing table/model/factory/relation.

- [ ] **Step 2: Generate, rename, and implement the additive migration**

~~~bash
php artisan make:migration create_menu_item_images_table --create=menu_item_images --no-interaction
~~~

Rename only the generated file to `database/migrations/2026_08_23_223000_create_menu_item_images_table.php`, then use this body:

~~~php
Schema::create('menu_item_images', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
    $table->string('path')->unique();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
    $table->unique(['menu_item_id', 'sort_order']);
    $table->index(['menu_item_id', 'sort_order']);
});
~~~

`down()` must call `Schema::dropIfExists('menu_item_images')`. Do not edit older migrations and do not backfill `menu_items.image`.

- [ ] **Step 3: Generate and implement model plus factory**

~~~bash
php artisan make:model MenuItemImage --factory --no-interaction
~~~

`MenuItemImage` must use `#[Fillable(['menu_item_id', 'path', 'sort_order'])]`, `HasFactory`, an integer `sort_order` cast, and:

~~~php
/** @return BelongsTo<MenuItem, $this> */
public function item(): BelongsTo
{
    return $this->belongsTo(MenuItem::class, 'menu_item_id')->withTrashed();
}

public function imageUrl(): string
{
    return Storage::disk('public')->url($this->path);
}
~~~

The factory default must be valid in isolation:

~~~php
return [
    'menu_item_id' => MenuItem::factory(),
    'path' => 'media/testing/menu-item-images/'.fake()->unique()->uuid().'.jpg',
    'sort_order' => 0,
];
~~~

- [ ] **Step 4: Add the aggregate invariant and relation to `MenuItem`**

~~~php
public const MAX_IMAGES = 8;

/** @return HasMany<MenuItemImage, $this> */
public function galleryImages(): HasMany
{
    return $this->hasMany(MenuItemImage::class)
        ->orderBy('sort_order')
        ->orderBy('id');
}
~~~

- [ ] **Step 5: Prove GREEN and migration reversibility**

~~~bash
php artisan test --compact tests/Feature/MenuSchemaTest.php tests/Feature/ModelFactoryAuditTest.php
GALLERY_DB_PATH="$(mktemp "${TMPDIR:-/tmp}/restaurant-menu-gallery.sqlite.XXXXXX")"
trap 'rm -f "$GALLERY_DB_PATH"' EXIT
DB_CONNECTION=sqlite DB_DATABASE="$GALLERY_DB_PATH" php artisan migrate:fresh --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$GALLERY_DB_PATH" php artisan migrate:rollback --step=1 --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$GALLERY_DB_PATH" php artisan migrate --no-interaction
~~~

Expected: tests pass and the new migration rolls backward and forward without changing old primary-image data.

- [ ] **Step 6: Commit the isolated slice if ownership is clean**

~~~bash
git add database/migrations/2026_08_23_223000_create_menu_item_images_table.php app/Models/MenuItemImage.php database/factories/MenuItemImageFactory.php app/Models/MenuItem.php tests/Feature/MenuSchemaTest.php
git diff --cached --check
git commit -m "feat(menu): add menu item image records"
~~~

Skip the commit if any listed file contains pre-existing unattributable edits.

### Task 2: Append multiple files securely and enforce the eight-image limit

**Files:**

- Create: `app/Actions/Menus/AddMenuItemImagesAction.php`
- Modify: `app/Support/Validation/RestaurantValidationRules.php`
- Create: `tests/Feature/MenuItemImageGalleryTest.php`
- Modify: `tests/Feature/LocalMediaStorageTest.php`

- [ ] **Step 1: Write failing Action tests**

Cover these exact cases with `Storage::fake('public')` and factories:

1. Three uploads on an item without a primary set upload 1 as `menu_items.image` and create secondary records at orders `0` and `1` for uploads 2 and 3.
2. Two uploads on an item with an existing primary keep the primary and append orders after the current maximum.
3. A total above `MenuItem::MAX_IMAGES` throws `ValidationException` with key `images`, creates no rows, and stores no new files.
4. A foreign-branch item throws `InvalidArgumentException` before storing a file.
5. A dangerous extension, scriptable content, and oversized file are rejected by the direct Action call.
6. If persistence throws after files are stored, every new path is deleted while old primary/gallery paths remain.

Inject the persistence failure without a production test hook by registering a `MenuItemImage::creating()` test listener that throws `RuntimeException` after `StoreLocalImageAction` has written the files. Assert the transaction rolls back and `Storage::disk('public')->allFiles()` contains none of the attempted uploads.

~~~bash
php artisan test --compact tests/Feature/MenuItemImageGalleryTest.php tests/Feature/LocalMediaStorageTest.php
~~~

Expected RED: Action and array validation do not exist.

- [ ] **Step 2: Add reusable bounded array validation**

Add to `RestaurantValidationRules`:

~~~php
/** @return array<string, list<mixed>> */
public static function imageUploads(string $field, int $maxFiles): array
{
    return [
        $field => ['required', 'array', 'min:1', 'max:'.$maxFiles],
        $field.'.*' => StoreLocalImageAction::validationRules(),
    ];
}
~~~

Call `StoreLocalImageAction::validationMessages($field.'.*')` for per-file messages and add a localized outer-array maximum message at `$field.'.max'`.

- [ ] **Step 3: Implement `AddMenuItemImagesAction`**

The public contract is:

~~~php
/** @param list<UploadedFile> $files */
public function handle(Branch $branch, MenuItem $item, array $files): MenuItem
~~~

Implement these operations in this order:

1. Reject an empty list, non-`UploadedFile` entries, and an item whose `menu_id` is not owned by `$branch`.
2. Enter `DB::transaction`; reload the branch-owned item, count `filled($item->image) ? 1 : 0` plus `galleryImages()->count()`, and throw `ValidationException::withMessages(['images' => __('uploads.errors.maximum_images', ['count' => MenuItem::MAX_IMAGES])])` before storage if the requested total is too high.
3. Store each file through `StoreLocalImageAction` in `media/organizations/{organization}/brands/{brand}/branches/{branch}/menu-items/{item}/images`, collecting every new path.
4. If primary is blank, `array_shift` the first new path into `menu_items.image`. Append each remaining path with consecutive orders beginning at `(int) $item->galleryImages()->max('sort_order') + 1`, using a single `createMany` call rather than queries in a loop.
5. Catch any `Throwable` outside the transaction, delete every newly stored path via `DeleteLocalMediaFileAction`, and rethrow. On success return the refreshed item with `galleryImages` loaded.

Do not copy original filenames, expose absolute paths, replace an existing primary, or delete old files.

- [ ] **Step 4: Prove GREEN and format**

~~~bash
php artisan test --compact tests/Feature/MenuItemImageGalleryTest.php tests/Feature/LocalMediaStorageTest.php
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/MenuItemImageGalleryTest.php tests/Feature/LocalMediaStorageTest.php
~~~

Expected: all append, limit, content security, ownership, and compensation cases pass.

- [ ] **Step 5: Commit the isolated slice if ownership is clean**

~~~bash
git add app/Actions/Menus/AddMenuItemImagesAction.php app/Support/Validation/RestaurantValidationRules.php tests/Feature/MenuItemImageGalleryTest.php tests/Feature/LocalMediaStorageTest.php
git diff --cached --check
git commit -m "feat(menu): upload menu item galleries"
~~~

### Task 3: Promote and remove individual images without file duplication

**Files:**

- Create: `app/Actions/Menus/PromoteMenuItemImageAction.php`
- Create: `app/Actions/Menus/RemoveMenuItemGalleryImageAction.php`
- Modify: `app/Actions/Menus/RemoveMenuItemImageAction.php`
- Modify: `tests/Feature/MenuItemImageGalleryTest.php`

- [ ] **Step 1: Write failing promotion/removal tests**

Assert all of the following:

- promotion swaps `menu_items.image` and the chosen secondary `path` in one transaction, leaves row count/order unchanged, and neither copies nor deletes either file;
- promoting an image belonging to another item or branch throws `InvalidArgumentException` and changes nothing;
- removing a secondary deletes its row only after persistence succeeds, then deletes exactly its file;
- removing a primary promotes the lowest ordered secondary, deletes that secondary row and the old primary file, and leaves every remaining file;
- removing a primary with no secondary sets `image` to null and deletes the old file;
- a simulated persistence failure keeps the referenced file and all previous database paths.

~~~bash
php artisan test --compact tests/Feature/MenuItemImageGalleryTest.php
~~~

Expected RED: missing Actions and primary promotion.

- [ ] **Step 2: Implement atomic promotion**

`PromoteMenuItemImageAction::handle(Branch $branch, MenuItem $item, MenuItemImage $image): MenuItem` must verify both branch ownership and `$image->menu_item_id === $item->id`, then transactionally reload both records. If the primary is blank, set it to the selected path and delete the secondary row; otherwise swap the two path values. Never touch the filesystem.

- [ ] **Step 3: Implement secondary removal through the media boundary**

`RemoveMenuItemGalleryImageAction::handle(Branch $branch, MenuItem $item, MenuItemImage $image): MenuItem` must repeat both ownership checks and call:

~~~php
$this->removeLocalImage->handle(
    oldPath: $image->path,
    persist: fn (): bool => $image->deleteOrFail(),
);
~~~

Return the item refreshed with `galleryImages`; do not renumber unaffected rows.

- [ ] **Step 4: Upgrade primary removal**

Change the contract to `RemoveMenuItemImageAction::handle(Branch $branch, MenuItem $item): MenuItem`. Inside the `RemoveLocalImageAction` persistence closure, use one database transaction: reload the branch-owned item, select the first secondary by relation order, set its path as primary or null, save, then delete the promoted secondary row when present. The old primary file is deleted only after the transaction succeeds.

- [ ] **Step 5: Prove GREEN and format**

~~~bash
php artisan test --compact tests/Feature/MenuItemImageGalleryTest.php tests/Feature/LocalMediaStorageTest.php
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/MenuItemImageGalleryTest.php tests/Feature/LocalMediaStorageTest.php
~~~

- [ ] **Step 6: Commit the isolated slice if ownership is clean**

~~~bash
git add app/Actions/Menus/PromoteMenuItemImageAction.php app/Actions/Menus/RemoveMenuItemGalleryImageAction.php app/Actions/Menus/RemoveMenuItemImageAction.php tests/Feature/MenuItemImageGalleryTest.php
git diff --cached --check
git commit -m "feat(menu): manage primary dish images"
~~~

### Task 4: Clean every gallery file when parent menu records are removed

**Files:**

- Modify: `app/Actions/Menus/DeleteMenuItemAction.php`
- Modify: `app/Actions/Menus/DeleteMenuCategoryAction.php`
- Modify: `app/Actions/Menus/DeleteMenuAction.php`
- Modify: `tests/Feature/MenuCrudTest.php`

- [ ] **Step 1: Extend the existing parent-cleanup test and observe RED**

For the item, nested category, and menu cases in `manager can delete dishes categories and menus while cleaning local dish photos`, create two `MenuItemImage` records and matching fake-disk files per relevant dish. After each Livewire delete, assert every primary and secondary path is missing and active gallery rows for the affected item IDs are gone.

~~~bash
php artisan test --compact tests/Feature/MenuCrudTest.php --filter="manager can delete dishes categories and menus while cleaning local dish photos"
~~~

Expected RED: secondary files remain.

- [ ] **Step 2: Update cleanup Actions without loop queries**

For each Action:

1. Resolve the bounded affected item IDs with explicit selected columns.
2. Query `MenuItemImage` once with `whereIn('menu_item_id', $itemIds)` and pluck paths before mutation.
3. Inside one `DB::transaction`, bulk-delete those gallery rows and perform the existing item/category/menu deletion behavior. Preserve the current soft-delete cascade behavior and do not force-delete historical menu entities.
4. After the transaction commits, pass the combined primary and secondary path collection through `DeleteLocalMediaFileAction`.

For one item, use its already eager-loaded gallery collection or one direct relation query. For category/menu, never call a relation query inside an item loop.

- [ ] **Step 3: Prove GREEN and no regression**

~~~bash
php artisan test --compact tests/Feature/MenuCrudTest.php --filter="manager can delete dishes categories and menus while cleaning local dish photos"
php artisan test --compact tests/Feature/MenuCrudTest.php tests/Feature/MenuItemImageGalleryTest.php
vendor/bin/pint --dirty --format agent
~~~

- [ ] **Step 4: Commit the isolated slice if ownership is clean**

~~~bash
git add app/Actions/Menus/DeleteMenuItemAction.php app/Actions/Menus/DeleteMenuCategoryAction.php app/Actions/Menus/DeleteMenuAction.php tests/Feature/MenuCrudTest.php
git diff --cached --check
git commit -m "fix(menu): clean gallery files with menu records"
~~~

### Task 5: Expose the gallery only inside dish editing

**Files:**

- Modify: `app/Services/Menus/CatalogData.php`
- Modify: `app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php`
- Modify: `resources/views/livewire/organizations/brands/branches/menu/catalog.blade.php`
- Modify: `lang/en.json`
- Modify: `lang/lt.json`
- Modify: `lang/ru.json`
- Modify: `tests/Feature/MenuCrudTest.php`

- [ ] **Step 1: Write failing Livewire, authorization, payload, markup, and localization tests**

Add tests to `MenuCrudTest.php` that:

1. start editing a dish, set `itemImageUploads.{id}` to three valid images, call `saveItemImages`, and assert primary plus two ordered secondaries and cleared transient state;
2. upload to a dish with a primary and assert append-only behavior;
3. reject the ninth total image with an error on `itemImageUploads.{id}`;
4. reject a caller without `ManageMenu` and a tampered item/image from another branch without file or row mutation;
5. call `promoteItemImage`, `removeItemGalleryImage`, and `removeItemImage` and assert the exact Action results;
6. assert upload/gallery controls are absent before `startEditingItem`, present afterward, and the input contains `multiple`;
7. assert saved image markup contains stable keys, useful translated alt text, a text primary badge, visible make-primary/remove labels, and confirmation for removal;
8. render multiple items with multiple images under `DB::enableQueryLog()` and assert the catalogue adds one bounded eager-load query rather than one query per dish;
9. decode all three language files and assert every new key exists with identical placeholder sets.

~~~bash
php artisan test --compact tests/Feature/MenuCrudTest.php --filter="menu item image gallery"
~~~

Expected RED: missing component state/methods/payload/UI/copy.

- [ ] **Step 2: Prepare gallery data in `CatalogData`**

Add `findBranchItemImage(int $branchId, int $itemId, int $imageId): MenuItemImage` with explicit selected columns, `where('menu_item_id', $itemId)`, and a branch-scoped `whereHas('item.menu', fn ($query) => $query->where('branch_id', $branchId))` ownership check.

In `menus()`, eager-load:

~~~php
'galleryImages' => fn ($imageQuery) => $imageQuery
    ->select(['id', 'menu_item_id', 'path', 'sort_order', 'created_at', 'updated_at'])
    ->orderBy('sort_order')
    ->orderBy('id'),
~~~

In `presentItem()`, construct a prepared `images` list whose primary entry has `key => 'primary-'.$item->id`, `id => null`, `is_primary => true`, URL from `imageUrl()`, and localized alt text using dish name and position. Secondary entries use `key => 'gallery-'.$image->id`, numeric `id`, `is_primary => false`, and `MenuItemImage::imageUrl()`. Also return `image_count` and `remaining_image_slots = MenuItem::MAX_IMAGES - image_count`. Blade must receive arrays only and must not call models, relationships, collections, authorization, or storage.

- [ ] **Step 3: Replace single-upload Livewire state with bounded list state**

Use:

~~~php
/** @var array<int, list<mixed>> */
public array $itemImageUploads = [];
~~~

Replace `saveItemImage` with `saveItemImages(int $itemId, AddMenuItemImagesAction $addImages)`. Authorize, resolve the branch-scoped item, compute available slots from a fresh count, validate `RestaurantValidationRules::imageUploads('itemImageUploads.'.$item->id, $remainingSlots)`, verify every entry is `UploadedFile`, invoke the Action, clear only that item's state/error bag, forget menu computed data, and toast the localized plural upload message. If the Action returns an `images` count error, attach it to the exact Livewire property.

Add:

~~~php
public function promoteItemImage(int $itemId, int $imageId, PromoteMenuItemImageAction $promoteImage): void
public function removeItemGalleryImage(int $itemId, int $imageId, RemoveMenuItemGalleryImageAction $removeImage): void
~~~

Both must authorize first, resolve both item and image through `CatalogData`, invoke the Action, clear only the item's transient upload state, refresh computed data, and toast. Update `removeItemImage` to pass `$this->branch`. Clear `itemImageUploads[$itemId]` on item deletion, successful upload, primary/secondary mutation, edit cancellation, and when switching the edited dish.

- [ ] **Step 4: Move all upload/gallery UI inside the existing edit form**

Do not nest a form. Inside `<form wire:submit="updateItem">`, add a semantic gallery section after the dietary fields:

- visible localized heading and “:count of :max images” hint;
- `<x-ui.image-upload-input id="item-images-{{ $item['id'] }}" multiple wire:model="itemImageUploads.{{ $item['id'] }}" :aria-label="__('uploads.labels.multiple_images')" />` while remaining slots are positive;
- a `type="button"` upload button using `wire:click="saveItemImages({id})"`, precise `wire:target`, and disabled/loading state;
- per-index and outer-array errors;
- responsive `grid-cols-2 sm:grid-cols-3 lg:grid-cols-4` saved preview grid;
- primary text badge; make-primary buttons only on secondary images;
- `x-dangerous-action-confirmation` around each remove action;
- durable `wire:key="menu-item-{item}-image-{key}"`, escaped URL/name/alt values, explicit width/height, lazy decoding, visible focus, and practical touch targets.

Delete the old non-editing `<form wire:submit="saveItemImage({{ $item['id'] }})">` block completely. Keep the compact 64px primary thumbnail in non-edit mode unchanged.

- [ ] **Step 5: Add aligned EN/LT/RU semantic keys**

Add the same keys to all three JSON files, with placeholder parity:

~~~text
uploads.actions.make_primary
uploads.errors.maximum_images (:count)
uploads.labels.image_count (:count, :max)
uploads.labels.image_position (:name, :position)
uploads.labels.multiple_images
uploads.labels.primary_image
uploads.labels.up_to_images (:count)
uploads.messages.images_uploaded (:count)
uploads.messages.primary_changed
~~~

Use natural English, Lithuanian, and Russian translations. Reuse existing upload/remove/confirmation keys rather than duplicating them.

- [ ] **Step 6: Prove component and translation GREEN**

~~~bash
php artisan test --compact tests/Feature/MenuCrudTest.php --filter="menu item image gallery"
php artisan test --compact tests/Feature/MenuCrudTest.php tests/Feature/MenuItemImageGalleryTest.php tests/Feature/LocalMediaStorageTest.php
php artisan translations:audit
php artisan translations:scan --json
npm run build
vendor/bin/pint --dirty --format agent
~~~

Expected: Livewire tests, authorization/security checks, localization audit/scan, and production Vite build pass.

- [ ] **Step 7: Commit the isolated slice if ownership is clean**

~~~bash
git add app/Services/Menus/CatalogData.php app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php resources/views/livewire/organizations/brands/branches/menu/catalog.blade.php lang/en.json lang/lt.json lang/ru.json tests/Feature/MenuCrudTest.php
git diff --cached --check
git commit -m "feat(menu): add gallery controls to dish editing"
~~~

Do not use this commit command until the staged `CatalogData` move and concurrent `Catalog.php` refactor have attributable ownership.

### Task 6: Update the canonical documentation and complete all gates

**Files:**

- Modify: `docs/requirements.md`
- Modify: `docs/compliance-matrix.md`
- Modify: `docs/architecture.md`
- Modify: `docs/data-model.md`
- Modify: `docs/seeding.md`
- Modify: `docs/security.md`
- Modify: `docs/testing.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Update canonical contracts and inventories**

Record the approved behavior under the existing menu/media requirement rather than creating a competing requirement. Update the compliance evidence to the exact tests/Actions/UI paths, data-model relationship and constraints, local-media security/cleanup contract, factory count, migration count, and test commands. Recount models/factories/migrations/actions from the final tree instead of incrementing stale numbers manually.

~~~bash
find app/Models -name '*.php' -type f | wc -l
find database/factories -name '*Factory.php' -type f | wc -l
find database/migrations -name '*.php' -type f | wc -l
find app/Actions -name '*Action.php' -type f | wc -l
~~~

- [ ] **Step 2: Run focused and full backend gates sequentially**

~~~bash
composer validate --strict
composer audit
vendor/bin/pint --dirty --format agent
composer analyse
php artisan test --compact tests/Feature/MenuSchemaTest.php tests/Feature/MenuItemImageGalleryTest.php tests/Feature/MenuCrudTest.php tests/Feature/LocalMediaStorageTest.php tests/Feature/ModelFactoryAuditTest.php
php artisan test --compact
php artisan test --compact --parallel
composer test:coverage
php artisan translations:audit
php artisan translations:scan --json
GALLERY_DB_PATH="$(mktemp "${TMPDIR:-/tmp}/restaurant-menu-gallery.sqlite.XXXXXX")"
trap 'rm -f "$GALLERY_DB_PATH"' EXIT
DB_CONNECTION=sqlite DB_DATABASE="$GALLERY_DB_PATH" php artisan migrate:fresh --seed --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$GALLERY_DB_PATH" php artisan db:seed --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$GALLERY_DB_PATH" php artisan db:seed --no-interaction
~~~

Expected: all applicable gates pass, coverage remains at least 90%, translations align, fresh SQLite migrations work, and fixed seeders remain idempotent. Record any environmental blocker exactly; do not claim a skipped gate passed.

- [ ] **Step 3: Run frontend, cache, and dependency gates**

~~~bash
npm audit
npm run build
php artisan route:cache
php artisan config:cache
php artisan view:cache
php artisan optimize:clear
~~~

Expected: no introduced dependency vulnerability, production build passes, caches compile, and generated cache state is cleared afterward.

- [ ] **Step 4: Browser acceptance through Herd with an isolated disposable profile**

Use Laravel Boost `get-absolute-url` for `/organizations/1/brands/1/branches/1/menu`, then Chrome DevTools MCP or Playwright MCP with a new temporary profile. Never attach to the user's personal profile and never start a server.

Verify:

- gallery controls appear only after opening dish edit mode;
- selecting three JPG/PNG/WebP files produces one successful Livewire request and three saved previews;
- the existing primary is not replaced by later uploads;
- make-primary changes the compact non-edit thumbnail after saving/closing edit mode;
- removing secondary and primary images uses confirmation and preserves the correct remaining files;
- the ninth image is blocked with localized feedback;
- keyboard focus/order works, labels are announced, and state is not color-only;
- layout has no horizontal overflow at 320px, 768px, desktop, and 200% zoom;
- EN/LT/RU strings fit; reduced motion and forced colors remain usable;
- browser console is empty and all Livewire/network requests succeed.

Use disposable fixture files only. If browser acceptance changes application data, restrict it to the approved local demo branch/item and remove only those created test records/files through the UI or a targeted, verified application command.

- [ ] **Step 5: Review the final diff and query impact**

~~~bash
git diff --check
git diff --stat
git status --short --branch
git diff -- app/Models/MenuItem.php app/Models/MenuItemImage.php app/Actions/Menus app/Services/Menus/CatalogData.php app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php resources/views/livewire/organizations/brands/branches/menu/catalog.blade.php tests/Feature/MenuItemImageGalleryTest.php tests/Feature/MenuCrudTest.php
~~~

Expected query delta for the catalogue: one additional eager-load query for all gallery records, independent of dish count; zero queries in Blade and zero per-image queries in render loops.

- [ ] **Step 6: Commit documentation if the slice is attributable**

~~~bash
git add docs/requirements.md docs/compliance-matrix.md docs/architecture.md docs/data-model.md docs/seeding.md docs/security.md docs/testing.md CHANGELOG.md
git diff --cached --check
git commit -m "docs(menu): record image gallery delivery"
~~~

- [ ] **Step 7: Report observed completion evidence**

Report exact test/build/browser results, query delta, migration and rollback evidence, changed paths, any uncommitted state caused by shared-worktree ownership, and whether commits were created. Do not push unless the user asks.

## Final contract checklist

- [ ] Existing `menu_items.image` values remain untouched by migration and continue to drive current guest/catalogue cards.
- [ ] Authorized managers can upload several images only from dish edit mode; later uploads append.
- [ ] Total primary plus secondary count never exceeds eight in Livewire or direct Action calls.
- [ ] Promote swaps paths without copying; primary removal promotes the first ordered secondary.
- [ ] Invalid, oversized, scriptable, foreign-branch, and unauthorized mutations leave database and filesystem unchanged.
- [ ] Dish/category/menu deletion leaves no owned gallery files and no active gallery rows.
- [ ] Catalogue gallery data is eager-loaded once, explicitly selected, and prepared before Blade.
- [ ] EN/LT/RU, keyboard, mobile, zoom, focus, and confirmation behavior are verified.
- [ ] Targeted/full/parallel/coverage/static-analysis/build/migration/seeding/browser gates have observed results.

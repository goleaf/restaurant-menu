<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\CopyLocalImageAction;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class DuplicateMenuItemAction
{
    public function __construct(private readonly EnsureMenuOperationAccessAction $access, private readonly CopyLocalImageAction $copyImage) {}

    public function handle(User $actor, Branch $branch, MenuItem $item, string $requestId): MenuOperation
    {
        return DB::transaction(function () use ($actor, $branch, $item, $requestId): MenuOperation {
            $branch = $this->access->handle($actor, $branch, $requestId);
            $existing = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($existing !== null) {
                $this->access->assertOwner($existing, $actor, $branch);
                if ($existing->kind !== MenuOperationKind::DuplicateItem || $existing->target_id !== (int) $item->id) {
                    throw new AuthorizationException;
                }

                return $existing;
            }
            $source = MenuItem::query()->whereKey($item->id)->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id)->whereNull('menus.deleted_at'))
                ->with(['translations' => fn ($query) => $query->limit(4), 'galleryImages' => fn ($query) => $query->limit(MenuItem::MAX_IMAGES)])
                ->withCount('galleryImages')->lockForUpdate()->first();
            if (! $source instanceof MenuItem || ! MenuCategory::query()->whereKey($source->category_id)->where('menu_id', $source->menu_id)->exists()
                || ($source->kitchen_department_id !== null && ! KitchenDepartment::query()->whereKey($source->kitchen_department_id)->where('branch_id', $branch->id)->exists())) {
                throw new AuthorizationException;
            }
            if (MenuOperation::query()->where('active_scope', 'menu:'.$source->menu_id)->exists()) {
                throw ValidationException::withMessages(['operation' => __('menu.operations.errors.already_running')]);
            }
            if ((int) $source->gallery_images_count + (filled($source->image) ? 1 : 0) > MenuItem::MAX_IMAGES || $source->translations->count() > 3) {
                throw ValidationException::withMessages(['operation' => __('menu.operations.errors.invalid_source')]);
            }
            $copy = new MenuItem;
            $copy->forceFill($source->only($source->getFillable()));
            $copy->forceFill(['name' => $this->copyName($source->name, $requestId), 'image' => null, 'is_available' => false, 'deleted_at' => now()]);
            if ($copy->save() !== true) {
                throw new RuntimeException('The staged menu copy could not be saved.');
            }
            foreach (['en', 'lt', 'ru'] as $locale) {
                $translation = $source->translations->firstWhere('language_code', $locale);
                if (! $copy->translations()->create(['language_code' => $locale,
                    'name' => $this->copyName($translation->name ?? $source->name, $requestId, $locale),
                    'description' => $translation->description ?? $source->description])->exists) {
                    throw new RuntimeException('The copied item translation could not be saved.');
                }
            }
            $directory = 'media/organizations/'.$branch->organization_id.'/brands/'.$branch->brand_id.'/branches/'.$branch->id.'/menu-items/'.$copy->id.'/images';
            $plan = [];
            if (filled($source->image)) {
                $plan[] = $this->copyImage->plan($source->image, $directory) + ['primary' => true, 'sort_order' => 0];
            }
            foreach ($source->galleryImages as $image) {
                $plan[] = $this->copyImage->plan($image->path, $directory) + ['primary' => false, 'sort_order' => $image->sort_order];
            }
            $operation = new MenuOperation;
            $operation->forceFill(['request_id' => $requestId, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id,
                'menu_id' => $source->menu_id, 'kind' => MenuOperationKind::DuplicateItem, 'target_id' => $source->id,
                'result_id' => $copy->id, 'active_scope' => 'menu:'.$source->menu_id, 'phase' => MenuOperationPhase::Media,
                'processed_count' => 4, 'pending_cleanup' => array_column($plan, 'target'),
                'payload' => ['media_plan' => $plan, 'source_gallery' => $source->galleryImages->map->only(['id', 'path', 'sort_order'])->all()]]);
            if ($operation->save() !== true) {
                throw new RuntimeException('The copy operation could not be saved.');
            }

            return $operation;
        });
    }

    private function copyName(string $name, string $requestId, ?string $locale = null): string
    {
        return __('menu.operations.copy_name', ['name' => Str::limit($name, 120, ''), 'suffix' => substr($requestId, 0, 8)], $locale);
    }
}

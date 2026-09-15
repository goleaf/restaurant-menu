<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Livewire\Forms\MenuImagePresentationForm;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\User;
use App\Support\MenuImagePresentation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateMenuItemImagePresentationAction
{
    public function __construct(private readonly ForgetBranchCacheAction $forgetBranchCache) {}

    /** @return array{focal_x: int, focal_y: int, translations: array<string, array{alt: string, caption: string}>} */
    public function handle(User $actor, Branch $branch, int $itemId, ?int $imageId, string $expectedIdentity, string $expectedVersion, mixed $input): array
    {
        return DB::transaction(function () use ($actor, $branch, $itemId, $imageId, $expectedIdentity, $expectedVersion, $input): array {
            $currentActor = $actor->fresh();
            $currentBranch = Branch::query()->whereKey($branch->id)
                ->where('organization_id', $branch->organization_id)->where('brand_id', $branch->brand_id)->first();
            if (! $currentActor instanceof User || ! $currentBranch instanceof Branch) {
                throw new AuthorizationException;
            }
            Gate::forUser($currentActor)->authorize('manageMenu', $currentBranch);
            $presentation = MenuImagePresentationForm::validatedInput($input);
            $item = MenuItem::query()->select(['id', 'menu_id', 'image', 'image_presentation'])
                ->whereKey($itemId)->whereHas('menu', fn ($query) => $query->where('branch_id', $currentBranch->id))
                ->lockForUpdate()->first();
            if (! $item instanceof MenuItem) {
                throw new AuthorizationException;
            }
            $image = $imageId === null ? null : MenuItemImage::query()
                ->select(['id', 'menu_item_id', 'path', 'presentation'])
                ->whereKey($imageId)->where('menu_item_id', $item->id)->lockForUpdate()->first();
            if ($imageId !== null && ! $image instanceof MenuItemImage) {
                throw new AuthorizationException;
            }
            $path = $image->path ?? $item->image;
            if (! is_string($path) || $path === '' || ! hash_equals(hash('sha256', $path), $expectedIdentity)) {
                throw ValidationException::withMessages(['presentation' => __('uploads.errors.image_changed')]);
            }
            $current = $image instanceof MenuItemImage ? $image->presentation : $item->image_presentation;
            if (MenuImagePresentation::normalize($current) === $presentation) {
                DB::afterCommit(fn () => $this->forgetBranchCache->handle($currentBranch->id));

                return $presentation;
            }
            if (! hash_equals(MenuImagePresentation::version($current), $expectedVersion)) {
                throw ValidationException::withMessages(['presentation' => __('uploads.presentation.stale')]);
            }
            $target = $image ?? $item;
            $target->setAttribute($image === null ? 'image_presentation' : 'presentation', [...$presentation, 'revision' => (string) Str::uuid()]);
            if ($target->save() !== true) {
                throw new RuntimeException('The image presentation could not be saved.');
            }
            DB::afterCommit(fn () => $this->forgetBranchCache->handle($currentBranch->id));

            return $presentation;
        });
    }
}

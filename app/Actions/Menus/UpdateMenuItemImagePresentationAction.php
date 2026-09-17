<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Livewire\Forms\MenuImagePresentationForm;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\User;
use App\Support\MenuImagePresentation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateMenuItemImagePresentationAction
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly EnsureMenuOperationAccessAction $access,
    ) {}

    /** @return array{focal_x: int, focal_y: int, translations: array<string, array{alt: string, caption: string}>} */
    public function handle(User $actor, Branch $branch, int $itemId, ?int $imageId, string $expectedIdentity, string $expectedVersion, mixed $input, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $branch, $itemId, $imageId, $expectedIdentity, $expectedVersion, $input, $requestId): array {
            $currentBranch = $this->access->handle($actor, $branch, $requestId);
            $presentation = MenuImagePresentationForm::validatedInput($input);
            $payload = ['image_id' => $imageId, 'image_identity' => $expectedIdentity, 'expected_version' => $expectedVersion, 'presentation' => $presentation];
            $receipt = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($receipt instanceof MenuOperation) {
                $this->access->assertOwner($receipt, $actor, $currentBranch);
                if ($receipt->kind !== MenuOperationKind::ImagePresentation || $receipt->target_id !== $itemId
                    || $receipt->payload !== $payload || $receipt->completed_at === null) {
                    throw new AuthorizationException;
                }
            }
            $item = MenuItem::query()->select(['id', 'menu_id', 'image', 'image_presentation'])
                ->whereKey($itemId)->whereHas('menu', fn ($query) => $query->where('branch_id', $currentBranch->id))
                ->lockForUpdate()->first();
            if (! $item instanceof MenuItem) {
                throw new AuthorizationException;
            }
            if ($receipt instanceof MenuOperation) {
                DB::afterCommit(fn () => $this->forgetBranchCache->handle($currentBranch->id));

                return $presentation;
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
            if (! hash_equals(MenuImagePresentation::version($current), $expectedVersion)) {
                throw ValidationException::withMessages(['presentation' => __('uploads.presentation.stale')]);
            }
            if (MenuImagePresentation::normalize($current) !== $presentation) {
                $target = $image ?? $item;
                $target->setAttribute($image === null ? 'image_presentation' : 'presentation', [...$presentation, 'revision' => (string) Str::uuid()]);
                if ($target->save() !== true) {
                    throw new RuntimeException('The image presentation could not be saved.');
                }
            }
            $receipt = new MenuOperation;
            $receipt->forceFill(['request_id' => $requestId, 'branch_id' => $currentBranch->id, 'actor_user_id' => $actor->id,
                'menu_id' => $item->menu_id, 'target_id' => $item->id, 'result_id' => $item->id,
                'kind' => MenuOperationKind::ImagePresentation, 'phase' => MenuOperationPhase::Completed,
                'payload' => $payload, 'processed_count' => 1, 'completed_at' => now()]);
            if ($receipt->save() !== true) {
                throw new RuntimeException('The image presentation receipt could not be saved.');
            }
            DB::afterCommit(fn () => $this->forgetBranchCache->handle($currentBranch->id));

            return $presentation;
        }, attempts: 3);
    }
}

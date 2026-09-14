<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\CopyLocalImageAction;
use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Enums\MenuOperationPhase;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ContinueMenuDuplicationAction
{
    public function __construct(private readonly CopyLocalImageAction $copyImage, private readonly DeleteLocalMediaFileAction $deleteImage) {}

    public function handle(MenuOperation $operation): void
    {
        $source = MenuItem::query()->whereKey($operation->target_id)->where('menu_id', $operation->menu_id)->first();
        $copy = MenuItem::withTrashed()->whereKey($operation->result_id)->where('menu_id', $operation->menu_id)->firstOrFail();
        if ($source === null || $operation->source_changed || ! $copy->trashed()
            || ! MenuCategory::query()->whereKey($copy->category_id)->where('menu_id', $operation->menu_id)->exists()) {
            $this->fail($operation);

            return;
        }
        if ($source->galleryImages()->limit(MenuItem::MAX_IMAGES)->get(['id', 'path', 'sort_order'])->map->only(['id', 'path', 'sort_order'])->all() !== ($operation->payload['source_gallery'] ?? [])) {
            $this->fail($operation);

            return;
        }
        match ($operation->phase) {
            MenuOperationPhase::Media => $this->copyMedia($operation, $copy),
            MenuOperationPhase::Variants => $this->copyVariants($operation, $source, $copy),
            MenuOperationPhase::Modifiers => $this->copyModifiers($operation, $source, $copy),
            MenuOperationPhase::Finalizing => $this->publish($operation, $copy),
            default => throw new RuntimeException('Unsupported duplicate phase.'),
        };
    }

    private function copyMedia(MenuOperation $operation, MenuItem $copy): void
    {
        $plan = $operation->payload['media_plan'] ?? [];
        if (! is_array($plan) || count($plan) > MenuItem::MAX_IMAGES) {
            throw new RuntimeException('Invalid duplicate media plan.');
        }
        foreach ($operation->pending_cleanup as $path) {
            $this->deleteImage->handle($path);
        }
        $destinations = $operation->pending_cleanup;
        DB::afterRollBack(function () use ($destinations): void {
            foreach ($destinations as $path) {
                $this->deleteImage->handle($path);
            }
        });
        foreach ($plan as $entry) {
            $this->copyImage->handle($entry['source'], $entry['target']);
            if ($entry['primary']) {
                $copy->image = $entry['target'];
                if ($copy->save() !== true) {
                    throw new RuntimeException('The copied primary image could not be saved.');
                }
            } elseif (! $copy->galleryImages()->create(['path' => $entry['target'], 'sort_order' => $entry['sort_order']])->exists) {
                throw new RuntimeException('The copied gallery image could not be saved.');
            }
            $operation->processed_count++;
        }
        $operation->pending_cleanup = [];
        $operation->phase = MenuOperationPhase::Variants;
        $operation->cursor = 0;
    }

    private function copyVariants(MenuOperation $operation, MenuItem $source, MenuItem $copy): void
    {
        $variants = $source->variants()->reorder()->where('id', '>', $operation->cursor)->orderBy('id')->limit(12)
            ->with(['translations' => fn ($query) => $query->limit(4)])->get();
        foreach ($variants as $variant) {
            if ($variant->translations->count() > 3) {
                $this->fail($operation);

                return;
            }
            $replica = $copy->variants()->create($variant->only(['type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order']));
            if (! $replica->exists) {
                throw new RuntimeException('The copied variant could not be saved.');
            }
            foreach ($variant->translations as $translation) {
                if (! $replica->translations()->create($translation->only(['language_code', 'name']))->exists) {
                    throw new RuntimeException('The copied variant translation could not be saved.');
                }
            }
            $operation->processed_count += 1 + $variant->translations->count();
            $operation->cursor = $variant->id;
        }
        if ($variants->count() < 12) {
            $operation->phase = MenuOperationPhase::Modifiers;
            $operation->cursor = 0;
        }
    }

    private function copyModifiers(MenuOperation $operation, MenuItem $source, MenuItem $copy): void
    {
        $groups = $source->modifierGroups()->select(['modifier_groups.id'])->reorder()->where('modifier_groups.branch_id', $operation->branch_id)
            ->where('modifier_groups.id', '>', $operation->cursor)->orderBy('modifier_groups.id')->limit(50)->get();
        $copy->modifierGroups()->attach($groups->modelKeys());
        $operation->processed_count += $groups->count();
        if ($groups->isNotEmpty()) {
            $operation->cursor = $groups->last()->id;
        }
        if ($groups->count() < 50) {
            $operation->phase = MenuOperationPhase::Finalizing;
            $operation->cursor = 0;
        }
    }

    private function publish(MenuOperation $operation, MenuItem $copy): void
    {
        if ($copy->restore() !== true) {
            throw new RuntimeException('The completed copy could not be published.');
        }
        $operation->phase = MenuOperationPhase::Completed;
        $operation->completed_at = now();
        $operation->active_scope = null;
    }

    private function fail(MenuOperation $operation): void
    {
        $operation->phase = MenuOperationPhase::Failed;
        $operation->active_scope = null;
        $operation->pending_cleanup = array_column($operation->payload['media_plan'] ?? [], 'target');
        if ($operation->pending_cleanup === []) {
            $operation->completed_at = now();
        }
    }
}

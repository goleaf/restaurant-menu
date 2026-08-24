<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateModifierOptionAction
{
    public function __construct(
        private readonly BuildModifierOptionAttributesAction $buildAttributes,
        private readonly SyncModifierOptionTranslationsAction $syncTranslations,
    ) {}

    /** @param array{name: string, price_delta?: string|int, is_available?: bool, sort_order: int, translations?: array<string, string|null>} $data */
    public function handle(User $actor, Branch $branch, ModifierOption $option, array $data): ModifierOption
    {
        $belongsToBranch = ModifierGroup::query()
            ->whereKey($option->modifier_group_id)
            ->where('branch_id', $branch->id)
            ->exists();

        if (! $belongsToBranch) {
            throw new InvalidArgumentException('The modifier option must belong to the selected branch.');
        }

        return DB::transaction(function () use ($actor, $branch, $option, $data): ModifierOption {
            $option->updateOrFail($this->buildAttributes->handle($actor, $branch, $data, $option));

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($option, $data['translations']);
            }

            return $option->refresh()->load('translations');
        }, attempts: 3);
    }
}

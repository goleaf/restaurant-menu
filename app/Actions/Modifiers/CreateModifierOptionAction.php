<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateModifierOptionAction
{
    public function __construct(
        private readonly BuildModifierOptionAttributesAction $buildAttributes,
        private readonly SyncModifierOptionTranslationsAction $syncTranslations,
    ) {}

    /** @param array{name: string, price_delta?: string|int, is_available?: bool, sort_order: int, translations?: array<string, string|null>} $data */
    public function handle(User $actor, Branch $branch, ModifierGroup $group, array $data): ModifierOption
    {
        if ($group->branch_id !== $branch->id) {
            throw new InvalidArgumentException('The modifier group must belong to the selected branch.');
        }

        return DB::transaction(function () use ($actor, $branch, $group, $data): ModifierOption {
            $option = $group->options()->create($this->buildAttributes->handle($actor, $branch, $data));

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($option, $data['translations']);
            }

            return $option->load('translations');
        }, attempts: 3);
    }
}

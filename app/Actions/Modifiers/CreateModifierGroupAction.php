<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;

final class CreateModifierGroupAction
{
    public function __construct(
        private readonly SyncModifierGroupTranslationsAction $syncTranslations,
    ) {}

    /** @param array{name: string, is_required: bool, min_select: int, max_select: int, sort_order: int, translations?: array<string, string|null>} $data */
    public function handle(Branch $branch, array $data): ModifierGroup
    {
        return DB::transaction(function () use ($branch, $data): ModifierGroup {
            $group = $branch->modifierGroups()->create([
                'name' => PlainText::required($data['name'], 160, squish: true),
                'is_required' => $data['is_required'],
                'min_select' => $data['min_select'],
                'max_select' => $data['max_select'],
                'sort_order' => $data['sort_order'],
            ]);

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($group, $data['translations']);
            }

            return $group->load('translations');
        }, attempts: 3);
    }
}

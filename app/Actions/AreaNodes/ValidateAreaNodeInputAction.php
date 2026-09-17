<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Support\Validation\Branches\AreaRules;
use Illuminate\Support\Facades\Validator;

final class ValidateAreaNodeInputAction
{
    /** @param array<string,mixed> $data @return array{parent_id:int|null,type:string,name:string,icon:string|null,sort_order:int,is_active:bool} */
    public function handle(array $data): array
    {
        if (is_string($data['name'] ?? null)) {
            $data['name'] = trim($data['name']);
        }
        $validated = Validator::make($data, AreaRules::mutation(), attributes: AreaRules::mutationAttributes())->validate();

        return [
            'parent_id' => isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            'type' => $validated['type'], 'name' => $validated['name'], 'icon' => $validated['icon'] ?? null,
            'sort_order' => (int) $validated['sort_order'], 'is_active' => (bool) $validated['is_active'],
        ];
    }
}

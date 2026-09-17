<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints;

use App\Support\Floor\FloorOptions;
use App\Support\Validation\Branches\ServicePointRules;
use Illuminate\Support\Facades\Validator;

final class ValidateServicePointInputAction
{
    /** @param array<string,mixed> $data */
    public function bulk(array $data): void
    {
        $map = ['type' => 'bulkType', 'prefix' => 'bulkPrefix', 'from' => 'bulkFrom', 'to' => 'bulkTo', 'capacity' => 'bulkCapacity',
            'area_node_id' => 'areaNodeId', 'icon' => 'icon', 'is_active' => 'isActive'];
        $input = [];
        foreach ($map as $storage => $field) {
            if (array_key_exists($storage, $data)) {
                $input[$field] = $data[$storage];
            }
        }
        $rules = ServicePointRules::bulkServicePoint();
        $pointRules = ServicePointRules::servicePoint(iconValues: FloorOptions::icons());
        $rules['icon'] = ['nullable', ...array_filter($pointRules['icon'], static fn ($rule): bool => $rule !== 'required')];
        $rules['isActive'] = ['required', ...$pointRules['isActive']];
        $rules['areaNodeId'] = ['nullable', 'numeric', 'integer'];
        $attributes = [];
        foreach (['bulkType' => 'type', 'bulkPrefix' => 'prefix', 'bulkFrom' => 'from', 'bulkTo' => 'to', 'bulkCapacity' => 'capacity', 'areaNodeId' => 'area', 'icon' => 'icon', 'isActive' => 'active'] as $field => $key) {
            $attributes[$field] = __('floor.fields.'.$key);
        }
        Validator::make($input, $rules, attributes: $attributes)->validate();
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{area_node_id:int|null,type:string,name:string,display_number:string|null,capacity:int,icon:string|null,is_active:bool}
     */
    public function handle(array $data): array
    {
        $map = ['area_node_id' => 'areaNodeId', 'type' => 'type', 'name' => 'name', 'display_number' => 'displayNumber', 'capacity' => 'capacity', 'icon' => 'icon', 'is_active' => 'isActive'];
        $input = [];
        foreach ($map as $storage => $field) {
            if (array_key_exists($storage, $data)) {
                $input[$field] = in_array($storage, ['name', 'display_number'], true) && is_string($data[$storage]) ? trim($data[$storage]) : $data[$storage];
            }
        }
        $rules = ServicePointRules::servicePoint(iconValues: FloorOptions::icons());
        $rules['icon'] = ['nullable', ...array_filter($rules['icon'], static fn ($rule): bool => $rule !== 'required')];
        $rules['isActive'] = ['required', ...$rules['isActive']];
        $rules['areaNodeId'] = ['nullable', 'numeric', 'integer'];
        $attributes = [];
        foreach (['areaNodeId' => 'area', 'type' => 'type', 'name' => 'name', 'displayNumber' => 'number', 'capacity' => 'capacity', 'icon' => 'icon', 'isActive' => 'active'] as $field => $key) {
            $attributes[$field] = __('floor.fields.'.$key);
        }
        $validated = Validator::make($input, $rules, attributes: $attributes)->validate();

        return ['area_node_id' => isset($validated['areaNodeId']) ? (int) $validated['areaNodeId'] : null,
            'type' => $validated['type'], 'name' => $validated['name'], 'display_number' => $validated['displayNumber'] ?? null,
            'capacity' => (int) $validated['capacity'], 'icon' => $validated['icon'] ?? null, 'is_active' => (bool) $validated['isActive']];
    }
}

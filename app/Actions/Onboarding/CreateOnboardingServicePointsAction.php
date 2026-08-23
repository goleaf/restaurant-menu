<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\ServicePoints\CreateServicePointAction;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;

final readonly class CreateOnboardingServicePointsAction
{
    public function __construct(private CreateServicePointAction $createServicePoint) {}

    /**
     * @param  array{tableCount: int, tablePrefix: string, tableCapacity: int}  $data
     * @return list<int>
     */
    public function handle(Branch $branch, AreaNode $areaNode, array $data): array
    {
        $servicePointIds = [];

        for ($number = 1; $number <= $data['tableCount']; $number++) {
            $servicePoint = $this->createServicePoint->handle($branch, [
                'area_node_id' => $areaNode->id,
                'type' => ServicePointType::Table->value,
                'name' => $data['tablePrefix'].' '.$number,
                'display_number' => (string) $number,
                'capacity' => $data['tableCapacity'],
                'icon' => 'squares-2x2',
                'is_active' => true,
            ]);

            $servicePointIds[] = $servicePoint->id;
        }

        return $servicePointIds;
    }
}

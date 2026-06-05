<?php

namespace App\Actions\ServicePoints;

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BulkCreateServicePointsAction
{
    public const MAX_RANGE_SIZE = 200;

    /**
     * @param  array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool}  $data
     * @return list<array{code: string, name: string, display_number: string, exists: bool, will_create: bool}>
     */
    public function preview(Branch $branch, array $data): array
    {
        $this->ensureAreaNodeBelongsToBranch($branch, $data['area_node_id']);

        $codes = $this->codes($data['prefix'], $data['from'], $data['to']);
        $existingCodes = $this->existingCodes($branch, $codes);

        return collect($codes)
            ->map(fn (string $code): array => [
                'code' => $code,
                'name' => $code,
                'display_number' => $code,
                'exists' => in_array($code, $existingCodes, true),
                'will_create' => ! in_array($code, $existingCodes, true),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool}  $data
     * @return array{created_count: int, skipped_count: int, created_ids: list<int>, preview: list<array{code: string, name: string, display_number: string, exists: bool, will_create: bool}>}
     */
    public function handle(Branch $branch, array $data): array
    {
        return DB::transaction(function () use ($branch, $data): array {
            $preview = $this->preview($branch, $data);
            $creatableRows = collect($preview)
                ->filter(fn (array $row): bool => (bool) $row['will_create'])
                ->values();

            if ($creatableRows->isEmpty()) {
                return [
                    'created_count' => 0,
                    'skipped_count' => count($preview),
                    'created_ids' => [],
                    'preview' => $preview,
                ];
            }

            $servicePoints = $creatableRows
                ->map(function (array $row) use ($branch, $data): ServicePoint {
                    $servicePoint = $branch->servicePoints()->make([
                        'area_node_id' => $data['area_node_id'],
                        'type' => ServicePointType::from($data['type']),
                        'name' => $row['name'],
                        'display_number' => $row['display_number'],
                        'internal_code' => $row['code'],
                        'capacity' => $data['capacity'],
                        'icon' => $data['icon'],
                        'is_active' => $data['is_active'],
                        'metadata' => [],
                    ]);
                    $servicePoint->forceFill(['status' => ServicePointStatus::Free])->save();

                    return $servicePoint;
                });
            $createdCount = $servicePoints->count();

            return [
                'created_count' => $createdCount,
                'skipped_count' => count($preview) - $createdCount,
                'created_ids' => $servicePoints
                    ->pluck('id')
                    ->map(fn (int $id): int => $id)
                    ->values()
                    ->all(),
                'preview' => $this->preview($branch, $data),
            ];
        });
    }

    private function ensureAreaNodeBelongsToBranch(Branch $branch, ?int $areaNodeId): void
    {
        if ($areaNodeId === null) {
            return;
        }

        $areaNodeExists = AreaNode::query()
            ->whereKey($areaNodeId)
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $areaNodeExists) {
            throw new InvalidArgumentException('The selected area is not available.');
        }
    }

    /**
     * @return list<string>
     */
    private function codes(string $prefix, int $from, int $to): array
    {
        return collect(range($from, $to))
            ->map(fn (int $number): string => $prefix.$number)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function existingCodes(Branch $branch, array $codes): array
    {
        return ServicePoint::query()
            ->withTrashed()
            ->where('branch_id', $branch->id)
            ->whereIn('internal_code', $codes)
            ->pluck('internal_code')
            ->filter()
            ->map(fn (string $code): string => $code)
            ->values()
            ->all();
    }
}

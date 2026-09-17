<?php

namespace App\Actions\ServicePoints;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Floor\RunFloorOperationAction;
use App\Actions\ServicePoints\Support\ServicePointMutationGuard;
use App\Enums\AuditLogAction;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BulkCreateServicePointsAction
{
    public const MAX_RANGE_SIZE = 200;

    public function __construct(
        private readonly ServicePointMutationGuard $guard,
        private readonly ValidateServicePointInputAction $validateInput,
        private readonly RunFloorOperationAction $operations,
        private readonly RecordAuditLogAction $audit,
    ) {}

    /**
     * @param  array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool}  $data
     * @return list<array{code: string, name: string, display_number: string, exists: bool, will_create: bool}>
     */
    public function preview(Branch $branch, array $data, ?User $actor = null): array
    {
        return $this->previewState($branch, $data, $actor)['rows'];
    }

    /**
     * @param  array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool}  $data
     * @return array{rows: list<array{code: string, name: string, display_number: string, exists: bool, will_create: bool}>, fingerprint: string}
     */
    public function previewState(Branch $branch, array $data, ?User $actor = null): array
    {
        $this->validateData($data);
        $currentBranch = $this->guard->branch($branch->id);
        Gate::forUser($this->guard->actor($actor))->authorize('create', [ServicePoint::class, $currentBranch]);

        return $this->state($currentBranch, $data);
    }

    /**
     * @param  array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool}  $data
     * @return array{rows: list<array{code: string, name: string, display_number: string, exists: bool, will_create: bool}>, fingerprint: string}
     */
    private function state(Branch $branch, array $data): array
    {
        $codes = $this->codes($data['prefix'], $data['from'], $data['to']);
        $existingCodes = $this->existingCodes($branch, $codes);
        sort($existingCodes);
        $areaVersion = $data['area_node_id'] === null ? null : AreaNode::query()->where('branch_id', $branch->id)->whereKey($data['area_node_id'])->value('structure_version');
        if ($data['area_node_id'] !== null && $areaVersion === null) {
            throw new \InvalidArgumentException('errors.domain.selected_area_unavailable');
        }
        $rows = array_map(static fn (string $code): array => [
            'code' => $code, 'name' => $code, 'display_number' => $code,
            'exists' => in_array($code, $existingCodes, true), 'will_create' => ! in_array($code, $existingCodes, true),
        ], $codes);

        return ['rows' => $rows, 'fingerprint' => hash('sha256', json_encode([$branch->id, $data, $existingCodes, $areaVersion], JSON_THROW_ON_ERROR))];
    }

    /**
     * @param  array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool}  $data
     * @return array{created_count: int, skipped_count: int, created_ids: list<int>, preview: list<array{code: string, name: string, display_number: string, exists: bool, will_create: bool}>}
     */
    public function handle(Branch $branch, array $data, ?User $actor = null, ?string $requestId = null, ?string $expectedPreviewFingerprint = null): array
    {
        $this->validateData($data);
        $authorize = static fn (User $user, Branch $currentBranch) => Gate::forUser($user)->authorize('create', [ServicePoint::class, $currentBranch]);
        $apply = function (User $user, Branch $branch) use ($data, $expectedPreviewFingerprint): array {
            $state = $this->state($branch, $data);
            if ($expectedPreviewFingerprint !== null && ! hash_equals($state['fingerprint'], $expectedPreviewFingerprint)) {
                throw ValidationException::withMessages(['expectedPreviewFingerprint' => __('floor.errors.selection_changed')]);
            }
            $preview = $state['rows'];
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
                ->map(function (array $row) use ($branch, $data, $user): ServicePoint {
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
                    if (! $servicePoint->forceFill(['status' => ServicePointStatus::Free])->save()) {
                        throw new RuntimeException('The service point could not be saved.');
                    }

                    $this->audit->handle(AuditLogAction::ServicePointChanged, 'service_point', $servicePoint->id,
                        actorUser: $user, organizationId: $branch->organization_id, branchId: $branch->id,
                        newValues: ['kind' => 'created', ...$servicePoint->only(['name', 'display_number', 'area_node_id', 'type', 'capacity', 'is_active'])]);

                    return $servicePoint;
                });
            $createdCount = $servicePoints->count();
            $createdCodes = array_fill_keys($servicePoints->pluck('internal_code')->all(), true);

            return [
                'created_count' => $createdCount,
                'skipped_count' => count($preview) - $createdCount,
                'created_ids' => $servicePoints
                    ->pluck('id')
                    ->map(fn (int $id): int => $id)
                    ->values()
                    ->all(),
                'preview' => array_map(function (array $row) use ($createdCodes): array {
                    $exists = $row['exists'] || isset($createdCodes[$row['code']]);

                    return [...$row, 'exists' => $exists, 'will_create' => ! $exists];
                }, $preview),
            ];
        };
        if ($requestId !== null) {
            return $this->operations->handle($this->guard->actor($actor), $branch, $requestId, 'service_point_bulk_create', null,
                ['data' => $data, 'fingerprint' => $expectedPreviewFingerprint], $authorize, $apply);
        }

        return DB::transaction(function () use ($actor, $branch, $authorize, $apply): array {
            $user = $this->guard->actor($actor);
            $currentBranch = $this->guard->branch($branch->id);
            $authorize($user, $currentBranch);

            return $apply($user, $currentBranch);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function validateData(array $data): void
    {
        if (is_int($data['from'] ?? null) && is_int($data['to'] ?? null)) {
            $this->validateRange($data['from'], $data['to']);
        }
        $this->validateInput->bulk($data);
        $this->validateRange((int) $data['from'], (int) $data['to']);
    }

    private function validateRange(int $from, int $to): void
    {
        if ($from < 1) {
            throw ValidationException::withMessages([
                'bulkFrom' => __('errors.domain.bulk_start_positive'),
            ]);
        }

        if ($to < $from) {
            throw ValidationException::withMessages([
                'bulkTo' => __('errors.domain.bulk_end_before_start'),
            ]);
        }

        if ($to - $from >= self::MAX_RANGE_SIZE) {
            throw ValidationException::withMessages([
                'bulkTo' => __('ui.livewire.organizations.brands.branches.servicepoints.index.create_up_to', [
                    'count' => self::MAX_RANGE_SIZE,
                ]),
            ]);
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

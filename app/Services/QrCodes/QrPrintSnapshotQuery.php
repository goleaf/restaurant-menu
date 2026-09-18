<?php

declare(strict_types=1);

namespace App\Services\QrCodes;

use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodeSvgRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class QrPrintSnapshotQuery
{
    public const int MAX_SERVICE_POINTS = 100;

    public function __construct(private QrCodeSvgRenderer $renderer, private PublicQrUrl $publicUrl) {}

    /** @param list<int> $servicePointIds @param array<int,int> $expectedQrIds @return list<array{id:int,name:string,state:string,label:string}> */
    public function availability(User $actor, Branch $branch, array $servicePointIds, array $expectedQrIds = []): array
    {
        $this->validateSelection($servicePointIds, 'en', $expectedQrIds);
        $actor = $actor->fresh();
        abort_unless($actor instanceof User, 401);
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'is_active', 'deleted_at'])
            ->whereKey($branch->id)->where('organization_id', $branch->organization_id)->where('brand_id', $branch->brand_id)->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', [QrCode::class, $branch]);
        $points = ServicePoint::query()->select(['id', 'branch_id', 'name'])
            ->where('branch_id', $branch->id)->whereKey($servicePointIds)->limit(self::MAX_SERVICE_POINTS)
            ->with([
                'activeQrCode:id,service_point_id,status',
                'qrCodes' => fn ($query) => $query->select(['id', 'service_point_id', 'status'])->latest('id')->limit(1),
            ])->orderBy('name')->orderBy('id')->get();
        if ($points->count() !== count($servicePointIds)) {
            throw ValidationException::withMessages(['service_points' => __('floor.validation.print_changed')]);
        }

        return $points->map(function (ServicePoint $point) use ($expectedQrIds): array {
            $latest = $point->qrCodes->first();
            $state = $point->activeQrCode instanceof QrCode ? 'ready' : ($latest?->status->value ?? 'missing');
            if (isset($expectedQrIds[$point->id]) && $expectedQrIds[$point->id] !== $point->activeQrCode?->id) {
                $state = 'changed';
            }

            return ['id' => $point->id, 'name' => $point->name, 'state' => $state,
                'label' => match ($state) {
                    'ready' => __('floor.print.ready'),
                    'changed' => __('floor.validation.print_changed'),
                    default => $latest === null ? __('floor.qr.missing') : __($latest->status->label()),
                }];
        })->all();
    }

    /**
     * @param  list<int>  $servicePointIds
     * @param  array<int,int>  $expectedQrIds
     * @return array{fingerprint:string, actor_id:int, branch_id:int, branch_name:string, service_point_ids:list<int>, preset:string, print_table_number:bool, locale:string, items:list<array<string,mixed>>}
     */
    public function prepare(User $actor, Branch $branch, array $servicePointIds, QrLabelPreset $preset, bool $printTableNumber, string $locale, array $expectedQrIds = []): array
    {
        $this->validateSelection($servicePointIds, $locale, $expectedQrIds);
        $actor = $actor->fresh();
        abort_unless($actor instanceof User, 401);
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'is_active', 'deleted_at'])
            ->whereKey($branch->id)->where('organization_id', $branch->organization_id)->where('brand_id', $branch->brand_id)->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', [QrCode::class, $branch]);

        $points = ServicePoint::query()
            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'structure_version', 'is_active', 'status'])
            ->where('branch_id', $branch->id)->whereKey($servicePointIds)->limit(self::MAX_SERVICE_POINTS)
            ->with(['activeQrCode' => fn ($query) => $query
                ->select(['id', 'service_point_id', 'public_token', 'short_code', 'status', 'structure_version'])
                ->where('status', QrCodeStatus::Active->value)])
            ->orderBy('display_number')->orderBy('name')->orderBy('id')->get();

        if ($points->count() !== count($servicePointIds) || $points->contains(fn (ServicePoint $point): bool => ! $point->activeQrCode instanceof QrCode)) {
            throw ValidationException::withMessages(['service_points' => __('qr.validation.pdf_active_required')]);
        }

        $items = [];
        foreach ($points as $point) {
            $qrCode = $point->activeQrCode;
            if (isset($expectedQrIds[$point->id]) && $expectedQrIds[$point->id] !== $qrCode->id) {
                throw ValidationException::withMessages(['service_points' => __('floor.validation.print_changed')]);
            }
            $items[] = [
                'service_point_id' => $point->id,
                'service_point_name' => $point->name,
                'service_point_label' => $point->display_number ?: $point->name,
                'service_point_version' => $point->structure_version,
                'area_node_id' => $point->area_node_id,
                'is_active' => $point->is_active,
                'status' => $point->status->value,
                'qr_code_id' => $qrCode->id,
                'qr_version' => $qrCode->structure_version,
                'short_code' => $qrCode->short_code,
                'qr_image_data_uri' => 'data:image/svg+xml;base64,'.base64_encode($this->renderer->render($this->publicUrl->forToken($qrCode->public_token), 420)),
            ];
        }

        $snapshot = [
            'actor_id' => $actor->id,
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'service_point_ids' => $points->modelKeys(),
            'preset' => $preset->value,
            'print_table_number' => $printTableNumber,
            'locale' => $locale,
            'items' => $items,
        ];

        return ['fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), ...$snapshot];
    }

    /**
     * @param  list<int>  $servicePointIds
     * @param  array<string,mixed>  $reviewed
     * @return array<string,mixed>
     */
    public function reviewed(User $actor, Branch $branch, array $servicePointIds, QrLabelPreset $preset, bool $printTableNumber, array $reviewed): array
    {
        $locale = $reviewed['locale'] ?? null;
        if (! is_string($locale) || ! is_string($reviewed['fingerprint'] ?? null)) {
            throw ValidationException::withMessages(['service_points' => __('floor.validation.print_changed')]);
        }
        $current = $this->prepare($actor, $branch, $servicePointIds, $preset, $printTableNumber, $locale);
        if (! hash_equals($current['fingerprint'], $reviewed['fingerprint'])) {
            throw ValidationException::withMessages(['service_points' => __('floor.validation.print_changed')]);
        }

        return $current;
    }

    /** @param array<array-key,mixed> $ids @param array<array-key,mixed> $expectedQrIds */
    private function validateSelection(array $ids, string $locale, array $expectedQrIds): void
    {
        if (! array_is_list($ids) || $ids === [] || count($ids) > self::MAX_SERVICE_POINTS
            || count(array_filter($ids, fn (mixed $id): bool => is_int($id) && $id > 0)) !== count($ids)
            || count(array_unique($ids)) !== count($ids) || ! in_array($locale, ['en', 'lt', 'ru'], true)
            || array_diff(array_keys($expectedQrIds), $ids) !== []
            || count(array_filter($expectedQrIds, fn (mixed $id): bool => is_int($id) && $id > 0)) !== count($expectedQrIds)) {
            throw ValidationException::withMessages(['service_points' => __('floor.validation.print_selection', ['max' => self::MAX_SERVICE_POINTS])]);
        }
    }
}

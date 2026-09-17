<?php

declare(strict_types=1);

namespace App\Actions\QrCodes;

use App\Enums\QrCodeStatus;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class RepairQrCodeImageAction
{
    public function __construct(private StoreQrCodeImageAction $store) {}

    public function handle(User $actor, Branch $branch, QrCode $qrCode, int $expectedVersion): string
    {
        $actor = $actor->fresh();
        abort_unless($actor instanceof User, 401);
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'deleted_at'])
            ->whereKey($branch->id)->where('organization_id', $branch->organization_id)->where('brand_id', $branch->brand_id)->firstOrFail();
        Gate::forUser($actor)->authorize('generateQr', $branch);
        $current = QrCode::query()->select(['id', 'service_point_id', 'public_token', 'short_code', 'status', 'structure_version'])
            ->whereKey($qrCode->id)->where('service_point_id', $qrCode->service_point_id)
            ->whereHas('servicePoint', fn ($query) => $query->where('branch_id', $branch->id))->firstOrFail();
        $this->assertCurrent($current, $expectedVersion);
        $path = $this->store->handle($current);
        $fresh = $current->fresh();
        if (! $fresh instanceof QrCode || $fresh->status !== QrCodeStatus::Active || $fresh->structure_version !== $expectedVersion) {
            $this->store->delete($current);
            throw ValidationException::withMessages(['expectedVersion' => __('floor.validation.changed')]);
        }

        return $path;
    }

    private function assertCurrent(QrCode $qrCode, int $expectedVersion): void
    {
        if ($qrCode->status !== QrCodeStatus::Active || $qrCode->structure_version !== $expectedVersion) {
            throw ValidationException::withMessages(['expectedVersion' => __('floor.validation.changed')]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\QrCodes\StoreQrCodeImageAction;
use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class GenerateOnboardingQrCodesAction
{
    public function __construct(
        private GenerateQrCodeForServicePointAction $generateQrCode,
        private StoreQrCodeImageAction $storeQrCodeImage,
    ) {}

    public function handle(User $user, int $onboardingId): RestaurantOnboarding
    {
        $result = DB::transaction(function () use ($user, $onboardingId): array {
            $user = User::query()->whereKey($user->id)->firstOrFail();
            $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->whereKey($onboardingId)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $onboarding);
            abort_if($onboarding->branch_id === null, 409);
            abort_if($onboarding->area_node_id === null, 409);
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'name'])
                ->where('organization_id', $onboarding->organization_id)
                ->where('brand_id', $onboarding->brand_id)
                ->whereHas('brand', fn ($query) => $query->where('organization_id', $onboarding->organization_id))
                ->whereKey($onboarding->branch_id)
                ->firstOrFail();
            $area = AreaNode::query()
                ->select(['id', 'branch_id'])
                ->where('branch_id', $branch->id)
                ->whereKey($onboarding->area_node_id)
                ->firstOrFail();
            Gate::forUser($user)->authorize('generateQr', $branch);
            $points = $onboarding->servicePoints()
                ->withTrashed()
                ->select(['service_points.id', 'service_points.branch_id', 'service_points.area_node_id', 'service_points.type', 'service_points.deleted_at'])
                ->limit(BulkCreateServicePointsAction::MAX_RANGE_SIZE + 1)->get();

            abort_if($points->count() > BulkCreateServicePointsAction::MAX_RANGE_SIZE, 409);

            if ($points->isEmpty()) {
                throw ValidationException::withMessages([
                    'form.tableCount' => __('ui.livewire.onboarding.restaurantsetup.snacala_dobavte_stoly'),
                ]);
            }

            abort_if($points->contains(fn ($point): bool => (int) $point->branch_id !== (int) $branch->id), 404);
            abort_unless($onboarding->hasCompleteServicePointSet(
                $points,
                (int) $branch->id,
                (int) $area->id,
                $points->count(),
            ), 409);

            $qrCodes = [];

            foreach ($points as $point) {
                $point->setRelation('branch', $branch);
                Gate::forUser($user)->authorize('create', [QrCode::class, $point]);
                $qrCodes[] = $this->generateQrCode->handle($point, $user, storeImage: false);
            }

            return [
                'onboarding' => $onboarding->refresh(),
                'qr_codes' => $qrCodes,
            ];
        }, attempts: 3);

        foreach ($result['qr_codes'] as $qrCode) {
            $this->storeQrCodeImage->handle($qrCode);
        }

        return $result['onboarding'];
    }
}

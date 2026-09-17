<?php

declare(strict_types=1);

namespace App\Actions\QrCodes;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Floor\RunFloorOperationAction;
use App\Enums\AuditLogAction;
use App\Enums\QrCodeStatus;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\Validation\Floor\QrOperationRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class ApplyFloorQrAction
{
    public function __construct(
        private RunFloorOperationAction $operations,
        private GenerateQrCodeForServicePointAction $generate,
        private DisableQrCodeAction $disable,
        private ReissueQrCodeForServicePointAction $reissue,
        private RepairQrCodeImageAction $repair,
        private StoreQrCodeImageAction $images,
        private RecordAuditLogAction $audit,
    ) {}

    public function handle(User $actor, Branch $branch, ServicePoint $point, string $operation, ?int $qrId, ?int $expectedVersion, string $requestId, string $reason = '', string $confirmation = ''): QrCode
    {
        $data = Validator::make(compact('operation', 'qrId', 'expectedVersion', 'requestId', 'reason', 'confirmation'), QrOperationRules::rules(), attributes: QrOperationRules::attributes())->validate();
        $result = $this->operations->handle($actor, $branch, $requestId, 'qr.'.$operation, $point->id, $data,
            function (User $currentActor, Branch $currentBranch) use ($point): void {
                Gate::forUser($currentActor)->authorize('generateQr', $currentBranch);
                $this->point($currentBranch, $point->id);
            },
            function (User $currentActor, Branch $currentBranch) use ($point, $operation, $qrId, $expectedVersion, $reason, $confirmation): array {
                $currentPoint = $this->point($currentBranch, $point->id);
                if ($operation === 'generate') {
                    $existing = $currentPoint->qrCodes()->select(['id', 'status'])->latest('id')->first();
                    if ($existing !== null && $existing->status !== QrCodeStatus::Active) {
                        throw ValidationException::withMessages(['operation' => __('floor.validation.qr_reissue_required')]);
                    }
                    $qrCode = $this->generate->handle($currentPoint, $currentActor, storeImage: false);
                    if ($qrCode->wasRecentlyCreated) {
                        $this->audit->handle(AuditLogAction::QrGenerated, 'qr_code', $qrCode->id, $currentActor,
                            organizationId: $currentBranch->organization_id, branchId: $currentBranch->id,
                            newValues: ['service_point_id' => $currentPoint->id, 'short_code' => $qrCode->short_code, 'status' => $qrCode->status->value]);
                    }
                } else {
                    $qrCode = $this->qr($currentPoint->id, $qrId);
                    if ($qrCode->structure_version !== $expectedVersion) {
                        throw ValidationException::withMessages(['expectedVersion' => __('floor.validation.changed')]);
                    }
                    if ($operation === 'disable') {
                        $qrCode = $this->disable->handle($qrCode, $currentActor, $reason, $expectedVersion);
                    } elseif ($operation === 'reissue') {
                        if (! hash_equals($qrCode->short_code, $confirmation)) {
                            throw ValidationException::withMessages(['confirmation' => __('qr.validation.reissue_confirmation_mismatch')]);
                        }
                        $qrCode = $this->reissue->handle($qrCode, $currentActor, $expectedVersion, storeImage: false, reason: $reason);
                    } elseif ($qrCode->status !== QrCodeStatus::Active) {
                        throw ValidationException::withMessages(['expectedVersion' => __('floor.validation.changed')]);
                    }
                }

                return ['qr_id' => $qrCode->id, 'qr_version' => $qrCode->structure_version];
            });

        $qrCode = $this->qr($point->id, $result['qr_id']);
        if ($operation !== 'disable') {
            $this->repair->handle($actor, $branch, $qrCode, $result['qr_version']);
        }
        if ($operation === 'reissue' && $qrId !== $qrCode->id) {
            $previous = $this->qr($point->id, $qrId);
            if ($previous->status === QrCodeStatus::Revoked && ! $this->images->delete($previous)) {
                throw new RuntimeException('The retired QR image could not be removed.');
            }
        }

        return $qrCode;
    }

    private function point(Branch $branch, int $pointId): ServicePoint
    {
        return ServicePoint::query()->select(['id', 'branch_id', 'name', 'structure_version'])
            ->where('branch_id', $branch->id)->whereKey($pointId)->firstOrFail();
    }

    private function qr(int $pointId, ?int $qrId): QrCode
    {
        return QrCode::query()->select(['id', 'service_point_id', 'public_token', 'short_code', 'status', 'structure_version'])
            ->where('service_point_id', $pointId)->whereKey($qrId)->firstOrFail();
    }
}

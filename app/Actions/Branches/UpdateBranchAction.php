<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\SupportedCurrency;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateBranchAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /**
     * @param  array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data, User $changedBy, ?string $reason = null): Branch
    {
        return DB::transaction(function () use ($branch, $data, $changedBy, $reason): Branch {
            $originalBranch = $branch;
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active', 'created_at', 'updated_at', 'deleted_at'])
                ->where('organization_id', $branch->getRawOriginal('organization_id'))
                ->where('brand_id', $branch->getRawOriginal('brand_id'))
                ->whereKey($branch->getKey())
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($changedBy->getKey())->first())
                ->authorize('update', $branch);

            $currency = SupportedCurrency::normalize($data['currency']);
            $wasActive = (bool) $branch->is_active;

            $branch->fill([
                'name' => $data['name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'timezone' => $data['timezone'],
                'currency' => $currency,
                'is_active' => $data['is_active'],
            ]);

            $branch->save();

            if ($wasActive && ! (bool) $branch->is_active) {
                $this->recordAuditLog->handle(
                    action: AuditLogAction::BranchSuspended,
                    entityType: 'branch',
                    entityId: $branch->id,
                    actorUser: $changedBy,
                    organizationId: $branch->organization_id,
                    branchId: $branch->id,
                    oldValues: [
                        'name' => $branch->name,
                        'is_active' => true,
                    ],
                    newValues: [
                        'name' => $branch->name,
                        'is_active' => false,
                        'reason' => trim((string) $reason),
                    ],
                );
            }

            $branch->settings()
                ->select(['id', 'branch_id', 'default_currency'])
                ->update(['default_currency' => $currency]);

            return $originalBranch->refresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\SupportedCurrency;
use App\Models\Branch;
use App\Models\User;
use App\Support\Validation\Common\AuditReasonRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateBranchAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly EnsureBranchCurrencyCanChangeAction $ensureCurrency,
    ) {}

    /**
     * @param  array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data, User $changedBy, ?string $reason = null, ?string $expectedFingerprint = null): Branch
    {
        return DB::transaction(function () use ($branch, $data, $changedBy, $reason, $expectedFingerprint): Branch {
            $originalBranch = $branch;
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active', 'created_at', 'updated_at', 'deleted_at'])
                ->where('organization_id', $branch->getRawOriginal('organization_id'))
                ->where('brand_id', $branch->getRawOriginal('brand_id'))
                ->whereKey($branch->getKey())
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($changedBy->getKey())->first())
                ->authorize('update', $branch);

            if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, $branch->identityFingerprint())) {
                throw ValidationException::withMessages(['form.name' => __('center.conflict')]);
            }
            $oldName = $branch->name;

            $currency = SupportedCurrency::clean($data['currency']);
            Validator::make(['currency' => $currency], ['currency' => ['required', Rule::in(SupportedCurrency::values())]], attributes: ['currency' => __('settings.fields.default_currency')])->validate();
            $this->ensureCurrency->handle($branch, $currency, 'form.currency');
            $wasActive = (bool) $branch->is_active;
            if ($wasActive && ! $data['is_active']) {
                $reason = Validator::make(['form' => ['suspensionReason' => trim((string) $reason)]], AuditReasonRules::auditReason('form.suspensionReason'), attributes: ['form.suspensionReason' => __('validation.attributes.suspension_reason')])->validate()['form']['suspensionReason'];
            }

            $branch->fill([
                'name' => $data['name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'timezone' => $data['timezone'],
                'currency' => $currency,
                'is_active' => $data['is_active'],
            ]);

            if ($branch->save() !== true) {
                throw new \RuntimeException('The restaurant identity could not be saved.');
            }

            if ($wasActive && ! (bool) $branch->is_active) {
                $this->recordAuditLog->handle(
                    action: AuditLogAction::BranchSuspended,
                    entityType: 'branch',
                    entityId: $branch->id,
                    actorUser: $changedBy,
                    organizationId: $branch->organization_id,
                    branchId: $branch->id,
                    oldValues: [
                        'name' => $oldName,
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

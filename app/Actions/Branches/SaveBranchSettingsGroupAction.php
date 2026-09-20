<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\SupportedCurrency;
use App\Models\Branch;
use App\Models\User;
use App\Services\Branches\BranchSettingsQueryService;
use App\Support\Branches\BranchSettingsGroup;
use App\Support\MoneyFormatter;
use App\Support\Validation\Branches\BranchSettingsGroupRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class SaveBranchSettingsGroupAction
{
    public function __construct(
        private ExecuteBranchSettingsChangeAction $execute,
        private BranchSettingsQueryService $queries,
        private EnsureBranchSettingsAction $ensureSettings,
        private EnsureBranchCurrencyCanChangeAction $ensureCurrency,
        private RecordAuditLogAction $audit,
    ) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function handle(User $actor, Branch $branch, string $group, array $data, string $expectedFingerprint, string $requestId): array
    {
        $rules = BranchSettingsGroupRules::for($group);
        if (array_diff(array_keys($data), array_keys($rules)) !== []) {
            throw ValidationException::withMessages(['settings' => __('settings.errors.unexpected_fields')]);
        }
        if ($group === 'settlement' && is_string($data['default_currency'] ?? null)) {
            $data['default_currency'] = SupportedCurrency::clean($data['default_currency']);
        }
        $data = Validator::make($data, $rules, attributes: BranchSettingsGroupRules::attributes($group))->validate();
        $data = match ($group) {
            'guest_process' => array_map(static fn (mixed $value): bool => (bool) $value, $data),
            'advanced' => array_map(static fn (mixed $value): int => (int) $value, $data),
            'settlement' => [
                'default_currency' => $data['default_currency'],
                'service_charge_enabled' => (bool) $data['service_charge_enabled'],
                'service_charge_percent' => (string) $data['service_charge_percent'],
                'tips_enabled' => (bool) $data['tips_enabled'],
            ],
            default => $data,
        };

        return $this->execute->handle($actor, $branch, $group, $requestId, [
            'expected_fingerprint' => $expectedFingerprint, 'data' => $data,
        ], function (User $actor, Branch $branch) use ($group, $data, $expectedFingerprint): array {
            $settings = $this->queries->effective($branch);
            if (! hash_equals(BranchSettingsGroup::fingerprint($branch, $settings, $group), $expectedFingerprint)) {
                throw ValidationException::withMessages([$this->formName($group) => __('settings.errors.conflict')]);
            }
            $before = BranchSettingsGroup::values($settings, $group);
            $values = $data;
            if ($group === 'settlement') {
                $this->ensureCurrency->handle($branch, $values['default_currency']);
                $values['service_charge_basis_points'] = MoneyFormatter::decimalToBasisPoints($values['service_charge_percent']);
                unset($values['service_charge_percent']);
            }
            $settings = $settings->exists ? $settings : $this->ensureSettings->handle($branch);
            $settings->fill($values);
            $changed = $settings->isDirty();
            if (! $settings->save()) {
                throw new RuntimeException('The settings group could not be saved.');
            }
            if ($group === 'settlement' && $branch->currency !== $values['default_currency']) {
                $branch->currency = $values['default_currency'];
                if (! $branch->save()) {
                    throw new RuntimeException('The restaurant currency could not be saved.');
                }
                $changed = true;
            }
            $after = BranchSettingsGroup::values($settings, $group);
            if ($changed) {
                $this->audit->handle(
                    action: AuditLogAction::BranchSettingsChanged,
                    entityType: 'branch_settings', entityId: $settings->id, actorUser: $actor,
                    organizationId: $branch->organization_id, branchId: $branch->id,
                    oldValues: ['group' => $group, ...$before], newValues: ['group' => $group, ...$after],
                );
            }

            return ['group' => $group, 'values' => $after, 'fingerprint' => BranchSettingsGroup::fingerprint($branch, $settings, $group)];
        });
    }

    private function formName(string $group): string
    {
        return $group === 'guest_process' ? 'guests' : $group;
    }
}

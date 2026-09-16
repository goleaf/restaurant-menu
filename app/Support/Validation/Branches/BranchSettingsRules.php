<?php

declare(strict_types=1);

namespace App\Support\Validation\Branches;

use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Support\Validation\DecimalMoney;
use Illuminate\Validation\Rule;

final class BranchSettingsRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function branchSettings(): array
    {
        return [
            'requireWaiterConfirmationForOrders' => ['boolean'],
            'allowGuestCreatedSessions' => ['boolean'],
            'allowWaiterOpenedSessions' => ['boolean'],
            'allowGuestInviteLinks' => ['boolean'],
            'guestJoinRequiresApproval' => ['boolean'],
            'pollingIntervalSeconds' => ['required', 'integer', 'min:1', 'max:60'],
            'inactivityWarningMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'pendingSessionExpireMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'defaultLanguage' => ['required', 'string', Rule::in(SupportedLocale::values())],
            'defaultCurrency' => ['required', 'string', 'size:3', Rule::in(SupportedCurrency::values())],
            'serviceChargeEnabled' => ['boolean'],
            'serviceChargePercent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2', new DecimalMoney],
            'tipsEnabled' => ['boolean'],
            'orderFlowMode' => ['required', 'string', Rule::in(BranchOrderFlowMode::values())],
            'serviceModes' => ['required', 'array', 'list', 'min:1', 'max:'.count(BranchServiceMode::cases())],
            'serviceModes.*' => ['required', 'string', 'distinct', Rule::in(BranchServiceMode::values())],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function temporaryClosure(bool $temporarilyClosed): array
    {
        return [
            'temporarilyClosed' => ['boolean'],
            'temporaryClosedReason' => [
                Rule::requiredIf($temporarilyClosed),
                'nullable',
                'string',
                'max:255',
            ],
            'temporaryClosedUntil' => ['nullable', 'date'],
        ];
    }
}

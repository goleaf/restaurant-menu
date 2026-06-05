<?php

namespace App\Support\Validation;

use App\Actions\Media\StoreLocalImageAction;
use App\Enums\AreaNodeType;
use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Enums\KitchenDepartmentType;
use App\Enums\ManualPaymentMethod;
use App\Enums\MenuStatus;
use App\Enums\ServicePointType;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Models\Branch;
use Illuminate\Validation\Rule;

class RestaurantValidationRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function organizationName(string $field = 'name'): array
    {
        return [
            $field => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function brandName(string $field = 'name'): array
    {
        return [
            $field => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function branchBase(string $prefix = ''): array
    {
        return [
            self::field($prefix, 'name') => ['required', 'string', 'max:160'],
            self::field($prefix, 'address') => ['required', 'string', 'max:255'],
            self::field($prefix, 'city') => ['required', 'string', 'max:120'],
            self::field($prefix, 'country') => ['required', 'string', 'max:120'],
            self::field($prefix, 'timezone') => ['required', 'timezone', 'max:64'],
            self::field($prefix, 'currency') => ['required', 'string', 'size:3', Rule::in(SupportedCurrency::values())],
            self::field($prefix, 'isActive') => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function branchProfile(): array
    {
        return [
            'publicName' => ['nullable', 'string', 'max:160'],
            'publicDescription' => ['nullable', 'string', 'max:1200'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'websiteUrl' => ['nullable', 'url', 'max:2048'],
            'instagramUrl' => ['nullable', 'url', 'max:2048'],
            'facebookUrl' => ['nullable', 'url', 'max:2048'],
            'tiktokUrl' => ['nullable', 'url', 'max:2048'],
        ];
    }

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
            'serviceChargePercent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'tipsEnabled' => ['boolean'],
            'orderFlowMode' => ['required', 'string', Rule::in(BranchOrderFlowMode::values())],
            'serviceModes' => ['required', 'array', 'min:1'],
            'serviceModes.*' => ['required', 'string', Rule::in(BranchServiceMode::values())],
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

    /**
     * @return array<string, list<mixed>>
     */
    public static function openingHours(): array
    {
        return [
            'openingHoursConfigured' => ['boolean'],
            'openingHours' => ['array', 'size:7'],
            'openingHours.*.day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'openingHours.*.label' => ['required', 'string', 'max:40'],
            'openingHours.*.is_closed' => ['boolean'],
            'openingHours.*.intervals' => ['array', 'max:4'],
            'openingHours.*.intervals.*.opens_at' => ['nullable', 'date_format:H:i'],
            'openingHours.*.intervals.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @param  list<string>  $iconValues
     * @return array<string, list<mixed>>
     */
    public static function areaNode(string $prefix = '', array $iconValues = []): array
    {
        return [
            self::field($prefix, 'name') => ['required', 'string', 'max:160'],
            self::field($prefix, 'type') => ['required', 'string', Rule::in(AreaNodeType::values())],
            self::field($prefix, 'icon') => self::enumTextRules($iconValues, required: true),
            self::field($prefix, 'sortOrder') => ['required', 'integer', 'min:0', 'max:9999'],
            self::field($prefix, 'isActive') => ['boolean'],
        ];
    }

    /**
     * @param  list<string>  $iconValues
     * @return array<string, list<mixed>>
     */
    public static function servicePoint(string $prefix = '', array $iconValues = []): array
    {
        return [
            self::field($prefix, 'type') => ['required', 'string', Rule::in(ServicePointType::values())],
            self::field($prefix, 'icon') => self::enumTextRules($iconValues, required: true),
            self::field($prefix, 'name') => ['required', 'string', 'max:160'],
            self::field($prefix, 'displayNumber') => ['nullable', 'string', 'max:80'],
            self::field($prefix, 'capacity') => ['required', 'integer', 'min:1', 'max:999'],
            self::field($prefix, 'isActive') => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function bulkServicePoint(): array
    {
        return [
            'bulkType' => ['required', 'string', Rule::in(ServicePointType::values())],
            'bulkPrefix' => ['required', 'string', 'max:20', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'bulkFrom' => ['required', 'integer', 'min:1', 'max:9999'],
            'bulkTo' => ['required', 'integer', 'min:1', 'max:9999', 'gte:bulkFrom'],
            'bulkCapacity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function menu(string $prefix = ''): array
    {
        return [
            self::field($prefix, 'menuName') => ['required', 'string', 'max:160'],
            self::field($prefix, 'menuStatus') => ['required', 'string', Rule::in(MenuStatus::values())],
            self::field($prefix, 'menuSortOrder') => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @param  list<string>  $iconValues
     * @return array<string, list<mixed>>
     */
    public static function category(string $prefix = '', array $iconValues = []): array
    {
        return [
            self::field($prefix, 'categoryName') => ['required', 'string', 'max:160'],
            self::field($prefix, 'categoryDescription') => ['nullable', 'string', 'max:1000'],
            self::field($prefix, 'categoryIcon') => self::enumTextRules($iconValues, required: false),
            self::field($prefix, 'categorySortOrder') => ['required', 'integer', 'min:0', 'max:9999'],
            self::field($prefix, 'categoryIsActive') => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function menuSchedule(): array
    {
        return [
            'scheduleDayOfWeek' => ['required', 'integer', 'min:1', 'max:7'],
            'scheduleStartsAt' => ['required', 'date_format:H:i'],
            'scheduleEndsAt' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function menuItem(string $prefix = '', bool $canChangePrices = true, bool $canChangeAvailability = true): array
    {
        $rules = [
            self::field($prefix, 'itemName') => ['required', 'string', 'max:180'],
            self::field($prefix, 'itemDescription') => ['nullable', 'string', 'max:1200'],
            self::field($prefix, 'itemWeight') => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            self::field($prefix, 'itemVolume') => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            self::field($prefix, 'itemCalories') => ['nullable', 'integer', 'min:0', 'max:999999'],
            self::field($prefix, 'itemSortOrder') => ['required', 'integer', 'min:0', 'max:9999'],
        ];

        if ($canChangePrices) {
            $rules[self::field($prefix, 'itemPrice')] = self::moneyRules();
        }

        if ($canChangeAvailability) {
            $rules[self::field($prefix, 'itemIsAvailable')] = ['boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function kitchenDepartment(string $prefix = ''): array
    {
        return [
            self::field($prefix, 'departmentName') => ['required', 'string', 'max:120'],
            self::field($prefix, 'departmentType') => ['required', 'string', Rule::in(KitchenDepartmentType::values())],
            self::field($prefix, 'departmentSortOrder') => ['required', 'integer', 'min:0', 'max:9999'],
            self::field($prefix, 'departmentIsActive') => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function modifierGroup(string $prefix = ''): array
    {
        return [
            self::field($prefix, 'modifierGroupName') => ['required', 'string', 'max:160'],
            self::field($prefix, 'modifierGroupIsRequired') => ['boolean'],
            self::field($prefix, 'modifierGroupMinSelect') => ['required', 'integer', 'min:0', 'max:50'],
            self::field($prefix, 'modifierGroupMaxSelect') => [
                'required',
                'integer',
                'min:0',
                'max:50',
                'gte:'.self::field($prefix, 'modifierGroupMinSelect'),
            ],
            self::field($prefix, 'modifierGroupSortOrder') => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function modifierOption(string $prefix = '', bool $canChangePrices = true, bool $canChangeAvailability = true): array
    {
        $rules = [
            self::field($prefix, 'modifierOptionName') => ['required', 'string', 'max:160'],
            self::field($prefix, 'modifierOptionSortOrder') => ['required', 'integer', 'min:0', 'max:9999'],
        ];

        if ($canChangePrices) {
            $rules[self::field($prefix, 'modifierOptionPriceDelta')] = self::moneyRules(allowNegative: true);
        }

        if ($canChangeAvailability) {
            $rules[self::field($prefix, 'modifierOptionIsAvailable')] = ['boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function selectedModifierOptions(string $field = 'selectedModifierOptions'): array
    {
        return [
            $field => ['array'],
            $field.'.*' => ['array'],
            $field.'.*.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function guestName(string $field = 'guestName'): array
    {
        return [
            $field => ['required', 'string', 'min:2', 'max:80'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function optionalGuestName(string $field = 'guestName'): array
    {
        return [
            $field => ['nullable', 'string', 'min:2', 'max:80'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function guestComment(string $field = 'itemComment'): array
    {
        return [
            $field => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function waiterRejectionReason(string $field = 'rejectionReason'): array
    {
        return [
            $field => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function auditReason(string $field): array
    {
        return [
            $field => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function quantity(string $field): array
    {
        return [
            $field => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function price(string $field = 'price'): array
    {
        return [
            $field => self::moneyRules(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function manualPaymentAmount(string $field = 'tipsAmount'): array
    {
        return [
            $field => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function paymentMethod(string $field = 'paymentMethod'): array
    {
        return [
            $field => ['required', 'string', Rule::in(ManualPaymentMethod::values())],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function paymentNote(string $field = 'paymentNote'): array
    {
        return [
            $field => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function manualStaff(mixed $roleRule = null): array
    {
        $roleRules = ['required', 'integer'];

        if ($roleRule !== null) {
            $roleRules[] = $roleRule;
        }

        return [
            'manualName' => ['required', 'string', 'max:120'],
            'manualEmail' => ['required', 'email', 'max:255'],
            'manualRoleId' => $roleRules,
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function staffInvitation(mixed $roleRule = null): array
    {
        $roleRules = ['required', 'integer'];

        if ($roleRule !== null) {
            $roleRules[] = $roleRule;
        }

        return [
            'inviteEmail' => ['nullable', 'email', 'max:255'],
            'invitePhone' => ['nullable', 'string', 'max:40'],
            'inviteRoleId' => $roleRules,
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function imageUpload(string $field = 'image'): array
    {
        return [
            $field => StoreLocalImageAction::validationRules(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function optionalImageUpload(string $field = 'image'): array
    {
        return [
            $field => StoreLocalImageAction::optionalValidationRules(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function reportDateRange(string $fromField = 'reportFrom', string $toField = 'reportTo'): array
    {
        return [
            $fromField => ['required', 'date'],
            $toField => ['required', 'date', 'after_or_equal:'.$fromField],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function branchId(string $field = 'branchId', ?int $organizationId = null): array
    {
        $rule = Rule::exists((new Branch)->getTable(), 'id');

        if ($organizationId !== null) {
            $rule->where(fn ($query) => $query->where('organization_id', $organizationId));
        }

        return [
            $field => ['required', 'integer', 'min:1', $rule],
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function enumTextRules(array $values, bool $required): array
    {
        $rules = [$required ? 'required' : 'nullable', 'string'];

        if ($values !== []) {
            $rules[] = Rule::in($values);
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private static function moneyRules(bool $allowNegative = false): array
    {
        return [
            'required',
            'numeric',
            $allowNegative ? 'min:-999999.99' : 'min:0',
            'max:999999.99',
            'decimal:0,2',
        ];
    }

    private static function field(string $prefix, string $name): string
    {
        if ($prefix === '') {
            return $name;
        }

        return $prefix.ucfirst($name);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\PublicQr;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\QrCodeStatus;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Models\BranchSetting;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Services\Branches\BranchPublicProfilePresenter;
use App\Support\DisplayPreferences;
use Illuminate\Support\Facades\App;

final readonly class BuildGuestEntryContextAction
{
    public function __construct(
        private GetGuestMenuForBranchAction $getGuestMenuForBranch,
        private GetBranchOpeningStatusAction $getBranchOpeningStatus,
        private GetBranchPollingIntervalAction $getBranchPollingInterval,
        private BranchPublicProfilePresenter $profilePresenter,
    ) {}

    /**
     * @return array{state: string, title: string, message: string, language: string, qr_code: QrCode|null, landing: array<string, mixed>|null}
     */
    public function handle(string $token, string $language, bool $hasRequestedLanguage, bool $hasInvite): array
    {
        $qrCode = $this->findQrCode($token);

        if (! $qrCode instanceof QrCode) {
            return $this->error('not_found', __('qr.errors.not_found.title'), __('qr.errors.not_found.description'), $language);
        }

        if ($qrCode->status === QrCodeStatus::Disabled) {
            return $this->error('disabled', __('qr.errors.disabled.title'), __('qr.errors.disabled.description'), $language);
        }

        if ($qrCode->status === QrCodeStatus::Revoked) {
            return $this->error('revoked', __('qr.errors.revoked.title'), __('qr.errors.revoked.description'), $language);
        }

        $servicePoint = $qrCode->servicePoint;

        if (! $servicePoint instanceof ServicePoint || ! $servicePoint->is_active) {
            return $this->error(
                'inactive_service_point',
                __('qr.errors.service_point_unavailable.title'),
                __('qr.errors.service_point_unavailable.description'),
                $language,
            );
        }

        $branch = $servicePoint->branch;
        $brand = $branch->brand;
        $organization = $branch->organization;

        if ($organization->subscription?->status === OrganizationSubscriptionStatus::Inactive) {
            return $this->error(
                'restaurant_unavailable',
                __('guest.table.restaurant_unavailable_title'),
                __('guest.table.restaurant_unavailable_message'),
                $language,
            );
        }

        $language = $this->getGuestMenuForBranch->resolveLanguageForBranch(
            $branch->id,
            $hasRequestedLanguage ? $language : null,
        );
        App::setLocale($language);

        $branchSettingsRelation = $branch->getRelation('settings');
        $branchSettings = $branchSettingsRelation instanceof BranchSetting ? $branchSettingsRelation : null;
        $openingStatus = $this->getBranchOpeningStatus->handle($branch, preferences: DisplayPreferences::defaults());
        $defaultLanguage = SupportedLocale::normalize($branchSettings?->default_language);
        $defaultCurrency = SupportedCurrency::normalize($branch->currency);
        $languageLabels = SupportedLocale::labels();
        $profile = $this->profilePresenter->present($branch, $language, $defaultLanguage);
        $venueName = $profile['venue_name'];

        return [
            'state' => 'ready',
            'title' => $venueName,
            'message' => $hasInvite ? __('guest.table.invite_request_name') : __('guest.table.enter_name'),
            'language' => $language,
            'qr_code' => $qrCode,
            'landing' => [
                'organization_name' => $organization->name,
                'brand_name' => $brand->name,
                'brand_initial' => str($brand->name)->substr(0, 1)->upper()->toString(),
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_city' => $branch->city,
                'branch_country' => $branch->country,
                'branch_address' => (string) $branch->address,
                'branch_currency' => $defaultCurrency,
                'default_language' => $defaultLanguage,
                'default_language_label' => $languageLabels[$defaultLanguage] ?? $defaultLanguage,
                'default_currency' => $defaultCurrency,
                'polling_interval_seconds' => $this->getBranchPollingInterval->handle($branch->id),
                ...$profile,
                'opening_status_label' => $openingStatus['label'],
                'opening_status_detail' => $openingStatus['detail'],
                'opening_status_tone' => $openingStatus['tone'],
                'can_accept_orders' => $openingStatus['can_accept_orders'],
                'service_point_name' => $servicePoint->name,
                'service_point_display_number' => $servicePoint->display_number,
                'service_point_type' => __(sprintf('reports.service_point_types.%s', $servicePoint->type->value)),
                'area_name' => $servicePoint->areaNode?->name,
                'short_code' => $qrCode->short_code,
            ],
        ];
    }

    public function findQrCode(string $token): ?QrCode
    {
        return QrCode::query()
            ->select(['id', 'service_point_id', 'public_token', 'short_code', 'status'])
            ->with([
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                    ->with([
                        'areaNode' => fn ($query) => $query->select(['id', 'branch_id', 'name']),
                        'branch' => fn ($query) => $query
                            ->select([
                                'id',
                                'organization_id',
                                'brand_id',
                                'name',
                                'public_name',
                                'public_description',
                                'public_translations',
                                'logo_path',
                                'cover_image_path',
                                'address',
                                'phone',
                                'email',
                                'website_url',
                                'instagram_url',
                                'facebook_url',
                                'tiktok_url',
                                'city',
                                'country',
                                'timezone',
                                'currency',
                                'is_temporarily_closed',
                                'temporary_closed_reason',
                                'temporary_closed_until',
                            ])
                            ->with([
                                'settings' => fn ($query) => $query->select([
                                    'id',
                                    'branch_id',
                                    'default_language',
                                    'default_currency',
                                ]),
                                'brand' => fn ($query) => $query->select([
                                    'id',
                                    'organization_id',
                                    'name',
                                    'logo_path',
                                ]),
                                'organization' => fn ($query) => $query
                                    ->select(['id', 'name', 'logo_path'])
                                    ->with([
                                        'subscription' => fn ($query) => $query->select([
                                            'id',
                                            'organization_id',
                                            'status',
                                        ]),
                                    ]),
                            ]),
                    ]),
            ])
            ->where('public_token', $token)
            ->first();
    }

    /**
     * @return array{state: string, title: string, message: string, language: string, qr_code: null, landing: null}
     */
    private function error(string $state, string $title, string $message, string $language): array
    {
        return [
            'state' => $state,
            'title' => $title,
            'message' => $message,
            'language' => $language,
            'qr_code' => null,
            'landing' => null,
        ];
    }
}

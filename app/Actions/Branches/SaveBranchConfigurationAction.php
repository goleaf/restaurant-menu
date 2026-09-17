<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\User;
use App\Services\Branches\BranchSettingsQueryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @phpstan-type ConfigurationData array{
 *   settings: array{
 *     require_waiter_confirmation_for_orders: bool,
 *     allow_guest_created_sessions: bool,
 *     allow_waiter_opened_sessions: bool,
 *     allow_guest_invite_links: bool,
 *     guest_join_requires_approval: bool,
 *     polling_interval_seconds: int,
 *     inactivity_warning_minutes: int,
 *     pending_session_expire_minutes: int,
 *     default_language: string,
 *     default_currency: string,
 *     service_charge_enabled: bool,
 *     service_charge_percent?: string,
 *     tips_enabled: bool,
 *     order_flow_mode: string,
 *     service_modes?: list<string>
 *   },
 *   profile: array{
 *     public_name: string|null,
 *     public_description: string|null,
 *     logo_path?: string|null,
 *     cover_image_path?: string|null,
 *     phone: string|null,
 *     email: string|null,
 *     website_url: string|null,
 *     instagram_url: string|null,
 *     facebook_url: string|null,
 *     tiktok_url: string|null
 *   },
 *   logo: UploadedFile|null,
 *   cover: UploadedFile|null
 * }
 */
final class SaveBranchConfigurationAction
{
    public function __construct(
        private readonly BranchSettingsQueryService $settingsQueries,
        private readonly UpdateBranchSettingsAction $updateSettings,
        private readonly UpdateBranchPublicProfileAction $updateProfile,
        private readonly UpdateBranchLogoAction $updateLogo,
        private readonly UpdateBranchCoverImageAction $updateCover,
    ) {}

    /**
     * @param  ConfigurationData  $data
     * @return array{branch: Branch, settings: BranchSetting}
     */
    public function handle(User $actor, Branch $branch, int $settingsId, array $data): array
    {
        return DB::transaction(function () use ($actor, $branch, $settingsId, $data): array {
            $branch = Branch::query()
                ->select([
                    'id', 'organization_id', 'brand_id', 'name', 'public_name', 'public_description',
                    'logo_path', 'cover_image_path', 'address', 'phone', 'email', 'website_url',
                    'instagram_url', 'facebook_url', 'tiktok_url', 'city', 'country', 'timezone',
                    'currency', 'is_active', 'is_temporarily_closed', 'temporary_closed_reason',
                    'temporary_closed_until', 'created_at', 'updated_at', 'deleted_at',
                ])
                ->whereKey($branch->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('manageSettings', $branch);
            $settings = $this->settingsQueries->find($branch, $settingsId);
            $settings = $this->updateSettings->handle($settings, $data['settings']);

            if ($data['logo'] instanceof UploadedFile) {
                $this->updateLogo->handle($branch, $data['logo']);
            }

            if ($data['cover'] instanceof UploadedFile) {
                $this->updateCover->handle($branch, $data['cover']);
            }

            $branch = $this->updateProfile->handle($branch, $data['profile']);

            return ['branch' => $branch, 'settings' => $settings];
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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
    /**
     * @param  ConfigurationData  $data
     * @return array{branch: Branch, settings: BranchSetting}
     */
    public function handle(User $actor, Branch $branch, int $settingsId, array $data): array
    {
        throw new \LogicException('Use independent versioned profile, media and settings operations.');
    }
}

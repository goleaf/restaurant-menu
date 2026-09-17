<?php

declare(strict_types=1);

namespace App\Services\Restaurant;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\User;
use App\Services\Availability\AvailabilityEvaluator;
use App\Services\Onboarding\RestaurantSetupQueryService;
use App\Support\LocalizedDateFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * @phpstan-type ReadinessItem array{key: string, status: string, label: string, description: string, url: string|null}
 * @phpstan-type OrderingState array{kind: string, is_open: bool, can_accept_orders: bool, label: string, detail: string, reason: string|null, until: string|null, until_input: string, closed_until_label: string|null, can_manage: bool, is_temporarily_closed: bool, next_opens_at: string|null, timezone: string, availability_url: string|null}
 */
final class BranchReadinessService
{
    public function __construct(
        private readonly GetBranchOpeningStatusAction $openingStatus,
        private readonly AvailabilityEvaluator $availability,
        private readonly RestaurantSetupQueryService $setupQueries,
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveBranchPermissions,
    ) {}

    /** @return array{status: string, items: list<ReadinessItem>, ordering: OrderingState, onboarding_completed: bool} */
    public function handle(User $user, Branch $branch): array
    {
        $branch = Branch::query()->select([
            'id', 'organization_id', 'brand_id', 'name', 'is_active', 'timezone', 'is_temporarily_closed',
            'temporary_closed_reason', 'temporary_closed_until', 'deleted_at', 'logo_path', 'pause_version', 'opening_hours_version',
        ])->whereKey($branch->id)->where('organization_id', $branch->organization_id)->where('brand_id', $branch->brand_id)
            ->withExists(['settings', 'staffAssignments as has_active_staff' => fn ($query) => $query->where('status', OrganizationUserStatus::Active->value)])
            ->withCount([
                'servicePoints as usable_points_count' => fn ($query) => $query->where('is_active', true)->whereNotIn('status', [ServicePointStatus::Closed->value, ServicePointStatus::Blocked->value]),
                'servicePoints as usable_qr_points_count' => fn ($query) => $query->where('is_active', true)->whereNotIn('status', [ServicePointStatus::Closed->value, ServicePointStatus::Blocked->value])->whereHas('activeQrCode'),
            ])->firstOrFail();
        Gate::forUser($user)->authorize('view', $branch);
        $permissions = $this->resolveBranchPermissions->handleMany($user, [SystemPermission::ManageServicePoints, SystemPermission::GenerateQr, SystemPermission::ManageMenu, SystemPermission::ManageStaff]);
        $canManage = Gate::forUser($user)->allows('manageSettings', $branch);
        $routes = [$branch->organization_id, $branch->brand_id, $branch->id];
        $settingsUrl = $canManage ? route('organizations.brands.branches.settings.index', $routes) : null;
        $menuUrl = $permissions[SystemPermission::ManageMenu->value]->contains($branch->id) ? route('organizations.brands.branches.menu.index', $routes) : null;
        $instant = CarbonImmutable::now();
        $opening = $this->openingStatus->handle($branch, $instant);
        $summary = $this->availability->branchSummary($branch, $instant);
        $effective = $summary['availability'];
        $until = $branch->temporaryClosedUntilForBranch();
        $paused = $branch->is_temporarily_closed && ($until === null || $until->greaterThan($instant));
        $points = (int) $branch->getAttribute('usable_points_count');
        $qrPoints = (int) $branch->getAttribute('usable_qr_points_count');
        $items = [
            $this->item('branch', $branch->is_active ? 'ready' : 'blocker', 'readiness.branch', 'readiness.branch_description', $settingsUrl),
            $this->item('tables', $points > 0 ? 'ready' : 'blocker', 'readiness.tables', 'readiness.tables_description', $permissions[SystemPermission::ManageServicePoints->value]->contains($branch->id) ? route('organizations.brands.branches.service-points.index', $routes) : null),
            $this->item('qr', $qrPoints === 0 ? 'blocker' : ($qrPoints < $points ? 'warning' : 'ready'), 'readiness.qr', 'readiness.qr_description', $permissions[SystemPermission::GenerateQr->value]->contains($branch->id) ? route('organizations.brands.branches.qr.print', $routes) : null),
            $this->item('menu', $summary['menu_available'] ? 'ready' : 'blocker', 'readiness.menu', 'readiness.menu_description', $menuUrl),
            $this->item('routing', $summary['routing_ready'] ? 'ready' : 'warning', 'readiness.routing', 'readiness.routing_description', $menuUrl === null ? null : route('organizations.brands.branches.menu.index', [...$routes, 'section' => 'departments'])),
            $this->item('hours', ! $opening['is_configured'] || ! $opening['can_accept_orders'] ? 'warning' : 'ready', 'readiness.hours', 'readiness.hours_description', $settingsUrl),
            $this->item('settings', $branch->getAttribute('settings_exists') ? 'ready' : 'warning', 'readiness.settings', 'readiness.settings_description', $settingsUrl),
            $this->item('profile', $branch->logo_path ? 'ready' : 'optional', 'readiness.profile', 'readiness.profile_description', $settingsUrl),
        ];
        if ($permissions[SystemPermission::ManageStaff->value]->contains($branch->id)) {
            $items[] = $this->item('staff', $branch->getAttribute('has_active_staff') ? 'ready' : 'optional', 'readiness.staff', 'readiness.staff_description', route('organizations.brands.branches.staff.index', $routes));
        }
        $blocked = collect($items)->contains('status', 'blocker');
        $onboarding = $this->setupQueries->findForUser($user);

        return [
            'status' => $blocked ? 'blocked' : (collect($items)->contains('status', 'warning') ? 'warning' : 'ready'),
            'items' => $items,
            'onboarding_completed' => $onboarding?->branch_id === $branch->id && $onboarding->completed_at !== null,
            'ordering' => [
                'kind' => match (true) {
                    $paused => 'manual_pause', ! $opening['can_accept_orders'] => 'schedule_closed', ! $effective->acceptsNewOrders => 'setup_problem', default => 'open'
                },
                'is_open' => $opening['is_open'],
                'can_accept_orders' => $effective->acceptsNewOrders,
                'label' => $effective->acceptsNewOrders ? __('dashboard.control.accepting_orders') : $effective->publicMessage(),
                'detail' => isset($effective->reasons[0]) ? $effective->reasons[0]->toArray()['label'] : $opening['detail'],
                'reason' => $paused ? $branch->temporary_closed_reason : null,
                'until' => $paused ? $until?->toIso8601String() : null,
                'until_input' => $paused ? ($until?->format('Y-m-d\TH:i') ?? '') : '',
                'closed_until_label' => $paused ? LocalizedDateFormatter::dateTime($until) : null,
                'can_manage' => $canManage, 'is_temporarily_closed' => $paused,
                'next_opens_at' => $effective->nextOrderableAt?->toIso8601String(), 'timezone' => $opening['timezone'],
                'availability_url' => $canManage ? route('organizations.brands.branches.availability.index', $routes) : null,
            ],
        ];
    }

    /** @return ReadinessItem */
    private function item(string $key, string $status, string $label, string $description, ?string $url): array
    {
        return ['key' => $key, 'status' => $status, 'label' => __($label), 'description' => __($description), 'url' => $status === 'ready' ? null : $url];
    }
}

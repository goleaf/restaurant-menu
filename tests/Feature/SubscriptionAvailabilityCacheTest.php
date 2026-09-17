<?php

declare(strict_types=1);

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Subscriptions\SetOrganizationSubscriptionStatusAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Support\BranchReportCacheVersion;
use Illuminate\Support\Facades\Cache;

function subscriptionAvailabilityBranch(?Organization $organization = null): Branch
{
    $branch = Branch::factory()->for($organization ?? Organization::factory()->create())->create(['timezone' => 'UTC']);
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->create(['is_active' => true]);
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);

    return $branch;
}

test('subscription transitions invalidate warm guest decisions only for the affected organization', function (bool $activate): void {
    $branch = subscriptionAvailabilityBranch();
    $organization = $branch->organization;
    $sibling = subscriptionAvailabilityBranch($organization);
    $independent = subscriptionAvailabilityBranch();
    $initial = $activate ? OrganizationSubscriptionStatus::Inactive : OrganizationSubscriptionStatus::Active;
    $target = $activate ? OrganizationSubscriptionStatus::Active : OrganizationSubscriptionStatus::Inactive;
    OrganizationSubscription::factory()->for($organization)->create(['status' => $initial]);
    $menus = app(GetGuestMenuForBranchAction::class);
    $cache = Cache::store(GetGuestMenuForBranchAction::cacheStore());
    $previous = [];
    foreach ([$branch, $sibling, $independent] as $restaurant) {
        $menus->handle($restaurant->id);
        $previous[$restaurant->id] = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$restaurant->id]));
    }
    $otherPayload = $menus->handle($independent->id);
    app(SetOrganizationSubscriptionStatusAction::class)->handle($organization, $target);
    foreach ([$branch, $sibling] as $restaurant) {
        $payload = $menus->handle($restaurant->id);
        expect($payload['categories'] !== [])->toBe($activate)
            ->and(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$restaurant->id])))->not->toBe($previous[$restaurant->id]);
        if ($activate) {
            expect($payload['categories'][0]['items'][0]['is_available'])->toBeTrue();
        }
    }
    expect($menus->handle($independent->id))->toBe($otherPayload)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$independent->id])))->toBe($previous[$independent->id]);

    $current = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id]));
    $auditCount = AuditLog::query()->count();
    app(SetOrganizationSubscriptionStatusAction::class)->handle($organization, $target);
    expect(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id])))->toBe($current)
        ->and(AuditLog::query()->count())->toBe($auditCount);
})->with(['deactivate' => false, 'reactivate' => true]);

test('a failed subscription audit rolls back the state and preserves its warm guest decision', function (): void {
    $branch = subscriptionAvailabilityBranch();
    $organization = $branch->organization;
    $subscription = OrganizationSubscription::factory()->for($organization)->active()->create();
    $menus = app(GetGuestMenuForBranchAction::class);
    $payload = $menus->handle($branch->id);
    $cache = Cache::store(GetGuestMenuForBranchAction::cacheStore());
    $generation = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id]));
    $auditCount = AuditLog::query()->count();
    $this->mock(RecordAuditLogAction::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable'));

    expect(fn () => app(SetOrganizationSubscriptionStatusAction::class)->handle($organization, OrganizationSubscriptionStatus::Inactive))
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    expect($subscription->fresh()->status)->toBe(OrganizationSubscriptionStatus::Active)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id])))->toBe($generation)
        ->and($menus->handle($branch->id))->toBe($payload);
});

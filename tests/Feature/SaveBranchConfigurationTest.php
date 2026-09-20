<?php

declare(strict_types=1);

use App\Actions\Branches\SaveBranchConfigurationAction;
use App\Actions\Branches\UpdateBranchPublicProfileAction;
use App\Actions\Branches\UpdateBranchSettingsAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchSetting;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Branches\BranchSettingsQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
});

test('settings effective read keeps a fixed budget for empty and full schedules', function (int $intervalCount): void {
    [, $branch, $settings] = createAggregateBranchConfigurationContext();
    if ($intervalCount > 0) {
        BranchOpeningHour::factory()->for($branch)->count($intervalCount)
            ->sequence(fn (Sequence $sequence): array => [
                'day_of_week' => intdiv($sequence->index, 4) + 1,
                'opens_at' => sprintf('%02d:00', 9 + ($sequence->index % 4) * 2),
                'closes_at' => sprintf('%02d:00', 11 + ($sequence->index % 4) * 2),
                'sort_order' => (($sequence->index % 4) + 1) * 10,
            ])->create();
    }
    $original = $settings->getRawOriginal();
    $queryCount = countDatabaseQueries(fn () => app(BranchSettingsQueryService::class)->effective($branch));
    expect($queryCount)->toBe(1)
        ->and($settings->fresh()->getRawOriginal())->toBe($original)
        ->and($branch->openingHours()->count())->toBe($intervalCount);
})->with(['unconfigured schedule' => 0, 'full weekly schedule' => 28]);

test('the profile configuration operation returns its version and leaves settings unchanged', function (): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();
    $original = $settings->getRawOriginal();
    $result = app(UpdateBranchPublicProfileAction::class)->handle($owner, $branch, ['public_name' => 'Updated restaurant'], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid());
    expect($result['fingerprint'])->toBe(UpdateBranchPublicProfileAction::fingerprint($branch->fresh()))
        ->and($branch->fresh()->public_name)->toBe('Updated restaurant')
        ->and($branch->fresh()->currency)->toBe('EUR')
        ->and($settings->fresh()->getRawOriginal())->toBe($original);
});

test('retired aggregate configuration actions cannot bypass independent writes', function (): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();
    expect(fn () => app(SaveBranchConfigurationAction::class)->handle($owner, $branch, $settings->id, []))->toThrow(LogicException::class);
    expect(fn () => app(UpdateBranchSettingsAction::class)->handle($settings, []))->toThrow(LogicException::class);
});

test('profile configuration rejects an unauthorized actor before changing data or storing files', function (): void {
    [, $branch, $settings] = createAggregateBranchConfigurationContext();
    $original = [$branch->getRawOriginal(), $settings->getRawOriginal()];
    expect(fn () => app(UpdateBranchPublicProfileAction::class)->handle(User::factory()->create(), $branch, ['public_name' => 'Rejected'], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid()))->toThrow(AuthorizationException::class);
    expect([$branch->fresh()->getRawOriginal(), $settings->fresh()->getRawOriginal()])->toBe($original);
    Storage::disk('public')->assertDirectoryEmpty('/');
});

test('configuration receipt cannot target a different branch even when actor owns both', function (): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();
    $other = Branch::factory()->forBrand($branch->brand()->firstOrFail())->withDefaultSettings()->create();
    $request = (string) Str::uuid();
    $action = app(UpdateBranchPublicProfileAction::class);
    $data = ['public_name' => 'Updated restaurant'];
    $version = UpdateBranchPublicProfileAction::fingerprint($branch);
    $action->handle($owner, $branch, $data, $version, $request);
    expect(fn () => $action->handle($owner, $other, $data, $version, $request))->toThrow(ValidationException::class);
    expect($other->fresh()->public_name)->toBeNull();
});

test('configuration action ignores forged tenant attributes on supplied branch', function (): void {
    [$owner, $owned] = createAggregateBranchConfigurationContext();
    [, $foreign, $settings] = createAggregateBranchConfigurationContext();
    $original = [$foreign->getRawOriginal(), $settings->getRawOriginal()];
    $foreign->organization_id = $owned->organization_id;
    $foreign->brand_id = $owned->brand_id;
    expect(fn () => app(UpdateBranchPublicProfileAction::class)->handle($owner, $foreign, ['public_name' => 'Rejected'], UpdateBranchPublicProfileAction::fingerprint($foreign), (string) Str::uuid()))->toThrow(AuthorizationException::class);
    expect([$foreign->fresh()->getRawOriginal(), $settings->fresh()->getRawOriginal()])->toBe($original);
});

test('configuration action rechecks persisted tenant after branch reassignment', function (): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();
    [, $other] = createAggregateBranchConfigurationContext();
    Branch::query()->whereKey($branch->id)->update(['organization_id' => $other->organization_id, 'brand_id' => $other->brand_id]);
    $original = $settings->getRawOriginal();
    expect(fn () => app(UpdateBranchPublicProfileAction::class)->handle($owner, $branch, ['public_name' => 'Rejected'], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
    expect($branch->fresh()->public_name)->toBeNull()->and($settings->fresh()->getRawOriginal())->toBe($original);
});

test('configuration action rechecks membership revocation after earlier authorization', function (): void {
    [$owner, $branch, $settings, $membership] = createAggregateBranchConfigurationContext();
    $owner->load('organizationMemberships');
    expect(Gate::forUser($owner)->allows('manageSettings', $branch))->toBeTrue();
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => app(UpdateBranchPublicProfileAction::class)->handle($owner, $branch, ['public_name' => 'Rejected'], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid()))->toThrow(AuthorizationException::class);
    expect($branch->fresh()->public_name)->toBeNull();
});

test('configuration action rejects branch archived since form opening', function (): void {
    [$owner, $branch] = createAggregateBranchConfigurationContext();
    Branch::query()->whereKey($branch->id)->delete();
    expect(fn () => app(UpdateBranchPublicProfileAction::class)->handle($owner, $branch, ['public_name' => 'Rejected'], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
    expect(Branch::withTrashed()->findOrFail($branch->id)->public_name)->toBeNull();
});

/** @return array{User, Branch, BranchSetting, OrganizationUser} */
function createAggregateBranchConfigurationContext(): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create();
    $membership = OrganizationUser::factory()->forOrganization($organization)->forUser($owner)->forSystemRole(SystemRole::Owner)->active()->create();
    $branch = Branch::factory()->for($organization)->withDefaultSettings()->create();

    return [$owner, $branch->refresh(), $branch->settings()->firstOrFail(), $membership];
}

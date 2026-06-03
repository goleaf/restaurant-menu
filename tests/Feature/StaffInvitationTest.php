<?php

use App\Actions\Invitations\CreateInvitationAction;
use App\Enums\InvitationStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(SystemRolesSeeder::class);
});

test('invitation statuses are fixed', function () {
    expect(InvitationStatus::values())->toBe([
        'pending',
        'accepted',
        'expired',
        'cancelled',
        'rejected',
    ]);
});

test('invitations table stores staff invitation scope and credentials', function () {
    expect(Schema::hasTable('invitations'))->toBeTrue();
    expect(Schema::hasColumns('invitations', [
        'organization_id',
        'brand_id',
        'branch_id',
        'role_id',
        'email',
        'phone',
        'invite_token',
        'invite_code',
        'expires_at',
        'status',
        'invited_by_user_id',
    ]))->toBeTrue();
});

test('staff invitation can target organization brand and branch', function () {
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create();
    $role = Role::query()->where('code', 'waiter')->firstOrFail();
    $invitedBy = User::factory()->create();

    $invitation = (new CreateInvitationAction)->handle($organization, $role, $invitedBy, [
        'brand' => $brand,
        'branch' => $branch,
        'email' => 'waiter@example.test',
        'phone' => '+37060000000',
        'invite_token' => 'fixed-token-for-test',
        'invite_code' => 'WAITER01',
        'expires_at' => now()->addDays(3),
    ]);

    expect($invitation)->toBeInstanceOf(Invitation::class);
    expect($invitation->organization_id)->toBe($organization->id);
    expect($invitation->brand_id)->toBe($brand->id);
    expect($invitation->branch_id)->toBe($branch->id);
    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->email)->toBe('waiter@example.test');
    expect($invitation->phone)->toBe('+37060000000');
    expect($invitation->invite_token)->toBe('fixed-token-for-test');
    expect($invitation->invite_code)->toBe('WAITER01');
    expect($invitation->status)->toBe(InvitationStatus::Pending);
    expect($invitation->invited_by_user_id)->toBe($invitedBy->id);
});

test('staff invitation can target only organization with generated token and code', function () {
    $organization = Organization::factory()->create();
    $role = Role::query()->where('code', 'director')->firstOrFail();
    $invitedBy = User::factory()->create();

    $invitation = (new CreateInvitationAction)->handle($organization, $role, $invitedBy, []);

    expect($invitation->organization_id)->toBe($organization->id);
    expect($invitation->brand_id)->toBeNull();
    expect($invitation->branch_id)->toBeNull();
    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->email)->toBeNull();
    expect($invitation->phone)->toBeNull();
    expect($invitation->invite_token)->not->toBeEmpty();
    expect($invitation->invite_code)->not->toBeEmpty();
    expect($invitation->expires_at)->not->toBeNull();
    expect($invitation->status)->toBe(InvitationStatus::Pending);
});

test('staff invitation rejects brand from another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $brand = Brand::factory()->for($otherOrganization)->create();
    $role = Role::query()->where('code', 'waiter')->firstOrFail();
    $invitedBy = User::factory()->create();

    expect(fn () => (new CreateInvitationAction)->handle($organization, $role, $invitedBy, [
        'brand' => $brand,
        'email' => 'waiter@example.test',
    ]))->toThrow(InvalidArgumentException::class);
});

test('staff invitation rejects branch outside the selected organization or brand', function () {
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $otherOrganization = Organization::factory()->create();
    $otherBrand = Brand::factory()->for($otherOrganization)->create();
    $otherBranch = Branch::factory()
        ->for($otherOrganization)
        ->for($otherBrand)
        ->create();
    $role = Role::query()->where('code', 'waiter')->firstOrFail();
    $invitedBy = User::factory()->create();

    expect(fn () => (new CreateInvitationAction)->handle($organization, $role, $invitedBy, [
        'brand' => $brand,
        'branch' => $otherBranch,
        'email' => 'waiter@example.test',
    ]))->toThrow(InvalidArgumentException::class);
});

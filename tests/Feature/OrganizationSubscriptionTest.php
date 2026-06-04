<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Subscriptions\EnsureOrganizationSubscriptionAction;
use App\Actions\Subscriptions\SetOrganizationSubscriptionStatusAction;
use App\Enums\OrganizationSubscriptionPaymentStatus;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Index as OrganizationsIndex;
use App\Livewire\Superadmin\Dashboard as SuperadminDashboard;
use App\Models\OrganizationSubscription;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('organization subscriptions table stores local SaaS billing state', function () {
    expect(Schema::hasTable('organization_subscriptions'))->toBeTrue();
    expect(Schema::hasColumns('organization_subscriptions', [
        'organization_id',
        'status',
        'started_at',
        'next_payment_at',
        'payment_status',
    ]))->toBeTrue();
});

test('creating organization creates default single plan subscription', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Single Plan Group']);

    $subscription = OrganizationSubscription::query()
        ->where('organization_id', $organization->id)
        ->firstOrFail();

    expect($subscription->status)->toBe(OrganizationSubscriptionStatus::Active);
    expect($subscription->payment_status)->toBe(OrganizationSubscriptionPaymentStatus::Pending);
    expect($subscription->started_at)->not->toBeNull();
    expect($subscription->next_payment_at)->not->toBeNull();
    expect($owner->fresh()->canAccessOrganization($organization))->toBeTrue();
});

test('superadmin can deactivate and activate organization subscription', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Manual Billing Group']);
    $superadmin = createSubscriptionSuperadminUser();

    Livewire::actingAs($superadmin)
        ->test(SuperadminDashboard::class)
        ->assertSee('Manual Billing Group')
        ->assertSee('Active')
        ->call('deactivateOrganization', $organization->id)
        ->assertSee('Inactive');

    expect($organization->fresh()->subscription->status)->toBe(OrganizationSubscriptionStatus::Inactive);
    expect($owner->fresh()->canAccessOrganization($organization))->toBeFalse();

    Livewire::actingAs($superadmin)
        ->test(SuperadminDashboard::class)
        ->call('activateOrganization', $organization->id)
        ->assertSee('Active');

    expect($organization->fresh()->subscription->status)->toBe(OrganizationSubscriptionStatus::Active);
    expect($owner->fresh()->canAccessOrganization($organization))->toBeTrue();
});

test('ordinary user cannot access inactive organization from restaurant workspace', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Paused Restaurant Group']);

    (new SetOrganizationSubscriptionStatusAction(new EnsureOrganizationSubscriptionAction))
        ->handle($organization, OrganizationSubscriptionStatus::Inactive);

    Livewire::actingAs($owner)
        ->test(OrganizationsIndex::class)
        ->assertDontSee('Paused Restaurant Group');

    $this->actingAs($owner)
        ->get(route('organizations.brands.index', $organization))
        ->assertForbidden();
});

function createSubscriptionSuperadminUser(): User
{
    $user = User::factory()->create([
        'name' => 'Subscription Superadmin',
        'email' => 'subscription-superadmin@example.test',
    ]);
    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    return $user;
}

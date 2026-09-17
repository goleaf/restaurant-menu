<?php

declare(strict_types=1);

use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\InvitationStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\Support\IsolatedBrowserIdentity;

test('recipients retain validation handle rotated forms and switch mismatched accounts in isolated browsers', function (string $locale): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Šeimos restoranų komanda — Команда ресторанов']);
    $branch = Branch::factory()->for($organization)->withDefaultSettings()->create(['name' => 'Senamiesčio šeimos terasa — Старый город']);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $create = app(CreateInvitationAction::class);
    $invitation = $create->handle($organization, $role, $owner->fresh(), ['email' => 'recipient.new@example.test', 'branch' => $branch]);
    $recipient = visit($invitation->inviteLink().'?lang='.$locale);
    $recipient->assertPathIs(route('invitations.pending', absolute: false))->assertSee($organization->name)->assertSee($branch->name);
    $recipient->fill('input[name="name"]', 'Živilė Новая')->fill('input[name="password"]', 'short')->fill('input[name="password_confirmation"]', 'different');
    teamRecipientClick($recipient, 'form[wire\\:submit="register"] button[type="submit"]');
    $recipient->assertValue('input[name="name"]', 'Živilė Новая');
    $recipient->assertSee(__('invitations.validation.password_min', ['min' => 8], $locale))
        ->assertSee(__('ui.auth.register.full_name', [], $locale));
    expect(User::query()->where('email', 'recipient.new@example.test')->exists())->toBeFalse();
    $recipient->script("window.dispatchEvent(new Event('offline'))");
    $recipient->assertDisabled('form[wire\\:submit="register"] button[type="submit"]')->assertSee(__('invitations.offline', [], $locale));
    $recipient->script("window.dispatchEvent(new Event('online'))");
    $recipient->fill('input[name="password"]', 'ValidPassword2026!')->fill('input[name="password_confirmation"]', 'ValidPassword2026!');
    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        $recipient->resize($width, $height);
        $recipient->script("window.scrollTo({ top: 0, left: 0, behavior: 'instant' })");
        expect($recipient->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        $recipient->screenshot(true, "team-recipient-{$locale}-{$width}");
    }
    $recipient->script("document.documentElement.classList.add('dark')");
    $recipient->resize(390, 844);
    $recipient->script("window.scrollTo({ top: 0, left: 0, behavior: 'instant' })");
    $recipient->screenshot(true, 'team-recipient-'.$locale.'-dark');
    $recipient->script("document.documentElement.classList.remove('dark')");
    teamRecipientClick($recipient, 'form[wire\\:submit="register"] button[type="submit"]');
    $recipient->assertPathIs(route('restaurant.waiter.dashboard', absolute: false))->assertQueryStringHas('branch', (string) $branch->id)
        ->assertAttribute('html[lang]', 'lang', $locale);
    $newUser = User::query()->where('email', 'recipient.new@example.test')->sole();
    expect($newUser->locale)->toBe($locale)
        ->and($newUser->email_verified_at)->toBeNull()
        ->and(OrganizationUser::query()->where('user_id', $newUser->id)->count())->toBe(1)
        ->and(BranchUser::query()->where('user_id', $newUser->id)->count())->toBe(1);

    $stale = $create->handle($organization, $role, $owner->fresh(), ['email' => 'recipient.stale@example.test', 'branch' => $branch]);
    $oldForm = visit($stale->inviteLink().'?lang='.$locale);
    $oldForm->assertPresent('form[wire\\:submit="register"]')->fill('input[name="name"]', 'Stale Recipient')
        ->fill('input[name="password"]', 'ValidPassword2026!')->fill('input[name="password_confirmation"]', 'ValidPassword2026!');
    app(ReissueInvitationAction::class)->handle($owner->fresh(), $organization, $stale->invitation);
    teamRecipientClick($oldForm, 'form[wire\\:submit="register"] button[type="submit"]');
    expect(User::query()->where('email', 'recipient.stale@example.test')->exists())->toBeFalse()
        ->and($stale->invitation->fresh()->status)->toBe(InvitationStatus::Pending);

    $existing = User::factory()->create(['email' => 'recipient.existing@example.test', 'locale' => $locale]);
    $other = User::factory()->create(['email' => 'recipient.other@example.test', 'locale' => $locale]);
    $matching = $create->handle($organization, $role, $owner->fresh(), ['email' => $existing->email, 'branch' => $branch]);
    $account = visit(route('login', ['lang' => $locale], false));
    $account->fill('email', $other->email)->fill('password', 'password')->click('@login-button')->assertPathIs('/dashboard');
    $account->navigate($matching->inviteLink())->assertAttribute('html[lang]', 'lang', $locale)
        ->assertSee(__('invitations.states.email_mismatch_title', [], $locale));
    teamRecipientClick($account, 'form[wire\\:submit="switchAccount"] button[type="submit"]');
    $account->assertPathIs('/login')->fill('email', $existing->email)->fill('password', 'password')->click('@login-button');
    $account->assertPathIs(route('invitations.pending', absolute: false))->assertSee($organization->name);
    expect(OrganizationUser::query()->where('user_id', $existing->id)->exists())->toBeFalse();
    teamRecipientClick($account, 'form[wire\\:submit="accept"] button[type="submit"]');
    $account->assertPathIs(route('restaurant.waiter.dashboard', absolute: false));
    expect(OrganizationUser::query()->where('user_id', $existing->id)->count())->toBe(1)
        ->and(OrganizationUser::query()->where('user_id', $other->id)->exists())->toBeFalse();
    $recipient->assertNoJavaScriptErrors();
    $account->assertNoJavaScriptErrors();
})->with(['en', 'lt', 'ru']);

function teamRecipientClick(PendingAwaitablePage $page, string $selector): void
{
    $page->assertVisible($selector)->assertEnabled($selector);
    $encoded = json_encode($selector, JSON_THROW_ON_ERROR);
    $page->script("document.querySelector({$encoded}).click()");
    $page->wait(0.3);
}

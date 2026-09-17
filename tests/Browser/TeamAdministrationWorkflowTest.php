<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;

test('team administrators invite assign zones and change scoped access across independent browser sessions', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create(['email' => 'team.owner@example.test']);
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Šeimos restoranų komanda — Команда ресторанов']);
    $branch = Branch::factory()->for($organization)->withDefaultSettings()->create(['name' => 'Senamiesčio šeimos terasa — Старый город']);
    $area = AreaNode::factory()->forBranch($branch)->active()->create(['name' => 'Šeimos terasa — Семейная терраса']);
    $staffUrl = route('organizations.brands.branches.staff.index', [$organization, $branch->brand, $branch], false);
    $admin = visit(route('login', absolute: false));
    $admin->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->assertQueryStringHas('branch', (string) $branch->id);
    $encodedStaffUrl = json_encode($staffUrl, JSON_THROW_ON_ERROR);
    $admin->script("Livewire.navigate({$encodedStaffUrl})");
    $admin->wait(0.4)->assertSee($organization->name)->assertSee($branch->name);
    teamAdminClick($admin, 'button[wire\\:click="openInvitation"]');
    $admin->fill('input[name="invitationForm.email"]', 'history.draft@example.test');
    $admin->script('history.back()');
    $admin->wait(0.3)->assertSee(__('staff.workspace.unsaved_title'));
    teamAdminClick($admin, 'button[\\@click="cancelNavigation"]');
    $admin->assertPathIs($staffUrl)->assertValue('input[name="invitationForm.email"]', 'history.draft@example.test');

    $admin->script('history.back()');
    $admin->wait(0.2)->assertSee(__('staff.workspace.unsaved_title'));
    $admin->assertPathIs($staffUrl)->assertValue('input[name="invitationForm.email"]', 'history.draft@example.test');
    teamAdminClick($admin, 'button[\\@click="discardAndNavigate"]');
    $admin->wait(0.4)->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->assertQueryStringHas('branch', (string) $branch->id);
    $admin->script('history.forward()');
    $admin->wait(0.4)->assertPathIs($staffUrl);
    teamAdminClick($admin, 'button[wire\\:click="openInvitation"]');
    $admin->fill('input[name="invitationForm.email"]', 'escape.draft@example.test');
    $admin->keys('input[name="invitationForm.email"]', 'Escape');
    teamAdminClick($admin, 'button[\\@click="discardAndNavigate"]');
    $admin->assertMissing('[data-staff-editor]');
    expect($admin->script('document.activeElement.getAttribute("wire:click")'))->toBe('openInvitation');

    $admin->assertMissing('button[wire\\:click="addManualStaffMember"]');
    teamAdminClick($admin, 'button[wire\\:click="openInvitation"]');
    $admin->fill('input[name="invitationForm.email"]', 'team.new@example.test');
    teamAdminClick($admin, 'nav button:nth-child(2)');
    $admin->assertSee(__('staff.workspace.unsaved_title'));
    teamAdminClick($admin, 'button[\\@click="cancelNavigation"]');
    $admin->assertValue('input[name="invitationForm.email"]', 'team.new@example.test');
    teamAdminClick($admin, 'nav button:nth-child(2)');
    teamAdminClick($admin, 'button[\\@click="discardAndNavigate"]');
    $admin->assertQueryStringHas('section', 'invitations')->assertMissing('[data-staff-editor]');
    teamAdminClick($admin, 'button[wire\\:click="openInvitation"]');
    $admin->fill('input[name="invitationForm.email"]', 'team.new@example.test');
    teamAdminClick($admin, 'form[wire\\:submit="previewInvitation"] button[type="submit"]');
    expect(User::query()->where('email', 'team.new@example.test')->exists())->toBeFalse();
    teamAdminClick($admin, 'button[wire\\:click="createInviteLink"]');
    $admin->assertVisible('[data-invitation-link]')->assertSee(__('staff.link.once'));
    expect(User::query()->where('email', 'team.new@example.test')->exists())->toBeFalse();
    $link = $admin->script('document.querySelector("[data-invitation-link]").value');
    $admin->click('[data-copy-invitation]')->wait(0.2);
    expect($admin->script("Alpine.\$data(document.querySelector('[data-invitation-clipboard]')).copied"))->toBeTrue();
    $admin->script("Object.defineProperty(navigator, 'clipboard', {configurable:true, value:{writeText:async()=>{throw new Error('Denied')}}})");
    teamAdminClick($admin, '[data-copy-invitation]');
    $admin->assertSee(__('staff.link.fallback'));
    expect($admin->script("Alpine.\$data(document.querySelector('[data-invitation-clipboard]')).copied"))->toBeFalse();
    $recipient = visit($link);
    $recipient->assertPathIs(route('invitations.pending', absolute: false))
        ->assertVisible('form[wire\\:submit="register"]');
    $invitation = Invitation::query()->where('email', 'team.new@example.test')->sole();
    $originalDigest = $invitation->invite_token_hash;
    $admin->navigate($staffUrl.'?section=invitations');
    teamAdminClick($admin, 'button[wire\\:click="confirmInvitation('.$invitation->id.', \'reissue\')"]');
    $admin->assertSee(__('staff.workspace.reissue_warning'));
    teamAdminClick($admin, 'button[wire\\:click="reissueInvitation('.$invitation->id.')"]');
    $admin->assertVisible('[data-invitation-link]');
    $replacementLink = $admin->script('document.querySelector("[data-invitation-link]").value');
    expect($replacementLink)->not->toBe($link)
        ->and($invitation->fresh()->invite_token_hash)->not->toBe($originalDigest);
    $recipient->navigate($link)->assertPathIs(route('invitations.pending', absolute: false))
        ->assertSee(__('invitations.states.unavailable_title'))->assertMissing('form[wire\\:submit="register"]');
    $recipient->navigate($replacementLink)->assertPathIs(route('invitations.pending', absolute: false))
        ->assertVisible('form[wire\\:submit="register"]');
    $token = basename((string) parse_url($replacementLink, PHP_URL_PATH));
    $forbidden = [$token, hash('sha256', $token), $invitation->fresh()->credentialVersion(), 'ValidPassword2026!'];
    $snapshots = $recipient->script('Array.from(document.querySelectorAll("[wire\\\\:snapshot]"), element => element.getAttribute("wire:snapshot")).join("\\n")');
    expect($snapshots)->toContain('"name":"invitations.show"');
    foreach ($forbidden as $value) {
        expect($snapshots)->not->toContain($value);
    }
    teamObserveInvitationRequests($recipient, $forbidden);
    $recipient->fill('input[name="name"]', 'Živilė Сотрудница')->fill('input[name="password"]', 'ValidPassword2026!')->fill('input[name="password_confirmation"]', 'ValidPassword2026!');
    teamAdminClick($recipient, 'form[wire\\:submit="register"] button[type="submit"]');
    $recipient->assertPathIs(route('restaurant.waiter.dashboard', absolute: false))
        ->assertQueryStringHas('branch', (string) $branch->id);
    $requests = $recipient->script('JSON.parse(sessionStorage.getItem("teamInvitationRequests") ?? "[]")');
    expect($requests)->toHaveCount(1)
        ->and($requests[0])->toMatchArray([
            'calls' => ['register'], 'status' => 200, 'snapshotSafe' => true, 'responseSafe' => true, 'path' => 'invite/pending',
        ]);
    $user = User::query()->where('email', 'team.new@example.test')->sole();
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Accepted)
        ->and($invitation->fresh()->accepted_by_user_id)->toBe($user->id)
        ->and(OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->count())->toBe(1);
    $member = BranchUser::query()->where('branch_id', $branch->id)->where('user_id', $user->id)->sole();
    $organizationMember = OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->sole();
    $admin->navigate($staffUrl)->assertSee($user->name);
    teamAdminClick($admin, 'nav button:nth-child(3)');
    $admin->assertQueryStringHas('section', 'assignments');
    teamAdminClick($admin, 'section[aria-labelledby="staff-coverage-heading"] > ul a[href*="/staff/members/'.$organizationMember->id.'"]');
    $admin->assertQueryStringHas('section', 'areas');
    $admin->check('input[type="checkbox"][value="'.$area->id.'"]');
    expect(AreaNodeWaiter::query()->where('user_id', $user->id)->exists())->toBeFalse();
    teamAdminClick($admin, 'form[wire\\:submit="previewAreaAssignments"] button[type="submit"]');
    $admin->screenshot(false, 'team-area-change-preview');
    teamAdminClick($admin, 'button[wire\\:click="saveAreaAssignments"]');
    expect(AreaNodeWaiter::query()->where('user_id', $user->id)->pluck('area_node_id')->all())->toBe([$area->id]);
    $admin->assertSee(__('staff.messages.waiter_zones_updated'));
    teamAdminClick($admin, '[data-team-section="overview"]');
    $admin->screenshot(false, 'team-area-coverage');
    $admin->script('history.back()');
    $admin->wait(0.5)->assertQueryStringHas('section', 'areas');
    $admin->script('history.forward()');
    $admin->wait(0.5)->assertQueryStringMissing('section');
    $admin->navigate($staffUrl.'?section=assignments');
    teamAdminClick($admin, 'article[wire\\:key="coverage-'.$area->id.'"] a[href*="/staff/members/'.$organizationMember->id.'"]');
    $admin->assertQueryStringHas('section', 'areas')->assertChecked('input[type="checkbox"][value="'.$area->id.'"]');
    teamAdminClick($admin, '.rm-team-card__identity a');
    $admin->assertPathIs($staffUrl)->assertQueryStringHas('section', 'assignments');
    $admin->navigate($staffUrl);
    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        $admin->resize($width, $height);
        expect($admin->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        $admin->screenshot(false, "team-staff-{$width}");
    }
    $admin->script("document.documentElement.style.zoom = '2'");
    expect($admin->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $admin->script("document.documentElement.style.zoom = ''; localStorage.setItem('flux.appearance', 'dark')");
    foreach (['lt', 'ru'] as $locale) {
        $admin->navigate($staffUrl.'?lang='.$locale)->resize(320, 844)->assertAttribute('html[lang]', 'lang', $locale);
        expect($admin->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        $admin->screenshot(false, 'team-staff-320-'.$locale.'-dark');
    }
    $admin->navigate($staffUrl.'?lang=en')->resize(390, 844);
    teamAdminClick($admin, 'a[href*="/staff/members/'.$organizationMember->id.'"]');
    teamAdminClick($admin, '[data-team-section="access"]');
    teamAdminClick($admin, 'button[wire\\:click="openMember('.$member->id.', \'status\')"]');
    $admin->select('select[name="memberForm.status"]', 'suspended')->fill('input[name="memberForm.reason"]', 'Temporary branch access review');
    $admin->script("window.dispatchEvent(new Event('offline'))");
    $admin->assertDisabled('form[wire\\:submit="previewMemberChange"] button[type="submit"]');
    $admin->script("window.dispatchEvent(new Event('online'))");
    teamAdminClick($admin, 'form[wire\\:submit="previewMemberChange"] button[type="submit"]');
    $admin->screenshot(false, 'team-staff-status-preview');
    teamAdminClick($admin, 'button[wire\\:click="saveMember"]');
    expect($member->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
        ->and(OrganizationUser::query()->where('user_id', $user->id)->sole()->status)->toBe(OrganizationUserStatus::Active);
    $recipient->navigate(route('restaurant.waiter.dashboard', ['branch' => $branch->id], false));
    $recipient->assertDontSee($branch->name);
    teamAdminClick($admin, 'button[wire\\:click="openMember('.$member->id.', \'status\')"]');
    $admin->select('select[name="memberForm.status"]', 'active')->fill('input[name="memberForm.reason"]', 'Branch access review complete');
    teamAdminClick($admin, 'form[wire\\:submit="previewMemberChange"] button[type="submit"]');
    teamAdminClick($admin, 'button[wire\\:click="saveMember"]');
    expect($member->fresh()->status)->toBe(OrganizationUserStatus::Active);
    $recipient->navigate(route('restaurant.waiter.dashboard', ['branch' => $branch->id], false))->assertSee($branch->name);
    $admin->navigate($staffUrl.'?lang=en');
    $admin->keys('body[class]', 'Tab');
    expect($admin->script("document.activeElement.matches(':focus-visible')"))->toBeTrue();
    $admin->assertNoJavaScriptErrors();
    $recipient->assertNoJavaScriptErrors();
    expect(Invitation::query()->where('email', $user->email)->count())->toBe(1);
});

test('team editor is modal on phones and can close safely while offline', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create(['email' => 'team.editor@example.test']);
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Editor test organization']);
    $page = visit(route('login', absolute: false));
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')->assertPathIs('/dashboard');
    $page->navigate(route('organizations.staff.index', $organization, false))->resize(390, 844);
    teamAdminClick($page, 'button[wire\\:click="openInvitation"]');
    expect($page->script('document.querySelector("[data-staff-editor]").matches("dialog:modal")'))->toBeTrue();
    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        $page->resize($width, $height);
        expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        $page->screenshot(false, 'team-editor-'.$width);
    }
    $page->resize(390, 844);
    foreach (range(1, 10) as $tab) {
        $page->keys('dialog[data-staff-editor]', 'Tab');
        expect($page->script('document.querySelector("dialog[data-staff-editor]").contains(document.activeElement)'))->toBeTrue();
    }
    $page->fill('input[name="invitationForm.email"]', 'local.draft@example.test');
    teamBrowserOffline($page, true);
    expect($page->script('navigator.onLine'))->toBeFalse();
    $page->keys('input[name="invitationForm.email"]', 'Escape');
    teamAdminClick($page, 'button[\\@click="cancelNavigation"]');
    $page->assertValue('input[name="invitationForm.email"]', 'local.draft@example.test');
    $page->keys('input[name="invitationForm.email"]', 'Escape');
    teamAdminClick($page, 'button[\\@click="discardAndNavigate"]');
    expect($page->script('document.querySelector("[data-staff-editor]")?.open ?? false'))->toBeFalse();
    teamBrowserOffline($page, false);
    expect($page->script('navigator.onLine'))->toBeTrue();
    teamAdminClick($page, 'button[wire\\:click="openInvitation"]');
    $page->assertValue('input[name="invitationForm.email"]', '');
    $page->resize(1440, 1000);
    $page->assertScript('document.querySelector("[data-staff-editor]").open && !document.querySelector("[data-staff-editor]").matches(":modal")');
    $page->assertNoJavaScriptErrors();
});

function teamAdminClick(PendingAwaitablePage $page, string $selector): void
{
    $page->assertVisible($selector)->assertEnabled($selector);
    $page->click($selector);
    $page->wait(0.3);
}

/** @param list<string> $forbidden */
function teamObserveInvitationRequests(PendingAwaitablePage $page, array $forbidden): void
{
    $sensitiveValues = json_encode($forbidden, JSON_THROW_ON_ERROR);
    $page->script(<<<JS
        (() => {
            const forbidden = {$sensitiveValues};
            const original = window.fetch;
            sessionStorage.removeItem('teamInvitationRequests');
            window.fetch = async (...args) => {
                const body = typeof args[1]?.body === 'string' ? args[1].body : '';
                const components = body.startsWith('{') ? (JSON.parse(body).components ?? []) : [];
                const invitation = components.find(component => JSON.parse(component.snapshot).memo.name === 'invitations.show');
                const response = await original(...args);
                if (invitation) {
                    const text = await response.clone().text();
                    const records = JSON.parse(sessionStorage.getItem('teamInvitationRequests') ?? '[]');
                    records.push({
                        calls: invitation.calls.map(call => call.method),
                        status: response.status,
                        path: JSON.parse(invitation.snapshot).memo.path,
                        snapshotSafe: !forbidden.some(value => invitation.snapshot.includes(value)),
                        responseSafe: !forbidden.some(value => text.includes(value)),
                    });
                    sessionStorage.setItem('teamInvitationRequests', JSON.stringify(records));
                }
                return response;
            };
        })()
        JS);
}

function teamBrowserOffline(PendingAwaitablePage $page, bool $offline): void
{
    $context = $page->page()->context();
    $guid = (new ReflectionProperty($context, 'guid'))->getValue($context);
    assert(is_string($guid));
    foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
        // Consume the installed Playwright protocol response before checking browser state.
    }
}

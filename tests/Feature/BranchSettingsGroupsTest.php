<?php

declare(strict_types=1);

use App\Actions\Branches\SaveBranchSettingsGroupAction;
use App\Actions\Branches\UpdateBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Payments\BuildManualPaymentSummaryAction;
use App\Actions\Payments\RecordManualPaymentAction;
use App\Actions\TableSessions\CreateGuestInviteLinkAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Forms\Branches\AdvancedSettingsForm;
use App\Livewire\Forms\Branches\GuestProcessForm;
use App\Livewire\Forms\Branches\LocaleSettingsForm;
use App\Livewire\Waiter\TableDetail\Payment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\User;
use App\Services\Branches\BranchSettingsQueryService;
use App\Support\Branches\BranchSettingsGroup;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(SystemPermissionsSeeder::class));

function settingsGroupFixture(): array
{
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Settings fixture']);
    $branch = Branch::factory()->for($organization)->create();
    $settings = BranchSetting::factory()->for($branch)->create();

    return [$branch, $settings, $branch->organization->owner];
}

test('independent settings groups preserve unrelated stale values and replay once', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $localeVersion = BranchSettingsGroup::fingerprint($branch, $settings, 'locale');
    $settlementVersion = BranchSettingsGroup::fingerprint($branch, $settings, 'settlement');
    $action = app(SaveBranchSettingsGroupAction::class);
    $data = ['default_currency' => 'USD', 'service_charge_enabled' => true, 'service_charge_percent' => '12.50', 'tips_enabled' => true];
    $request = (string) Str::uuid();
    $first = $action->handle($actor, $branch, 'settlement', $data, $settlementVersion, $request);
    $repeat = $action->handle($actor, $branch, 'settlement', $data, $settlementVersion, $request);
    $action->handle($actor, $branch, 'locale', ['default_language' => 'lt'], $localeVersion, (string) Str::uuid());

    expect($repeat)->toBe($first)
        ->and($settings->fresh()->default_currency)->toBe('USD')
        ->and($branch->fresh()->currency)->toBe('USD')
        ->and($settings->fresh()->service_charge_basis_points)->toBe(1250)
        ->and($settings->fresh()->default_language)->toBe('lt')
        ->and(AuditLog::query()->where('action', 'branch_settings_changed')->count())->toBe(2);
});

test('settings groups reject same group stale writes and foreign fields', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $version = BranchSettingsGroup::fingerprint($branch, $settings, 'locale');
    $action = app(SaveBranchSettingsGroupAction::class);
    $action->handle($actor, $branch, 'locale', ['default_language' => 'lt'], $version, (string) Str::uuid());
    expect(fn () => $action->handle($actor, $branch, 'locale', ['default_language' => 'ru'], $version, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(fn () => $action->handle($actor, $branch, 'locale', ['default_language' => 'ru', 'default_currency' => 'USD'], $version, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($settings->fresh()->default_language)->toBe('lt')->and($branch->fresh()->currency)->toBe('EUR');
});

test('currency change rechecks financial records after preview', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $version = BranchSettingsGroup::fingerprint($branch, $settings, 'settlement');
    $menu = Menu::factory()->for($branch)->create();
    MenuItem::factory()->for($menu)->create(['price_cents' => 100]);
    expect(fn () => app(SaveBranchSettingsGroupAction::class)->handle($actor, $branch, 'settlement', [
        'default_currency' => 'USD', 'service_charge_enabled' => false, 'service_charge_percent' => '0.00', 'tips_enabled' => false,
    ], $version, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($branch->fresh()->currency)->toBe('EUR');
});

test('structural editor cannot bypass currency financial guard', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    Order::factory()->for($branch)->create();
    $data = $branch->only(['name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active']);
    $data['currency'] = 'USD';
    expect(fn () => app(UpdateBranchAction::class)->handle($branch, $data, $actor))->toThrow(ValidationException::class);
    expect($branch->fresh()->currency)->toBe('EUR')->and($settings->fresh()->default_currency)->toBe('EUR');
});

test('missing settings reads and guest defaults never initialize a row', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $settings->delete();
    $effective = app(BranchSettingsQueryService::class)->effective($branch);
    expect($effective->exists)->toBeFalse()->and($branch->settings()->exists())->toBeFalse();
    $version = BranchSettingsGroup::fingerprint($branch, $effective, 'locale');
    app(SaveBranchSettingsGroupAction::class)->handle($actor, $branch, 'locale', ['default_language' => 'ru'], $version, (string) Str::uuid());
    expect($branch->settings()->count())->toBe(1)->and($branch->settings()->sole()->default_language)->toBe('ru');
});

test('unrelated malformed legacy settings survive group save', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $settings->setRawAttributes([...$settings->getAttributes(), 'order_flow_mode' => 'unknown_legacy_mode', 'service_modes' => 'not-json']);
    $settings->save();
    app(SaveBranchSettingsGroupAction::class)->handle($actor, $branch, 'locale', ['default_language' => 'ru'], BranchSettingsGroup::fingerprint($branch, $settings, 'locale'), (string) Str::uuid());
    expect($settings->fresh()->getRawOriginal('order_flow_mode'))->toBe('unknown_legacy_mode')
        ->and($settings->fresh()->getRawOriginal('service_modes'))->toBe('not-json');
});

test('revoked membership blocks settings replay and new writes', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $version = BranchSettingsGroup::fingerprint($branch, $settings, 'locale');
    $request = (string) Str::uuid();
    $action = app(SaveBranchSettingsGroupAction::class);
    $action->handle($actor, $branch, 'locale', ['default_language' => 'lt'], $version, $request);
    $branch->organization->users()->updateExistingPivot($actor->id, ['status' => 'suspended']);
    expect(fn () => $action->handle($actor, $branch, 'locale', ['default_language' => 'lt'], $version, $request))->toThrow(AuthorizationException::class);
});

test('disabling waiter openings prevents a new visit but preserves an existing visit', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $point = ServicePoint::factory()->for($branch)->create();
    $settings->update(['allow_waiter_opened_sessions' => false]);
    expect(fn () => app(OpenTableSessionForServicePointAction::class)->handle($point, $actor))->toThrow(ValidationException::class);
    expect($point->tableSessions()->exists())->toBeFalse();
});

test('disabling invitations rejects an old invite on the next request while ordinary QR still works', function (): void {
    [$branch, $settings] = settingsGroupFixture();
    $point = ServicePoint::factory()->for($branch)->create();
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $guest = TableSessionGuest::factory()->for($session)->active()->create();
    $invite = app(CreateGuestInviteLinkAction::class)->handle($session, $guest);
    $settings->update(['allow_guest_invite_links' => false]);
    $action = app(CreateTableSessionJoinRequestAction::class);
    expect($action->handle($session, 'Invited guest', inviteToken: $invite->token))->toBeNull()
        ->and($action->handle($session, 'QR guest'))->toBeInstanceOf(TableSessionJoinRequest::class);
});

test('changed settlement terms reject an already opened payment dialog and preserve recorded payments', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $point = ServicePoint::factory()->for($branch)->create();
    $session = TableSession::factory()->forServicePoint($point)->create(['status' => TableSessionStatus::PaymentRequested]);
    $order = Order::factory()->for($branch)->for($session)->create(['status' => OrderStatus::PaymentRequested]);
    OrderItem::factory()->for($order)->create(['total_price_cents' => 1000]);
    $summary = app(BuildManualPaymentSummaryAction::class)->handle($session);
    $settings->update(['service_charge_enabled' => true, 'service_charge_basis_points' => 1250]);
    expect(fn () => app(RecordManualPaymentAction::class)->recordTable($session, $actor, 'cash', expectedSettlementFingerprint: $summary['settlement_fingerprint']))->toThrow(ValidationException::class);
    expect(ManualPayment::query()->count())->toBe(0);
    $payment = app(RecordManualPaymentAction::class)->recordTable($session, $actor, 'cash');
    $snapshot = $payment->getRawOriginal();
    $settings->update(['service_charge_basis_points' => 2500]);
    expect($payment->fresh()->getRawOriginal())->toBe($snapshot)->and($payment->service_charge_basis_points)->toBe(1250);
});

test('a saved numeric string yields a reusable canonical group baseline', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $action = app(SaveBranchSettingsGroupAction::class);
    $first = $action->handle($actor, $branch, 'advanced', [
        'polling_interval_seconds' => '5', 'inactivity_warning_minutes' => '50', 'pending_session_expire_minutes' => '40',
    ], BranchSettingsGroup::fingerprint($branch, $settings, 'advanced'), (string) Str::uuid());
    $action->handle($actor, $branch, 'advanced', [
        'polling_interval_seconds' => '6', 'inactivity_warning_minutes' => '50', 'pending_session_expire_minutes' => '40',
    ], $first['fingerprint'], (string) Str::uuid());
    expect($settings->fresh()->polling_interval_seconds)->toBe(6);
});

test('independent form validation retains other section errors on failure and success', function (): void {
    $component = new class extends Component {};
    $form = new LocaleSettingsForm($component, 'locale');
    $component->addError('settlement.defaultCurrency', 'Settlement needs attention.');
    $component->addError('locale.defaultLanguage', 'Old language error.');
    $form->defaultLanguage = 'xx';
    try {
        $form->validatedData();
        $this->fail('Invalid language must fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['settlement.defaultCurrency'])->toBe(['Settlement needs attention.'])
            ->and($exception->errors()['locale.defaultLanguage'])->not->toContain('Old language error.');
    }
    $form->defaultLanguage = 'lt';
    expect($form->validatedData())->toBe(['default_language' => 'lt'])
        ->and($component->getErrorBag()->has('settlement.defaultCurrency'))->toBeTrue()
        ->and($component->getErrorBag()->has('locale.defaultLanguage'))->toBeFalse();
});

test('guest allocation changes with the same table total invalidate the payment baseline', function (): void {
    [$branch, , $actor] = settingsGroupFixture();
    $point = ServicePoint::factory()->for($branch)->create();
    $session = TableSession::factory()->forServicePoint($point)->create(['status' => TableSessionStatus::PaymentRequested]);
    $first = TableSessionGuest::factory()->for($session)->active()->create();
    $second = TableSessionGuest::factory()->for($session)->active()->create();
    $order = Order::factory()->for($branch)->for($session)->create(['status' => OrderStatus::PaymentRequested]);
    $item = OrderItem::factory()->for($order)->for($first, 'guest')->create(['total_price_cents' => 1000]);
    $builder = app(BuildManualPaymentSummaryAction::class);
    $before = $builder->handle($session);
    $item->update(['table_session_guest_id' => $second->id]);
    $after = $builder->handle($session);
    expect($before['confirmed_total_cents'])->toBe($after['confirmed_total_cents'])
        ->and($before['settlement_fingerprint'])->not->toBe($after['settlement_fingerprint']);
    expect(fn () => app(RecordManualPaymentAction::class)->recordGuest($session, $second, $actor, 'cash', expectedSettlementFingerprint: $before['settlement_fingerprint']))->toThrow(ValidationException::class);
});

test('explicit payment refresh preserves entered note and tips without recording a payment', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    $point = ServicePoint::factory()->for($branch)->create();
    $session = TableSession::factory()->forServicePoint($point)->create(['status' => TableSessionStatus::PaymentRequested]);
    $order = Order::factory()->for($branch)->for($session)->create(['status' => OrderStatus::PaymentRequested]);
    OrderItem::factory()->for($order)->create(['total_price_cents' => 1000]);
    $component = Livewire::actingAs($actor)->test(Payment::class, ['tableSessionId' => $session->id])
        ->set('paymentNote', 'Keep this note')->set('tipsAmount', '2.50');
    $before = $component->get('settlementFingerprint');
    $settings->update(['service_charge_enabled' => true, 'service_charge_basis_points' => 1000, 'tips_enabled' => true]);
    $component->call('recordTablePayment')->assertHasErrors('manual_payment')
        ->call('refreshPayment')->assertHasNoErrors('manual_payment')
        ->assertSet('paymentNote', 'Keep this note')->assertSet('tipsAmount', '2.50');
    expect($component->get('settlementFingerprint'))->not->toBe($before)
        ->and(ManualPayment::query()->count())->toBe(0);
});

test('section forms preserve malformed stored values until their own explicit correction', function (): void {
    [$branch, $settings, $actor] = settingsGroupFixture();
    BranchSetting::query()->whereKey($settings->id)->update(['polling_interval_seconds' => 'broken interval', 'allow_guest_invite_links' => 'broken permission']);
    $settings = $settings->fresh();
    $component = new class extends Component {};
    $advanced = new AdvancedSettingsForm($component, 'advanced');
    $guests = new GuestProcessForm($component, 'guests');
    $advanced->populate($branch, $settings);
    $guests->populate($branch, $settings);
    expect($advanced->pollingIntervalSeconds)->toBe('broken interval')
        ->and($guests->allowGuestInviteLinks)->toBe('broken permission');
    expect(fn () => $advanced->validatedData())->toThrow(ValidationException::class)
        ->and(fn () => $guests->validatedData())->toThrow(ValidationException::class);
    app(SaveBranchSettingsGroupAction::class)->handle($actor, $branch, 'locale', ['default_language' => 'lt'], BranchSettingsGroup::fingerprint($branch, $settings, 'locale'), (string) Str::uuid());
    expect($settings->fresh()->getRawOriginal('polling_interval_seconds'))->toBe('broken interval')
        ->and($settings->fresh()->getRawOriginal('allow_guest_invite_links'))->toBe('broken permission')
        ->and(BranchSettingsGroup::values($settings, 'advanced')['polling_interval_seconds'])->toBe('broken interval');
});

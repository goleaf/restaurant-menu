<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use App\Policies\AreaNodePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\DraftOrderPolicy;
use App\Policies\KitchenTicketPolicy;
use App\Policies\ManualPaymentPolicy;
use App\Policies\MenuPolicy;
use App\Policies\OrderItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrganizationUserPolicy;
use App\Policies\QrCodePolicy;
use App\Policies\ServicePointPolicy;
use App\Policies\TableSessionPolicy;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('laravel discovers policies for the main restaurant modules', function (): void {
    $policies = [
        AreaNode::class => AreaNodePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        DraftOrder::class => DraftOrderPolicy::class,
        KitchenTicket::class => KitchenTicketPolicy::class,
        ManualPayment::class => ManualPaymentPolicy::class,
        Menu::class => MenuPolicy::class,
        Order::class => OrderPolicy::class,
        OrderItem::class => OrderItemPolicy::class,
        OrganizationUser::class => OrganizationUserPolicy::class,
        QrCode::class => QrCodePolicy::class,
        ServicePoint::class => ServicePointPolicy::class,
        TableSession::class => TableSessionPolicy::class,
    ];

    foreach ($policies as $model => $policy) {
        expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
    }
});

test('menu and venue policies preserve branch permissions and tenant isolation', function (): void {
    $context = mainModulePolicyContext();
    $headChef = attachMainModulePolicyUser($context['organization'], SystemRole::HeadChef);
    $waiter = attachMainModulePolicyUser($context['organization'], SystemRole::Waiter);
    $outsider = User::factory()->create();
    $foreignContext = mainModulePolicyContext('Foreign venue policy organization');

    expect(Gate::forUser($context['owner'])->allows('create', [Menu::class, $context['branch']]))->toBeTrue()
        ->and(Gate::forUser($context['owner'])->allows('update', $context['menu']))->toBeTrue()
        ->and(Gate::forUser($headChef)->allows('changeAvailability', $context['menu']))->toBeTrue()
        ->and(Gate::forUser($headChef)->allows('changePrice', $context['menu']))->toBeFalse()
        ->and(Gate::forUser($headChef)->allows('update', $context['menu']))->toBeFalse()
        ->and(Gate::forUser($context['owner'])->allows('update', $context['area']))->toBeTrue()
        ->and(Gate::forUser($context['owner'])->allows('update', $context['servicePoint']))->toBeTrue()
        ->and(Gate::forUser($context['owner'])->allows('manage', $context['qrCode']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('changeStatus', $context['servicePoint']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('openTable', $context['servicePoint']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('update', $context['servicePoint']))->toBeFalse()
        ->and(Gate::forUser($headChef)->allows('view', $foreignContext['menu']))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('view', $context['area']))->toBeFalse();
});

test('staff policy separates membership visibility management and permission changes', function (): void {
    $context = mainModulePolicyContext();
    $waiter = attachMainModulePolicyUser($context['organization'], SystemRole::Waiter);
    $waiterMembership = $context['organization']->memberships()
        ->where('user_id', $waiter->id)
        ->firstOrFail();
    $outsider = User::factory()->create();

    expect(Gate::forUser($waiter)->allows('view', $waiterMembership))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('update', $waiterMembership))->toBeFalse()
        ->and(Gate::forUser($waiter)->allows('managePermissions', $waiterMembership))->toBeFalse()
        ->and(Gate::forUser($context['owner'])->allows('update', $waiterMembership))->toBeTrue()
        ->and(Gate::forUser($context['owner'])->allows('managePermissions', $waiterMembership))->toBeTrue()
        ->and(Gate::forUser($context['owner'])->allows('managePermissions', $context['ownerMembership']))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('view', $waiterMembership))->toBeFalse();
});

test('operations policies preserve role specific access and branch isolation', function (): void {
    $context = mainModulePolicyContext();
    $waiter = attachMainModulePolicyUser($context['organization'], SystemRole::Waiter);
    $cashier = attachMainModulePolicyUser($context['organization'], SystemRole::Cashier);
    $headChef = attachMainModulePolicyUser($context['organization'], SystemRole::HeadChef);
    $outsider = User::factory()->create();
    $foreignContext = mainModulePolicyContext('Foreign operations policy organization');

    expect(Gate::forUser($waiter)->allows('view', $context['tableSession']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('viewOrders', $context['tableSession']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('viewPayments', $context['tableSession']))->toBeFalse()
        ->and(Gate::forUser($waiter)->allows('transfer', $context['tableSession']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('merge', $context['tableSession']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('updateItems', $context['draftOrder']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('confirm', $context['order']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('sendToKitchen', $context['order']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('cancel', $context['order']))->toBeFalse()
        ->and(Gate::forUser($waiter)->allows('view', $context['orderItem']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('cancel', $context['orderItem']))->toBeFalse()
        ->and(Gate::forUser($context['owner'])->allows('cancel', $context['orderItem']))->toBeTrue()
        ->and(Gate::forUser($context['owner'])->allows('delete', $context['orderItem']))->toBeFalse()
        ->and(Gate::forUser($waiter)->allows('close', $context['tableSession']))->toBeTrue()
        ->and(Gate::forUser($cashier)->allows('view', $context['tableSession']))->toBeTrue()
        ->and(Gate::forUser($cashier)->allows('viewOrders', $context['tableSession']))->toBeTrue()
        ->and(Gate::forUser($cashier)->allows('viewPayments', $context['tableSession']))->toBeTrue()
        ->and(Gate::forUser($cashier)->allows('view', $context['payment']))->toBeTrue()
        ->and(Gate::forUser($cashier)->allows('create', [ManualPayment::class, $context['branch']]))->toBeTrue()
        ->and(Gate::forUser($cashier)->allows('correct', $context['payment']))->toBeTrue()
        ->and(Gate::forUser($cashier)->allows('confirm', $context['order']))->toBeFalse()
        ->and(Gate::forUser($headChef)->allows('view', $context['kitchenTicket']))->toBeTrue()
        ->and(Gate::forUser($headChef)->allows('updateStatus', $context['kitchenTicket']))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('view', $foreignContext['order']))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('view', $context['tableSession']))->toBeFalse();
});

test('audit log policy allows scoped reads and denies mutations', function (): void {
    $context = mainModulePolicyContext();
    $director = attachMainModulePolicyUser($context['organization'], SystemRole::Director);
    $outsider = User::factory()->create();

    expect(Gate::forUser($director)->allows('viewAny', AuditLog::class))->toBeTrue()
        ->and(Gate::forUser($director)->allows('view', $context['auditLog']))->toBeTrue()
        ->and(Gate::forUser($director)->allows('update', $context['auditLog']))->toBeFalse()
        ->and(Gate::forUser($director)->allows('delete', $context['auditLog']))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('view', $context['auditLog']))->toBeFalse();
});

/**
 * @return array{
 *     organization: Organization,
 *     branch: Branch,
 *     owner: User,
 *     ownerMembership: OrganizationUser,
 *     menu: Menu,
 *     area: AreaNode,
 *     servicePoint: ServicePoint,
 *     qrCode: QrCode,
 *     tableSession: TableSession,
 *     draftOrder: DraftOrder,
 *     order: Order,
 *     orderItem: OrderItem,
 *     kitchenTicket: KitchenTicket,
 *     payment: ManualPayment,
 *     auditLog: AuditLog
 * }
 */
function mainModulePolicyContext(string $name = 'Main module policy organization'): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => $name]);
    $owner = $owner->fresh();
    $ownerMembership = $organization->memberships()->where('user_id', $owner->id)->firstOrFail();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $menu = Menu::factory()->for($branch)->create();
    $area = AreaNode::factory()->forBranch($branch)->create();
    $servicePoint = ServicePoint::factory()->forBranch($branch)->inAreaNode($area)->create();
    $qrCode = QrCode::factory()->forServicePoint($servicePoint)->create();
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    $draftOrder = DraftOrder::factory()->forTableSession($tableSession)->sentToWaiter()->create();
    $order = Order::factory()->forTableSession($tableSession)->create(['draft_order_id' => $draftOrder->id]);
    $orderItem = OrderItem::factory()->for($order)->create();
    $kitchenDepartment = KitchenDepartment::factory()->for($branch)->create();
    $kitchenTicket = KitchenTicket::factory()->forOrder($order)->create([
        'kitchen_department_id' => $kitchenDepartment->id,
    ]);
    $payment = ManualPayment::factory()->forTableSession($tableSession)->create([
        'recorded_by_user_id' => $owner->id,
    ]);
    $auditLog = AuditLog::factory()->for($branch)->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'action' => AuditLogAction::MenuPriceChanged,
    ]);

    return compact(
        'organization',
        'branch',
        'owner',
        'ownerMembership',
        'menu',
        'area',
        'servicePoint',
        'qrCode',
        'tableSession',
        'draftOrder',
        'order',
        'orderItem',
        'kitchenTicket',
        'payment',
        'auditLog',
    );
}

function attachMainModulePolicyUser(Organization $organization, SystemRole $systemRole): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('code', $systemRole->value)->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $user->fresh();
}

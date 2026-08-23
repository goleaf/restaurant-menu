<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\AreaNodePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BranchPolicy;
use App\Policies\BrandPolicy;
use App\Policies\DraftOrderPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\KitchenTicketPolicy;
use App\Policies\ManualPaymentPolicy;
use App\Policies\MenuPolicy;
use App\Policies\OrderItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\OrganizationUserPolicy;
use App\Policies\QrCodePolicy;
use App\Policies\ServicePointPolicy;
use App\Policies\TableSessionPolicy;
use Illuminate\Database\Eloquent\Model;

test('policy method returns an explicit authorization decision for an unrelated user', function (string $policyClass, string $methodName): void {
    $policy = app($policyClass);
    $method = new ReflectionMethod($policy, $methodName);
    $arguments = [User::factory()->create()];

    foreach (array_slice($method->getParameters(), 1) as $parameter) {
        $type = $parameter->getType();

        expect($type)->toBeInstanceOf(ReflectionNamedType::class);

        /** @var class-string<Model> $modelClass */
        $modelClass = $type->getName();
        $arguments[] = $modelClass::factory()->create();
    }

    expect($method->invokeArgs($policy, $arguments))->toBeBool();
})->with([
    'area node view any' => [AreaNodePolicy::class, 'viewAny'],
    'area node create' => [AreaNodePolicy::class, 'create'],
    'area node delete' => [AreaNodePolicy::class, 'delete'],
    'area node restore' => [AreaNodePolicy::class, 'restore'],
    'area node force delete' => [AreaNodePolicy::class, 'forceDelete'],
    'audit log create' => [AuditLogPolicy::class, 'create'],
    'branch view any' => [BranchPolicy::class, 'viewAny'],
    'branch delete' => [BranchPolicy::class, 'delete'],
    'branch restore' => [BranchPolicy::class, 'restore'],
    'branch force delete' => [BranchPolicy::class, 'forceDelete'],
    'branch close table' => [BranchPolicy::class, 'closeTable'],
    'brand delete' => [BrandPolicy::class, 'delete'],
    'brand restore' => [BrandPolicy::class, 'restore'],
    'brand force delete' => [BrandPolicy::class, 'forceDelete'],
    'draft order view' => [DraftOrderPolicy::class, 'view'],
    'draft order create' => [DraftOrderPolicy::class, 'create'],
    'draft order update' => [DraftOrderPolicy::class, 'update'],
    'draft order confirm' => [DraftOrderPolicy::class, 'confirm'],
    'draft order reject' => [DraftOrderPolicy::class, 'reject'],
    'draft order return rejected' => [DraftOrderPolicy::class, 'returnRejected'],
    'draft order delete' => [DraftOrderPolicy::class, 'delete'],
    'invitation view any' => [InvitationPolicy::class, 'viewAny'],
    'invitation create' => [InvitationPolicy::class, 'create'],
    'invitation update' => [InvitationPolicy::class, 'update'],
    'invitation delete' => [InvitationPolicy::class, 'delete'],
    'invitation restore' => [InvitationPolicy::class, 'restore'],
    'invitation force delete' => [InvitationPolicy::class, 'forceDelete'],
    'kitchen ticket view any' => [KitchenTicketPolicy::class, 'viewAny'],
    'kitchen ticket print' => [KitchenTicketPolicy::class, 'print'],
    'kitchen ticket update' => [KitchenTicketPolicy::class, 'update'],
    'kitchen ticket delete' => [KitchenTicketPolicy::class, 'delete'],
    'manual payment view any' => [ManualPaymentPolicy::class, 'viewAny'],
    'manual payment manage' => [ManualPaymentPolicy::class, 'manage'],
    'manual payment update' => [ManualPaymentPolicy::class, 'update'],
    'manual payment delete' => [ManualPaymentPolicy::class, 'delete'],
    'menu view any' => [MenuPolicy::class, 'viewAny'],
    'menu delete' => [MenuPolicy::class, 'delete'],
    'menu restore' => [MenuPolicy::class, 'restore'],
    'menu force delete' => [MenuPolicy::class, 'forceDelete'],
    'order item force delete' => [OrderItemPolicy::class, 'forceDelete'],
    'order view any' => [OrderPolicy::class, 'viewAny'],
    'order create' => [OrderPolicy::class, 'create'],
    'order update' => [OrderPolicy::class, 'update'],
    'order edit pending' => [OrderPolicy::class, 'editPending'],
    'order send to departments' => [OrderPolicy::class, 'sendToDepartments'],
    'order mark served' => [OrderPolicy::class, 'markServed'],
    'order view history' => [OrderPolicy::class, 'viewHistory'],
    'order delete' => [OrderPolicy::class, 'delete'],
    'organization view any' => [OrganizationPolicy::class, 'viewAny'],
    'organization delete' => [OrganizationPolicy::class, 'delete'],
    'organization restore' => [OrganizationPolicy::class, 'restore'],
    'organization force delete' => [OrganizationPolicy::class, 'forceDelete'],
    'organization manage branches' => [OrganizationPolicy::class, 'manageBranches'],
    'organization user view any' => [OrganizationUserPolicy::class, 'viewAny'],
    'organization user create' => [OrganizationUserPolicy::class, 'create'],
    'organization user delete' => [OrganizationUserPolicy::class, 'delete'],
    'organization user assign branches' => [OrganizationUserPolicy::class, 'assignBranches'],
    'qr code view' => [QrCodePolicy::class, 'view'],
    'qr code create' => [QrCodePolicy::class, 'create'],
    'qr code generate' => [QrCodePolicy::class, 'generate'],
    'qr code update' => [QrCodePolicy::class, 'update'],
    'qr code delete' => [QrCodePolicy::class, 'delete'],
    'service point view any' => [ServicePointPolicy::class, 'viewAny'],
    'service point view' => [ServicePointPolicy::class, 'view'],
    'service point create' => [ServicePointPolicy::class, 'create'],
    'service point delete' => [ServicePointPolicy::class, 'delete'],
    'service point restore' => [ServicePointPolicy::class, 'restore'],
    'service point force delete' => [ServicePointPolicy::class, 'forceDelete'],
    'service point generate qr' => [ServicePointPolicy::class, 'generateQr'],
    'table session view any' => [TableSessionPolicy::class, 'viewAny'],
    'table session create' => [TableSessionPolicy::class, 'create'],
    'table session update' => [TableSessionPolicy::class, 'update'],
    'table session delete' => [TableSessionPolicy::class, 'delete'],
]);

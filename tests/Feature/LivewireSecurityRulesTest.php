<?php

use App\Livewire\Organizations\Brands\Branches\Menu\Availability as MenuAvailability;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog as MenuCatalog;
use App\Livewire\Organizations\Brands\Branches\Menu\KitchenDepartments as MenuKitchenDepartments;
use App\Livewire\Organizations\Brands\Branches\Menu\Modifiers as MenuModifiers;
use App\Livewire\Organizations\Staff\Permissions;
use App\Livewire\PublicQr\DraftOrder;
use App\Livewire\PublicQr\DraftTotals;
use App\Livewire\PublicQr\GuestActions;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\PublicQr\JoinRequests;
use App\Livewire\PublicQr\Notifications;
use App\Livewire\PublicQr\OrderStatuses;
use App\Livewire\PublicQr\Show;
use App\Livewire\PublicQr\TableGuests;
use App\Livewire\Waiter\TableDetail;
use App\Livewire\Waiter\TableDetail\DraftReview;
use App\Livewire\Waiter\TableDetail\OrderFulfilment;
use App\Livewire\Waiter\TableDetail\Overview;
use App\Livewire\Waiter\TableDetail\Payment;
use Livewire\Attributes\Locked;

test('livewire security boundary properties are locked', function (string $component, string $property): void {
    expect(livewireSecurityPropertyIsLocked($component, $property))->toBeTrue();
})->with([
    'guest QR token' => [Show::class, 'token'],
    'guest entry QR token' => [GuestEntry::class, 'token'],
    'guest entry table session id' => [GuestEntry::class, 'currentTableSessionId'],
    'guest entry current guest id' => [GuestEntry::class, 'currentGuestId'],
    'guest entry current join request id' => [GuestEntry::class, 'currentJoinRequestId'],
    'guest entry invite presence flag' => [GuestEntry::class, 'hasCurrentInviteToken'],
    'guest entry landing payload' => [GuestEntry::class, 'landing'],
    'guest actions table session id' => [GuestActions::class, 'tableSessionId'],
    'guest actions current guest id' => [GuestActions::class, 'currentGuestId'],
    'guest actions public token' => [GuestActions::class, 'publicToken'],
    'guest menu branch id' => [GuestMenu::class, 'branchId'],
    'guest menu table session id' => [GuestMenu::class, 'tableSessionId'],
    'guest menu current guest id' => [GuestMenu::class, 'currentGuestId'],
    'guest menu public token' => [GuestMenu::class, 'publicToken'],
    'guest draft table session id' => [DraftOrder::class, 'tableSessionId'],
    'guest draft current guest id' => [DraftOrder::class, 'currentGuestId'],
    'guest draft public token' => [DraftOrder::class, 'publicToken'],
    'guest draft totals table session id' => [DraftTotals::class, 'tableSessionId'],
    'guest draft totals current guest id' => [DraftTotals::class, 'currentGuestId'],
    'guest draft totals public token' => [DraftTotals::class, 'publicToken'],
    'guest join requests table session id' => [JoinRequests::class, 'tableSessionId'],
    'guest join requests guest id' => [JoinRequests::class, 'guestId'],
    'guest join requests public token' => [JoinRequests::class, 'publicToken'],
    'guest notifications table session id' => [Notifications::class, 'tableSessionId'],
    'guest notifications current guest id' => [Notifications::class, 'currentGuestId'],
    'guest notifications public token' => [Notifications::class, 'publicToken'],
    'guest table guests session id' => [TableGuests::class, 'tableSessionId'],
    'guest table guests current guest id' => [TableGuests::class, 'currentGuestId'],
    'guest order statuses table session id' => [OrderStatuses::class, 'tableSessionId'],
    'menu availability organization id' => [MenuAvailability::class, 'organizationId'],
    'menu availability brand id' => [MenuAvailability::class, 'brandId'],
    'menu availability branch id' => [MenuAvailability::class, 'branchId'],
    'menu catalog organization id' => [MenuCatalog::class, 'organizationId'],
    'menu catalog brand id' => [MenuCatalog::class, 'brandId'],
    'menu catalog branch id' => [MenuCatalog::class, 'branchId'],
    'menu kitchen departments organization id' => [MenuKitchenDepartments::class, 'organizationId'],
    'menu kitchen departments brand id' => [MenuKitchenDepartments::class, 'brandId'],
    'menu kitchen departments branch id' => [MenuKitchenDepartments::class, 'branchId'],
    'menu modifiers organization id' => [MenuModifiers::class, 'organizationId'],
    'menu modifiers brand id' => [MenuModifiers::class, 'brandId'],
    'menu modifiers branch id' => [MenuModifiers::class, 'branchId'],
    'waiter table detail session id' => [TableDetail::class, 'tableSessionId'],
    'waiter table overview session id' => [Overview::class, 'tableSessionId'],
    'waiter draft review session id' => [DraftReview::class, 'tableSessionId'],
    'waiter order fulfilment session id' => [OrderFulfilment::class, 'tableSessionId'],
    'waiter table payment session id' => [Payment::class, 'tableSessionId'],
    'staff permission membership role id' => [Permissions::class, 'membershipRoleId'],
]);

test('guest-facing livewire components do not expose guest auth tokens as public properties', function (string $component): void {
    $publicProperties = collect((new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC))
        ->map(fn (ReflectionProperty $property): string => $property->getName())
        ->all();

    expect($publicProperties)
        ->not->toContain('guestToken')
        ->and($publicProperties)->not->toContain('currentInviteToken');
})->with([
    Show::class,
    GuestEntry::class,
    GuestActions::class,
    GuestMenu::class,
    DraftOrder::class,
    DraftTotals::class,
    JoinRequests::class,
    Notifications::class,
    TableGuests::class,
    OrderStatuses::class,
]);

function livewireSecurityPropertyIsLocked(string $component, string $property): bool
{
    $reflectionProperty = new ReflectionProperty($component, $property);

    return $reflectionProperty->getAttributes(Locked::class) !== [];
}

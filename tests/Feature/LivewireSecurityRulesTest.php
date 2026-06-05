<?php

use App\Livewire\Organizations\Staff\Permissions;
use App\Livewire\PublicQr\DraftOrder;
use App\Livewire\PublicQr\DraftTotals;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\PublicQr\JoinRequests;
use App\Livewire\PublicQr\Notifications;
use App\Livewire\PublicQr\OrderStatuses;
use App\Livewire\PublicQr\Show;
use App\Livewire\PublicQr\TableGuests;
use App\Livewire\Waiter\TableDetail;
use Livewire\Attributes\Locked;

test('livewire security boundary properties are locked', function (string $component, string $property): void {
    expect(livewireSecurityPropertyIsLocked($component, $property))->toBeTrue();
})->with([
    'guest QR token' => [Show::class, 'token'],
    'guest QR table session id' => [Show::class, 'currentTableSessionId'],
    'guest QR current guest id' => [Show::class, 'currentGuestId'],
    'guest QR current join request id' => [Show::class, 'currentJoinRequestId'],
    'guest QR invite presence flag' => [Show::class, 'hasCurrentInviteToken'],
    'guest QR landing payload' => [Show::class, 'landing'],
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
    'waiter table detail session id' => [TableDetail::class, 'tableSessionId'],
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

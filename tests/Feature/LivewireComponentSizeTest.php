<?php

declare(strict_types=1);

use App\Livewire\Organizations\Brands\Branches\Menu\Availability;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Livewire\Organizations\Brands\Branches\Menu\Index as MenuIndex;
use App\Livewire\Organizations\Brands\Branches\Menu\KitchenDepartments;
use App\Livewire\Organizations\Brands\Branches\Menu\Modifiers;
use App\Livewire\PublicQr\GuestActions;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Livewire\Waiter\TableDetail;
use App\Livewire\Waiter\TableDetail\DraftReview;
use App\Livewire\Waiter\TableDetail\OrderFulfilment;
use App\Livewire\Waiter\TableDetail\Overview;
use App\Livewire\Waiter\TableDetail\Payment;

test('complex livewire pages are split into bounded workflow components', function (string $component, int $maximumLines): void {
    expect(class_exists($component))->toBeTrue();

    $file = (new ReflectionClass($component))->getFileName();

    expect($file)->toBeString()
        ->and(count(file($file)))->toBeLessThanOrEqual($maximumLines);
})->with([
    'menu route shell' => [MenuIndex::class, 300],
    'menu catalog' => [Catalog::class, 1000],
    'menu availability' => [Availability::class, 400],
    'menu kitchen departments' => [KitchenDepartments::class, 500],
    'menu modifiers' => [Modifiers::class, 750],
    'public QR route shell' => [PublicQrShow::class, 500],
    'public QR guest entry' => [GuestEntry::class, 1000],
    'public QR guest actions' => [GuestActions::class, 400],
    'waiter table route shell' => [TableDetail::class, 300],
    'waiter table overview' => [Overview::class, 700],
    'waiter draft review' => [DraftReview::class, 1000],
    'waiter order fulfilment' => [OrderFulfilment::class, 550],
    'waiter table payment' => [Payment::class, 550],
]);

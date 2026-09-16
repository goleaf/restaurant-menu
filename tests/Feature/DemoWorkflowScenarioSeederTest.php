<?php

declare(strict_types=1);

use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Services\Notifications\UserNotificationQueryService;
use App\Services\Onboarding\RestaurantSetupQueryService;
use Database\Seeders\DemoRestaurantSeeder;
use Database\Seeders\DemoWorkflowScenarioSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->workflowDisk = 'workflow-seeder-'.getmypid().'-'.Str::uuid();
    Storage::set('public', Storage::fake($this->workflowDisk));
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks/'.$this->workflowDisk));
});

test('demo workflow scenarios expose completed onboarding and scoped unread and read notifications', function (): void {
    $this->seed(DemoRestaurantSeeder::class);
    $owner = User::query()->where('email', 'owner@demo.test')->firstOrFail();
    $waiter = User::query()->where('email', 'waiter@demo.test')->firstOrFail();

    $presentation = app(RestaurantSetupQueryService::class)->presentation($owner);
    expect($presentation['completed'])->toBeTrue()
        ->and($presentation['step'])->toBe(8)
        ->and($presentation['summary']['service_points'])->toBeGreaterThan(0);

    $snapshot = app(UserNotificationQueryService::class)->snapshot($waiter, true);
    expect($snapshot['count'])->toBe(1)
        ->and($snapshot['notifications'])->toHaveCount(2)
        ->and($snapshot['destinations'])->toHaveCount(2);

    $state = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $originalState = $state->getAttributes();
    $ids = $waiter->notifications()->orderBy('id')->pluck('id')->all();
    $waiter->unreadNotifications->markAsRead();

    $this->seed(DemoRestaurantSeeder::class);

    expect($state->fresh()->getAttributes())->toBe($originalState)
        ->and($waiter->notifications()->orderBy('id')->pluck('id')->all())->toBe($ids)
        ->and($waiter->unreadNotifications()->count())->toBe(0)
        ->and(User::query()->count())->toBe(17);
});

test('demo workflow scenario refuses production before reading or writing fixtures', function (): void {
    config()->set('app.env', 'production');

    expect(fn () => $this->seed(DemoWorkflowScenarioSeeder::class))->toThrow(LogicException::class)
        ->and(RestaurantOnboarding::query()->count())->toBe(0);
});

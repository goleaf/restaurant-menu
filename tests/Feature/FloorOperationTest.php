<?php

use App\Actions\Floor\RunFloorOperationAction;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\FloorOperation;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('records one exact floor command and reauthorizes every replay', function (): void {
    $actor = User::factory()->create();
    $branch = Branch::factory()->create();
    $request = (string) Str::uuid();
    $checks = 0;
    $writes = 0;
    $authorize = function () use (&$checks): void {
        $checks++;
    };
    $apply = function () use (&$writes): array {
        $writes++;

        return ['id' => 123];
    };
    $action = app(RunFloorOperationAction::class);
    expect($action->handle($actor, $branch, $request, 'create-table', null, ['name' => 'A'], $authorize, $apply))->toBe(['id' => 123]);
    expect($action->handle($actor, $branch, $request, 'create-table', null, ['name' => 'A'], $authorize, $apply))->toBe(['id' => 123]);
    expect($checks)->toBe(2)->and($writes)->toBe(1)->and(FloorOperation::query()->count())->toBe(1);
    expect(fn () => $action->handle($actor, $branch, $request, 'create-table', null, ['name' => 'B'], $authorize, $apply))->toThrow(ValidationException::class);
});

it('rolls back a floor command when its receipt is rejected', function (): void {
    $actor = User::factory()->create();
    $branch = Branch::factory()->create();
    FloorOperation::creating(fn (): bool => false);
    try {
        expect(fn () => app(RunFloorOperationAction::class)->handle($actor, $branch, (string) Str::uuid(), 'create-area', null, [], fn () => null, function () use ($branch): array {
            return ['id' => AreaNode::factory()->for($branch)->create()->id];
        }))->toThrow(RuntimeException::class);
        expect(AreaNode::query()->count())->toBe(0);
    } finally {
        FloorOperation::flushEventListeners();
    }
});

it('advances independent structure revisions across rename and ABA', function (string $kind): void {
    $class = $kind === 'area' ? AreaNode::class : ServicePoint::class;
    $model = $class::factory()->create(['name' => 'Before']);
    expect($model->structure_version)->toBe(0);
    $model->update(['name' => 'After']);
    $model->update(['name' => 'Before']);
    expect($model->fresh()->structure_version)->toBe(2);
})->with(['area' => ['area'], 'point' => ['point']]);

it('rejects a stale Eloquent structure writer instead of reusing a revision', function (): void {
    $point = ServicePoint::factory()->create(['name' => 'Original']);
    $stale = $point->fresh();
    $point->update(['name' => 'New']);
    expect(fn () => $stale->update(['name' => 'Stale']))->toThrow(ValidationException::class);
    expect($point->fresh()->name)->toBe('New')->and($point->fresh()->structure_version)->toBe(1);
});

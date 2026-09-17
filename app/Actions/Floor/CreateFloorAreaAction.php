<?php

declare(strict_types=1);

namespace App\Actions\Floor;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateFloorAreaAction
{
    public function __construct(private RunFloorOperationAction $run, private CreateAreaNodeAction $create) {}

    /** @param array<string,mixed> $data */
    public function handle(User $actor, Branch $branch, array $data, string $requestId): AreaNode
    {
        $result = $this->run->handle($actor, $branch, $requestId, 'create-area', null, $data,
            fn (User $actor, Branch $branch) => Gate::forUser($actor)->authorize('create', [AreaNode::class, $branch]),
            fn (User $actor, Branch $branch): array => ['id' => $this->create->handle($branch, $data, $actor)->id]);

        return $branch->areaNodes()->whereKey($result['id'])->firstOrFail();
    }
}

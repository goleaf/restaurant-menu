<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ResumeMenuImageCleanupAction
{
    public function __construct(
        private readonly EnsureMenuOperationAccessAction $access,
        private readonly FlushMenuOperationMediaAction $flushMedia,
    ) {}

    public function handle(User $actor, Branch $branch, int $itemId, string $requestId): MenuOperation
    {
        $operation = DB::transaction(function () use ($actor, $branch, $itemId, $requestId): MenuOperation {
            $branch = $this->access->handle($actor, $branch, $requestId);
            $operation = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if (! $operation instanceof MenuOperation) {
                throw new AuthorizationException;
            }
            $this->access->assertOwner($operation, $actor, $branch);
            if (! in_array($operation->kind, [MenuOperationKind::ImageRemove, MenuOperationKind::ImagePromote], true)
                || $operation->target_id !== $itemId
                || ! MenuItem::withTrashed()->whereKey($itemId)->where('menu_id', $operation->menu_id)->exists()
                || ! Menu::withTrashed()->whereKey($operation->menu_id)->where('branch_id', $branch->id)->exists()) {
                throw new AuthorizationException;
            }
            if ($operation->completed_at === null || $operation->pending_cleanup !== []) {
                DB::afterCommit(fn () => $this->flushMedia->handle($operation->id));
            }

            return $operation;
        }, attempts: 3);

        return $operation->refresh();
    }
}

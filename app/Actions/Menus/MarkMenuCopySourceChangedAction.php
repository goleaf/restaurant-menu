<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Models\MenuOperation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

final class MarkMenuCopySourceChangedAction
{
    public function handle(int $itemId): void
    {
        try {
            MenuOperation::query()
                ->where('kind', MenuOperationKind::DuplicateItem)
                ->where('target_id', $itemId)
                ->whereNotNull('active_scope')
                ->where('source_changed', false)
                ->update(['source_changed' => true]);
        } catch (QueryException $exception) {
            // Historical data migrations can dispatch observers before this additive table exists.
            if (Schema::hasTable('menu_operations')) {
                throw $exception;
            }
        }
    }
}

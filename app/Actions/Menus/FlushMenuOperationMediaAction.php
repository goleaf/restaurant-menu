<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\MenuOperation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FlushMenuOperationMediaAction
{
    public function __construct(private readonly DeleteLocalMediaFileAction $deleteImage) {}

    public function handle(int $operationId): void
    {
        DB::transaction(function () use ($operationId): void {
            $operation = MenuOperation::query()->lockForUpdate()->findOrFail($operationId);
            if (count($operation->pending_cleanup) > 400) {
                throw new RuntimeException('The media cleanup batch exceeds its limit.');
            }
            foreach ($operation->pending_cleanup as $path) {
                $this->deleteImage->handle($path);
            }
            $operation->pending_cleanup = [];
            if ($operation->phase === MenuOperationPhase::Failed) {
                $operation->completed_at = now();
            }
            if (in_array($operation->kind, [MenuOperationKind::ImageRemove, MenuOperationKind::ImagePromote], true)) {
                $operation->phase = MenuOperationPhase::Completed;
                $operation->completed_at = now();
            }
            if ($operation->save() !== true) {
                throw new RuntimeException('The media cleanup checkpoint could not be saved.');
            }
        });
    }
}

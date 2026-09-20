<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\Enums\KitchenTicketItemStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class UpdatePreparationTicketItemsAction
{
    public function __construct(
        private readonly UpdateDepartmentTicketItemStatusAction $updateItem,
    ) {}

    /**
     * @param  list<array{id: int, status: string, updated_at: string}>  $items
     * @return list<array{id: int, successful: bool, message: string, notification_pending: bool}>
     */
    public function handle(User $user, int $branchId, int $ticketId, KitchenTicketItemStatus $status, array $items): array
    {
        Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'min:1', 'max:24'],
            'items.*' => ['required', 'array:id,status,updated_at'],
            'items.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.status' => ['required', Rule::enum(KitchenTicketItemStatus::class)],
            'items.*.updated_at' => ['required', 'string', 'max:64'],
        ])->validate();

        $outcomes = [];
        foreach ($items as $item) {
            try {
                $updated = $this->updateItem->handlePreparation(
                    (int) $item['id'], $status, $user, $branchId,
                    KitchenTicketItemStatus::from($item['status']), $item['updated_at'], $ticketId,
                );
                $pending = (bool) $updated->getAttribute('notification_delivery_pending');
                $outcomes[] = [
                    'id' => (int) $item['id'], 'successful' => true,
                    'message' => __($pending ? 'preparation.notifications.pending' : 'ui.livewire.departments.dashboard.status_updated'),
                    'notification_pending' => $pending,
                ];
            } catch (ValidationException $exception) {
                $outcomes[] = [
                    'id' => (int) $item['id'], 'successful' => false,
                    'message' => (string) collect($exception->errors())->flatten()->first(),
                    'notification_pending' => false,
                ];
            } catch (ModelNotFoundException|HttpException) {
                $outcomes[] = [
                    'id' => (int) $item['id'], 'successful' => false,
                    'message' => __('ui.actions.departments.updatedepartmentticketitemstatusaction.u_vas_net_dos'),
                    'notification_pending' => false,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $outcomes[] = [
                    'id' => (int) $item['id'], 'successful' => false,
                    'message' => __('errors.types.system_error.message'),
                    'notification_pending' => false,
                ];
            }
        }

        return $outcomes;
    }
}

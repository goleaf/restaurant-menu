<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Branches\ForgetBranchCacheAction;
use App\Enums\AuditLogAction;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MenuItemObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /**
     * Handle the MenuItem "created" event.
     */
    public function created(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    /**
     * Handle the MenuItem "updated" event.
     */
    public function updated(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
        $this->recordAuditedChanges($menuItem);
    }

    /**
     * Handle the MenuItem "deleted" event.
     */
    public function deleted(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
        $this->recordDeletion($menuItem);
    }

    /**
     * Handle the MenuItem "restored" event.
     */
    public function restored(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    /**
     * Handle the MenuItem "force deleted" event.
     */
    public function forceDeleted(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    private function forgetGuestMenu(MenuItem $menuItem): void
    {
        $this->forgetForMenuId($menuItem->menu_id);

        $originalMenuId = $menuItem->getOriginal('menu_id');

        if (is_numeric($originalMenuId) && (int) $originalMenuId !== $menuItem->menu_id) {
            $this->forgetForMenuId((int) $originalMenuId);
        }
    }

    private function forgetForMenuId(?int $menuId): void
    {
        if ($menuId === null) {
            return;
        }

        $branchId = Menu::query()
            ->select('branch_id')
            ->whereKey($menuId)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }

    private function recordAuditedChanges(MenuItem $menuItem): void
    {
        $context = $this->contextForMenuId($menuItem->menu_id);
        $actor = $this->currentUser();

        if ($menuItem->wasChanged('price_cents')) {
            $this->recordAuditLog->handle(
                action: AuditLogAction::MenuPriceChanged,
                entityType: 'menu_item',
                entityId: $menuItem->id,
                actorUser: $actor,
                organizationId: $context['organization_id'],
                branchId: $context['branch_id'],
                oldValues: [
                    'name' => $menuItem->name,
                    'price_cents' => $menuItem->getOriginal('price_cents'),
                ],
                newValues: [
                    'name' => $menuItem->name,
                    'price_cents' => $menuItem->price_cents,
                ],
            );
        }

        if ($menuItem->wasChanged('is_available')) {
            $this->recordAuditLog->handle(
                action: AuditLogAction::MenuAvailabilityChanged,
                entityType: 'menu_item',
                entityId: $menuItem->id,
                actorUser: $actor,
                organizationId: $context['organization_id'],
                branchId: $context['branch_id'],
                oldValues: [
                    'name' => $menuItem->name,
                    'is_available' => (bool) $menuItem->getOriginal('is_available'),
                ],
                newValues: [
                    'name' => $menuItem->name,
                    'is_available' => (bool) $menuItem->is_available,
                ],
            );
        }
    }

    private function recordDeletion(MenuItem $menuItem): void
    {
        $context = $this->contextForMenuId($menuItem->menu_id);

        $this->recordAuditLog->handle(
            action: AuditLogAction::MenuItemDeleted,
            entityType: 'menu_item',
            entityId: $menuItem->id,
            actorUser: $this->currentUser(),
            organizationId: $context['organization_id'],
            branchId: $context['branch_id'],
            oldValues: [
                'menu_id' => $menuItem->menu_id,
                'category_id' => $menuItem->category_id,
                'name' => $menuItem->name,
                'price_cents' => $menuItem->price_cents,
                'is_available' => (bool) $menuItem->is_available,
            ],
        );
    }

    /**
     * @return array{organization_id: int|null, branch_id: int|null}
     */
    private function contextForMenuId(?int $menuId): array
    {
        if ($menuId === null) {
            return [
                'organization_id' => null,
                'branch_id' => null,
            ];
        }

        $menu = Menu::query()
            ->select(['id', 'branch_id'])
            ->with(['branch:id,organization_id'])
            ->whereKey($menuId)
            ->first();

        return [
            'organization_id' => $menu?->branch?->organization_id,
            'branch_id' => $menu?->branch_id,
        ];
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}

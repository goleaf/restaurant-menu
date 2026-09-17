<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\MarkMenuCopySourceChangedAction;
use App\Enums\AuditLogAction;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class MenuItemObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly MarkMenuCopySourceChangedAction $markCopySourceChanged,
    ) {}

    public function updating(MenuItem $menuItem): void
    {
        if ($menuItem->isDirty(['menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'weight', 'volume', 'calories', 'sort_order'])) {
            $menuItem->content_version = (int) MenuItem::query()->whereKey($menuItem->id)->value('content_version') + 1;
        }
        if ($menuItem->isDirty('image')) {
            $menuItem->media_version = (int) MenuItem::query()->whereKey($menuItem->id)->value('media_version') + 1;
        }
        if ($menuItem->isDirty(['is_available', 'hidden_until']) && ! $menuItem->isDirty('availability_version')) {
            $menuItem->availability_version = (int) $menuItem->getRawOriginal('availability_version') + 1;
        }
    }

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
        if ($menuItem->restrictionAuditContext === null) {
            $this->forgetGuestMenu($menuItem);
        }
        $this->recordAuditedChanges($menuItem);
    }

    /**
     * Handle the MenuItem "deleted" event.
     */
    public function deleted(MenuItem $menuItem): void
    {
        $this->invalidateModifierUsage($menuItem);
        $this->forgetGuestMenu($menuItem);
        $this->recordDeletion($menuItem);
    }

    /**
     * Handle the MenuItem "restored" event.
     */
    public function restored(MenuItem $menuItem): void
    {
        $this->invalidateModifierUsage($menuItem);
        $this->forgetGuestMenu($menuItem);
    }

    /**
     * Handle the MenuItem "force deleted" event.
     */
    public function forceDeleted(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    private function invalidateModifierUsage(MenuItem $menuItem): void
    {
        ModifierGroup::query()->whereHas('items', fn ($query) => $query->withoutGlobalScope(SoftDeletingScope::class)->whereKey($menuItem->id))->increment('content_version');
    }

    private function forgetGuestMenu(MenuItem $menuItem): void
    {
        $this->markCopySourceChanged->handle($menuItem->id);
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
        $explicit = $menuItem->restrictionAuditContext;
        if ($explicit !== null) {
            $this->recordAuditLog->handle(AuditLogAction::MenuAvailabilityChanged, 'menu_item', $menuItem->id,
                actorUser: $explicit->actor, organizationId: $explicit->organizationId, branchId: $explicit->branchId,
                oldValues: ['is_available' => (bool) $menuItem->getOriginal('is_available'), 'hidden_until' => $menuItem->getOriginal('hidden_until'), 'availability_version' => $menuItem->getOriginal('availability_version')],
                newValues: ['is_available' => $menuItem->is_available, 'hidden_until' => $menuItem->hidden_until, 'availability_version' => $menuItem->availability_version,
                    'operation' => $explicit->operation, 'reason' => $explicit->reason]);

            return;
        }
        if (! $menuItem->wasChanged(['price_cents', 'is_available'])) {
            return;
        }

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

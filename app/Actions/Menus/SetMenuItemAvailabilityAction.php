<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SetMenuItemAvailabilityAction
{
    public function __construct(private readonly SetMenuItemsRestrictionAction $restrictions) {}

    public function handle(User $actor, Branch $branch, MenuItem $item, bool $isAvailable, ?int $expectedVersion = null, ?string $requestId = null): MenuItem
    {
        if (! Menu::query()->whereKey($item->menu_id)->where('branch_id', $branch->id)->exists()) {
            throw new InvalidArgumentException('The menu item must belong to the selected branch.');
        }
        $this->restrictions->handle($actor, $branch, [['id' => $item->id, 'version' => $expectedVersion ?? $item->availability_version]],
            $isAvailable ? 'resume' : 'stop', null, $branch->timezone, $requestId ?? (string) Str::uuid());

        return $item->refresh();
    }
}

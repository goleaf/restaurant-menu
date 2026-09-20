<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\TableSession;
use Illuminate\Validation\ValidationException;

final class EnsureBranchCurrencyCanChangeAction
{
    public function handle(Branch $branch, string $currency, string $errorField = 'settlement.defaultCurrency'): void
    {
        if ($currency === $branch->currency) {
            return;
        }

        if ($this->hasMonetaryData($branch)) {
            throw ValidationException::withMessages([$errorField => __('settings.errors.currency_has_data')]);
        }
    }

    public function hasMonetaryData(Branch $branch): bool
    {
        $menus = Menu::withTrashed()->select('id')->where('branch_id', $branch->id);
        $items = MenuItem::withTrashed()->select('id')->whereIn('menu_id', $menus);

        return (clone $items)->exists()
            || MenuItemVariant::query()->whereIn('menu_item_id', $items)->exists()
            || ModifierOption::query()->whereIn('modifier_group_id', ModifierGroup::query()->select('id')->where('branch_id', $branch->id))->exists()
            || DraftOrder::query()->where('branch_id', $branch->id)->exists()
            || Order::query()->where('branch_id', $branch->id)->exists()
            || ManualPayment::query()->where('branch_id', $branch->id)->exists()
            || TableSession::query()->where('branch_id', $branch->id)->whereNotIn('status', [TableSessionStatus::Closed->value, TableSessionStatus::Cancelled->value])->exists();
    }
}

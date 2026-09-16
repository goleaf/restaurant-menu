<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\KitchenTicketItem;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use App\Notifications\DraftOrderSentToWaiterNotification;
use App\Notifications\KitchenItemReadyNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DemoWorkflowScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() || strtolower((string) config('app.env')) === 'production') {
            throw new LogicException('Demo workflow scenarios cannot be seeded in production.');
        }

        $organization = Organization::query()->select(['id', 'owner_user_id'])
            ->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)
            ->whereHas('owner', fn ($query) => $query->where('email', 'owner@demo.test'))
            ->firstOrFail();
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id'])
            ->where('organization_id', $organization->id)->where('name', 'Bella Pizza Old Town')->firstOrFail();

        DB::transaction(function () use ($organization, $branch): void {
            $this->completedOnboarding($organization, $branch);
            $this->notifications($branch);
        });
    }

    private function completedOnboarding(Organization $organization, Branch $branch): void
    {
        if (RestaurantOnboarding::query()->where('user_id', $organization->owner_user_id)->exists()) {
            return;
        }

        $area = AreaNode::query()->select(['id', 'branch_id'])->where('branch_id', $branch->id)
            ->whereHas('servicePoints', fn ($query) => $query->where('type', ServicePointType::Table))
            ->orderBy('sort_order')->orderBy('id')->firstOrFail();
        $points = ServicePoint::query()->select(['id', 'branch_id', 'area_node_id'])
            ->where('branch_id', $branch->id)->where('area_node_id', $area->id)->where('type', ServicePointType::Table)
            ->orderBy('id')->limit(20)->get();
        $item = MenuItem::query()->select(['id', 'menu_id', 'category_id'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
            ->where('is_available', true)->orderBy('sort_order')->orderBy('id')->firstOrFail();

        $checkpoint = RestaurantOnboarding::factory()->create([
            'user_id' => $organization->owner_user_id,
            'organization_id' => $organization->id,
            'brand_id' => $branch->brand_id,
            'branch_id' => $branch->id,
            'area_node_id' => $area->id,
            'menu_id' => $item->menu_id,
            'menu_category_id' => $item->category_id,
            'menu_item_id' => $item->id,
            'expected_service_point_count' => $points->count(),
            'completed_at' => CarbonImmutable::parse('2026-08-23 12:00:00', 'UTC'),
        ]);

        $checkpoint->servicePoints()->sync($points->mapWithKeys(
            fn (ServicePoint $point, int $index): array => [$point->id => ['position' => $index + 1]],
        )->all());
    }

    private function notifications(Branch $branch): void
    {
        $waiter = User::query()->where('email', 'waiter@demo.test')->firstOrFail();

        if (! $waiter->canAccessBranch($branch->id)) {
            return;
        }

        $draft = DraftOrder::query()->withCount('items')
            ->whereHas('tableSession', fn ($query) => $query->where('branch_id', $branch->id))
            ->where('status', DraftOrderStatus::SentToWaiter)->orderBy('id')->firstOrFail();
        $ticketItem = KitchenTicketItem::query()
            ->whereHas('kitchenTicket', fn ($query) => $query->where('branch_id', $branch->id))
            ->where('status', KitchenTicketItemStatus::Ready)->orderBy('id')->firstOrFail();
        $notifications = [
            '56a16577-d736-40d7-b91d-10db1b357800' => new DraftOrderSentToWaiterNotification($draft),
            '56a16577-d736-40d7-b91d-10db1b357801' => new KitchenItemReadyNotification($ticketItem),
        ];

        foreach ($notifications as $id => $notification) {
            if ($waiter->notifications()->whereKey($id)->exists()) {
                continue;
            }

            $notification->id = $id;
            $waiter->notify($notification->locale($waiter->locale));

            if ($notification instanceof KitchenItemReadyNotification) {
                $waiter->notifications()->whereKey($id)->firstOrFail()->markAsRead();
            }
        }
    }
}

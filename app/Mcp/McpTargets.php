<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\DraftOrder;
use App\Models\KitchenTicketItem;
use App\Models\MenuItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\WaiterCall;

final class McpTargets
{
    public function servicePoint(McpContext $context, int $id): ServicePoint
    {
        return ServicePoint::query()->select(['id', 'branch_id', 'area_node_id', 'is_active', 'status'])
            ->where('branch_id', $context->branch->id)->whereKey($id)->firstOrFail()
            ->setRelation('branch', $context->branch);
    }

    public function session(McpContext $context, int $id): TableSession
    {
        return TableSession::query()->select(['id', 'branch_id', 'service_point_id', 'status'])
            ->where('branch_id', $context->branch->id)
            ->whereHas('servicePoint', fn ($query) => $query->where('branch_id', $context->branch->id))
            ->whereKey($id)->firstOrFail()->setRelation('branch', $context->branch);
    }

    public function guest(TableSession $session, int $id): TableSessionGuest
    {
        return TableSessionGuest::query()->select(['id', 'table_session_id', 'guest_name', 'status'])
            ->where('table_session_id', $session->id)->whereKey($id)->firstOrFail();
    }

    public function menuItem(McpContext $context, int $id): MenuItem
    {
        $item = MenuItem::query()->select(['id', 'menu_id', 'is_available'])
            ->with('menu:id,branch_id')->whereHas('menu', fn ($query) => $query->where('branch_id', $context->branch->id))
            ->whereKey($id)->firstOrFail();
        $item->menu->setRelation('branch', $context->branch);

        return $item;
    }

    public function draft(McpContext $context, int $id): DraftOrder
    {
        return DraftOrder::query()->select(['id', 'table_session_id', 'status'])
            ->with('tableSession:id,branch_id,service_point_id,status')
            ->whereHas('tableSession', fn ($query) => $query->where('branch_id', $context->branch->id)
                ->whereHas('servicePoint', fn ($points) => $points->where('branch_id', $context->branch->id)))
            ->whereKey($id)->firstOrFail();
    }

    public function waiterCall(McpContext $context, int $id): WaiterCall
    {
        return WaiterCall::query()->select(['id', 'branch_id', 'service_point_id', 'table_session_id', 'status'])
            ->where('branch_id', $context->branch->id)
            ->whereHas('servicePoint', fn ($query) => $query->where('branch_id', $context->branch->id))
            ->whereKey($id)->firstOrFail();
    }

    public function ticketItem(McpContext $context, int $id): KitchenTicketItem
    {
        return KitchenTicketItem::query()->select(['id', 'kitchen_ticket_id', 'order_item_id', 'status', 'served_at'])
            ->with(['kitchenTicket:id,branch_id,kitchen_department_id,order_id,table_session_id',
                'kitchenTicket.kitchenDepartment:id,branch_id,type', 'kitchenTicket.order:id,branch_id,table_session_id,status'])
            ->whereHas('kitchenTicket', fn ($query) => $query->where('branch_id', $context->branch->id)
                ->whereHas('kitchenDepartment', fn ($departments) => $departments->where('branch_id', $context->branch->id))
                ->whereHas('order', fn ($orders) => $orders->where('branch_id', $context->branch->id)
                    ->whereHas('tableSession', fn ($sessions) => $sessions->where('branch_id', $context->branch->id))))
            ->whereKey($id)->firstOrFail();
    }
}

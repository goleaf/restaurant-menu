<?php

declare(strict_types=1);

namespace App\Enums;

enum McpAbility: string
{
    case BranchContext = 'branch_context';
    case ListMenuItems = 'list_menu_items';
    case ListTables = 'list_tables';
    case ListOrders = 'list_orders';
    case ListDrafts = 'list_drafts';
    case ListWaiterCalls = 'list_waiter_calls';
    case ListDepartmentTickets = 'list_department_tickets';
    case BranchReport = 'branch_report';
    case ListAuditEvents = 'list_audit_events';
    case PaymentSummary = 'payment_summary';
    case SetMenuAvailability = 'set_menu_availability';
    case SetOrderingPause = 'set_ordering_pause';
    case OpenTable = 'open_table';
    case ConfirmDraft = 'confirm_draft';
    case RejectDraft = 'reject_draft';
    case HandleWaiterCall = 'handle_waiter_call';
    case UpdateTicketItem = 'update_ticket_item';
    case ServeTicketItem = 'serve_ticket_item';
    case RecordPayment = 'record_payment';
    case CloseTable = 'close_table';

    public function isMutation(): bool
    {
        return match ($this) {
            self::SetMenuAvailability, self::SetOrderingPause, self::OpenTable,
            self::ConfirmDraft, self::RejectDraft, self::HandleWaiterCall,
            self::UpdateTicketItem, self::ServeTicketItem, self::RecordPayment,
            self::CloseTable => true,
            default => false,
        };
    }

    /** @return list<string> */
    public static function readOnly(): array
    {
        return array_column(array_filter(
            self::cases(),
            static fn (self $ability): bool => ! $ability->isMutation(),
        ), 'value');
    }
}

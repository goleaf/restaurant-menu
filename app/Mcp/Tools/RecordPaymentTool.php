<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Mcp\ExecuteMcpMutationAction;
use App\Actions\Payments\RecordManualPaymentAction;
use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Mcp\McpResponse;
use App\Mcp\McpTargets;
use App\Models\ManualPayment;
use App\Support\MoneyFormatter;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class RecordPaymentTool extends RestaurantMutationTool
{
    protected string $name = 'record_payment';

    protected string $description = 'Record an approved offline settlement of the current remaining table or guest balance. This does not charge a card. Tips are integer cents.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly RecordManualPaymentAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::RecordPayment;
    }

    protected function rules(): array
    {
        return ['table_session_id' => ['required', 'integer:strict', 'min:1'], 'guest_id' => ['sometimes', 'integer:strict', 'min:1'], 'method' => ['required', 'string', 'in:cash,card_terminal,other'], 'note' => ['sometimes', 'string', 'max:500'], 'tips_cents' => ['sometimes', 'integer:strict', 'between:0,100000000']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        $session = $this->targets->session($context, $input['table_session_id']);
        if (isset($input['guest_id'])) {
            $this->targets->guest($session, $input['guest_id']);
        }

        return Gate::forUser($context->user)->authorize('create', [ManualPayment::class, $context->branch]);
    }

    protected function perform(McpContext $context, array $input): array
    {
        $session = $this->targets->session($context, $input['table_session_id']);
        $tips = MoneyFormatter::centsToDecimal($input['tips_cents'] ?? 0);
        $payment = isset($input['guest_id'])
            ? $this->action->recordGuest($session, $this->targets->guest($session, $input['guest_id']), $context->user, $input['method'], $input['note'] ?? null, $tips)
            : $this->action->recordTable($session, $context->user, $input['method'], $input['note'] ?? null, $tips);

        return ['payment_id' => $payment->id, 'table_session_id' => $payment->table_session_id,
            'amount_cents' => $payment->amount_cents, 'tips_cents' => $payment->tips_cents, 'currency' => $payment->currency];
    }
}

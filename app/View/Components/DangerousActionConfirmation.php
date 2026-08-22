<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\DangerousAction;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class DangerousActionConfirmation extends Component
{
    public readonly string $title;

    public readonly string $consequence;

    public readonly bool $reasonRequired;

    public readonly ?string $submitTarget;

    public function __construct(
        public readonly string $name,
        ?string $title = null,
        ?string $consequence = null,
        DangerousAction|string|null $action = null,
        public readonly ?string $confirmAction = null,
        public readonly ?string $confirmHref = null,
        public readonly string $confirmLabel = 'ui.actions.confirm',
        ?string $submitTarget = null,
        public readonly ?string $reasonModel = null,
        public readonly string $reasonLabel = 'ui.confirmations.reason.label',
        public readonly string $reasonPlaceholder = 'ui.confirmations.reason.placeholder',
        ?bool $reasonRequired = null,
        public readonly ?string $confirmationModel = null,
        public readonly ?string $confirmationText = null,
        public readonly string $confirmationLabel = 'ui.confirmations.confirmation_text.label',
        public readonly ?string $confirmationHelp = null,
        public readonly string $loadingLabel = 'ui.actions.working',
    ) {
        $dangerousAction = is_string($action) ? DangerousAction::tryFrom($action) : $action;

        $this->title = $title ?? $dangerousAction?->title() ?? 'ui.confirmations.danger.title';
        $this->consequence = $consequence ?? $dangerousAction?->consequence() ?? 'ui.confirmations.danger.description';
        $this->reasonRequired = $reasonRequired ?? $dangerousAction?->requiresReason() ?? false;
        $this->submitTarget = $submitTarget ?: $confirmAction;
    }

    public function render(): View
    {
        return view('components.dangerous-action-confirmation');
    }
}

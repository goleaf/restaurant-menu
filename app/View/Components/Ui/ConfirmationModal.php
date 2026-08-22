<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

final class ConfirmationModal extends Component
{
    public readonly string $modalId;

    public readonly ?string $resolvedConfirmTarget;

    public function __construct(
        public readonly string $triggerLabel = 'ui.actions.delete',
        public readonly string $triggerIcon = 'trash',
        public readonly string $title = 'ui.confirmations.danger.title',
        public readonly string $description = 'ui.confirmations.danger.description',
        public readonly string $confirmLabel = 'ui.actions.delete',
        public readonly string $cancelLabel = 'ui.actions.cancel',
        public readonly string $confirmIcon = 'exclamation-triangle',
        public readonly ?string $confirmAction = null,
        ?string $confirmTarget = null,
    ) {
        $this->modalId = 'confirmation-modal-'.Str::random(8);
        $this->resolvedConfirmTarget = $confirmTarget ?? $confirmAction;
    }

    public function render(): View
    {
        return view('components.ui.confirmation-modal');
    }
}

<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Money extends Component
{
    public readonly string $displayAmount;

    public function __construct(
        int $cents = 0,
        public readonly ?string $currency = null,
        bool $signed = false,
        public readonly ?string $label = null,
    ) {
        $this->displayAmount = $signed
            ? MoneyFormatter::formatSignedCents($cents, $currency)
            : MoneyFormatter::formatCents($cents, $currency);
    }

    public function render(): View
    {
        return view('components.ui.money');
    }
}

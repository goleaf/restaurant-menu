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
        int|float|string|null $amount = 0,
        public readonly ?string $currency = null,
        bool $signed = false,
        bool $cents = false,
        public readonly ?string $label = null,
    ) {
        $this->displayAmount = match (true) {
            $cents => MoneyFormatter::formatCents((int) $amount, $currency),
            $signed => MoneyFormatter::formatSigned($amount, $currency),
            default => MoneyFormatter::format($amount, $currency),
        };
    }

    public function render(): View
    {
        return view('components.ui.money');
    }
}

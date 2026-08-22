<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class ValidationError extends Component
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $error = null,
    ) {}

    public function render(): View
    {
        return view('components.ui.validation-error');
    }
}

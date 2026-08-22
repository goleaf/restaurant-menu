<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Stringable;

final class PlainText extends Component
{
    public readonly string $value;

    public readonly string $fallback;

    public function __construct(
        mixed $text = null,
        mixed $placeholder = null,
        public readonly bool $preserveLines = true,
    ) {
        $this->value = is_scalar($text) || $text instanceof Stringable ? trim((string) $text) : '';
        $this->fallback = is_scalar($placeholder) || $placeholder instanceof Stringable ? trim((string) $placeholder) : '';
    }

    public function render(): View
    {
        return view('components.ui.plain-text');
    }
}

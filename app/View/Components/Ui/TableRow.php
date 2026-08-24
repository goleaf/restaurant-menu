<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class TableRow extends Component
{
    public readonly string $rowClasses;

    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle = null,
        public readonly ?string $meta = null,
        public readonly ?string $href = null,
    ) {
        $this->rowClasses = 'grid min-h-16 gap-3 border-b border-border-subtle px-4 py-3 text-sm last:border-b-0 md:grid-cols-[minmax(0,1fr)_auto] md:items-center';
    }

    public function render(): View
    {
        return view('components.ui.table-row');
    }
}

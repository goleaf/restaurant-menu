<?php

declare(strict_types=1);

namespace App\View\Components\Layouts\App;

use App\Actions\Navigation\BuildApplicationNavigationAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

final class Sidebar extends Component
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $navigationContext;

    public function __construct(
        BuildApplicationNavigationAction $buildApplicationNavigation,
        Request $request,
        public readonly ?string $title = null,
    ) {
        $this->navigationContext = $buildApplicationNavigation->handle($request->user(), $request);
    }

    public function render(): View
    {
        return view('layouts.app.sidebar', $this->navigationContext);
    }
}

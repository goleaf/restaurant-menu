<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

final class ErrorPage extends Component
{
    public readonly string $title;

    public readonly string $message;

    public readonly string $hint;

    public function __construct(
        public readonly int $status,
        Request $request,
    ) {
        $surface = $request->is('q/*') ? 'qr' : ($request->is('guest*') ? 'guest' : 'admin');
        $error = match ($status) {
            403 => 'permission_denied',
            404 => $surface === 'qr' ? 'qr_not_found' : 'not_found',
            419 => 'session_expired',
            422 => 'validation',
            default => 'system',
        };
        $translationSurface = $surface === 'admin' ? 'admin' : 'guest';
        $prefix = 'errors.'.$translationSurface.'.'.$error;

        $this->title = __($prefix.'.title');
        $this->message = __($prefix.'.message');
        $this->hint = __($prefix.'.hint');
    }

    public function render(): View
    {
        return view('errors.shell');
    }
}

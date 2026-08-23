<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\BuildDemoLoginPageAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class ShowDemoLoginController extends Controller
{
    public function __invoke(BuildDemoLoginPageAction $buildDemoLoginPage): Response
    {
        return response()->view('auth.demo-login', [
            'accounts' => $buildDemoLoginPage->handle(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Support\Auth\AuthRequestAdapter;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse;
use Symfony\Component\HttpFoundation\Response;

final class LogoutAction
{
    public function __construct(private readonly StatefulGuard $guard, private readonly AuthRequestAdapter $adapter, private readonly LogoutResponse $response) {}

    public function handle(Request $source): Response
    {
        abort_unless($this->guard->check(), 401);
        $this->guard->logout();
        $source->session()->invalidate();
        $source->session()->regenerateToken();

        return $this->adapter->response($this->response, $this->adapter->request($source, []));
    }
}

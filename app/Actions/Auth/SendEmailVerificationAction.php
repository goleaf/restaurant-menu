<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Support\Auth\AuthRequestAdapter;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

final class SendEmailVerificationAction
{
    public function __construct(private readonly StatefulGuard $guard, private readonly AuthRequestAdapter $adapter) {}

    public function handle(Request $source): void
    {
        abort_unless(Features::enabled(Features::emailVerification()), 404);
        $user = $this->guard->user();
        abort_if($user === null, 401);
        $request = $this->adapter->request($source, []);
        $this->adapter->throttle($request, function () use ($user): Response {
            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            }

            return response()->noContent();
        }, (string) config('fortify.limiters.verification', '6,1'));
    }
}

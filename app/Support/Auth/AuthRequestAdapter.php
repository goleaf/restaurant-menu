<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Livewire\Features\SupportRedirects\Redirector;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;

final class AuthRequestAdapter
{
    /**
     * @template T of Request
     *
     * @param  array<string, mixed>  $input
     * @param  class-string<T>  $type
     * @return T
     */
    public function request(Request $source, array $input, string $type = Request::class): Request
    {
        $request = $type::createFrom($source);
        $request->query->replace([]);
        $request->request->replace($input);
        $request->setJson(new InputBag($input));
        $request->setMethod('POST');
        $request->headers->set('Accept', 'text/html');
        $request->headers->set('Content-Type', 'application/x-www-form-urlencoded');

        if ($request instanceof FormRequest) {
            $request->setContainer(app());
            $request->setRedirector(app('redirect'));
        }

        return $request;
    }

    /**
     * @template T of Request
     *
     * @param  T  $request
     * @param  Closure(T): Response  $next
     */
    public function throttle(Request $request, Closure $next, string $limiter): Response
    {
        return app(ThrottleRequests::class)->handle($request, $next, ...explode(',', $limiter));
    }

    public function passwordResetResponse(string $status, Request $request): Response
    {
        return $this->response(app(PasswordResetResponse::class, ['status' => $status]), $request);
    }

    public function response(Responsable|Response|Redirector $result, Request $request): Response
    {
        $response = $result instanceof Responsable ? $result->toResponse($request) : $result;

        // Livewire's redirector has already recorded the component redirect effect.
        return $response instanceof Redirector ? new LivewireAuthRedirectResponse : $response;
    }
}

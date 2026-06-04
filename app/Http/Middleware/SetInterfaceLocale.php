<?php

namespace App\Http\Middleware;

use App\Enums\SupportedLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetInterfaceLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $userLocale = $request->user()?->locale;

        if (SupportedLocale::isSupported(is_string($userLocale) ? $userLocale : null)) {
            return SupportedLocale::normalize($userLocale);
        }

        $queryLocale = $request->query('lang');

        if (is_string($queryLocale) && SupportedLocale::isSupported($queryLocale)) {
            $locale = SupportedLocale::normalize($queryLocale);
            $request->session()->put('interface_locale', $locale);

            return $locale;
        }

        $sessionLocale = $request->session()->get('interface_locale');

        return SupportedLocale::normalize(
            is_string($sessionLocale) ? $sessionLocale : null,
            (string) config('app.locale', SupportedLocale::English->value),
        );
    }
}
